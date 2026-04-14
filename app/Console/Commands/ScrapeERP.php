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

        // Extract Category from URL Path or Breadcrumbs
        $category = 'Uncategory';
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', trim($path, '/'));
        if (count($segments) >= 1) {
            $category = ucwords(str_replace('-', ' ', $segments[0]));
        }

        $images = [];
        $videos = [];
        $formFields = [];
        $mdContent = "# {$title} 🚀\n\n";

        // Enrichment Lookup Table (Integrated from legacy commands)
        $enrichmentLookup = [
            'Order Pembelian' => [
                'No. Transaksi' => 'Otomatis dihasilkan sistem.',
                'Tgl. Transaksi' => 'Tanggal pencatatan transaksi.',
                'Tgl. PO' => 'Tanggal Order Pembelian.',
                'T.O.P Hari' => 'Jangka waktu pembayaran.',
                'Cabang' => 'Cabang pembuat pesanan.',
                'Gudang Tujuan' => 'Gudang penerima barang.',
                'Supplier' => 'Nama pemasok barang.',
                'Kode Barang' => 'ID unik produk (pilih dari daftar).',
                'Qty Order' => 'Jumlah barang yang dipesan.',
                'Harga' => 'Harga satuan barang.',
                'Disc Item %' => 'Diskon per item dalam persentase.',
                'Netto Rp' => 'Harga bersih setelah diskon dan pajak.'
            ],
            'Klaim Barang' => [
                'No. Transaksi' => 'Otomatis dihasilkan sistem ERP.',
                'Jenis Klaim' => 'Pilihan alasan klaim (Barang Rusak/Kurang).',
                'No. Transaksi TTB' => 'Referensi TTB asal barang.',
                'Qty. Klaim' => 'Jumlah barang yang diklaim.',
                'Qty. Kirim' => 'Jumlah fisik barang yang dikirim balik.'
            ],
            'Penyelesaian PDC' => [
                'Kliring' => 'Proses penyetoran PDC/giro ke bank.',
                'Batal Cair' => 'Membatalkan pencairan yang sudah diproses.',
                'Tanggal Setor' => 'Tanggal penyerahan fisik ke bank.',
                'Rekening Tujuan' => 'Akun bank penerima dana.'
            ]
        ];

        // Linear DOM Traversal to preserve 100% web layout
        $contentNode->filter('h1, h2, h3, h4, h5, h6, p, ul, ol, hr, figure, img, .wp-video video')->each(function (Crawler $node) use (&$mdContent, &$images, &$videos, &$formFields, $title, $enrichmentLookup) {
            $nodeName = $node->nodeName();
            
            // Handle Headers
            if (in_array($nodeName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                $level = substr($nodeName, 1);
                $text = trim($node->text());
                if ($text) {
                    $prefix = str_repeat('#', $level + 1);
                    $emoji = $this->getHeaderEmoji($text);
                    $mdContent .= "{$prefix} {$text} {$emoji}\n\n";
                }
            }
            
            // Handle Paragraphs
            elseif ($nodeName === 'p') {
                // Check for embedded images in p
                $imgNode = $node->filter('img');
                if ($imgNode->count()) {
                    $src = $imgNode->attr('src');
                    $alt = $imgNode->attr('alt') ?: 'Gambar';
                    $mdContent .= "![{$alt}]({$src})\n\n";
                    $images[] = ['src' => $src, 'alt' => $alt, 'caption' => ''];
                }
                
                $text = trim($node->text());
                if ($text) {
                    $mdContent .= "{$text}\n\n";
                    $this->detectFields($text, $formFields, $title, $enrichmentLookup);
                }
            }
            
            // Handle Lists
            elseif ($nodeName === 'ul' || $nodeName === 'ol') {
                $node->filter('li')->each(function (Crawler $li) use (&$mdContent, &$formFields, $title, $enrichmentLookup) {
                    $text = trim($li->text());
                    if ($text) {
                        $mdContent .= "- {$text}\n";
                        $this->detectFields($text, $formFields, $title, $enrichmentLookup);
                        
                        // Check for images inside li
                        if ($li->filter('img')->count()) {
                            $src = $li->filter('img')->attr('src');
                            $mdContent .= "  ![Gambar]({$src})\n";
                        }
                    }
                });
                $mdContent .= "\n";
            }
            
            // Handle Standalone Images / Figures
            elseif ($nodeName === 'img' || $nodeName === 'figure') {
                $img = $nodeName === 'img' ? $node : $node->filter('img');
                if ($img->count()) {
                    $src = $img->attr('src');
                    $alt = $img->attr('alt') ?: 'Gambar Panduan';
                    $mdContent .= "![{$alt}]({$src})\n\n";
                    $images[] = ['src' => $src, 'alt' => $alt, 'caption' => ''];
                }
            }
            
            // Handle Horizontal Rule
            elseif ($nodeName === 'hr') {
                $mdContent .= "---\n\n";
            }
        });

        // Final Field Enrichment Section
        if (!empty($formFields)) {
            $mdContent .= "### 📋 Penjelasan Field Formulir\n\n";
            $mdContent .= "Berikut adalah penjelasan detail mengenai field yang perlu diisi pada gambar di atas:\n\n";
            
            // Unique fields only
            $uniqueFields = [];
            foreach ($formFields as $f) {
                if (!isset($uniqueFields[$f['field']])) {
                    $uniqueFields[$f['field']] = $f;
                }
            }

            foreach ($uniqueFields as $field) {
                $desc = !empty($field['explanation']) ? $field['explanation'] : $field['description'];
                $mdContent .= "- **{$field['field']}**: {$desc}\n";
            }
            $mdContent .= "\n";
        }

        // Final Object
        return [
            'id' => md5($url),
            'title' => $title,
            'category' => $category,
            'keywords' => $this->generateKeywords($title, $category),
            'detail_panduan_lengkap' => trim($mdContent),
            'url' => $url,
            'form_fields' => array_values(array_slice($uniqueFields, 0, 20)),
            'images' => $images,
            'video' => $videos[0] ?? null,
            'last_fetched' => now()->format('Y-m-d H:i:s')
        ];
    }

    private function getHeaderEmoji(string $text): string
    {
        $text = strtolower($text);
        if (str_contains($text, 'petunjuk') || str_contains($text, 'langkah')) return '🛠️';
        if (str_contains($text, 'syarat')) return '📋';
        if (str_contains($text, 'catatan')) return '💡';
        if (str_contains($text, 'fungsi')) return '📋';
        return '';
    }

    private function detectFields(string $text, &$formFields, $title, $enrichmentLookup)
    {
        // Improved Regex for fields: "Field :" or "**Field** :"
        if (preg_match('/(?:^|\n|\s)(?:\*\*)?([a-zA-Z0-9\.\s\/]{2,30})(?:\*\*)?\s*[:\-]\s*(.{5,200})/', $text, $matches)) {
            $field = trim($matches[1]);
            $description = trim($matches[2]);
            
            // Ignore common non-field text
            if (in_array(strtolower($field), ['http', 'https', 'catatan', 'fungsi', 'petunjuk'])) return;

            $explanation = '';
            // Check enrichment lookup
            foreach ($enrichmentLookup as $key => $fields) {
                if (str_contains(strtolower($title), strtolower($key))) {
                    foreach ($fields as $fieldName => $exp) {
                        if (str_contains(strtolower($field), strtolower($fieldName))) {
                            $explanation = $exp;
                            break 2;
                        }
                    }
                }
            }

            $formFields[] = [
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
