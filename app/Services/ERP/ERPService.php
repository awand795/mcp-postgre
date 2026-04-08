<?php

namespace App\Services\ERP;

use App\Services\BaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

/**
 * ERPService
 *
 * Handles ERP navigation, guidance search, web scraping,
 * and local guidance management.
 */
class ERPService extends BaseService
{
    // ── get_erp_menu_navigation ─────────────────────────────────────────────
    public function getErpMenuNavigation(string $module = '', string $menuKeyword = ''): string
    {
        $navigationData = $this->getErpNavigationTree();

        // If module specified, return that module's details
        if (!empty($module)) {
            $normalizedModule = $this->normalizeModuleName($module);

            if (empty($normalizedModule) || !isset($navigationData[$normalizedModule])) {
                return $this->errorResponse(
                    "Modul '{$module}' tidak ditemukan. Gunakan get_erp_menu_navigation tanpa parameter untuk melihat daftar modul."
                );
            }

            $subMenus = $navigationData[$normalizedModule];
            $displayLines = [];
            $displayLines[] = "Berikut adalah lokasi dan menu pada modul **{$normalizedModule}** di sistem ERP:";
            $displayLines[] = '';

            foreach ($subMenus as $category => $items) {
                if (empty($items)) continue;

                if (count($items) === 1) {
                    $displayLines[] = "Terdapat {$items[0]['description']} pada menu **{$category}**.";
                    $displayLines[] = '';
                    continue;
                }

                $displayLines[] = "Pada bagian **{$category}**, tersedia beberapa menu berikut:";
                $displayLines[] = '';

                foreach ($items as $item) {
                    $path = $item['path'];
                    $desc = $item['description'];
                    $shortPath = preg_replace('/^' . preg_quote($normalizedModule, '/') . '\s*→\s*/', '', $path);
                    $displayLines[] = "- **{$shortPath}** — {$desc}";
                }
                $displayLines[] = '';
            }

            // Closing note
            $displayLines[] = 'Silakan pilih menu sesuai kebutuhan. Jika memerlukan panduan langkah penggunaan salah satu menu di atas, saya siap membantu.';

            return $this->safeJsonEncode([
                'module' => $normalizedModule,
                'location' => "Sidebar utama → {$normalizedModule}",
                'sub_menus' => $subMenus,
                'display_text' => implode("\n", $displayLines),
                'usage_tip' => 'Tampilkan display_text langsung ke user tanpa mengubah format.',
            ]);
        }

        // If menu_keyword specified, search across all modules
        if (!empty($menuKeyword)) {
            $keywordLower = strtolower($menuKeyword);
            $results = [];
            $displayLines = [];
            $displayLines[] = "Berikut menu yang terkait dengan **\"{$menuKeyword}**\" di sistem ERP:";
            $displayLines[] = '';

            foreach ($navigationData as $moduleName => $subMenus) {
                $matchedSubMenus = [];
                foreach ($subMenus as $category => $items) {
                    $matchedItems = [];
                    foreach ($items as $item) {
                        $path = strtolower($item['path'] ?? '');
                        $desc = strtolower($item['description'] ?? '');
                        if (strpos($path, $keywordLower) !== false || strpos($desc, $keywordLower) !== false) {
                            $matchedItems[] = $item;
                        }
                    }
                    if (!empty($matchedItems)) {
                        $matchedSubMenus[$category] = $matchedItems;
                    }
                }

                if (!empty($matchedSubMenus)) {
                    $results[$moduleName] = [
                        'module' => $moduleName,
                        'location' => "Sidebar utama → {$moduleName}",
                        'matched_sub_menus' => $matchedSubMenus,
                    ];

                    $displayLines[] = "Pada modul **{$moduleName}** (Sidebar utama → {$moduleName}):";
                    $displayLines[] = '';

                    foreach ($matchedSubMenus as $category => $items) {
                        foreach ($items as $item) {
                            $shortPath = preg_replace('/^' . preg_quote($moduleName, '/') . '\s*→\s*/', '', $item['path']);
                            $displayLines[] = "- **{$shortPath}** — {$item['description']}";
                        }
                    }
                    $displayLines[] = '';
                }
            }

            if (empty($results)) {
                return $this->safeJsonEncode([
                    'error' => "Tidak ditemukan menu yang cocok untuk keyword '{$menuKeyword}'.",
                    'hint' => 'Coba gunakan keyword yang lebih spesifik seperti "pembayaran", "piutang", "stok", dll.',
                ]);
            }

            $displayLines[] = 'Silakan pilih menu yang sesuai. Jika memerlukan panduan langkah penggunaan, saya siap membantu.';

            return $this->safeJsonEncode([
                'search_keyword' => $menuKeyword,
                'total_modules_matched' => count($results),
                'results' => array_values($results),
                'display_text' => implode("\n", $displayLines),
            ]);
        }

        // No filter: return list of module names only (NOT full details)
        $moduleNames = array_keys($navigationData);
        $displayLines = [];
        $displayLines[] = 'Sistem ERP memiliki beberapa modul utama yang dapat diakses melalui sidebar kiri aplikasi. Berikut daftar modul yang tersedia:';
        $displayLines[] = '';
        foreach ($moduleNames as $name) {
            $displayLines[] = "- **{$name}**";
        }
        $displayLines[] = '';
        $displayLines[] = 'Silakan sebutkan nama modul tertentu untuk melihat panduan lokasi dan daftar menu yang tersedia.';

        return $this->safeJsonEncode([
            'message' => 'Daftar modul ERP yang tersedia. Sebutkan nama modul spesifik untuk melihat detail navigasi.',
            'modules' => array_map(fn($name) => ['name' => $name], $moduleNames),
            'display_text' => implode("\n", $displayLines),
            'usage_hint' => 'Panggil tool ini lagi dengan parameter "module" untuk melihat path navigasi lengkap satu modul tertentu.',
        ]);
    }

    // ── ERP Navigation Tree Data ──────────────────────────────────────────
    private function getErpNavigationTree(): array
    {
        return [
            'Finance' => [
                'Transaksi' => [
                    ['path' => 'Finance → Transaksi → Penyelesaian PDC/Giro Masuk', 'description' => 'Proses giro masuk yang sudah di-kliring'],
                    ['path' => 'Finance → Transaksi → Pembayaran DP Pembelian', 'description' => 'Bayar uang muka ke supplier'],
                    ['path' => 'Finance → Transaksi → Terima Pembayaran Piutang', 'description' => 'Terima piutang dari pelanggan'],
                    ['path' => 'Finance → Transaksi → Pembayaran Tagihan Hutang', 'description' => 'Bayar hutang ke supplier'],
                ],
            ],
            'Account Payable' => [
                'Transaksi' => [
                    ['path' => 'Account Payable → Transaksi → Terima Tagihan Hutang', 'description' => 'Catat tagihan hutang dari faktur pembelian'],
                    ['path' => 'Account Payable → Transaksi → Pembayaran Hutang', 'description' => 'Proses pembayaran hutang dagang'],
                ],
            ],
            'Account Receivable' => [
                'Transaksi' => [
                    ['path' => 'Account Receivable → Transaksi → Terima Penagihan Piutang', 'description' => 'Catat hasil penagihan dari sales/collector'],
                    ['path' => 'Account Receivable → Transaksi → Pelunasan Piutang', 'description' => 'Lunasi piutang setelah pembayaran diterima'],
                ],
                'Cetak' => [
                    ['path' => 'Account Receivable → Cetak → Cetak Tagihan Piutang', 'description' => 'Cetak tagihan piutang pelanggan'],
                ],
            ],
            'Inventory' => [
                'Transaksi' => [
                    ['path' => 'Inventory → Transaksi → Order Pembelian', 'description' => 'Buat purchase order ke supplier'],
                    ['path' => 'Inventory → Transaksi → Permintaan Pembelian', 'description' => 'Request pembelian dari cabang'],
                    ['path' => 'Inventory → Transaksi → Penerimaan Barang', 'description' => 'Terima barang dari supplier'],
                    ['path' => 'Inventory → Transaksi → Pengeluaran Barang', 'description' => 'Keluarkan barang dari gudang'],
                    ['path' => 'Inventory → Transaksi → Penyesuaian Stok', 'description' => 'Sesuaikan stok fisik dengan sistem'],
                    ['path' => 'Inventory → Transaksi → Klaim Barang', 'description' => 'Proses klaim barang retur/rusak'],
                ],
                'Pembelian' => [
                    ['path' => 'Inventory → Transaksi → Pembelian → Pengajuan DP Pembelian', 'description' => 'Ajukan DP untuk PO'],
                ],
                'Insentif Sales' => [
                    ['path' => 'Inventory → Transaksi → Insentif Sales → Perhitungan Insentif Sales', 'description' => 'Hitung insentif tim penjualan'],
                    ['path' => 'Inventory → Transaksi → Insentif Sales → Pengajuan Proposal Insentif', 'description' => 'Buat proposal insentif'],
                ],
                'Lain-lain' => [
                    ['path' => 'Inventory → Transaksi → Lain-lain → Penerimaan Lain-lain — HPP', 'description' => 'Set HPP untuk penerimaan khusus'],
                ],
            ],
            'Warehouse' => [
                'Navigasi' => [
                    ['path' => 'Warehouse → Transfer Antar Gudang', 'description' => 'Pindah barang antar gudang'],
                    ['path' => 'Warehouse → Opname', 'description' => 'Stock opname fisik vs sistem'],
                    ['path' => 'Warehouse → Mutasi Stok', 'description' => 'Pindah stok antar lokasi/gudang'],
                ],
            ],
            'Report Center' => [
                'Fitur' => [
                    ['path' => 'Report Center → Laporan Tersedia', 'description' => 'Daftar semua laporan yang bisa diakses'],
                    ['path' => 'Report Center → Riwayat Laporan', 'description' => 'Histori laporan yang pernah dibuka'],
                    ['path' => 'Report Center → Setting', 'description' => 'Kustomisasi kolom & format laporan (PDF, XLS, CSV)'],
                ],
            ],
            'Document' => [
                'Navigasi' => [
                    ['path' => 'Document → Serah Dokumen', 'description' => 'Proses serah terima dokumen antar departemen'],
                    ['path' => 'Document → Nota Kredit Penjualan', 'description' => 'Buat nota kredit untuk retur penjualan'],
                ],
            ],
        ];
    }

    // ── Normalize module name for matching ────────────────────────────────
    private function normalizeModuleName(string $name): string
    {
        $normalized = strtolower(trim($name));

        // Direct mapping
        $map = [
            'finance' => 'Finance',
            'account payable' => 'Account Payable',
            'ap' => 'Account Payable',
            'hutang' => 'Account Payable',
            'account receivable' => 'Account Receivable',
            'ar' => 'Account Receivable',
            'piutang' => 'Account Receivable',
            'inventory' => 'Inventory',
            'inventory management' => 'Inventory',
            'warehouse' => 'Warehouse',
            'gudang' => 'Warehouse',
            'report center' => 'Report Center',
            'report' => 'Report Center',
            'laporan' => 'Report Center',
            'document' => 'Document',
            'dokumen' => 'Document',
        ];

        return $map[$normalized] ?? $this->fuzzyMatchModule($normalized);
    }

    // ── Fuzzy match module name ───────────────────────────────────────────
    private function fuzzyMatchModule(string $input): string
    {
        $modules = array_keys($this->getErpNavigationTree());

        foreach ($modules as $module) {
            if (stripos(strtolower($module), $input) !== false || stripos($input, strtolower($module)) !== false) {
                return $module;
            }
        }

        return ''; // No match
    }

    // ════════════════════════════════════════════════════════════════════════
    // ERP GUIDANCE TOOLS
    // ════════════════════════════════════════════════════════════════════════

    // ── get_erp_guidance ────────────────────────────────────────────────────────
    public function getErpGuidance(string $keyword = '', string $category = '', bool $listAll = false): string
    {
        $path = config_path('erp_guidance.json');

        if (!file_exists($path)) {
            Log::error("[ToolCallExecutor] ERP Guidance file not found at: {$path}");
            return $this->errorResponse('Data panduan ERP belum tersedia atau file konfigurasi tidak ditemukan.');
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (!$data || !isset($data['guides'])) {
            return $this->errorResponse('Format file panduan ERP tidak valid.');
        }

        $guides = $data['guides'];
        $results = [];

        // Jika minta semua, kirimkan daftar judul/akses saja (karena kepanjangan kalau diload utuh)
        if ($listAll) {
            $summary = array_map(function($g) {
                return [
                    'id' => $g['id'] ?? '',
                    'title' => $g['title'] ?? '',
                    'category' => $g['category'] ?? '',
                    // Cuplikan singkat dari detail
                    'summary' => substr($g['detail_panduan_lengkap'] ?? '', 0, 100) . '...'
                ];
            }, $guides);

            return $this->safeJsonEncode([
                'source' => $data['source'] ?? '',
                'total_found' => count($summary),
                'message' => 'Ini adalah daftar kategori dan judul panduan yang tersedia. Jika ingin melihat detail langkah-langkah, lakukan pencarian dengan parameter keyword spesifik sesuai judul ini.',
                'guides' => $summary
            ]);
        }

        // Kalau tidak cari keyword/kategori tapi mau akses list/search
        if (empty($keyword) && empty($category)) {
             return $this->safeJsonEncode([
                'message' => 'Harap berikan kata kunci atau kategori untuk mencari panduan.'
            ]);
        }

        $keywordLower = strtolower(trim($keyword));
        $categoryLower = strtolower(trim($category));

        // First pass: searching and scoring
        foreach ($guides as $guide) {
            $score = 0;
            $gTitle = strtolower($guide['title'] ?? '');
            $gDetail = strtolower($guide['detail_panduan_lengkap'] ?? '');

            $gKeys = [];
            if (isset($guide['keywords']) && is_array($guide['keywords'])) {
                $gKeys = array_map('strtolower', $guide['keywords']);
            }

            // Category Bonus (Replaced hard filter with priority boost)
            if (!empty($categoryLower)) {
                $gCat = strtolower($guide['category'] ?? '');
                if (strpos($gCat, $categoryLower) !== false) {
                    $score += 200; // Priority boost for target category
                }
            }

            if (!empty($keywordLower)) {
                // Normalize search keyword: collapse multiple spaces, dashes, underscores
                $normalizedKeyword = preg_replace('/[\s\-_]+/', ' ', trim($keywordLower));
                $keywordTokens = explode(' ', $normalizedKeyword);
                $keywordTokens = array_filter($keywordTokens, fn($t) => strlen($t) > 0);

                // Tier 1: Title match (Strongest signal)
                $normalizedGTitle = preg_replace('/[\s\-_]+/', ' ', $gTitle);
                if (strpos($normalizedGTitle, $normalizedKeyword) !== false) {
                    $score += 500; // Contains
                    if (strpos($normalizedGTitle, $normalizedKeyword) === 0) $score += 300; // Starts with
                    if ($normalizedGTitle === $normalizedKeyword) $score += 1000; // Exact match
                } else {
                    // Partial title match: check if most keyword tokens appear in title
                    $matchedTokens = 0;
                    foreach ($keywordTokens as $token) {
                        if (strpos($normalizedGTitle, $token) !== false) {
                            $matchedTokens++;
                        }
                    }
                    if ($matchedTokens > 0 && $matchedTokens <= count($keywordTokens)) {
                        $matchRatio = $matchedTokens / count($keywordTokens);
                        if ($matchRatio >= 0.5) {
                            $score += 300 * $matchRatio; // Partial match bonus
                        }
                    }
                }

                // Tier 2: Keyword match (bidirectional matching)
                foreach ($gKeys as $key) {
                    $normalizedKey = preg_replace('/[\s\-_]+/', ' ', $key);

                    // Check if search keyword is in stored keyword
                    if (strpos($normalizedKey, $normalizedKeyword) !== false) {
                        $score += 100;
                        if ($normalizedKey === $normalizedKeyword) $score += 300;
                        break;
                    }

                    // Check if stored keyword is in search keyword (reverse match)
                    if (strpos($normalizedKeyword, $normalizedKey) !== false) {
                        $score += 80;
                        break;
                    }

                    // Token-based matching: check if most tokens match
                    $keyTokens = explode(' ', $normalizedKey);
                    $keyTokens = array_filter($keyTokens, fn($t) => strlen($t) > 0);
                    $matchedTokens = 0;
                    foreach ($keywordTokens as $token) {
                        foreach ($keyTokens as $keyToken) {
                            if (strpos($keyToken, $token) !== false || strpos($token, $keyToken) !== false) {
                                $matchedTokens++;
                                break;
                            }
                        }
                    }
                    if ($matchedTokens > 0 && count($keyTokens) > 0) {
                        $matchRatio = $matchedTokens / max(count($keywordTokens), count($keyTokens));
                        if ($matchRatio >= 0.6) {
                            $score += 60 * $matchRatio;
                            break;
                        }
                    }
                }

                // Tier 3: Detail match (Low priority fallback)
                if (strpos($gDetail, $keywordLower) !== false) {
                    $score += 1;
                }
            } else {
                $score = 1;
            }

            if ($score > 0) {
                // Prepend category to title for better AI disambiguation
                $catPrefix = "[" . ($guide['category'] ?? 'General') . "] ";
                $guide['title'] = $catPrefix . ($guide['title'] ?? 'Untitled');

                $guide['_relevance_score'] = $score;
                $results[] = $guide;
            }
        }

        // Sort results by relevance score descending
        usort($results, function($a, $b) {
            return ($b['_relevance_score'] ?? 0) <=> ($a['_relevance_score'] ?? 0);
        });

        // Noise Suppression: If we have a very strong Title-Match result (> 500),
        // filter out all low-confidence matches (< 10) to avoid AI confusion.
        if (!empty($results) && ($results[0]['_relevance_score'] ?? 0) >= 500) {
            $results = array_filter($results, function($r) {
                return ($r['_relevance_score'] ?? 0) >= 10;
            });
            $results = array_values($results); // Re-index
        }

        // Limit results to top 5 to prevent context overflow and AI selection fatigue
        $results = array_slice($results, 0, 5);

        // Clean up internal score before returning
        foreach ($results as &$r) {
            unset($r['_relevance_score']);
        }

        if (empty($results)) {
             return $this->safeJsonEncode([
                'total_found' => 0,
                'message' => 'Tidak ditemukan panduan ERP yang cocok dengan kriteria pencarian: ' . ($keyword ?: $category),
            ]);
        }

        return $this->safeJsonEncode([
            'total_found' => count($results),
            'source'      => $data['source'] ?? '',
            'guides'      => $results
        ]);
    }

    /**
     * ── fetch_erp_guidance_from_web ──────────────────────────────────────────
     * Mengambil panduan langsung dari web (scraping) dengan login.
     */
    public function fetchErpGuidanceFromWeb(string $url): string
    {
        if (empty($url)) {
            return $this->errorResponse('URL wajib diisi.');
        }

        if (!str_contains($url, 'erp-guidance.online')) {
            return $this->errorResponse('Hanya URL dari erp-guidance.online yang diizinkan.');
        }

        try {
            Log::info("[ToolCallExecutor] Fetching ERP Guidance from web: {$url}");

            $response = $this->requestWithAuth($url);

            if (!$response->successful()) {
                return $this->errorResponse("Gagal mengambil halaman. Status: " . $response->status());
            }

            $html = $response->body();
            $data = $this->parseErpGuidancePage($html, $url);

            if (isset($data['error'])) {
                return $this->safeJsonEncode($data);
            }

            // Opsi: Simpan ke local JSON jika belum ada atau ingin update
            $this->updateLocalGuidance($data);

            return $this->safeJsonEncode([
                'message' => 'Panduan berhasil diambil dari web.',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error("[ToolCallExecutor] fetchErpGuidanceFromWeb failed: " . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat mengambil data dari web.');
        }
    }

    private function requestWithAuth(string $url)
    {
        $email = env('ERP_GUIDANCE_EMAIL');
        $password = env('ERP_GUIDANCE_PASSWORD');

        if (!$email || !$password) {
            throw new \Exception("Kredensial ERP Guidance belum dikonfigurasi di .env");
        }

        $loginUrl = 'https://erp-guidance.online/wp-login.php';
        $cookieJar = new \GuzzleHttp\Cookie\CookieJar();

        // Step 1: Login
        Http::asForm()->withOptions([
            'cookies' => $cookieJar,
            'allow_redirects' => true
        ])->post($loginUrl, [
            'log' => $email,
            'pwd' => $password,
            'wp-submit' => 'Log In',
            'testcookie' => 1
        ]);

        // Step 2: Fetch the actual URL with the same cookies
        return Http::withOptions([
            'cookies' => $cookieJar,
            'allow_redirects' => true
        ])->get($url);
    }

    private function parseErpGuidancePage(string $html, string $url): array
    {
        $crawler = new Crawler($html);

        if ($crawler->filter('form#loginform')->count() > 0) {
            Log::warning("[ToolCallExecutor] Scraper hit login page at: " . $url);
            return ['error' => 'Gagal login ke website. Periksa kredensial di .env'];
        }

        $title = $crawler->filter('h1.entry-title')->count() > 0
            ? $crawler->filter('h1.entry-title')->text()
            : 'Tanpa Judul';

        $contentNode = $crawler->filter('.entry-content');
        if ($contentNode->count() === 0) {
            return ['error' => 'Konten panduan tidak ditemukan di halaman ini.'];
        }

        // --- SECTION EXTRACTION & PREMIUM FORMATTING ---
        $sections = [
            'fungsi' => '',
            'persyaratan' => '',
            'petunjuk' => '',
            'catatan' => '',
            'video' => ''
        ];

        $currentSection = '';
        $formFields = [];
        $images = [];

        // Parse content elements for sectioning
        $contentNode->filter('h2, h3, h4, p, li, b, strong')->each(function (Crawler $node) use (&$sections, &$currentSection, &$formFields, &$images) {
            $text = trim($node->text());
            if (empty($text)) return;

            $lowerText = strtolower($text);
            if (str_contains($lowerText, 'fungsi :') || str_contains($lowerText, 'fungsi:')) {
                $currentSection = 'fungsi';
                $sections['fungsi'] .= str_replace(['Fungsi :', 'Fungsi:'], '', $text) . " ";
            } elseif (str_contains($lowerText, 'persyaratan data') || str_contains($lowerText, 'persyaratan:')) {
                $currentSection = 'persyaratan';
            } elseif (str_contains($lowerText, 'petunjuk pemakaian')) {
                $currentSection = 'petunjuk';
            } elseif (str_contains($lowerText, 'catatan :') || str_contains($lowerText, 'catatan:')) {
                $currentSection = 'catatan';
            } elseif (str_contains($lowerText, 'video :') || str_contains($lowerText, 'video:')) {
                $currentSection = 'video';
            } else {
                if ($currentSection && isset($sections[$currentSection])) {
                    $sections[$currentSection] .= $text . " ";
                }

                // Detect form fields while parsing text
                if (preg_match('/^([a-zA-Z0-9\.\s\/]+)\s*[:\-]\s*(.+)$/', $text, $matches)) {
                    $formFields[] = [
                        'field' => trim($matches[1]),
                        'description' => trim($matches[2])
                    ];
                }
            }
        });

        // Extract Images & Descriptions
        $contentNode->filter('img')->each(function (Crawler $img) use (&$images) {
            $src = $img->attr('src');
            $alt = $img->attr('alt') ?: '';
            if ($src) {
                $images[] = ['url' => $src, 'alt' => $alt];
            }
        });

        // Extract Video URLs (if any)
        $videos = [];
        $contentNode->filter('iframe')->each(function (Crawler $iframe) use (&$videos) {
            $src = $iframe->attr('src');
            if ($src) {
                $videos[] = $src;
            }
        });

        return [
            'url' => $url,
            'title' => trim($title),
            'sections' => array_map('trim', $sections),
            'form_fields' => $formFields,
            'images' => $images,
            'videos' => $videos,
        ];
    }

    /**
     * Update local ERP guidance JSON with new data.
     */
    private function updateLocalGuidance(array $newData): void
    {
        $path = config_path('erp_guidance.json');

        $existingData = [];
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $existingData = json_decode($content, true) ?? [];
        }

        if (!isset($existingData['guides'])) {
            $existingData['guides'] = [];
        }

        // Check if guide already exists
        $found = false;
        foreach ($existingData['guides'] as &$guide) {
            if (($guide['url'] ?? '') === ($newData['url'] ?? '')) {
                $guide = array_merge($guide, $newData);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $existingData['guides'][] = $newData;
        }

        $existingData['last_updated'] = now()->toISOString();
        $existingData['source'] = 'erp-guidance.online';

        file_put_contents($path, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * ── refresh_all_erp_guidance ──────────────────────────────────────────
     * Batch refresh multiple ERP guidance URLs.
     */
    public function refreshAllErpGuidance(array $urls): string
    {
        if (empty($urls)) {
            return $this->errorResponse('No URLs provided.');
        }

        $results = [];
        foreach ($urls as $url) {
            $result = $this->fetchErpGuidanceFromWeb($url);
            $results[] = ['url' => $url, 'result' => $result];
        }

        return $this->safeJsonEncode([
            'message' => 'Batch refresh completed.',
            'total_urls' => count($urls),
            'results' => $results,
        ]);
    }
}
