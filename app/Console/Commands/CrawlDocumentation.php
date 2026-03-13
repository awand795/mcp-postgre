<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrawlDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:crawl-documentation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting ERP Documentation Crawl...');

        $loginPageUrl = 'https://erp-guidance.online/login/';
        $email = 'afdolfarosir19@gmail.com';
        $password = 'Alfaro19@wp';

        $jar = new \GuzzleHttp\Cookie\CookieJar;

        // 1. Get Login Page to extract Nonce
        $this->info('Fetching login page for nonce...');
        $res = \Illuminate\Support\Facades\Http::withOptions(['cookies' => $jar, 'verify' => false])->get($loginPageUrl);
        if (!$res->successful()) {
            $this->error('Failed to fetch login page.');
            return 1;
        }

        $crawler = new \Symfony\Component\DomCrawler\Crawler($res->body());
        $nonce = $crawler->filter('input[name="erpgl_login_nonce"]')->count() 
                 ? $crawler->filter('input[name="erpgl_login_nonce"]')->attr('value') 
                 : null;
        $referer = $crawler->filter('input[name="_wp_http_referer"]')->count() 
                   ? $crawler->filter('input[name="_wp_http_referer"]')->attr('value') 
                   : '/login/';

        if (!$nonce) {
            $this->error('Could not find login nonce.');
            // Fallback: try standard wp-login
            $this->info('Continuing without nonce, though it might fail...');
        } else {
            $this->info('Found nonce: ' . $nonce);
        }

        // 2. Login POST
        $this->info('Logging in...');
        $response = \Illuminate\Support\Facades\Http::withOptions([
                'cookies' => $jar,
                'allow_redirects' => true,
                'verify' => false,
            ])
            ->asForm()
            ->post($loginPageUrl, [
                'log' => $email,
                'pwd' => $password,
                'wp-submit' => 'Log In',
                'rememberme' => 'forever',
                'erpgl_login_nonce' => $nonce,
                '_wp_http_referer' => $referer,
                'testcookie' => 1
            ]);

        if (!$response->successful()) {
            $this->error('Login request failed with status: ' . $response->status());
            return 1;
        }

        $this->info('Login attempt finished. Cookies: ' . count($jar->toArray()));

        $baseUrl = 'https://erp-guidance.online/docs/';
        $links = [];
        $page = 1;

        // 3. Crawl List of Links
        while (true) {
            $url = $page === 1 ? $baseUrl : "{$baseUrl}page/{$page}/";
            $this->info("Fetching page list: {$url}");

            $res = \Illuminate\Support\Facades\Http::withOptions(['cookies' => $jar, 'verify' => false])->get($url);
            if (!$res->successful()) {
                $this->warn("Failed to fetch list page {$page}: " . $res->status());
                break;
            }

            $body = $res->body();
            $crawler = new \Symfony\Component\DomCrawler\Crawler($body);
            $foundOnPage = false;

            $crawler->filter('h2.entry-title a')->each(function ($node) use (&$links, &$foundOnPage) {
                $href = $node->attr('href');
                if ($href && !in_array($href, $links)) {
                    $links[] = $href;
                    $foundOnPage = true;
                }
            });

            if (!$foundOnPage) {
                $this->warn("No links found on page {$page}.");
                // Debug: snippet of body if 0 links found
                if ($page === 1) {
                    $this->info("First 500 chars of body: " . substr(strip_tags($body), 0, 500));
                }
                break;
            }
            
            if ($crawler->filter('a.next.page-numbers')->count() === 0) {
                $this->info("No next page link found.");
                break;
            }

            $page++;
            if ($page > 20) break;
        }

        $links = array_unique($links);
        $this->info('Found ' . count($links) . ' documentation links.');

        if (empty($links)) {
            $this->error('No documentation links found. Crawl aborted.');
            return 1;
        }

        // 4. Crawl Individual Pages
        foreach ($links as $index => $url) {
            $this->info("[" . ($index + 1) . "/" . count($links) . "] Crawling: {$url}");

            try {
                $res = \Illuminate\Support\Facades\Http::withOptions(['cookies' => $jar, 'verify' => false])->get($url);
                if (!$res->successful()) {
                    $this->warn("Failed to fetch: {$url} (Status: {$res->status()})");
                    continue;
                }

                $crawler = new \Symfony\Component\DomCrawler\Crawler($res->body());

                $title = $crawler->filter('h1.entry-title')->count() ? $crawler->filter('h1.entry-title')->text() : 'Untitled';
                $contentCrawler = $crawler->filter('.entry-content');
                if ($contentCrawler->count() === 0) {
                    $this->warn("Empty content for: {$url}");
                    continue;
                }

                // Custom extraction to include images
                $rawContent = '';
                $contentCrawler->filter('p, ul, ol, h1, h2, h3, h4, h5, h6, img, video, .wp-video')->each(function ($node) use (&$rawContent) {
                    $nodeName = $node->nodeName();
                    
                    if ($nodeName === 'img') {
                        $src = $node->attr('src');
                        $alt = $node->attr('alt') ?: 'Gambar';
                        $rawContent .= "\n[GAMBAR: {$alt} - URL: {$src}]\n";
                    } elseif ($nodeName === 'video' || str_contains($node->attr('class') ?? '', 'wp-video')) {
                        $videoNode = $nodeName === 'video' ? $node : $node->filter('video');
                        if ($videoNode->count()) {
                            $src = $videoNode->filter('source')->count() ? $videoNode->filter('source')->attr('src') : $videoNode->attr('src');
                            $rawContent .= "\n[VIDEO: URL: {$src}]\n";
                        }
                    } else {
                        $rawContent .= "\n" . $node->text() . "\n";
                    }
                });

                if (empty(trim($rawContent))) {
                    $rawContent = $contentCrawler->text(null, true);
                }

                // Clean up whitespace: collapse spaces/tabs but keep newlines
                $content = preg_replace('/[ \t]+/', ' ', $rawContent);
                $content = preg_replace('/\n\s*\n/', "\n\n", $content);
                $content = trim($content);

                // Save to DB
                \Illuminate\Support\Facades\DB::table('documentation')->updateOrInsert(
                    ['url' => $url],
                    [
                        'title' => $title,
                        'content' => $content,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

            } catch (\Exception $e) {
                $this->error("Error crawling {$url}: " . $e->getMessage());
            }
        }

        $this->info('Crawl completed.');
    }
}
