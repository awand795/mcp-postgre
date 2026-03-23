<?php

namespace App\Http\Controllers;

use App\Helpers\LanguageDetector;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    private string $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';

    private array $models = [
        'anthropic/claude-3.5-sonnet',
        'anthropic/claude-3-opus',
        'anthropic/claude-3-haiku',
    ];

    private int $maxHistoryTurns = 10;

    // Cache nama kolom tanggal di tabel transaksi (auto-detect)
    private ?string $colTanggal = null;

    // Language detector instance
    private LanguageDetector $languageDetector;

    /**
     * Constructor - initialize language detector
     */
    public function __construct()
    {
        $this->languageDetector = new LanguageDetector();
    }

    /**
     * Mendapatkan daftar tabel yang boleh diakses berdasarkan role user
     */
    private function getAllowedTables(): array
    {
        if (!Auth::check()) {
            // Fallback: allow common tables for unauthenticated users (for testing)
            Log::warning('No authenticated user, using default allowed tables');
            return [
                'produk', 'kategori', 'transaksi', 'detail_transaksi', 'pembeli', 'karyawan',
                'view_data_penjualan_rinci_mbi', 'view_master_cabang_mbi', 'view_master_pelanggan_mbi',
                'view_data_target_realisasi_mbi', 'view_target_unit_mbi', 'view_master_barang_mbi',
                'view_data_kartu_stock_mbi', 'view_master_provinsi_mbi', 'view_master_kabupaten_mbi'
            ];
        }

        $user = Auth::user();
        
        // Bypass untuk Super Admin (is_admin = true)
        if ($user->is_admin) {
            return cache()->remember('all_db_tables_admin_bypass', 600, function() {
                $tables = DB::connection('pgsql_mbi')->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'sch_mbi' ORDER BY table_name");
                $tableList = array_column($tables, 'table_name');
                Log::info("Super Admin bypass: access granted to all " . count($tableList) . " tables.");
                return $tableList;
            });
        }

        $roleId = $user->role;

        return cache()->remember("allowed_tables_role_{$roleId}", 600, function () use ($roleId) {
            $tables = RolePermission::where('role_id', $roleId)->pluck('table_name')->toArray();
            Log::info("Allowed tables for role {$roleId}: " . implode(', ', $tables));
            return $tables;
        });
    }

    public function index()
    {
        return view('chatbot');
    }

    // ── Deteksi nama kolom tanggal di tabel transaksi ─────────────────────────
    // Beberapa database pakai 'tanggal', 'tanggal_transaksi', 'created_at', dll.
    private function getColTanggal(): string
    {
        if ($this->colTanggal !== null) {
            return $this->colTanggal;
        }

        $this->colTanggal = cache()->remember('col_tanggal_transaksi', 3600, function () {
            try {
                $cols = DB::connection('pgsql_mbi')->select("
                    SELECT column_name FROM information_schema.columns
                    WHERE table_name = 'transaksi' AND table_schema = 'public'
                    AND data_type IN ('date','timestamp','timestamp with time zone','timestamp without time zone')
                    ORDER BY ordinal_position
                    LIMIT 1
                ");
                if (!empty($cols)) {
                    Log::info("Auto-detected tanggal column: " . $cols[0]->column_name);
                    return $cols[0]->column_name;
                }
            } catch (\Exception $e) {
                Log::error("getColTanggal error: " . $e->getMessage());
            }
            return 'tanggal'; // default fallback
        });

        return $this->colTanggal;
    }

    // ── Deteksi nama kolom total bayar di tabel transaksi ────────────────────
    private function getColTotalBayar(): string
    {
        return 'total_harga';
    }

    public function send(Request $request)
    {
        set_time_limit(300);
        $message = $request->input('message');
        $history = $request->input('history', []);
        $apiKey  = env('OPENROUTER_API_KEY');

        Log::info("Chatbot send: ", ['message' => $message]);

        if (!$apiKey) {
            return response()->json(['response' => "Error: OPENROUTER_API_KEY atau NVIDIA_API_KEY tidak dikonfigurasi di .env"]);
        }

        $detectedLanguage = $this->languageDetector->detect($message);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Untuk Nginx agar tidak di-buffer
        
        // Kirim event pertama segera agar koneksi tidak timeout saat planning SQL
        echo "data: " . json_encode(['chunk' => '']) . "\n\n";
        ob_flush(); flush();

        $needsData = $this->messageNeedsDatabase($message);
        $dbContext = '';
        $docContext = '';

        Log::info("Needs database: " . ($needsData ? 'YES' : 'NO'));

        $needsDocs = $this->messageNeedsDocs($message);

        if ($needsDocs) {
            $docContext = $this->fetchRelevantDocs($message);
            Log::info("Needs docs: YES, length: " . strlen($docContext));
            if (!empty($docContext)) {
                $needsData = false;
            }
        }

        $schemaContext = $this->getSchemaContext($message);

        if ($needsData) {
            $dbContext = $this->fetchRelevantData($message, $schemaContext, $apiKey);
            Log::info("Needs docs: NO, fetching DB data, length: " . strlen($dbContext));
        }
        
        Log::info("Detected language: {$detectedLanguage}");
        $systemPrompt  = $this->buildSystemPrompt($schemaContext, $dbContext, $docContext, $detectedLanguage);

        Log::info("System prompt length: " . strlen($systemPrompt));
        Log::info("System prompt starts with: " . substr($systemPrompt, 0, 200));
        Log::info("DB Context empty: " . (empty($dbContext) ? 'YES' : 'NO'));

        // Build messages array with explicit language instruction
        $messages = [];
        
        // Add system prompt
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        
        // Add explicit language instruction as a separate system message (reinforcement)
        $langInstruction = $detectedLanguage === 'en' 
            ? "IMPORTANT: The user speaks ENGLISH. You MUST respond in ENGLISH only. Do not use any Indonesian words."
            : "PENTING: User berbicara BAHASA INDONESIA. Anda HARUS merespons dalam Bahasa Indonesia saja. Jangan gunakan kata bahasa Inggris.";
        $messages[] = ['role' => 'system', 'content' => $langInstruction];
        
        // Add conversation history
        $trimmedHistory = array_slice($history, -($this->maxHistoryTurns * 2));
        foreach ($trimmedHistory as $turn) {
            if (isset($turn['role'], $turn['content'])) {
                $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
            }
        }
        
        // Add current user message
        $messages[] = ['role' => 'user', 'content' => $message];

        // Ensure session is written and closed before streaming to avoid blocking other requests
        session_write_close();

        return response()->stream(function () use ($messages, $apiKey, $dbContext) {
            $this->streamAIResponse($messages, $apiKey, $dbContext);
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    // ── Panggil AI dengan auto-fallback (Streaming SSE) ───────────────────────
    // ── Perencanaan Query SQL (LLM call) ─────────────────────────────────────
    private function planSQLQueries(string $message, string $schemaContext, string $apiKey): array
    {
        Log::info("Planning SQL for: " . $message);

        $systemPrompt = "You are a SQL Planner for PostgreSQL database. SCHEMA INFORMATION:
{$schemaContext}

⚠️ CRITICAL RULES - READ CAREFULLY:
1. ONLY use table names that are explicitly listed in the SCHEMA above (e.g., view_master_cabang_mbi, view_data_penjualan_rinci_mbi, etc.)
2. NEVER invent table names like 'cabang', 'produk', 'pembeli', 'target', 'regions', 'master_cabang' - these DO NOT EXIST!
3. ALWAYS use the full table name with 'sch_mbi.' prefix (e.g., sch_mbi.view_master_cabang_mbi)
4. If user asks for data but you're unsure which table to use, respond with a clarification request
5. ONLY use column names that are listed for each table in the schema above

RESPONSE FORMAT:
- Respond ONLY: [LABEL]User Language Label[/LABEL] [SQL]SELECT ...[/SQL]
- Limit 50 rows maximum
- No explanation, no semicolon at the end

EXAMPLE CORRECT QUERIES:
✓ SELECT * FROM sch_mbi.view_master_cabang_mbi LIMIT 50
✓ SELECT nama_cabang, alamat_cabang FROM sch_mbi.view_master_cabang_mbi WHERE nama_propinsi_cabang ILIKE '%riau%'
✗ SELECT * FROM sch_mbi.cabang (WRONG - table 'cabang' does not exist!)
✗ SELECT * FROM sch_mbi.master_cabang (WRONG - use 'view_master_cabang_mbi' instead!)";

        try {
            $response = Http::timeout(120)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post($this->apiUrl, [
                'model'       => $this->models[0],
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message]
                ],
                'max_tokens'  => 400,
                'temperature' => 0.1,
            ]);

            if (!$response->successful()) {
                Log::error("SQL Planner failed: " . $response->body());
                return [];
            }

            $content = $response->json('choices.0.message.content');
            Log::info("SQL Planner RAW response: " . $content);

            $queries = [];
            preg_match_all('/\[LABEL\](.*?)\[\/LABEL\]\s*\[SQL\](.*?)\[\/SQL\]/si', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $label = trim($match[1]);
                $sql   = trim($match[2]);
                if (!empty($label) && !empty($sql)) {
                    $queries[$label] = $sql;
                }
            }
            
            Log::info("Parsed " . count($queries) . " queries from AI");
            return $queries;

        } catch (\Exception $e) {
            Log::error("planSQLQueries error: " . $e->getMessage());
            return [];
        }
    }

    // ── Validasi SQL untuk keamanan ──────────────────────────────────────────
    private function validateSQL(string $sql, array $allowedTables): bool
    {
        // 1. Harus SELECT
        if (!preg_match('/^\s*select/i', $sql)) {
            Log::warning("SQL Validation failed: Not a SELECT query.");
            return false;
        }

        // Allow a single trailing semicolon, but not in the middle (prevent multiple statements)
        if (str_contains(trim($sql, " \n\r\t;"), ';')) {
            Log::warning("SQL Validation failed: Multiple statements detected.");
            return false;
        }

        $forbidden = ['insert', 'update', 'delete', 'drop', 'truncate', 'alter', 'create', 'grant', 'revoke', '--', '/*'];
        $lowerSql = strtolower($sql);
        foreach ($forbidden as $word) {
            // Check for forbidden words as independent tokens
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $lowerSql)) {
                // Special case: 'delete' might be part of a column name, but we play it safe
                Log::warning("SQL Validation failed: Forbidden keyword '{$word}' detected.");
                return false;
            }
        }

        // 2. Pastikan semua tabel yang digunakan ada di daftar allowedTables
        // Regex untuk mencari nama tabel setelah FROM, JOIN, INTO, UPDATE, dll
        // Contoh: sch_mbi.view_master_cabang_mbi atau view_master_cabang_mbi
        if (preg_match_all('/(?:from|join|into|update|table)\s+([a-zA-Z0-9_\.]+)/i', $sql, $matches)) {
            foreach ($matches[1] as $fullTableName) {
                // Skip subqueries and parentheses
                if (in_array(strtolower($fullTableName), ['select', '('])) continue;
                
                $parts = explode('.', $fullTableName);
                $tableName = end($parts); // Ambil bagian setelah titik terakhir jika ada

                // Clean table name from any aliases or conditions
                $tableName = preg_replace('/\s+.*$/', '', $tableName);

                if (!in_array($tableName, $allowedTables)) {
                    Log::warning("SQL Validation failed: Table '{$tableName}' (from '{$fullTableName}') is not in allowed tables. Available: " . implode(', ', $allowedTables));
                    return false;
                }
            }
        }

        return true;
    }

    private function streamAIResponse(array $messages, string $apiKey, string $dbContext): void
    {
        $success = false;
        $fullContent = '';

        foreach ($this->models as $model) {
            try {
                Log::info("Trying model (stream): {$model}");
                $ch = curl_init($this->apiUrl);
                
                $payload = json_encode([
                    'model'       => $model,
                    'messages'    => $messages,
                    'max_tokens'  => 4096,
                    'temperature' => 0.3,
                    'top_p'       => 0.90,
                    'stream'      => true,
                ]);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);

                $httpCode = 0;
                curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$httpCode) {
                    if (preg_match('/^HTTP\/1\.[01] (\d+)/', $header, $matches)) {
                        $httpCode = (int)$matches[1];
                    }
                    return strlen($header);
                });

                $streamBuffer = '';
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$fullContent, &$streamBuffer) {
                    $streamBuffer .= $data;
                    $lines = explode("\n", $streamBuffer);
                    $streamBuffer = array_pop($lines); // Simpan potongan line terakhir yang belum selesai ke buffer

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (str_starts_with($line, 'data: ')) {
                            $jsonStr = trim(substr($line, 6));
                            if ($jsonStr === '[DONE]') continue;
                            
                            $json = json_decode($jsonStr, true);
                            if (isset($json['choices'][0]['delta']['content'])) {
                                $content = $json['choices'][0]['delta']['content'];
                                $fullContent .= $content;
                                echo "data: " . json_encode(['chunk' => $content]) . "\n\n";
                                ob_flush();
                                flush();
                            }
                        }
                    }
                    return strlen($data);
                });

                curl_exec($ch);
                $err = curl_error($ch);
                curl_close($ch);

                if ((!$err && $httpCode >= 200 && $httpCode < 300) || !empty($fullContent)) {
                    $success = true;
                    Log::info("Model {$model} stream succeeded (or partially succeeded).");
                    break;
                }
                
                Log::warning("Model {$model} stream failed: HTTP {$httpCode}. Error: {$err}");
            } catch (\Exception $e) {
                Log::warning("Model {$model} stream exception: " . $e->getMessage());
            }
        }

        if (!$success) {
            // AI failed, but we have data from database queries
            if ($dbContext) {
                $fallback = $this->formatContextAsResponse($dbContext);
                echo "data: " . json_encode(['fallback' => true, 'response' => $fallback]) . "\n\n";
                Log::info("AI unavailable, showing data with auto-generated insights");
            } else {
                // No data at all
                echo "data: " . json_encode(['error' => true, 'response' => "Maaf, tidak ada data yang tersedia untuk pertanyaan ini. Silakan coba dengan pertanyaan lain atau hubungi admin."]) . "\n\n";
                Log::warning("AI unavailable AND no data context");
            }
            ob_flush(); flush();
        } else {
            $messages[] = ['role' => 'assistant', 'content' => $fullContent];
            $history = $this->extractHistoryForClient($messages);
            echo "data: " . json_encode(['history' => $history]) . "\n\n";
            ob_flush(); flush();
        }

        echo "data: [DONE]\n\n";
        ob_flush(); flush();
    }

    // ── Query database & kembalikan sebagai konteks ───────────────────────────
    private function fetchRelevantData(string $message, string $schemaContext, string $apiKey): string
    {
        $lower         = mb_strtolower($message);
        $wilayahFilter = $this->extractWilayahFilter($lower);
        $allowedTables = $this->getAllowedTables();
        $results       = [];
        $allQueriesFailed = false;

        try {
            // 1. Coba perencanaan query dinamis (LLM)
            $queries = $this->planSQLQueries($message, $schemaContext, $apiKey);

            // 2. Fallback ke query statis jika dinamis gagal/kosong
            if (empty($queries)) {
                Log::info("Dynamic planner returned no queries, falling back to static templates.");
                $wilayahFilter = $this->extractWilayahFilter($lower);
                $queries = $this->selectQueries($lower, $wilayahFilter);
            }

            $validQueryCount = 0;
            $invalidQueryCount = 0;
            
            foreach ($queries as $label => $sql) {
                try {
                    // Validasi keamanan SQL
                    if (!$this->validateSQL($sql, $allowedTables)) {
                        Log::warning("Skipping unsafe/unauthorized query: {$sql}");
                        $results[$label] = ['error' => 'Query tidak diizinkan atau tidak aman.'];
                        $invalidQueryCount++;
                        continue;
                    }

                    if (!preg_match('/\blimit\b/i', $sql)) {
                        $sql = rtrim($sql, ';') . ' LIMIT 50';
                    }

                    $rows = DB::connection('pgsql_mbi')->select($sql);
                    $results[$label] = !empty($rows) ? $rows : ['info' => 'Tidak ada data.'];
                    Log::info("Query '{$label}': " . (is_array($rows) ? count($rows) : 0) . " rows");
                    $validQueryCount++;

                } catch (\Illuminate\Database\QueryException $qe) {
                    $errorCode = $qe->getCode();
                    $errorMsg = $qe->getMessage();

                    // Log detailed error information
                    Log::error("Query '{$label}' failed with SQLSTATE error: {$errorMsg}", [
                        'sql' => $sql,
                        'code' => $errorCode,
                        'label' => $label
                    ]);

                    // Provide user-friendly error message based on error code
                    $userError = 'Query gagal dijalankan.';

                    // PostgreSQL error codes (SQLSTATE)
                    if (str_contains($errorMsg, '42P01') || str_contains($errorMsg, 'relation does not exist')) {
                        $userError = 'Tabel yang diminta tidak ditemukan. Kemungkinan nama tabel salah.';
                        Log::error("Table not found error - check if table name exists in schema");
                    } elseif (str_contains($errorMsg, '42703') || str_contains($errorMsg, 'column does not exist')) {
                        $userError = 'Kolom yang diminta tidak ditemukan dalam tabel.';
                        Log::error("Column not found error - check column names");
                    } elseif (str_contains($errorMsg, '42601')) {
                        $userError = 'Sintaks SQL tidak valid.';
                        Log::error("SQL syntax error");
                    } elseif ($errorCode >= 1000) {
                        // Connection or server errors
                        $userError = 'Koneksi ke database gagal. Silakan coba lagi.';
                    }

                    $results[$label] = ['error' => $userError];
                    $invalidQueryCount++;

                } catch (\Exception $e) {
                    Log::error("Query '{$label}' error: " . $e->getMessage());
                    $results[$label] = ['error' => 'Error: ' . $e->getMessage()];
                    $invalidQueryCount++;
                }
            }
            
            // 3. Jika semua query dari AI gagal validasi, fallback ke static queries
            if ($validQueryCount === 0 && $invalidQueryCount > 0 && !empty($queries)) {
                Log::info("All AI queries failed validation, falling back to static templates.");
                $wilayahFilter = $this->extractWilayahFilter($lower);
                $queries = $this->selectQueries($lower, $wilayahFilter);
                
                // Re-run the static queries
                $results = [];
                foreach ($queries as $label => $sql) {
                    try {
                        if (!$this->validateSQL($sql, $allowedTables)) {
                            Log::warning("Skipping static query: {$sql}");
                            continue;
                        }
                        
                        if (!preg_match('/\blimit\b/i', $sql)) {
                            $sql = rtrim($sql, ';') . ' LIMIT 50';
                        }
                        
                        $rows = DB::connection('pgsql_mbi')->select($sql);
                        $results[$label] = !empty($rows) ? $rows : ['info' => 'Tidak ada data.'];
                        Log::info("Static query '{$label}': " . (is_array($rows) ? count($rows) : 0) . " rows");
                    } catch (\Exception $e) {
                        Log::error("Static query '{$label}' error: " . $e->getMessage());
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error("fetchRelevantData: " . $e->getMessage());
        }

        if (empty($results)) return '';

        $ctx  = "=== DATA NYATA DARI DATABASE ===\n";
        if ($wilayahFilter) $ctx .= "Filter wilayah: '{$wilayahFilter}'\n";
        $ctx .= "Gunakan HANYA data di bawah. Jangan mengarang.\n\n";

        foreach ($results as $label => $rows) {
            $ctx .= "--- {$label} ---\n";
            if (isset($rows['error'])) { $ctx .= "ERROR: {$rows['error']}\n\n"; continue; }
            if (isset($rows['info']))  { $ctx .= "{$rows['info']}\n\n"; continue; }

            $first   = (array) $rows[0];
            $headers = array_keys($first);
            $ctx .= "| " . implode(" | ", $headers) . " |\n";
            $ctx .= "| " . implode(" | ", array_fill(0, count($headers), "---")) . " |\n";
            foreach (array_slice($rows, 0, 30) as $row) {
                $vals = array_map(function($v, $key) {
                    if ($v === null || $v === '-') return '-';
                    // Format monetary values (columns containing: total, harga, bayar, revenue, profit, amount, dll)
                    if ($this->isMonetaryColumn($key) && is_numeric($v)) {
                        return $this->formatRupiah($v);
                    }
                    return $v;
                }, array_values((array)$row), array_keys((array)$row));
                $ctx .= "| " . implode(" | ", $vals) . " |\n";
            }
            $ctx .= "\nTotal: " . count($rows) . " baris\n\n";
        }

        return $ctx;
    }

    // ── Bangun semua query berdasarkan kata kunci ─────────────────────────────
        private function selectQueries(string $lower, string $wilayahFilter = ''): array
    {
        $queries = [];
        $tgl     = 'tgl_fak_jl';
        $bayar   = 'total_harga';
        $hasW    = !empty($wilayahFilter);
        $safe    = $hasW ? addslashes($wilayahFilter) : '';

        $allowedTables = $this->getAllowedTables();
        $isAllowed = function($table) use ($allowedTables) {
            return in_array($table, $allowedTables);
        };

        $vSales = 'sch_mbi.view_data_penjualan_rinci_mbi';
        $allowSales = $isAllowed('view_data_penjualan_rinci_mbi');

        $wAnd = $hasW ? "AND (LOWER(nama_propinsi_cabang) LIKE '%{$safe}%' OR LOWER(nama_kabupaten_cabang) LIKE '%{$safe}%' OR LOWER(alamat_pelanggan) LIKE '%{$safe}%')" : '';
        $wWhere = $hasW ? "WHERE (LOWER(nama_propinsi_cabang) LIKE '%{$safe}%' OR LOWER(nama_kabupaten_cabang) LIKE '%{$safe}%' OR LOWER(alamat_pelanggan) LIKE '%{$safe}%')" : '';

        // ── Produk terlaris ──────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['produk', 'terlaris', 'best seller', 'bestseller', 'paling laku', 'banyak terjual', 'laris', 'product', 'top selling', 'most sold'])
            && $allowSales) {
            $label = $hasW ? "Produk Terlaris di " . ucwords($wilayahFilter) : "Produk Terlaris";
            $queries[$label] = "
                SELECT 
                    nama_barang,
                    nama_kategori_barang as kategori,
                    SUM(qty_jual) as total_terjual,
                    SUM(total_netto) as total_pendapatan,
                    ROUND(SUM(total_netto) * 100.0 / NULLIF(SUM(SUM(total_netto)) OVER (), 0), 2) as persen_revenue
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY nama_barang, nama_kategori_barang
                ORDER BY total_terjual DESC 
                LIMIT 10";
        }

        // ── Pelanggan terbaik / terloyal ─────────────────────────────────────
        if ($this->hasKeyword($lower, ['pelanggan', 'pembeli', 'customer', 'loyal', 'setia', 'terbaik', 'terloyal', 'buyer', 'client', 'best customer'])
            && $allowSales) {
            $label = $hasW ? "Pelanggan Terbaik di " . ucwords($wilayahFilter) : "Pelanggan Terbaik";
            $queries[$label] = "
                SELECT 
                    nama_pelanggan,
                    alamat_pelanggan,
                    nama_kabupaten_cabang as kabupaten,
                    nama_propinsi_cabang as provinsi,
                    COUNT(DISTINCT no_fak_jl) as total_transaksi,
                    SUM(total_netto) as total_belanja,
                    ROUND(AVG(total_netto), 0) as rata_rata_belanja,
                    MAX(tgl_fak_jl) as transaksi_terakhir
                FROM {$vSales}
                " . ($hasW ? "WHERE (LOWER(nama_kabupaten_cabang) LIKE '%{$safe}%' OR LOWER(nama_propinsi_cabang) LIKE '%{$safe}%' OR LOWER(alamat_pelanggan) LIKE '%{$safe}%')" : "WHERE 1=1") . "
                GROUP BY nama_pelanggan, alamat_pelanggan, nama_kabupaten_cabang, nama_propinsi_cabang
                ORDER BY total_belanja DESC 
                LIMIT 10";
        }

        // ── Revenue per wilayah ──────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['wilayah', 'provinsi', 'kota', 'daerah', 'region', 'area', 'province', 'city'])
            && $allowSales) {
            $queries['Revenue per Wilayah'] = "
                SELECT nama_propinsi_cabang as provinsi,
                    COUNT(DISTINCT kode_pelanggan) as jumlah_pelanggan,
                    COUNT(DISTINCT no_fak_jl) as jumlah_transaksi,
                    SUM(total_harga) as total_revenue,
                    ROUND(AVG(total_harga), 0) as aov
                FROM {$vSales}
                GROUP BY nama_propinsi_cabang
                ORDER BY total_revenue DESC";
        }

        // ── Revenue trend / bulanan ──────────────────────────────────────────
        if ($this->hasKeyword($lower, ['tren', 'trend', 'revenue', 'pendapatan', 'omzet', 'per bulan', 'bulanan', 'penjualan bulan', 'monthly', 'sales trend', 'income'])
            && $allowSales) {
            $label = $hasW ? "Revenue Bulanan di " . ucwords($wilayahFilter) : "Revenue per Bulan";
            $queries[$label] = "
                SELECT periode_tahun || '-' || periode_bulan as bulan,
                    COUNT(DISTINCT no_fak_jl) as jumlah_transaksi,
                    SUM(total_harga) as total_revenue,
                    ROUND(AVG(total_harga), 0) as avg_order_value,
                    COUNT(DISTINCT kode_pelanggan) as unique_pelanggan
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY periode_tahun, periode_bulan
                ORDER BY periode_tahun DESC, periode_bulan DESC LIMIT 12";
        }

        // ── Kategori ─────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['kategori', 'category', 'jenis produk'])
            && $allowSales) {
            $label = $hasW ? "Kategori Terlaris di " . ucwords($wilayahFilter) : "Penjualan per Kategori";
            $queries[$label] = "
                SELECT nama_kategori_barang as nama_kategori,
                    COUNT(DISTINCT kode_barang) as jumlah_produk,
                    SUM(qty_jual) as total_terjual,
                    SUM(total_harga) as total_pendapatan
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY nama_kategori_barang
                ORDER BY total_pendapatan DESC";
        }

        // ── RFM ──────────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['rfm', 'recency', 'frequency', 'monetary', 'segmen pelanggan', 'segmentasi'])
            && $allowSales) {
            $label = $hasW ? "RFM di " . ucwords($wilayahFilter) : "Analisis RFM";
            $queries[$label] = "
                SELECT nama_pelanggan,
                    MAX({$tgl}) as last_purchase,
                    CURRENT_DATE - MAX({$tgl}) as recency_days,
                    COUNT(DISTINCT no_fak_jl) as frequency,
                    SUM(total_harga) as monetary,
                    CASE
                        WHEN CURRENT_DATE - MAX({$tgl}) <= 30 AND COUNT(DISTINCT no_fak_jl) >= 3 THEN 'Champions'
                        WHEN CURRENT_DATE - MAX({$tgl}) <= 60 AND COUNT(DISTINCT no_fak_jl) >= 2 THEN 'Loyal'
                        WHEN CURRENT_DATE - MAX({$tgl}) <= 90 THEN 'At Risk'
                        ELSE 'Lost'
                    END as rfm_segment
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY nama_pelanggan
                ORDER BY monetary DESC LIMIT 20";
        }

        // ── Metode pembayaran ─────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['metode bayar', 'pembayaran', 'payment', 'cara bayar', 'transfer', 'tunai', 'kredit'])
            && $allowSales) {
            $label = $hasW ? "Metode Pembayaran di " . ucwords($wilayahFilter) : "Metode Pembayaran";
            $queries[$label] = "
                SELECT CASE WHEN hari_jth_tempo > 0 THEN 'Kredit' ELSE 'Tunai' END as metode_bayar,
                    COUNT(*) as jumlah_transaksi,
                    SUM(total_harga) as total_revenue,
                    ROUND(AVG(total_harga), 0) as avg_transaksi,
                    ROUND(COUNT(*) * 100.0 / NULLIF(SUM(COUNT(*)) OVER (), 0), 2) as persen_penggunaan
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY CASE WHEN hari_jth_tempo > 0 THEN 'Kredit' ELSE 'Tunai' END
                ORDER BY jumlah_transaksi DESC";
        }

        // ── Diskon ───────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['diskon', 'discount', 'promo', 'potongan'])
            && $allowSales) {
            $queries['Efektivitas Diskon'] = "
                SELECT CASE WHEN total_disc > 0 THEN 'Ada Diskon' ELSE 'Tanpa Diskon' END as status_diskon,
                    COUNT(*) as jumlah_transaksi,
                    ROUND(AVG(total_harga), 0) as rata_nilai,
                    SUM(total_harga) as total_revenue,
                    ROUND(SUM(total_disc), 2) as total_diskon_nominal
                FROM {$vSales}
                GROUP BY CASE WHEN total_disc > 0 THEN 'Ada Diskon' ELSE 'Tanpa Diskon' END";
        }

        // ── Dead stock ────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['dead stock', 'tidak laku', 'stok mati', 'tidak terjual', 'slow moving'])
            && $isAllowed('view_master_barang_mbi') && $isAllowed('view_data_kartu_stock_mbi')) {
            $queries['Dead Stock'] = "
                SELECT b.nama_barang, b.nama_kategori_barang,
                    SUM(s.qty_saldo_akhir) as stok_akhir,
                    SUM(s.qty_jual) as terjual
                FROM sch_mbi.view_master_barang_mbi b
                LEFT JOIN sch_mbi.view_data_kartu_stock_mbi s ON b.kode_barang = s.kode_kategori_barang
                GROUP BY b.nama_barang, b.nama_kategori_barang
                HAVING SUM(s.qty_saldo_akhir) > 0 AND (SUM(s.qty_jual) IS NULL OR SUM(s.qty_jual) = 0)
                LIMIT 50";
        }

        // ── Cross-sell ────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['cross sell', 'cross-sell', 'kombinasi', 'sering dibeli bersama', 'bundle'])
            && $allowSales) {
            $queries['Cross-Sell'] = "
                SELECT dt1.nama_barang as produk_a, dt2.nama_barang as produk_b,
                    COUNT(*) as frekuensi_bersamaan
                FROM {$vSales} dt1
                JOIN {$vSales} dt2 ON dt1.no_fak_jl = dt2.no_fak_jl AND dt1.kode_barang < dt2.kode_barang
                GROUP BY dt1.nama_barang, dt2.nama_barang
                ORDER BY frekuensi_bersamaan DESC LIMIT 10";
        }

        // ── ABC Analysis ──────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['abc', 'pareto', '80/20'])
            && $allowSales) {
            $queries['ABC Analysis'] = "
                SELECT nama_barang, total_pendapatan,
                    ROUND(total_pendapatan * 100.0 / NULLIF(SUM(total_pendapatan) OVER (), 0), 2) as persen,
                    ROUND(SUM(total_pendapatan) OVER (ORDER BY total_pendapatan DESC) * 100.0 / NULLIF(SUM(total_pendapatan) OVER (), 0), 2) as kumulatif,
                    CASE
                        WHEN SUM(total_pendapatan) OVER (ORDER BY total_pendapatan DESC) * 100.0 / NULLIF(SUM(total_pendapatan) OVER (), 0) <= 80 THEN 'A - Prioritas'
                        WHEN SUM(total_pendapatan) OVER (ORDER BY total_pendapatan DESC) * 100.0 / NULLIF(SUM(total_pendapatan) OVER (), 0) <= 95 THEN 'B - Menengah'
                        ELSE 'C - Rendah'
                    END as kategori_abc
                FROM (
                    SELECT nama_barang, SUM(total_harga) as total_pendapatan
                    FROM {$vSales}
                    GROUP BY nama_barang
                ) sub ORDER BY total_pendapatan DESC LIMIT 20";
        }

        // ── Customer Retention ────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['retention', 'pelanggan baru', 'pelanggan kembali', 'repeat order', 'repeat buyer'])
            && $allowSales) {
            $queries['Customer Retention'] = "
                SELECT periode_tahun || '-' || periode_bulan as bulan,
                    COUNT(DISTINCT CASE WHEN fb.bulan_pertama = (periode_tahun || '-' || periode_bulan) THEN tr.kode_pelanggan END) as pelanggan_baru,
                    COUNT(DISTINCT CASE WHEN fb.bulan_pertama != (periode_tahun || '-' || periode_bulan) THEN tr.kode_pelanggan END) as pelanggan_kembali
                FROM {$vSales} tr
                JOIN (
                    SELECT kode_pelanggan, MIN(periode_tahun || '-' || periode_bulan) as bulan_pertama
                    FROM {$vSales} GROUP BY kode_pelanggan
                ) fb ON tr.kode_pelanggan = fb.kode_pelanggan
                GROUP BY periode_tahun, periode_bulan
                ORDER BY periode_tahun DESC, periode_bulan DESC LIMIT 12";
        }

        // ── Cabang ───────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['cabang', 'branch', 'lokasi', 'kantor'])) {
            if (!$isAllowed('view_master_cabang_mbi')) {
                // User tidak punya akses ke tabel cabang
            } elseif ($hasW) {
                // Ada filter wilayah spesifik (Medan, Riau, dll)
                $label = "Daftar Cabang di " . ucwords($wilayahFilter);
                $queries[$label] = "
                    SELECT 
                        nama_cabang,
                        alamat_cabang,
                        nama_kabupaten_cabang as kabupaten,
                        nama_propinsi_cabang as provinsi,
                        no_telepon,
                        nama_regional
                    FROM sch_mbi.view_master_cabang_mbi
                    WHERE LOWER(nama_kabupaten_cabang) LIKE '%{$safe}%'
                       OR LOWER(nama_propinsi_cabang) LIKE '%{$safe}%'
                       OR LOWER(nama_cabang) LIKE '%{$safe}%'
                       OR LOWER(nama_regional) LIKE '%{$safe}%'
                    ORDER BY nama_propinsi_cabang, nama_kabupaten_cabang, nama_cabang
                    LIMIT 50";
            } else {
                // Tampilkan semua cabang (tanpa filter)
                $queries['Daftar Cabang'] = "
                    SELECT 
                        nama_cabang,
                        alamat_cabang,
                        nama_kabupaten_cabang as kabupaten,
                        nama_propinsi_cabang as provinsi,
                        no_telepon,
                        nama_regional
                    FROM sch_mbi.view_master_cabang_mbi
                    ORDER BY nama_regional, nama_propinsi_cabang, nama_cabang
                    LIMIT 100";
            }
        }

        // ── Fallback: ringkasan umum ──────────────────────────────────────────
        if (empty($queries)) {
            if ($allowSales) {
                $queries[$hasW ? "Ringkasan di " . ucwords($wilayahFilter) : 'Ringkasan Bisnis'] = "
                    SELECT COUNT(DISTINCT no_fak_jl) as total_transaksi,
                        COALESCE(SUM(total_harga), 0) as total_revenue,
                        COUNT(DISTINCT kode_pelanggan) as total_pelanggan,
                        ROUND(AVG(total_harga), 0) as avg_order_value
                    FROM {$vSales}
                    " . ($hasW ? $wWhere : "WHERE 1=1");
            }
        }

        return $queries;
    }

    // ── Format data sebagai respons langsung (fallback jika AI gagal) ─────────
    private function formatContextAsResponse(string $ctx): string
    {
        // Extract data from context
        $dataSection = preg_replace('/^=== DATA NYATA.*?\n.*?\n\n/s', '', $ctx);
        
        // Parse data untuk generate insight
        $insights = $this->generateAutoInsights($ctx);
        
        $response = "### 📊 Hasil Data\n\n";
        $response .= $dataSection . "\n\n";
        
        // Add auto-generated insights
        if (!empty($insights['summary'])) {
            $response .= "### 🔍 Insight\n\n";
            foreach ($insights['summary'] as $insight) {
                $response .= "- {$insight}\n";
            }
            $response .= "\n";
        }
        
        // Add recommendations
        if (!empty($insights['recommendations'])) {
            $response .= "### 💡 Rekomendasi\n\n";
            $recNum = 1;
            foreach ($insights['recommendations'] as $rec) {
                $response .= "{$recNum}. {$rec}\n";
                $recNum++;
            }
            $response .= "\n";
        }
        
        $response .= "> ℹ️ Data di atas diambil langsung dari database. Hubungi admin jika ada pertanyaan lebih lanjut.";
        
        return $response;
    }
    
    // ── Generate auto insights dari data ──────────────────────────────────────
    private function generateAutoInsights(string $ctx): array
    {
        $insights = ['summary' => [], 'recommendations' => []];
        
        // Extract numeric data from context
        preg_match_all('/\| ([^|]+) \|/u', $ctx, $matches);
        
        if (empty($matches[1])) {
            return $insights;
        }
        
        $rows = array_slice($matches[1], 1); // Skip header separator
        $numericValues = [];
        $labels = [];
        
        foreach ($rows as $row) {
            $cols = explode('|', trim($row));
            if (count($cols) >= 2) {
                $labels[] = trim($cols[0]);
                // Extract numeric values
                foreach ($cols as $col) {
                    $col = trim($col);
                    if (preg_match('/[\d,]+\.?\d*/', str_replace('.', '', $col), $m)) {
                        $numericValues[] = (float)str_replace(',', '', $m[0]);
                    }
                }
            }
        }
        
        // Generate summary insights
        if (!empty($numericValues)) {
            $total = array_sum($numericValues);
            $avg = $total / count($numericValues);
            $max = max($numericValues);
            $min = min($numericValues);
            
            $insights['summary'][] = "Total nilai: " . $this->formatRupiah($total);
            $insights['summary'][] = "Rata-rata: " . $this->formatRupiah($avg);
            $insights['summary'][] = "Nilai tertinggi: " . $this->formatRupiah($max);
            $insights['summary'][] = "Nilai terendah: " . $this->formatRupiah($min);
        }
        
        // Generate recommendations based on data type
        $lowerCtx = strtolower($ctx);
        if (str_contains($lowerCtx, 'produk') || str_contains($lowerCtx, 'barang')) {
            $insights['recommendations'][] = "Fokus pada produk dengan penjualan tertinggi untuk optimasi stok";
            $insights['recommendations'][] = "Evaluasi produk dengan penjualan rendah untuk promosi atau clearance";
        } elseif (str_contains($lowerCtx, 'pelanggan') || str_contains($lowerCtx, 'customer')) {
            $insights['recommendations'][] = "Berikan reward khusus untuk pelanggan terbaik";
            $insights['recommendations'][] = "Buat program loyalitas untuk meningkatkan retensi";
        } elseif (str_contains($lowerCtx, 'cabang') || str_contains($lowerCtx, 'branch')) {
            $insights['recommendations'][] = "Tingkatkan dukungan untuk cabang dengan performa tinggi";
            $insights['recommendations'][] = "Review strategi untuk cabang dengan performa rendah";
        } else {
            $insights['recommendations'][] = "Monitor trend secara berkala untuk pengambilan keputusan";
            $insights['recommendations'][] = "Gunakan data ini sebagai baseline untuk target berikutnya";
        }
        
        return $insights;
    }

    // ── Ekstrak filter wilayah dari pesan ─────────────────────────────────────
    private function extractWilayahFilter(string $lower): string
    {
        $provinces = [
            'aceh','sumatera utara','sumut','sumatera barat','sumbar',
            'riau','kepulauan riau','kepri','jambi','sumatera selatan','sumsel',
            'bangka belitung','babel','bengkulu','lampung',
            'dki jakarta','jakarta','jawa barat','jabar','jawa tengah','jateng',
            'diy','yogyakarta','jogja','jawa timur','jatim',
            'banten','bali','nusa tenggara barat','ntb','nusa tenggara timur','ntt',
            'kalimantan barat','kalbar','kalimantan tengah','kalteng',
            'kalimantan selatan','kalsel','kalimantan timur','kaltim','kalimantan utara','kalut',
            'sulawesi utara','sulut','sulawesi tengah','sulteng',
            'sulawesi selatan','sulsel','sulawesi tenggara','sultra',
            'gorontalo','sulawesi barat','sulbar',
            'maluku','maluku utara','papua','papua barat',
        ];

        foreach ($provinces as $prov) {
            if (str_contains($lower, $prov)) return $prov;
        }

        if (preg_match('/(?:di|dari|untuk|wilayah|daerah|kota|area)\s+([a-z\s]+?)(?:\s|$|,|\?)/u', $lower, $m)) {
            $c = trim($m[1]);
            $stop = ['sini','sana','mana','atas','bawah','dalam','luar','mana','semua'];
            if (strlen($c) >= 3 && !in_array($c, $stop)) return $c;
        }

        return '';
    }

    // ── Deteksi apakah butuh database ────────────────────────────────────────
    private function messageNeedsDatabase(string $message): bool
    {
        $keywords = [
            // Indonesian keywords
            'produk','terlaris','revenue','transaksi','penjualan','pelanggan',
            'pembeli','kategori','stok','laporan','analisis','analisa','data',
            'tren','trend','statistik','ranking','rank','terbaik','tertinggi',
            'terendah','total','jumlah','bulan','tahun','wilayah','provinsi',
            'kota','cabang','diskon','profit','pendapatan','omzet','rfm','abc','retention',
            'cross-sell','cross sell','dead stock','metode bayar','metode pembayaran',
            'aov','lihat','tampilkan','tunjukkan','cari','berapa','siapa','mana',
            'show','display','top','paling','laku','laris','beli','jual','loyal','terloyal',
            'kolom','tabel','semua','berdasarkan','urutkan','filter','sort',
            'jawa barat','jawa tengah','jawa timur','jakarta','banten','bali',
            'sumatera','kalimantan','sulawesi','papua','aceh','riau','lampung',
            'jogja','yogyakarta','jabar','jateng','jatim','sumut','sumbar',
            // English keywords
            'product', 'bestseller', 'best seller', 'transaction', 'sales', 'customer',
            'buyer', 'category', 'stock', 'report', 'analysis', 'data',
            'trend', 'statistics', 'ranking', 'best', 'highest',
            'lowest', 'total', 'amount', 'sum', 'count', 'month', 'year', 'region', 'province',
            'city', 'branch', 'discount', 'profit', 'income', 'revenue', 'retention',
            'cross sell', 'dead stock', 'payment method', 'payment',
            'aov', 'see', 'show', 'display', 'find', 'search', 'how many', 'how much', 'what', 'who', 'which',
            'top', 'most', 'buy', 'sell', 'loyal', 'loyalty', 'column', 'table', 'based on',
            'west java', 'central java', 'east java', 'jakarta', 'banten', 'bali',
            'sumatra', 'kalimantan', 'sulawesi', 'papua', 'aceh', 'riau', 'lampung',
        ];
        $lower = mb_strtolower($message);
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) return true;
        }
        return false;
    }

    // ── Deteksi apakah butuh dokumentasi ERP ──────────────────────────────────
    private function messageNeedsDocs(string $message): bool
    {
        $keywords = [
            'cara', 'bagaimana', 'tutorial', 'panduan', 'tahap', 'langkah',
            'dokumentasi', 'docs', 'erp', 'finance', 'inventory', 'warehouse',
            'purchasing', 'sales', 'pembayaran', 'dp', 'pembelian', 'stok',
            'laporan', 'report', 'setting', 'konfigurasi', 'modul', 'fitur',
        ];
        $lower = mb_strtolower($message);
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) return true;
        }
        return false;
    }

    // ── Cari dokumentasi relevan ──────────────────────────────────────────────
    private function fetchRelevantDocs(string $message): string
    {
        $words = explode(' ', mb_strtolower($message));
        $words = array_filter($words, fn($w) => strlen($w) > 3 || in_array($w, ['dp', 'po', 'pr']));
        
        $stopWords = ['coba', 'perbaiki', 'bagaimana', 'cara', 'tolong', 'berikan', 'langkah', 'lengkap', 'apa', 'saja', 'lihat', 'tampilkan'];
        $words = array_filter($words, fn($w) => !in_array($w, $stopWords));

        if (empty($words)) return '';

        // Fetch all docs that match AT LEAST ONE word
        $query = DB::table('documentation');
        foreach ($words as $word) {
            $safe = addslashes($word);
            $query->orWhere('title', 'ILIKE', "%{$safe}%")
                  ->orWhere('content', 'ILIKE', "%{$safe}%");
        }

        $allDocs = $query->get();
        if ($allDocs->isEmpty()) return '';

        // Score the documents
        $scoredDocs = [];
        foreach ($allDocs as $doc) {
            $score = 0;
            $titleLower = mb_strtolower($doc->title);
            $contentLower = mb_strtolower($doc->content);

            foreach ($words as $word) {
                // Beri bobot lebih tinggi untuk kata kunci spesifik/jarang muncul
                $isRareWord = in_array($word, ['klaim', 'suplier', 'supplier', 'retur', 'rusak', 'dp', 'po', 'pr']);
                $titleWeight = $isRareWord ? 50 : 10;
                $contentWeight = $isRareWord ? 10 : 1;

                if (str_contains($titleLower, $word)) {
                    $score += $titleWeight;
                }
                if (str_contains($contentLower, $word)) {
                    $score += $contentWeight;
                }
            }
            $scoredDocs[] = ['doc' => $doc, 'score' => $score];
        }

        // Sort by score descending
        usort($scoredDocs, fn($a, $b) => $b['score'] <=> $a['score']);

        // Take top 3
        $topDocs = array_slice($scoredDocs, 0, 3);

        $ctx = "=== DOKUMENTASI ERP (PANDUAN PENGGUNA) ===\n";
        $ctx .= "Gunakan panduan ini untuk menjawab pertanyaan teknis tentang ERP. Selalu sertakan rincian field formulir jika tersedia.\n\n";

        foreach ($topDocs as $item) {
            $doc = $item['doc'];
            $ctx .= "--- Judul: {$doc->title} ---\n";
            $ctx .= "Link: {$doc->url}\n";
            $ctx .= "Konten: " . mb_substr($doc->content, 0, 8000) . "...\n\n";
        }

        return $ctx;
    }

    // ── System prompt ─────────────────────────────────────────────────────────
    private function buildSystemPrompt(string $schemaContext, string $dbContext, string $docContext = '', string $userLanguage = 'id'): string
    {
        $dataSection = !empty($dbContext) ? "\n\n## REAL DATABASE DATA\n{$dbContext}\n⚠️ CRITICAL: The data above is the only source of truth for database questions. You MUST use it. DO NOT make up numbers or information.\n" : "";
        $docSection = !empty($docContext) ? "\n\n## ERP DOCUMENTATION\n{$docContext}\nUSE THE GUIDE ABOVE for technical ERP instructions.\n" : "";

        // Language-specific system prompt
        if ($userLanguage === 'en') {
            return "### IDENTITY
You are DataBot, an expert AI Data Analyst and ERP Consultant.

### 🔒 LANGUAGE REQUIREMENT
YOU MUST respond ENTIRELY in ENGLISH. This includes:
- All greetings, explanations, analysis, and recommendations
- Data headers (use: 'Data Results', 'Analysis', 'Recommendations')
- Number formatting and date formats (DD/MM/YYYY)
- NEVER mix languages or use Indonesian words

### CORE RULES
1. **DATA INTEGRITY**: Use ONLY the real database data provided. Do NOT fabricate numbers.
2. **ACCESS DENIAL**: If user asks for tables NOT in 'ALLOWED TABLES', respond: 'I don't have permission to access that data.'
3. **FORMATTING**: Use professional markdown formatting with English headers.

### OUTPUT STRUCTURE
#### 📊 Data Results
| Column 1 | Column 2 |
|----------|----------|
| value    | value    |

#### 🔍 Analysis
- Key insights from data
- Trends and patterns identified

#### 💡 Recommendations
1. Concrete business action based on data
2. Next steps

### CONTEXT
{$schemaContext}
{$dataSection}
{$docSection}";
        }

        // Default: Bahasa Indonesia
        return "### IDENTITAS
Anda adalah DataBot, AI Analis Data dan Konsultan ERP yang ahli.

### 🔒 PERSYARATAN BAHASA
ANDA HARUS merespons SEPENUHNYA dalam BAHASA INDONESIA. Ini termasuk:
- Semua salam, penjelasan, analisis, dan rekomendasi
- Header data (gunakan: 'Hasil Data', 'Analisis', 'Rekomendasi')
- Format angka (Rp 1.000.000) dan tanggal (DD/MM/YYYY)
- JANGAN pernah mencampur bahasa atau menggunakan kata bahasa Inggris

### ATURAN UTAMA
1. **INTEGRITAS DATA**: Gunakan HANYA data riil yang disediakan. JANGAN mengarang angka.
2. **AKSES DITOLAK**: Jika user minta tabel yang TIDAK ada di 'ALLOWED TABLES', jawab: 'Saya tidak memiliki izin untuk mengakses data tersebut.'
3. **FORMAT**: Gunakan format markdown profesional dengan header bahasa Indonesia.

### STRUKTUR KELUARAN
#### 📊 Hasil Data
| Kolom 1 | Kolom 2 |
|---------|---------|
| value   | value   |

#### 🔍 Analisis
- Insight kunci dari data
- Tren dan pola yang teridentifikasi

#### 💡 Rekomendasi
1. Tindakan bisnis konkret berdasarkan data
2. Langkah selanjutnya

### KONTEKS
{$schemaContext}
{$dataSection}
{$docSection}";
    }

    // ── Ekstrak history untuk frontend ───────────────────────────────────────
    private function extractHistoryForClient(array $messages): array
    {
        $history = [];
        foreach ($messages as $msg) {
            if (in_array($msg['role'] ?? '', ['user', 'assistant']) && !empty($msg['content'])) {
                $history[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
        return array_slice($history, -($this->maxHistoryTurns * 2));
    }

    // ── Schema context ────────────────────────────────────────────────────────
    private function getSchemaContext(string $message = ''): string
    {
        try {
            $allowedTables = $this->getAllowedTables();
            $lowerMsg = mb_strtolower($message);

            // Priority tables based on keywords
            $priorityKeywords = [
                'penjualan' => ['view_data_penjualan_rinci_mbi', 'transaksi', 'detail_transaksi'],
                'sales'     => ['view_data_penjualan_rinci_mbi', 'transaksi', 'detail_transaksi'],
                'produk'    => ['produk', 'kategori', 'view_data_penjualan_rinci_mbi'],
                'product'   => ['produk', 'kategori', 'view_data_penjualan_rinci_mbi'],
                'cabang'    => ['view_master_cabang_mbi'],
                'branch'    => ['view_master_cabang_mbi'],
                'medan'     => ['view_master_cabang_mbi'],
                'jakarta'   => ['view_master_cabang_mbi'],
                'bandung'   => ['view_master_cabang_mbi'],
                'surabaya'  => ['view_master_cabang_mbi'],
                'riau'      => ['view_master_cabang_mbi'],
                'kota'      => ['view_master_cabang_mbi'],
                'city'      => ['view_master_cabang_mbi'],
                'sumatra'   => ['view_master_cabang_mbi'],
                'pelanggan' => ['view_master_pelanggan_mbi', 'pembeli'],
                'customer'  => ['view_master_pelanggan_mbi', 'pembeli'],
                'stok'      => ['stok', 'mutasi_stok'],
                'stock'     => ['stok', 'mutasi_stok'],
            ];

            $priorityTables = [];
            foreach ($priorityKeywords as $kw => $tabs) {
                if (str_contains($lowerMsg, $kw)) {
                    $priorityTables = array_merge($priorityTables, $tabs);
                }
            }
            $priorityTables = array_unique($priorityTables);

            // If no message or no keywords, we'll just show all allowed table names first
            // and columns for top 5 most common tables.
            $commonTables = ['view_data_penjualan_rinci_mbi', 'view_master_cabang_mbi', 'view_master_pelanggan_mbi', 'produk', 'transaksi'];

            $cacheKey = 'db_schema_context_v5_' . (Auth::user() ? Auth::user()->role : 'guest') . '_' . md5($message);

            return cache()->remember($cacheKey, 300, function () use ($allowedTables, $priorityTables) {
                // Single query to get all tables and their columns in sch_mbi
                $results = DB::connection('pgsql_mbi')->select("
                    SELECT table_name, column_name
                    FROM information_schema.columns
                    WHERE table_schema = 'sch_mbi'
                    AND table_name NOT IN ('migrations','cache','cache_locks','sessions','jobs','failed_jobs','personal_access_tokens','users','password_reset_tokens')
                    ORDER BY table_name, ordinal_position
                ");

                $tableGroups = [];
                foreach ($results as $row) {
                    if (!in_array($row->table_name, $allowedTables)) continue;
                    $tableGroups[$row->table_name][] = $row->column_name;
                }

                // IMPORTANT: Start with a clear header listing ALL available tables
                $context = "AVAILABLE TABLES (USE EXACT NAMES AS SHOWN):\n";
                $context .= "----------------------------------------\n";
                $context .= "Table names you MUST use (choose from this list only):\n";
                foreach (array_keys($tableGroups) as $tn) {
                    $context .= "  - {$tn}\n";
                }
                $context .= "----------------------------------------\n\n";
                $context .= "TABLE DETAILS (name(columns)):\n";
                
                $count = 0;

                // Add priority tables first
                foreach ($priorityTables as $tn) {
                    if (isset($tableGroups[$tn])) {
                        $context .= "{$tn}(" . implode(",", $tableGroups[$tn]) . ")\n";
                        unset($tableGroups[$tn]);
                        $count++;
                    }
                }

                // Add remaining allowed tables
                foreach ($tableGroups as $tn => $cols) {
                    $context .= "{$tn}(" . implode(",", $cols) . ")\n";
                    $count++;
                }

                if ($count === 0) return "No access to database tables.";
                return $context;
            });
        } catch (\Exception $e) {
            return "Error while fetching schema: " . $e->getMessage();
        }
    }

    // ── Helper keyword check ──────────────────────────────────────────────────
    private function hasKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) return true;
        }
        return false;
    }

    // ── Deteksi apakah kolom adalah nilai keuangan ────────────────────────────
    private function isMonetaryColumn(string $columnName): bool
    {
        $monetaryKeywords = [
            'bayar', 'revenue', 'profit', 'pendapatan', 'omzet', 'keuntungan',
            'biaya', 'cost', 'price', 'belanja', 'monetary', 'avg', 'rata_rata', 'rata-rata',
            'amount', 'sales'
        ];
        $quantityKeywords = [
            'qty', 'jumlah', 'total_terjual', 'total_transaksi', 'count', 'banyak',
            'kuantitas', 'unit', 'pcs', 'total_pelanggan', 'total_produk'
        ];
        $percentageKeywords = [
            'persen', 'persentase', 'percent', 'percentage', 'proporsi', 'rasio'
        ];
        
        $lower = mb_strtolower($columnName);
        
        // Percentage columns should NOT be formatted as Rupiah
        foreach ($percentageKeywords as $keyword) {
            if (str_contains($lower, $keyword)) return false;
        }
        
        // Quantity columns should NOT be formatted as Rupiah
        foreach ($quantityKeywords as $keyword) {
            if (str_contains($lower, $keyword)) return false;
        }
        
        // Then check if it's a monetary column
        foreach ($monetaryKeywords as $keyword) {
            if (str_contains($lower, $keyword)) return true;
        }
        
        // Special case: 'harga' alone is monetary, but check for false positives
        if ($lower === 'harga' || str_contains($lower, 'harga_')) return true;
        
        // Special case: 'total' at the beginning usually means money, unless it's quantity
        if (str_starts_with($lower, 'total_') && !str_contains($lower, 'terjual') && !str_contains($lower, 'transaksi')) {
            return true;
        }
        
        return false;
    }

    // ── Format nilai sebagai Rupiah ───────────────────────────────────────────
    private function formatRupiah(float|int $value): string
    {
        $value = (float) $value;
        // Format: Rp 1.000.000 (dengan titik sebagai pemisah ribuan)
        return 'Rp ' . number_format($value, 0, ',', '.');
    }


}
