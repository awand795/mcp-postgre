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

        // Extract Category
        $category = 'Uncategory';
        $crawler->filter('.cat-links a')->each(function (Crawler $node) use (&$category) {
            $category = trim($node->text());
        });

        // Initialize Sections
        $sections = [
            'fungsi' => '',
            'persyaratan' => '',
            'petunjuk' => '',
            'catatan' => '',
            'steps' => []
        ];

        $images = [];
        $videos = [];
        $formFields = [];
        $currentSection = '';

        // Parsing content for Markdown construction and image extraction
        $contentNode->filter('h2, h3, h4, p, li, strong, b, img, iframe, video, source, .wp-video video')->each(function (Crawler $node) use (&$sections, &$currentSection, &$images, &$videos, &$formFields) {
            $nodeName = $node->nodeName();
            $text = trim($node->text());

            if ($nodeName === 'img') {
                $src = $node->attr('src');
                if ($src) {
                    $images[] = [
                        'src' => $src,
                        'alt' => $node->attr('alt') ?: 'Gambar Panduan',
                        'caption' => ''
                    ];
                }
                return;
            }

            if ($nodeName === 'video' || $nodeName === 'iframe' || $nodeName === 'source') {
                $src = $node->attr('src') ?: $node->attr('data-src');
                if ($src && !in_array($src, $videos)) {
                    $videos[] = $src;
                }
                return;
            }

            if (empty($text)) return;

            $lowerText = strtolower($text);

            // Detect Sections
            if (str_contains($lowerText, 'fungsi :') || str_contains($lowerText, 'fungsi:')) {
                $currentSection = 'fungsi';
                $sections['fungsi'] .= str_replace(['Fungsi :', 'Fungsi:'], '', $text) . ' ';
            } elseif (str_contains($lowerText, 'persyaratan data') || str_contains($lowerText, 'persyaratan:')) {
                $currentSection = 'persyaratan';
            } elseif (str_contains($lowerText, 'petunjuk pemakaian') || str_contains($lowerText, 'langkah-langkah')) {
                $currentSection = 'petunjuk';
            } elseif (str_contains($lowerText, 'catatan :') || str_contains($lowerText, 'catatan:')) {
                $currentSection = 'catatan';
            } else {
                if ($currentSection && isset($sections[$currentSection])) {
                    if (is_array($sections[$currentSection])) {
                        $sections[$currentSection][] = $text;
                    } else {
                        $sections[$currentSection] .= $text . ' ';
                    }
                }

                // Detect form fields: "Field Name : Description"
                if (preg_match('/^([a-zA-Z0-9\.\s\/]+)\s*[:\-]\s*(.+)$/', $text, $matches)) {
                    $formFields[] = [
                        'field' => trim($matches[1]),
                        'description' => trim($matches[2])
                    ];
                }
            }
        });

        // Construct Premium Markdown
        $md = "# {$title} 🚀\n\n";
        
        if (!empty($sections['fungsi'])) {
            $md .= "> **Fungsi:** " . trim($sections['fungsi']) . "\n\n";
        }

        if (!empty($sections['persyaratan'])) {
            $md .= "### 📋 Persyaratan Data\n\n" . trim($sections['persyaratan']) . "\n\n";
        }

        // Re-construct the full content but pretty for AI
        // We'll use a more heuristic approach to preserve images in steps
        $fullContentText = "";
        $contentNode->filter('h2, h3, h4, p, li, img, .wp-video video, .wp-block-image')->each(function (Crawler $node) use (&$fullContentText) {
             if ($node->nodeName() === 'h2' || $node->nodeName() === 'h3') {
                 $fullContentText .= "### " . $node->text() . "\n\n";
             } elseif ($node->nodeName() === 'p' || $node->nodeName() === 'li') {
                 $text = trim($node->text());
                 if (!empty($text)) {
                    if ($node->nodeName() === 'li') {
                        $fullContentText .= "- " . $text . "\n";
                    } else {
                        $fullContentText .= $text . "\n\n";
                    }
                 }
             } elseif ($node->nodeName() === 'img') {
                 $src = $node->attr('src');
                 if ($src) {
                    $fullContentText .= "![Gambar Panduan]({$src})\n\n";
                 }
             } elseif ($node->filter('img')->count()) {
                 $src = $node->filter('img')->attr('src');
                 if ($src) {
                    $fullContentText .= "![Gambar Panduan]({$src})\n\n";
                 }
             }
        });

        if (!empty($fullContentText)) {
            // Remove redundant titles or duplicated sections if any
            $md .= $fullContentText;
        }

        if (!empty($sections['catatan'])) {
            $md .= "### 💡 Catatan Penting\n" . trim($sections['catatan']) . "\n";
        }

        // Final Object
        return [
            'id' => md5($url),
            'title' => $title,
            'category' => $category,
            'keywords' => $this->generateKeywords($title, $category),
            'detail_panduan_lengkap' => trim($md),
            'url' => $url,
            'form_fields' => array_slice($formFields, 0, 15), // Limit to relevant fields
            'images' => $images,
            'video' => $videos[0] ?? null,
            'last_fetched' => now()->format('Y-m-d H:i:s')
        ];
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

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("💾 JSON updated at: {$path}");
    }
}
