<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeERP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scrape-erp {--limit= : Limit the number of guides to scrape} {--section= : Limit to section (erp or pos)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape ERP and POS documentation from DarkoGuide and update erp_guidance.json';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting DarkoGuide (ERP & POS) Documentation Scraper...');

        $baseUrl = rtrim(env('ERP_GUIDANCE_URL', 'http://94.237.69.119:4000'), '/');
        $docsUrl = "{$baseUrl}/docs/";
        $loginUrl = "{$baseUrl}/login/";

        $email = env('ERP_GUIDANCE_EMAIL');
        $password = env('ERP_GUIDANCE_PASSWORD');

        if (!$email || !$password) {
            $this->error('❌ ERP Documentation credentials not configured in .env (ERP_GUIDANCE_EMAIL / ERP_GUIDANCE_PASSWORD)');
            return 1;
        }

        $this->info("🌐 Target URL: {$baseUrl}");
        $this->info("👤 User: {$email}");

        $jar = new \GuzzleHttp\Cookie\CookieJar();

        // 1. Get Login Page to extract Nonce
        $this->info('🔑 Fetching login page for nonce...');
        try {
            $res = Http::withOptions([
                'cookies' => $jar,
                'verify' => false,
                'timeout' => 20,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ]
            ])->get($loginUrl);
        } catch (\Exception $e) {
            $this->error('❌ Connection error to login page: ' . $e->getMessage());
            return 1;
        }

        if (!$res->successful()) {
            $this->error('❌ Failed to reach login page. Status: ' . $res->status());
            return 1;
        }

        $crawler = new Crawler($res->body());
        $nonce = $crawler->filter('input[name="erpgl_login_nonce"]')->count()
            ? $crawler->filter('input[name="erpgl_login_nonce"]')->attr('value')
            : null;
        $referer = $crawler->filter('input[name="_wp_http_referer"]')->count()
            ? $crawler->filter('input[name="_wp_http_referer"]')->attr('value')
            : '/login/';

        if (!$nonce) {
            $this->warn('⚠️  Could not find login nonce. Attempting standard login...');
        } else {
            $this->info("🔑 Found login nonce: {$nonce}");
        }

        // 2. Login POST
        $this->info('🔐 Submitting authentication credentials...');
        try {
            $response = Http::asForm()->withOptions([
                'cookies' => $jar,
                'allow_redirects' => true,
                'verify' => false,
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ]
            ])->post($loginUrl, [
                'log' => $email,
                'pwd' => $password,
                'wp-submit' => 'Log In',
                'rememberme' => 'forever',
                'erpgl_login_nonce' => $nonce,
                '_wp_http_referer' => $referer,
                'testcookie' => 1
            ]);
        } catch (\Exception $e) {
            $this->error('❌ Login request failed: ' . $e->getMessage());
            return 1;
        }

        if (!$response->successful()) {
            $this->error('❌ Failed to login. Status: ' . $response->status());
            return 1;
        }

        // Verify login by fetching /docs/
        $docsCheck = Http::withOptions([
            'cookies' => $jar,
            'verify' => false,
            'timeout' => 20
        ])->get($docsUrl);

        if (str_contains($docsCheck->body(), 'erpgl_login_nonce') || str_contains($docsCheck->body(), 'name="log"')) {
            $this->error('❌ Scraper is still seeing a login form. Login failed.');
            return 1;
        }

        $this->info('✅ Authentication successful! Logged in as ' . $email);

        // 3. Discover Categories from /docs/
        $this->info('🔍 Discovering ERP & POS categories from ' . $docsUrl . ' ...');
        $docsCrawler = new Crawler($docsCheck->body());

        $discoveredCategories = [];
        $docsCrawler->filter('a')->each(function (Crawler $a) use (&$discoveredCategories, $baseUrl) {
            $href = $a->attr('href');
            $text = trim($a->text());
            if ($href && str_contains($href, '/category/')) {
                // Filter only ERP and POS
                $isErp = str_contains($href, 'erp') || str_contains($text, '(ERP)') || str_contains(strtolower($text), 'erp');
                $isPos = str_contains($href, 'pos') || str_contains($text, '(POS)') || str_contains(strtolower($text), 'pos');
                
                if ($isErp || $isPos) {
                    $section = $isPos ? 'POS' : 'ERP';
                    $cleanName = preg_replace('/\s*\((ERP|POS)\)\s*/i', '', $text);
                    $cleanName = trim($cleanName);
                    
                    // Normalize URL to baseUrl host so cookies work
                    $path = parse_url($href, PHP_URL_PATH);
                    $normUrl = rtrim($baseUrl, '/') . $path;
                    
                    $discoveredCategories[$normUrl] = [
                        'url' => $normUrl,
                        'name' => $cleanName,
                        'full_name' => "{$cleanName} ({$section})",
                        'section' => $section
                    ];
                }
            }
        });

        // Filter by --section option if passed
        $sectionFilter = strtolower($this->option('section') ?? '');
        if ($sectionFilter === 'erp' || $sectionFilter === 'pos') {
            $discoveredCategories = array_filter($discoveredCategories, function ($cat) use ($sectionFilter) {
                return strtolower($cat['section']) === $sectionFilter;
            });
            $this->info("🔍 Filtered by section: " . strtoupper($sectionFilter));
        }

        $this->info('📋 Discovered ' . count($discoveredCategories) . ' target categories:');
        foreach ($discoveredCategories as $cat) {
            $this->line("   - [{$cat['section']}] {$cat['name']} -> {$cat['url']}");
        }

        // 4. Discover Articles across Categories
        $this->info("\n📄 Discovering articles across all target categories...");
        $articlesToScrape = [];

        foreach ($discoveredCategories as $cat) {
            $catPage = 1;
            $catFound = 0;
            
            while (true) {
                $pageUrl = $catPage === 1 ? $cat['url'] : rtrim($cat['url'], '/') . "/page/{$catPage}/";
                try {
                    $pageRes = Http::withOptions([
                        'cookies' => $jar,
                        'verify' => false,
                        'timeout' => 20
                    ])->get($pageUrl);

                    if (!$pageRes->successful()) {
                        break;
                    }

                    $pageCrawler = new Crawler($pageRes->body());
                    $foundOnPage = 0;

                    $selectors = ['article a', 'h2 a', '.entry-title a', '.ast-loop-builder-item-title a'];
                    foreach ($selectors as $sel) {
                        $pageCrawler->filter($sel)->each(function (Crawler $a) use (&$articlesToScrape, &$foundOnPage, $baseUrl, $cat) {
                            $href = $a->attr('href');
                            $title = trim($a->text());

                            if ($href && !preg_match('/\/(category|tag|author|page)\//', $href) && !in_array($href, ['#', ''])) {
                                // Normalize article URL
                                $path = parse_url($href, PHP_URL_PATH);
                                $normHref = rtrim($baseUrl, '/') . $path;

                                if (!isset($articlesToScrape[$normHref]) && strlen($path) > 3) {
                                    $articlesToScrape[$normHref] = [
                                        'url' => $normHref,
                                        'title' => $title,
                                        'section' => $cat['section'],
                                        'category' => $cat['name'],
                                        'full_category' => $cat['full_name']
                                    ];
                                    $foundOnPage++;
                                }
                            }
                        });
                        if ($foundOnPage > 0) {
                            break;
                        }
                    }

                    $catFound += $foundOnPage;

                    // Check pagination
                    $hasNext = $pageCrawler->filter('a.next.page-numbers, .next.page-numbers')->count() > 0;
                    if (!$hasNext || $foundOnPage === 0) {
                        break;
                    }

                    $catPage++;
                } catch (\Exception $e) {
                    $this->warn("⚠️  Error fetching category page {$pageUrl}: " . $e->getMessage());
                    break;
                }
            }

            $this->line("   [{$cat['section']}] {$cat['name']}: {$catFound} articles found.");
        }

        $totalArticles = count($articlesToScrape);
        $this->info("\n📎 Total unique articles discovered: {$totalArticles}");

        if ($totalArticles === 0) {
            $this->error('❌ No documentation articles found. Aborting.');
            return 1;
        }

        // 5. Scrape Each Article
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;
        if ($limit && $limit < $totalArticles) {
            $this->warn("⚠️  Limit applied: Scraping first {$limit} of {$totalArticles} articles.");
            $articlesToScrape = array_slice($articlesToScrape, 0, $limit, true);
            $totalArticles = count($articlesToScrape);
        }

        $guides = [];
        $categoriesList = [];
        $currentIndex = 0;

        foreach ($articlesToScrape as $artUrl => $meta) {
            $currentIndex++;
            $this->info("[{$currentIndex}/{$totalArticles}] Scraping [{$meta['section']}] {$meta['category']}: {$artUrl}");

            try {
                $res = Http::withOptions([
                    'cookies' => $jar,
                    'verify' => false,
                    'timeout' => 25,
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                    ]
                ])->get($artUrl);

                if (!$res->successful()) {
                    $this->warn("⚠️  Skipping: Failed to fetch {$artUrl} (HTTP {$res->status()})");
                    continue;
                }

                $guide = $this->parseGuidePage($res->body(), $artUrl, $baseUrl, $meta);
                if ($guide) {
                    $guides[] = $guide;
                    if (!in_array($guide['full_category'], $categoriesList)) {
                        $categoriesList[] = $guide['full_category'];
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ Error processing {$artUrl}: " . $e->getMessage());
            }
        }

        // 6. Update JSON
        $this->updateJson($guides, $categoriesList, $baseUrl);

        // Print Summary Table
        $erpCount = count(array_filter($guides, fn($g) => ($g['section'] ?? '') === 'ERP'));
        $posCount = count(array_filter($guides, fn($g) => ($g['section'] ?? '') === 'POS'));

        $this->info("\n========================================================");
        $this->info("✨ DarkoGuide Scraping Completed Successfully!");
        $this->info("📊 Total Guides Saved : " . count($guides));
        $this->info("🏢 ERP Guides         : {$erpCount}");
        $this->info("💳 POS Guides         : {$posCount}");
        $this->info("📁 Categories         : " . count($categoriesList));
        $this->info("========================================================");

        return 0;
    }

    /**
     * Parse a single guide page into the structured format.
     */
    private function parseGuidePage(string $html, string $url, string $baseUrl, array $meta): ?array
    {
        $crawler = new Crawler($html);

        $title = $crawler->filter('h1.entry-title, h1')->count()
            ? trim($crawler->filter('h1.entry-title, h1')->text())
            : (!empty($meta['title']) ? $meta['title'] : 'Untitled');

        $contentNode = $crawler->filter('.entry-content');
        if ($contentNode->count() === 0) {
            return null;
        }

        $section = $meta['section'] ?? 'ERP';
        $category = $meta['category'] ?? 'General';
        $fullCategory = $meta['full_category'] ?? "{$category} ({$section})";

        $images = [];
        $videos = [];
        $formFields = [];
        $fieldQueue = [];
        $mdContent = "# {$title} 🚀\n\n";

        $enrichmentLookup = [
            'Common' => [
                'No. Transaksi' => 'Nomor unik transaksi yang dihasilkan otomatis oleh sistem.',
                'Tgl. Transaksi' => 'Tanggal dilakukannya transaksi.',
                'Cabang' => 'Kantor cabang yang bertanggung jawab atas transaksi ini.',
                'Departemen' => 'Departemen internal yang terlibat dalam transaksi.',
                'Gudang' => 'Lokasi penyimpanan barang (Stock).',
                'Keterangan' => 'Catatan atau memo tambahan terkait transaksi.',
                'Supplier' => 'Pihak pemasok atau vendor barang/jasa.',
                'Customer' => 'Pihak pembeli atau pelanggan.',
                'Langganan' => 'Nama pelanggan atau member yang terdaftar.',
                'Kode Barang' => 'ID unik untuk identitas barang di sistem.',
                'Qty' => 'Jumlah atau kuantitas barang.',
                'Harga' => 'Nilai satuan harga barang.',
                'Total' => 'Nilai total transaksi (Qty x Harga).'
            ],
            'Tanda Terima Barang' => [
                'No. TTB' => 'Nomor urut transaksi Tanda Terima Barang.',
                'No. Referensi' => 'Nomor referensi dari dokumen pengirim (Faktur/Surat Jalan).',
                'Tgl. TTB' => 'Tanggal diterimanya barang.',
                'Supplier' => 'Nama pemasok barang.',
                'Langganan' => 'Nama pelanggan (jika retur jual).',
                'Kode Barang' => 'Kode identitas barang yang diterima.',
                'Qty. TTB' => 'Jumlah barang yang diterima sesuai fisik.',
                'Satuan' => 'Satuan unit barang (Pcs, Box, dll).',
                'Gudang' => 'Gudang penyimpanan barang yang diterima.',
                'Keterangan' => 'Catatan tambahan mengenai kondisi barang saat diterima.'
            ],
            'Order Pembelian' => [
                'Tgl. PO' => 'Tanggal resmi Order Pembelian diterbitkan.',
                'T.O.P Hari' => 'Term of Payment (jangka waktu jatuh tempo pembayaran).',
                'Gudang Tujuan' => 'Gudang yang akan menerima kiriman barang dari supplier.',
                'Disc Item %' => 'Persentase diskon yang diberikan untuk setiap item.',
                'Netto Rp' => 'Nilai bersih setelah dipotong diskon dan pajak.'
            ],
            'POS' => [
                'Kasir' => 'Nama kasir yang melayani transaksi penjualan.',
                'Tipe Pembayaran' => 'Metode pembayaran (Tunai, QRIS, Kartu Debit, Transfer).',
                'Nominal Bayar' => 'Jumlah uang yang dibayarkan oleh pelanggan.',
                'Kembalian' => 'Jumlah uang kembalian yang diberikan kepada pelanggan.',
                'No. Meja' => 'Nomor meja pelanggan untuk transaksi dine-in.'
            ]
        ];

        // Process children of entry-content recursively
        $rootNode = $contentNode->getNode(0);
        if ($rootNode) {
            $this->processNodesRecursively($rootNode, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, 0, $baseUrl);
        }

        // Flush remaining fields in the queue
        if (!empty($fieldQueue)) {
            $mdContent .= "\n### 📋 Penjelasan Field Tambahan\n\n";
            foreach ($fieldQueue as $field) {
                $desc = !empty($field['explanation']) ? $field['explanation'] : $field['description'];
                $mdContent .= "- **{$field['field']}**: {$desc}\n";
            }
            $mdContent .= "\n";
        }

        // Add Video Section if found
        if (!empty($videos)) {
            $mdContent .= "\n---\n\n### 🎥 Video Panduan\n\n";
            foreach ($videos as $v) {
                $mdContent .= "[Klik di sini untuk menonton video]({$v})\n\n";
            }
        }

        // Final deduplicated form fields
        $finalFields = [];
        $uniqueKeys = [];
        foreach (array_merge($formFields, $fieldQueue) as $f) {
            if (!isset($uniqueKeys[$f['field']])) {
                $finalFields[] = $f;
                $uniqueKeys[$f['field']] = true;
            }
        }

        return [
            'id' => md5($url),
            'title' => $title,
            'section' => $section,
            'category' => $category,
            'full_category' => $fullCategory,
            'keywords' => $this->generateKeywords($title, $category, $section),
            'detail_panduan_lengkap' => trim($mdContent),
            'url' => $url,
            'form_fields' => array_values(array_slice($finalFields, 0, 30)),
            'images' => $images,
            'video' => $videos[0] ?? null,
            'last_fetched' => now()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Normalize URLs: Handle relative paths and convert old IP/ports.
     */
    private function normalizeUrl(?string $url, string $baseUrl): ?string
    {
        if (!$url) return null;

        // 1. Handle relative URLs
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            if (str_starts_with($url, '/')) {
                $url = rtrim($baseUrl, '/') . $url;
            } else {
                $url = rtrim($baseUrl, '/') . '/' . $url;
            }
        }

        // 2. Replace old server IPs (74.48.112.31:4000 or 74.48.112.31:6000) with current baseUrl
        if (str_contains($url, '74.48.112.31:4000') || str_contains($url, '74.48.112.31:6000') || str_contains($url, '74.48.112.31')) {
            $url = preg_replace('#https?://74\.48\.112\.31(:\d+)?#', $baseUrl, $url);
        }

        return $url;
    }

    private function processNodesRecursively(\DOMNode $node, &$mdContent, &$images, &$videos, &$formFields, &$fieldQueue, $title, $enrichmentLookup, $level = 0, $baseUrl = '')
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = trim($child->textContent);
                if ($text && strlen($text) >= 2) {
                    $this->handleTextNode($text, $mdContent, $fieldQueue, $formFields, $title, $enrichmentLookup);
                }
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE)
                continue;

            $tagName = strtolower($child->nodeName);

            // Handle Headers
            if (in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                $text = trim($child->textContent);
                if ($text) {
                    $hLevel = (int) substr($tagName, 1) + 1;
                    $prefix = str_repeat('#', $hLevel);
                    $alertType = $this->getAlertType($text);

                    if ($alertType) {
                        $mdContent .= "\n> ### " . $this->getHeaderEmoji($text) . " " . rtrim($text, ' :-') . "\n>\n";
                    } else {
                        $mdContent .= "{$prefix} {$text} " . $this->getHeaderEmoji($text) . "\n\n";
                    }
                }
            }

            // Handle Images
            elseif ($tagName === 'img' || ($tagName === 'a' && $child->getElementsByTagName('img')->length > 0)) {
                $img = $tagName === 'img' ? $child : $child->getElementsByTagName('img')->item(0);
                $src = $this->normalizeUrl($img->getAttribute('src'), $baseUrl);
                $alt = $img->getAttribute('alt') ?: 'Gambar Panduan';

                if (in_array(strtolower(trim($alt)), ['gambar', 'gambar panduan']))
                    $alt = 'Langkah Panduan';

                if ($src) {
                    $mdContent .= "![{$alt}]({$src})\n\n";
                    $images[] = ['src' => $src, 'alt' => $alt, 'caption' => ''];

                    // Flush fieldQueue immediately under the image if any
                    if (!empty($fieldQueue)) {
                        $mdContent .= "> **📋 Penjelasan Field pada gambar:**\n";
                        foreach ($fieldQueue as $field) {
                            $desc = !empty($field['explanation']) ? $field['explanation'] : $field['description'];
                            $mdContent .= "> - **{$field['field']}**: {$desc}\n";
                            $formFields[] = $field;
                        }
                        $mdContent .= "\n";
                        $fieldQueue = [];
                    }
                }
            }

            // Handle Paragraphs and Lists
            elseif (in_array($tagName, ['p', 'ul', 'ol', 'li', 'div', 'article', 'section'])) {
                $firstStrong = $child->getElementsByTagName('strong')->item(0);
                if ($tagName === 'p' && $firstStrong && ($child->firstChild === $firstStrong || ($child->firstChild->nodeType === XML_TEXT_NODE && trim($child->firstChild->textContent) === ''))) {
                    $headerText = trim($firstStrong->textContent);
                    $alertType = $this->getAlertType($headerText);
                    if ($alertType) {
                        $mdContent .= "\n> ### " . $this->getHeaderEmoji($headerText) . " " . rtrim($headerText, ' :-') . "\n>\n";

                        $tempContent = "";
                        $this->processNodesRecursively($child, $tempContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1, $baseUrl);

                        $cleanContent = str_replace($headerText, '', $tempContent);
                        if (trim($cleanContent)) {
                            $mdContent .= "> " . trim($cleanContent) . "\n\n";
                        }
                        continue;
                    }
                }

                if ($tagName === 'li')
                    $mdContent .= "- ";

                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1, $baseUrl);

                if (in_array($tagName, ['p', 'ul', 'ol', 'div'])) {
                    $mdContent .= "\n\n";
                } elseif ($tagName === 'li') {
                    $mdContent .= "\n";
                }
            }

            // Handle Tables
            elseif ($tagName === 'table') {
                $mdContent .= "\n";
                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1, $baseUrl);
                $mdContent .= "\n";
            } elseif ($tagName === 'tr') {
                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1, $baseUrl);
                $mdContent .= "\n";
            } elseif (in_array($tagName, ['td', 'th'])) {
                $mdContent .= "| ";
                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1, $baseUrl);
                $mdContent .= " ";
            }

            // Handle Videos
            elseif ($tagName === 'video' || str_contains($child->getAttribute('class'), 'wp-video')) {
                $source = $child->getElementsByTagName('source')->item(0);
                $src = $source ? $source->getAttribute('src') : $child->getAttribute('src');
                if (!$src && $child->getElementsByTagName('a')->length > 0) {
                    $src = $child->getElementsByTagName('a')->item(0)->getAttribute('href');
                }

                $src = $this->normalizeUrl($src, $baseUrl);

                if ($src) {
                    $videos[] = $src;
                    $mdContent .= "### 🎥 Video Panduan\n[Klik di sini untuk menonton video]({$src})\n\n";
                }
            } elseif ($tagName === 'hr') {
                $mdContent .= "---\n\n";
            } else {
                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1, $baseUrl);
            }
        }
    }

    private function handleTextNode(string $text, &$mdContent, &$fieldQueue, &$formFields, $title, $enrichmentLookup)
    {
        $lower = strtolower($text);
        if ($lower === 'gambar' || $lower === 'gambar panduan' || $lower === 'video :' || $lower === 'video:')
            return;

        $mdContent .= "{$text} ";
        $this->detectFields($text, $fieldQueue, $title, $enrichmentLookup);
    }

    private function getAlertType(string $text): ?string
    {
        $text = strtolower($text);
        if (str_contains($text, 'fungsi'))
            return 'INFO';
        if (str_contains($text, 'persyaratan') || str_contains($text, 'syarat'))
            return 'IMPORTANT';
        if (str_contains($text, 'petunjuk') || str_contains($text, 'langkah'))
            return 'TIP';
        if (str_contains($text, 'catatan'))
            return 'NOTE';
        return null;
    }

    private function getHeaderEmoji(string $text): string
    {
        $text = strtolower($text);
        if (str_contains($text, 'petunjuk') || str_contains($text, 'langkah'))
            return '🛠️';
        if (str_contains($text, 'syarat'))
            return '📋';
        if (str_contains($text, 'catatan'))
            return '💡';
        if (str_contains($text, 'fungsi'))
            return '📋';
        if (str_contains($text, 'video'))
            return '🎥';
        return '';
    }

    private function detectFields(string $text, &$fieldQueue, $title, $enrichmentLookup)
    {
        if (preg_match('/(?:^|\n|\s)(?:\*\*)?([a-zA-Z0-9\s\/\(\)\.]{2,50})(?:\*\*)?\s*[:\-]\s*(.{2,500})/', $text, $matches)) {
            $field = trim($matches[1]);
            $description = trim($matches[2]);

            $lowerField = strtolower($field);
            if (in_array($lowerField, ['http', 'https', 'catatan', 'fungsi', 'petunjuk', 'persyaratan', 'syarat', 'input', 'update']))
                return;
            if (str_contains($lowerField, 'gambar') || count(explode(' ', $field)) > 7)
                return;

            $explanation = '';
            foreach ($enrichmentLookup as $category => $fields) {
                if ($category === 'Common' || str_contains(strtolower($title), strtolower($category))) {
                    foreach ($fields as $fieldName => $exp) {
                        if (str_contains(strtolower($field), strtolower($fieldName))) {
                            $explanation = $exp;
                            break 2;
                        }
                    }
                }
            }

            $fieldQueue[] = [
                'field' => $field,
                'description' => $description,
                'explanation' => $explanation
            ];
        }
    }

    private function generateKeywords(string $title, string $category, string $section): array
    {
        $cleanTitle = strtolower(trim($title));
        $keys = [
            str_replace(' ', '', $cleanTitle),
            $cleanTitle,
            strtolower($category),
            strtolower($section),
            strtolower("{$section} {$category}"),
            strtolower("panduan {$cleanTitle}")
        ];

        // Specific domain aliases
        if (str_contains($cleanTitle, 'pembayaran')) {
            $keys[] = 'kasir';
            $keys[] = 'bayar';
        }
        if (str_contains($cleanTitle, 'piutang'))
            $keys[] = 'ar';
        if (str_contains($cleanTitle, 'hutang'))
            $keys[] = 'ap';
        if (str_contains($cleanTitle, 'tanda terima barang')) {
            $keys[] = 'ttb';
            $keys[] = 'penerimaan barang';
        }
        if (str_contains($cleanTitle, 'penjualan')) {
            $keys[] = 'sales';
            $keys[] = 'jual';
        }
        if (str_contains($cleanTitle, 'pembelian')) {
            $keys[] = 'purchase';
            $keys[] = 'beli';
            $keys[] = 'po';
        }

        return array_values(array_unique($keys));
    }

    private function updateJson(array $guides, array $categories, string $baseUrl)
    {
        $path = config_path('erp_guidance.json');

        sort($categories);

        $data = [
            'source' => "{$baseUrl}/docs/ (DarkoGuide Scraper)",
            'last_updated' => now()->format('Y-m-d H:i:s'),
            'total_guides' => count($guides),
            'categories' => $categories,
            'guides' => $guides
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($json === false) {
            $this->error('❌ Failed to encode JSON: ' . json_last_error_msg());
            return;
        }

        file_put_contents($path, $json);
        $this->info("💾 JSON successfully saved at: {$path} (" . round(strlen($json) / 1024, 1) . " KB)");
    }
}
