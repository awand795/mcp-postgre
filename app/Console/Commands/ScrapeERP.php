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
    protected $signature = 'app:scrape-erp {--limit= : Limit the number of pages to crawl}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape ERP documentation from http://74.48.112.31:6000/docs/ and update erp_guidance.json';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting ERP Documentation Scraper...');

        $baseUrl = 'http://74.48.112.31:6000';
        $docsUrl = "{$baseUrl}/docs/";
        $loginUrl = "{$baseUrl}/wp-login.php";

        $email = env('ERP_GUIDANCE_EMAIL');
        $password = env('ERP_GUIDANCE_PASSWORD');

        if (!$email || !$password) {
            $this->error('❌ ERP Documentation credentials not configured in .env');
            return 1;
        }

        $jar = new \GuzzleHttp\Cookie\CookieJar();

        // 1. Get Login Page to extract Nonce
        $this->info('🔑 Fetching login page for nonce...');
        $res = Http::withOptions(['cookies' => $jar, 'verify' => false])->get("{$baseUrl}/login/");
        if (!$res->successful()) {
            $this->error('❌ Failed to bridge to login page.');
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
            $this->warn('⚠️ Could not find login nonce. Attempting standard login...');
        } else {
            $this->info("🔑 Found nonce: {$nonce}");
        }

        // 2. Login POST
        $this->info('🔐 Attempting to login...');
        $response = Http::asForm()->withOptions([
            'cookies' => $jar,
            'allow_redirects' => true,
            'verify' => false
        ])->post($baseUrl . '/login/', [
            'log' => $email,
            'pwd' => $password,
            'wp-submit' => 'Log In',
            'rememberme' => 'forever',
            'erpgl_login_nonce' => $nonce,
            '_wp_http_referer' => $referer,
            'testcookie' => 1
        ]);

        if (!$response->successful()) {
            $this->error('❌ Failed to login. Status: ' . $response->status());
            return 1;
        }

        // Verify login
        $res = Http::withOptions(['cookies' => $jar, 'verify' => false])->get($docsUrl);
        if (str_contains($res->body(), 'loginform') || str_contains($res->body(), 'user_login')) {
            $this->error('❌ Scraper is still seeing a login form. Login failed.');
            return 1;
        }

        $this->info('✅ Login successful!');

        // 3. Discover Links
        $this->info('🔍 Discovering documentation links...');
        $links = [];
        $page = 1;

        while (true) {
            $currentListUrl = $page === 1 ? $docsUrl : "{$docsUrl}page/{$page}/";
            $this->info("Fetching list page: {$currentListUrl}");

            $res = Http::withOptions(['cookies' => $jar, 'verify' => false])->get($currentListUrl);
            if (!$res->successful()) {
                $this->warn("⚠️  End of list or request failed at page {$page}.");
                break;
            }

            $crawler = new Crawler($res->body());
            $foundOnPage = false;

            // Try multiple selectors for better coverage
            $selectors = ['h2.entry-title a', '.entry-content a', 'article a', '.ast-loop-builder-item-title a'];
            foreach ($selectors as $selector) {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$links, &$foundOnPage, $baseUrl) {
                    $href = $node->attr('href');
                    if ($href && str_contains($href, $baseUrl) && !str_contains($href, '/page/') && !str_contains($href, '/docs/') && !in_array($href, $links)) {
                        // Ensure it's not a category link or pagination link
                        if (!preg_match('/\/(category|tag|author|page)\//', $href)) {
                            $links[] = $href;
                            $foundOnPage = true;
                        }
                    }
                });
                if ($foundOnPage) break;
            }

            if (!$foundOnPage && $page === 1) {
                $this->error('❌ No links found on the first page. Check selectors.');
                break;
            }
            
            // Check for next page
            $hasNext = $crawler->filter('a.next.page-numbers')->count() > 0 || 
                       $crawler->filter('.next.page-numbers')->count() > 0;
            
            if (!$hasNext) break;

            $page++;
            if ($this->option('limit') && $page > $this->option('limit')) break;
        }

        $links = array_unique($links);
        $totalLinks = count($links);
        $this->info("📎 Found {$totalLinks} documentation links.");

        if ($totalLinks === 0) {
            $this->error('❌ No links found. Aborting.');
            return 1;
        }

        // 3. Scrape Each Link
        $guides = [];
        $categories = [];

        foreach ($links as $index => $url) {
            $this->info("[" . ($index + 1) . "/{$totalLinks}] Processing: {$url}");

            try {
                $res = Http::withOptions(['cookies' => $jar, 'verify' => false])->get($url);
                if (!$res->successful()) {
                    $this->warn("⚠️  Skipping: Failed to fetch {$url}");
                    continue;
                }

                $guide = $this->parseGuidePage($res->body(), $url);
                if ($guide) {
                    $guides[] = $guide;
                    if (!empty($guide['category']) && !in_array($guide['category'], $categories)) {
                        $categories[] = $guide['category'];
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ Error processing {$url}: " . $e->getMessage());
            }

            // Optional: limit total guides if --limit is used (for quick testing)
            if ($this->option('limit') && count($guides) >= $this->option('limit')) break;
        }

        // 4. Update JSON
        $this->updateJson($guides, $categories);

        $this->info('✨ ERP Documentation Scrape completed successfully!');
        return 0;
    }

    /**
     * Parse a single guide page into the structured format.
     */
    private function parseGuidePage(string $html, string $url): ?array
    {
        $crawler = new Crawler($html);

        $title = $crawler->filter('h1.entry-title')->count() 
            ? trim($crawler->filter('h1.entry-title')->text()) 
            : 'Untitled';

        $contentNode = $crawler->filter('.entry-content');
        if ($contentNode->count() === 0) return null;

        // Extract Category from URL Path
        $category = 'Uncategory';
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        if (count($segments) >= 1) {
            $category = ucwords(str_replace('-', ' ', $segments[0]));
        }

        $images = [];
        $videos = [];
        $formFields = [];
        $fieldQueue = []; // Queue to store fields detected between images
        $mdContent = "# {$title} 🚀\n\n";

        // Enrichment Lookup Table - Expanded with manual visual extraction
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
            'Serah Dokumen' => [
                'Dari Cabang' => 'Cabang asal pengirim dokumen.',
                'Dari Departemen' => 'Departemen asal pengirim dokumen.',
                'Ditujukan Kepada' => 'Nama staff/personel penerima dokumen.',
                'Ditujukan Cabang' => 'Cabang tujuan pengiriman dokumen.',
                'Ditujukan Departemen' => 'Departemen tujuan pengiriman dokumen.',
                'No. Dokumen' => 'Nomor dokumen fisik yang diserahkan.',
                'No. Referensi' => 'Nomor referensi transaksi asal (misal No. Faktur).'
            ],
            'Order Pembelian' => [
                'Tgl. PO' => 'Tanggal resmi Order Pembelian diterbitkan.',
                'T.O.P Hari' => 'Term of Payment (jangka waktu jatuh tempo pembayaran).',
                'Gudang Tujuan' => 'Gudang yang akan menerima kiriman barang dari supplier.',
                'Disc Item %' => 'Persentase diskon yang diberikan untuk setiap item.',
                'Netto Rp' => 'Nilai bersih setelah dipotong diskon dan pajak.'
            ],
            'Klaim Barang' => [
                'Jenis Klaim' => 'Kategori alasan klaim (misal: Barang Cacat atau Qty Kurang).',
                'No. Transaksi TTB' => 'Nomor TTB (Tanda Terima Barang) yang menjadi acuan klaim.',
                'Qty. Klaim' => 'Jumlah unit barang yang diajukan untuk diklaim.',
                'Qty. Kirim' => 'Jumlah unit barang yang dikirimkan kembali ke supplier.'
            ]
        ];

        // Process children of entry-content recursively for 1:1 layout match
        $rootNode = $contentNode->getNode(0);
        if ($rootNode) {
            $this->processNodesRecursively($rootNode, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup);
        }

        // Flush any remaining fields in the queue at the end
        if (!empty($fieldQueue)) {
            $mdContent .= "\n### 📋 Penjelasan Field Tambahan\n\n";
            foreach ($fieldQueue as $field) {
                $desc = !empty($field['explanation']) ? $field['explanation'] : $field['description'];
                $mdContent .= "- **{$field['field']}**: {$desc}\n";
            }
            $mdContent .= "\n";
        }

        // Add Video Section at the end if found
        if (!empty($videos)) {
            $mdContent .= "\n---\n\n### 🎥 Video Panduan\n\n";
            foreach ($videos as $v) {
                $mdContent .= "[Klik di sini untuk menonton video]({$v})\n\n";
            }
        }

        // Final Collection for JSON field
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
            'category' => $category,
            'keywords' => $this->generateKeywords($title, $category),
            'detail_panduan_lengkap' => trim($mdContent),
            'url' => $url,
            'form_fields' => array_values(array_slice($finalFields, 0, 25)),
            'images' => $images,
            'video' => $videos[0] ?? null,
            'last_fetched' => now()->format('Y-m-d H:i:s')
        ];
    }

    private function processNodesRecursively(\DOMNode $node, &$mdContent, &$images, &$videos, &$formFields, &$fieldQueue, $title, $enrichmentLookup, $level = 0)
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = trim($child->textContent);
                if ($text && strlen($text) >= 2) {
                    $this->handleTextNode($text, $mdContent, $fieldQueue, $formFields, $title, $enrichmentLookup);
                }
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) continue;

            $tagName = strtolower($child->nodeName);

            // Handle Headers
            if (in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                $text = trim($child->textContent);
                if ($text) {
                    $hLevel = (int)substr($tagName, 1) + 1;
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
                $src = $img->getAttribute('src');
                $alt = $img->getAttribute('alt') ?: 'Gambar Panduan';
                
                if (in_array(strtolower(trim($alt)), ['gambar', 'gambar panduan'])) $alt = 'Langkah Panduan';

                if ($src && str_contains($src, 'http')) {
                    $mdContent .= "![{$alt}]({$src})\n\n";
                    $images[] = ['src' => $src, 'alt' => $alt, 'caption' => ''];
                    
                    // Visual Enrichment Logic: Manual OCR Mapping for specific images
                    $visualFieldMap = [
                        '2-68_PRad5nErYPe8hMenpAYI.png' => ['No. Transaksi', 'Dari Cabang', 'Dari Departemen', 'Ditujukan Kepada', 'Ditujukan Cabang', 'Ditujukan Departemen', 'Keterangan'],
                        '3-56_mtClmXotyFToLoxzzCpT.png' => ['No. Dokumen', 'Tgl. Dokumen', 'No. Referensi', 'Tgl. Referensi', 'Kode Langganan', 'Nama Langganan']
                    ];

                    $filename = basename($src);
                    if (isset($visualFieldMap[$filename])) {
                        // Add visual fields to the queue if not already there
                        foreach ($visualFieldMap[$filename] as $vField) {
                            $fieldQueue[] = [
                                'field' => $vField,
                                'description' => 'Terdeteksi pada formulir di gambar.',
                                'explanation' => $enrichmentLookup['Serah Dokumen'][$vField] ?? ($enrichmentLookup['Common'][$vField] ?? '')
                            ];
                        }
                    }

                    // Flush fieldQueue immediately under the image
                    if (!empty($fieldQueue)) {
                        $mdContent .= "> **📋 Penjelasan Field pada gambar:**\n";
                        foreach ($fieldQueue as $field) {
                            $desc = !empty($field['explanation']) ? $field['explanation'] : $field['description'];
                            $mdContent .= "> - **{$field['field']}**: {$desc}\n";
                            $formFields[] = $field;
                        }
                        $mdContent .= "\n";
                        $fieldQueue = []; // Clear queue after flushing
                    }
                }
            }

            // Handle Paragraphs and Lists (Recurse into them to preserve order)
            elseif (in_array($tagName, ['p', 'ul', 'ol', 'li', 'div', 'article', 'section'])) {
                // Check if paragraph starts with a strong label (Pseudo-Header)
                $firstStrong = $child->getElementsByTagName('strong')->item(0);
                if ($tagName === 'p' && $firstStrong && ($child->firstChild === $firstStrong || ($child->firstChild->nodeType === XML_TEXT_NODE && trim($child->firstChild->textContent) === ''))) {
                    $headerText = trim($firstStrong->textContent);
                    $alertType = $this->getAlertType($headerText);
                    if ($alertType) {
                        $mdContent .= "\n> ### " . $this->getHeaderEmoji($headerText) . " " . rtrim($headerText, ' :-') . "\n>\n";
                        
                        // Process the rest of the paragraph but prefix with >
                        $tempContent = "";
                        $this->processNodesRecursively($child, $tempContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1);
                        
                        // Remove the header text from temp content to avoid duplication
                        $cleanContent = str_replace($headerText, '', $tempContent);
                        if (trim($cleanContent)) {
                            $mdContent .= "> " . trim($cleanContent) . "\n\n";
                        }
                        continue;
                    }
                }

                if ($tagName === 'li') $mdContent .= "- ";

                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1);
                
                if (in_array($tagName, ['p', 'ul', 'ol', 'div'])) {
                    $mdContent .= "\n\n";
                } elseif ($tagName === 'li') {
                    $mdContent .= "\n";
                }
            }

            // Handle Tables
            elseif ($tagName === 'table') {
                $mdContent .= "\n";
                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1);
                $mdContent .= "\n";
            }
            elseif ($tagName === 'tr') {
                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1);
                $mdContent .= "\n";
            }
            elseif (in_array($tagName, ['td', 'th'])) {
                $mdContent .= "| ";
                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1);
                $mdContent .= " ";
            }

            // Handle Video
            elseif ($tagName === 'video' || str_contains($child->getAttribute('class'), 'wp-video')) {
                $source = $child->getElementsByTagName('source')->item(0);
                $src = $source ? $source->getAttribute('src') : $child->getAttribute('src');
                if (!$src && $child->getElementsByTagName('a')->length > 0) {
                    $src = $child->getElementsByTagName('a')->item(0)->getAttribute('href');
                }

                if ($src && str_contains($src, 'http')) {
                    $videos[] = $src;
                    $mdContent .= "### 🎥 Video Panduan\n[Klik di sini untuk menonton video]({$src})\n\n";
                }
            }

            elseif ($tagName === 'hr') {
                $mdContent .= "---\n\n";
            }

            else {
                // Default recursion for other elements (span, strong, b, etc.) to ensure text is captured
                $this->processNodesRecursively($child, $mdContent, $images, $videos, $formFields, $fieldQueue, $title, $enrichmentLookup, $level + 1);
            }
        }
    }

    private function handleTextNode(string $text, &$mdContent, &$fieldQueue, &$formFields, $title, $enrichmentLookup)
    {
        // Ignore residual placeholder text
        $lower = strtolower($text);
        if ($lower === 'gambar' || $lower === 'gambar panduan' || $lower === 'video :' || $lower === 'video:') return;

        $mdContent .= "{$text} ";
        $this->detectFields($text, $fieldQueue, $title, $enrichmentLookup);
    }

    private function getAlertType(string $text): ?string
    {
        $text = strtolower($text);
        if (str_contains($text, 'fungsi')) return 'INFO';
        if (str_contains($text, 'persyaratan') || str_contains($text, 'syarat')) return 'IMPORTANT';
        if (str_contains($text, 'petunjuk') || str_contains($text, 'langkah')) return 'TIP';
        if (str_contains($text, 'catatan')) return 'NOTE';
        return null;
    }

    private function getHeaderEmoji(string $text): string
    {
        $text = strtolower($text);
        if (str_contains($text, 'petunjuk') || str_contains($text, 'langkah')) return '🛠️';
        if (str_contains($text, 'syarat')) return '📋';
        if (str_contains($text, 'catatan')) return '💡';
        if (str_contains($text, 'fungsi')) return '📋';
        if (str_contains($text, 'video')) return '🎥';
        return '';
    }

    private function detectFields(string $text, &$fieldQueue, $title, $enrichmentLookup)
    {
        // Improved Regex for fields: "Field :" or "**Field** :"
        // Restricted field name length to 2-50 chars and allowed more characters to capture ERP fields correctly
        if (preg_match('/(?:^|\n|\s)(?:\*\*)?([a-zA-Z0-9\s\/\(\)\.]{2,50})(?:\*\*)?\s*[:\-]\s*(.{2,500})/', $text, $matches)) {
            $field = trim($matches[1]);
            $description = trim($matches[2]);
            
            // Ignore common non-field headers and short fragments
            $lowerField = strtolower($field);
            if (in_array($lowerField, ['http', 'https', 'catatan', 'fungsi', 'petunjuk', 'persyaratan', 'syarat', 'input', 'update'])) return;
            if (str_contains($lowerField, 'gambar') || count(explode(' ', $field)) > 7) return;

            $explanation = '';
            // Check enrichment lookup (Per Category and Common)
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

    private function generateKeywords(string $title, string $category): array
    {
        $keys = [
            str_replace(' ', '', strtolower($title)),
            strtolower($title),
            strtolower($category)
        ];
        
        // Add specific variants
        if (str_contains(strtolower($title), 'pembayaran')) $keys[] = 'kasir';
        if (str_contains(strtolower($title), 'piutang')) $keys[] = 'ar';
        if (str_contains(strtolower($title), 'hutang')) $keys[] = 'ap';
        if (str_contains(strtolower($title), 'tanda terima barang')) $keys[] = 'ttb';
        
        return array_unique($keys);
    }

    private function updateJson(array $guides, array $categories)
    {
        $path = config_path('erp_guidance.json');
        
        sort($categories);

        $data = [
            'source' => 'http://74.48.112.31:6000/docs/ (Unified Scraper)',
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
        $this->info("💾 JSON updated at: {$path}");
    }
}
