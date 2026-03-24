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

    // Qwen model dengan performa analisis terbaik untuk MCP
    private array $models = [
        'qwen/qwen-2.5-coder-32b-instruct',  // Primary - optimized for code/SQL analysis
        'qwen/qwen-2.5-72b-instruct',        // Fallback - general purpose large model
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
        ini_set('memory_limit', '512M');
        
        $message = $request->input('message');
        $history = $request->input('history', []);
        $apiKey  = env('OPENROUTER_API_KEY');

        Log::info("Chatbot send: ", ['message' => $message, 'history_count' => count($history)]);

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

        $systemPrompt = <<<'PROMPT'
You are an expert SQL Query Generator for PostgreSQL database with business intelligence capabilities.

## SCHEMA INFORMATION (YOUR ONLY SOURCE OF TRUTH):
PROMPT;

        $systemPrompt .= "\n" . $schemaContext . "\n\n";

        $systemPrompt .= <<<'PROMPT'
## CRITICAL RULES - YOU MUST FOLLOW:

### TABLE NAMES (MOST IMPORTANT):
1. ONLY use table names EXACTLY as listed in SCHEMA above
2. ALWAYS prefix with 'sch_mbi.' (e.g., sch_mbi.view_data_penjualan_rinci_mbi)
3. NEVER invent table names - common WRONG examples:
   - ❌ 'cabang' → ✅ 'view_master_cabang_mbi'
   - ❌ 'produk' → ✅ use table from schema
   - ❌ 'pembeli' → ✅ 'view_master_pelanggan_mbi' or 'pembeli'
   - ❌ 'transaksi' → ✅ 'view_data_penjualan_rinci_mbi'

### COLUMN NAMES:
1. ONLY use columns listed for each table in schema
2. For year filtering use: `periode_tahun = '2026'` or `EXTRACT(YEAR FROM tgl_fak_jl) = 2026`
3. For month filtering use: `periode_bulan = '03'` or `EXTRACT(MONTH FROM tgl_fak_jl) = 3`
4. For region filtering use: `nama_propinsi_cabang ILIKE '%riau%'`

### QUERY COMPLEXITY:
- For 'show all', 'seluruh data', 'tampilkan semua': Use simple SELECT * with LIMIT 50
- For 'total', 'summary', 'ringkasan': Use aggregate functions (SUM, COUNT, AVG)
- For 'trend', 'per bulan', 'bulanan': GROUP BY periode_tahun, periode_bulan
- For 'terbaik', 'top', 'terlaris': ORDER BY metric DESC LIMIT 10

## RESPONSE FORMAT (STRICT):
Respond ONLY in this format, NOTHING ELSE:
[LABEL]Descriptive Label[/LABEL] [SQL]SELECT your_query_here[/SQL]

You can return multiple queries if needed:
[LABEL]Query 1 Label[/LABEL] [SQL]SELECT ...[/SQL]
[LABEL]Query 2 Label[/LABEL] [SQL]SELECT ...[/SQL]

## FEW-SHOT EXAMPLES (LEARN FROM THESE):

### Example 1: Show all sales data for 2026
User: "tampilkan seluruh data penjualan di tahun 2026"
[LABEL]Data Penjualan 2026[/LABEL] [SQL]SELECT * FROM sch_mbi.view_data_penjualan_rinci_mbi WHERE periode_tahun = '2026' LIMIT 50[/SQL]

### Example 2: Sales summary by province
User: "tampilkan penjualan per provinsi"
[LABEL]Penjualan per Provinsi[/LABEL] [SQL]SELECT nama_propinsi_cabang, COUNT(DISTINCT no_fak_jl) as total_transaksi, SUM(total_netto) as total_pendapatan FROM sch_mbi.view_data_penjualan_rinci_mbi GROUP BY nama_propinsi_cabang ORDER BY total_pendapatan DESC[/SQL]

### Example 3: Top products in specific region
User: "produk terlaris di Riau tahun 2025"
[LABEL]Produk Terlaris Riau 2025[/LABEL] [SQL]SELECT nama_barang, SUM(qty_jual) as total_terjual, SUM(total_netto) as total_pendapatan FROM sch_mbi.view_data_penjualan_rinci_mbi WHERE nama_propinsi_cabang ILIKE '%riau%' AND periode_tahun = '2025' GROUP BY nama_barang ORDER BY total_terjual DESC LIMIT 10[/SQL]

### Example 4: Monthly sales trend
User: "tren penjualan per bulan"
[LABEL]Tren Penjualan Bulanan[/LABEL] [SQL]SELECT periode_tahun || '-' || periode_bulan as bulan, COUNT(DISTINCT no_fak_jl) as jumlah_transaksi, SUM(total_netto) as total_revenue FROM sch_mbi.view_data_penjualan_rinci_mbi GROUP BY periode_tahun, periode_bulan ORDER BY periode_tahun DESC, periode_bulan DESC LIMIT 12[/SQL]

### Example 5: Customer analysis
User: "pelanggan terbaik"
[LABEL]Pelanggan Terbaik[/LABEL] [SQL]SELECT nama_pelanggan, COUNT(DISTINCT no_fak_jl) as total_transaksi, SUM(total_netto) as total_belanja FROM sch_mbi.view_data_penjualan_rinci_mbi GROUP BY nama_pelanggan ORDER BY total_belanja DESC LIMIT 10[/SQL]

### Example 6: Branch information
User: "daftar cabang di Jakarta"
[LABEL]Cabang Jakarta[/LABEL] [SQL]SELECT nama_cabang, alamat_cabang, nama_kabupaten_cabang, no_telepon FROM sch_mbi.view_master_cabang_mbi WHERE nama_propinsi_cabang ILIKE '%jakarta%' OR nama_kabupaten_cabang ILIKE '%jakarta%'[/SQL]

### Example 7: Complex - Sales by category with filter
User: "penjualan per kategori produk di Sumatera Utara tahun 2024"
[LABEL]Penjualan per Kategori Sumut 2024[/LABEL] [SQL]SELECT nama_kategori_barang, COUNT(DISTINCT no_fak_jl) as transaksi, SUM(qty_jual) as total_qty, SUM(total_netto) as revenue FROM sch_mbi.view_data_penjualan_rinci_mbi WHERE (nama_propinsi_cabang ILIKE '%sumatera utara%' OR nama_propinsi_cabang ILIKE '%sumut%') AND periode_tahun = '2024' GROUP BY nama_kategori_barang ORDER BY revenue DESC[/SQL]

## NOW GENERATE SQL FOR THIS USER REQUEST:

PROMPT;

        try {
            // Increased max_tokens for complex queries
            $maxTokens = 500;

            $response = Http::timeout(90)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post($this->apiUrl, [
                'model'       => $this->models[0],
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message]
                ],
                'max_tokens'  => $maxTokens,
                'temperature' => 0.1,        // Slightly higher for creativity in complex queries
                'top_p'       => 0.90,       // High coherence for SQL context
                'frequency_penalty' => 0.05, // Minimal repetition penalty
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error("SQL Planner failed: " . $errorBody);

                // Check for credit/limit errors
                if (str_contains($errorBody, 'credits') || str_contains($errorBody, '402')) {
                    Log::error("API Credit limit reached! Falling back to static queries.");
                }

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
                    // Clean up SQL - remove trailing semicolons
                    $sql = rtrim($sql, ';');
                    $queries[$label] = $sql;
                }
            }

            Log::info("Parsed " . count($queries) . " queries from AI");
            
            // If no queries parsed, try simpler regex
            if (empty($queries)) {
                Log::info("Trying fallback regex pattern...");
                preg_match_all('/\[SQL\](.*?)\[\/SQL\]/si', $content, $matches, PREG_SET_ORDER);
                foreach ($matches as $idx => $match) {
                    $sql = trim($match[1]);
                    $sql = rtrim($sql, ';');
                    if (!empty($sql)) {
                        $queries["Query " . ($idx + 1)] = $sql;
                    }
                }
                Log::info("Fallback parsed " . count($queries) . " queries");
            }
            
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
                    'temperature' => 0.1,      // Lower temperature untuk analisis lebih fokus dan deterministik
                    'top_p'       => 0.95,     // Top-P tinggi untuk koherensi konteks yang lebih baik
                    'frequency_penalty' => 0.1, // Mencegah repetisi
                    'presence_penalty'  => 0.1, // Mendorong variasi respons
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
                $fallback = $this->formatContextAsResponse($dbContext, $detectedLanguage);
                echo "data: " . json_encode(['fallback' => true, 'response' => $fallback]) . "\n\n";
                Log::info("AI unavailable, showing data with auto-generated insights in language: {$detectedLanguage}");
            } else {
                // No data at all - use detected language for error message
                $errorMsg = $detectedLanguage === 'en'
                    ? "Sorry, AI service is temporarily unavailable. Please try again later or contact admin if the issue persists."
                    : "Maaf, layanan AI sedang tidak tersedia. Silakan coba lagi nanti atau hubungi admin jika masalah berlanjut.";
                echo "data: " . json_encode(['error' => true, 'response' => $errorMsg]) . "\n\n";
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
        $tahunFilter   = $this->extractTahunFilter($lower);
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
                $tahunFilter   = $this->extractTahunFilter($lower);
                $queries = $this->selectQueries($lower, $wilayahFilter, $tahunFilter);
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

                    Log::info("Executing query '{$label}': " . substr($sql, 0, 200));
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
                $tahunFilter   = $this->extractTahunFilter($lower);
                $queries = $this->selectQueries($lower, $wilayahFilter, $tahunFilter);

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

                        Log::info("Executing static query '{$label}': " . substr($sql, 0, 200));
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
        if ($tahunFilter) $ctx .= "Filter tahun: '{$tahunFilter}'\n";
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
    private function selectQueries(string $lower, string $wilayahFilter = '', string $tahunFilter = ''): array
    {
        $queries = [];
        $tgl     = 'tgl_fak_jl';
        $bayar   = 'total_harga';
        $hasW    = !empty($wilayahFilter);
        $hasT    = !empty($tahunFilter);
        $safe    = $hasW ? addslashes($wilayahFilter) : '';

        $allowedTables = $this->getAllowedTables();
        $isAllowed = function($table) use ($allowedTables) {
            return in_array($table, $allowedTables);
        };

        $vSales = 'sch_mbi.view_data_penjualan_rinci_mbi';
        $allowSales = $isAllowed('view_data_penjualan_rinci_mbi');

        // Build WHERE clause with both wilayah and tahun filters
        $whereConditions = [];
        if ($hasW) {
            $whereConditions[] = "(LOWER(nama_propinsi_cabang) LIKE '%{$safe}%' OR LOWER(nama_kabupaten_cabang) LIKE '%{$safe}%' OR LOWER(alamat_pelanggan) LIKE '%{$safe}%')";
        }
        if ($hasT) {
            $whereConditions[] = "(periode_tahun = '{$tahunFilter}' OR EXTRACT(YEAR FROM {$tgl}) = {$tahunFilter})";
        }
        
        $wWhere = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "WHERE 1=1";

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

        // ── Query untuk "seluruh", "semua", "all" data ───────────────────────
        // Jika user minta tampilkan seluruh data, SELALU tambahkan summary total DAN raw data
        if ($this->hasKeyword($lower, ['seluruh', 'semua', 'all', 'everything', 'daftar lengkap', 'full list', 'total'])) {
            if ($allowSales) {
                // Always add total summary for sales data
                $queries['📊 Total Keseluruhan'] = "
                    SELECT
                        COUNT(DISTINCT no_fak_jl) as total_transaksi,
                        COUNT(DISTINCT kode_pelanggan) as total_pelanggan,
                        COUNT(DISTINCT kode_barang) as total_produk,
                        SUM(qty_jual) as total_qty_terjual,
                        SUM(total_netto) as total_pendapatan,
                        ROUND(AVG(total_netto), 0) as rata_rata_transaksi,
                        MIN(tgl_fak_jl) as transaksi_pertama,
                        MAX(tgl_fak_jl) as transaksi_terakhir
                    FROM {$vSales}
                    " . ($hasW || $hasT ? $wWhere : "WHERE 1=1");
                
                // ADD RAW DATA QUERY for "seluruh data penjualan" requests
                if ($this->hasKeyword($lower, ['data', 'penjualan', 'transaksi', 'sales'])) {
                    $queries['📋 Data Penjualan Detail'] = "
                        SELECT
                            no_fak_jl,
                            tgl_fak_jl,
                            nama_pelanggan,
                            nama_barang,
                            qty_jual,
                            total_harga,
                            total_netto,
                            nama_kategori_barang,
                            periode_tahun,
                            periode_bulan
                        FROM {$vSales}
                        " . ($hasW || $hasT ? $wWhere : "WHERE 1=1") . "
                        ORDER BY tgl_fak_jl DESC
                        LIMIT 50";
                }
            }
            if ($isAllowed('view_master_cabang_mbi')) {
                $queries['🏢 Total Cabang'] = "
                    SELECT
                        COUNT(*) as jumlah_cabang,
                        COUNT(DISTINCT nama_regional) as jumlah_regional,
                        COUNT(DISTINCT nama_propinsi_cabang) as jumlah_provinsi,
                        COUNT(DISTINCT nama_kabupaten_cabang) as jumlah_kabupaten
                    FROM sch_mbi.view_master_cabang_mbi";
            }
            if ($isAllowed('view_master_pelanggan_mbi')) {
                $queries['👥 Total Pelanggan'] = "
                    SELECT
                        COUNT(*) as jumlah_pelanggan,
                        COUNT(DISTINCT nama_kabupaten_pelanggan) as kabupaten,
                        COUNT(DISTINCT nama_propinsi_pelanggan) as provinsi
                    FROM sch_mbi.view_master_pelanggan_mbi";
            }
        }

        return $queries;
    }

    // ── Format data sebagai respons langsung (fallback jika AI gagal) ─────────
    private function formatContextAsResponse(string $ctx, string $language = 'id'): string
    {
        // Extract data from context
        $dataSection = preg_replace('/^=== DATA NYATA.*?\n.*?\n\n/s', '', $ctx);
        
        // Parse data untuk generate insight
        $insights = $this->generateAutoInsights($ctx, $language);
        
        // Language-specific headers
        $headers = $language === 'en' ? [
            'data' => '### 📊 Data Results',
            'summary' => '### 📈 Statistical Summary',
            'insights' => '### 🔍 Deep Insights',
            'recommendations' => '### 💡 Strategic Recommendations',
            'action' => '### ✅ Action Plan',
            'footer' => '> ℹ️ Data and insights above are auto-generated from database. Contact admin for further discussion.'
        ] : [
            'data' => '### 📊 Hasil Data',
            'summary' => '### 📈 Ringkasan Statistik',
            'insights' => '### 🔍 Insight Mendalam',
            'recommendations' => '### 💡 Rekomendasi Strategis',
            'action' => '### ✅ Action Plan',
            'footer' => '> ℹ️ Data dan insight di atas digenerate otomatis dari database. Hubungi admin untuk diskusi lebih lanjut.'
        ];
        
        $response = "{$headers['data']}\n\n";
        $response .= $dataSection . "\n\n";
        
        // Add auto-generated insights
        if (!empty($insights['summary'])) {
            $response .= "{$headers['summary']}\n\n";
            foreach ($insights['summary'] as $insight) {
                $response .= "- {$insight}\n";
            }
            $response .= "\n";
        }
        
        // Add detailed insights
        if (!empty($insights['detailed_insights'])) {
            $response .= "{$headers['insights']}\n\n";
            foreach ($insights['detailed_insights'] as $insight) {
                $response .= "{$insight}\n";
            }
            $response .= "\n";
        }
        
        // Add recommendations
        if (!empty($insights['recommendations'])) {
            $response .= "{$headers['recommendations']}\n\n";
            foreach ($insights['recommendations'] as $rec) {
                $response .= "{$rec}\n\n";
            }
        }
        
        // Add action items
        if (!empty($insights['action_items'])) {
            $response .= "{$headers['action']}\n\n";
            foreach ($insights['action_items'] as $action) {
                $response .= "{$action}\n";
            }
            $response .= "\n";
        }
        
        $response .= $headers['footer'];
        
        return $response;
    }
    
    // ── Generate auto insights dari data ──────────────────────────────────────
    private function generateAutoInsights(string $ctx, string $language = 'id'): array
    {
        $insights = ['summary' => [], 'detailed_insights' => [], 'recommendations' => [], 'action_items' => []];
        
        // Extract data dari context
        $dataRows = $this->parseDataTable($ctx);
        
        if (empty($dataRows)) {
            return $insights;
        }
        
        // Deteksi tipe data
        $dataType = $this->detectDataType($ctx);
        
        // Extract numeric columns
        $numericColumns = $this->extractNumericColumns($dataRows);
        
        // === SUMMARY STATISTICS ===
        foreach ($numericColumns as $colName => $values) {
            if (empty($values)) continue;
            
            $total = array_sum($values);
            $avg = $total / count($values);
            $max = max($values);
            $min = min($values);
            $median = $this->calculateMedian($values);
            
            if ($language === 'en') {
                $insights['summary'][] = "Total {$colName}: " . $this->formatRupiah($total);
                $insights['summary'][] = "Average {$colName}: " . $this->formatRupiah($avg);
                if (count($values) > 1) {
                    $insights['summary'][] = "Median {$colName}: " . $this->formatRupiah($median);
                    $insights['summary'][] = "Range {$colName}: " . $this->formatRupiah($min) . " - " . $this->formatRupiah($max);
                    
                    $stdDev = $this->calculateStdDev($values, $avg);
                    $cv = ($avg > 0) ? ($stdDev / $avg) * 100 : 0;
                    if ($cv > 50) {
                        $insights['detailed_insights'][] = "⚠️ High data dispersion for {$colName} (CV: " . number_format($cv, 1) . "%). Significant gap between highest and lowest values.";
                    } else {
                        $insights['detailed_insights'][] = "✅ {$colName} distribution is relatively even (CV: " . number_format($cv, 1) . "%).";
                    }
                }
            } else {
                $insights['summary'][] = "Total {$colName}: " . $this->formatRupiah($total);
                $insights['summary'][] = "Rata-rata {$colName}: " . $this->formatRupiah($avg);
                if (count($values) > 1) {
                    $insights['summary'][] = "Median {$colName}: " . $this->formatRupiah($median);
                    $insights['summary'][] = "Range {$colName}: " . $this->formatRupiah($min) . " - " . $this->formatRupiah($max);
                    
                    $stdDev = $this->calculateStdDev($values, $avg);
                    $cv = ($avg > 0) ? ($stdDev / $avg) * 100 : 0;
                    if ($cv > 50) {
                        $insights['detailed_insights'][] = "⚠️ Dispersi data {$colName} tinggi (CV: " . number_format($cv, 1) . "%). Ada ketimpangan signifikan antara nilai tertinggi dan terendah.";
                    } else {
                        $insights['detailed_insights'][] = "✅ Distribusi {$colName} relatif merata (CV: " . number_format($cv, 1) . "%).";
                    }
                }
            }
        }
        
        // === DETAILED INSIGHTS ===
        // Top performers
        $topItems = $this->getTopPerformers($dataRows, $numericColumns, 3);
        if (!empty($topItems)) {
            if ($language === 'en') {
                $insights['detailed_insights'][] = "🏆 Top Performers: " . implode(', ', $topItems);
            } else {
                $insights['detailed_insights'][] = "🏆 Top Performers: " . implode(', ', $topItems);
            }
        }
        
        // Bottom performers
        $bottomItems = $this->getBottomPerformers($dataRows, $numericColumns, 3);
        if (!empty($bottomItems)) {
            if ($language === 'en') {
                $insights['detailed_insights'][] = "📉 Needs Attention: " . implode(', ', $bottomItems);
            } else {
                $insights['detailed_insights'][] = "📉 Perlu Perhatian: " . implode(', ', $bottomItems);
            }
        }
        
        // Percentage analysis
        $percentageInsights = $this->analyzePercentages($dataRows, $numericColumns, $language);
        foreach ($percentageInsights as $pi) {
            $insights['detailed_insights'][] = $pi;
        }
        
        // === RECOMMENDATIONS (Specific & Actionable) ===
        $recommendations = $this->generateRecommendations($dataType, $dataRows, $numericColumns, $ctx, $language);
        $insights['recommendations'] = $recommendations;
        
        // === ACTION ITEMS (Immediate Actions) ===
        $actionItems = $this->generateActionItems($language);
        $insights['action_items'] = $actionItems;
        
        return $insights;
    }
    
    // ── Parse data table dari context ─────────────────────────────────────────
    private function parseDataTable(string $ctx): array
    {
        $dataRows = [];
        preg_match_all('/\| ([^|]+) \|/u', $ctx, $matches);
        
        if (empty($matches[1])) {
            return $dataRows;
        }
        
        $rows = array_slice($matches[1], 2); // Skip header dan separator
        
        $headers = null;
        foreach ($rows as $row) {
            $cols = array_map('trim', explode('|', $row));
            if ($headers === null) {
                $headers = $cols;
                continue;
            }
            
            $rowData = [];
            foreach ($cols as $i => $col) {
                $key = $headers[$i] ?? "col_{$i}";
                $rowData[$key] = $col;
            }
            $dataRows[] = $rowData;
        }
        
        return $dataRows;
    }
    
    // ── Deteksi tipe data ─────────────────────────────────────────────────────
    private function detectDataType(string $ctx): string
    {
        $lowerCtx = strtolower($ctx);
        
        if (str_contains($lowerCtx, 'produk') || str_contains($lowerCtx, 'barang')) {
            return 'produk';
        } elseif (str_contains($lowerCtx, 'pelanggan') || str_contains($lowerCtx, 'customer')) {
            return 'pelanggan';
        } elseif (str_contains($lowerCtx, 'cabang') || str_contains($lowerCtx, 'branch')) {
            return 'cabang';
        } elseif (str_contains($lowerCtx, 'penjualan') || str_contains($lowerCtx, 'sales') || str_contains($lowerCtx, 'revenue')) {
            return 'penjualan';
        } elseif (str_contains($lowerCtx, 'stok') || str_contains($lowerCtx, 'stock')) {
            return 'stok';
        } elseif (str_contains($lowerCtx, 'target') || str_contains($lowerCtx, 'realisasi')) {
            return 'target';
        }
        
        return 'general';
    }
    
    // ── Extract numeric columns ───────────────────────────────────────────────
    private function extractNumericColumns(array $dataRows): array
    {
        $numericColumns = [];
        
        if (empty($dataRows)) {
            return $numericColumns;
        }
        
        $headers = array_keys($dataRows[0]);
        
        foreach ($headers as $header) {
            $values = [];
            foreach ($dataRows as $row) {
                $val = $row[$header] ?? null;
                if ($val !== null && is_numeric(str_replace(['.', ','], ['', '.'], $val))) {
                    $values[] = (float)str_replace(',', '', str_replace('.', '', $val));
                }
            }
            if (!empty($values)) {
                $numericColumns[$header] = $values;
            }
        }
        
        return $numericColumns;
    }
    
    // ── Calculate median ──────────────────────────────────────────────────────
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }
    
    // ── Calculate standard deviation ──────────────────────────────────────────
    private function calculateStdDev(array $values, float $mean): float
    {
        $sum = 0;
        foreach ($values as $val) {
            $sum += pow($val - $mean, 2);
        }
        return sqrt($sum / count($values));
    }
    
    // ── Get top performers ────────────────────────────────────────────────────
    private function getTopPerformers(array $dataRows, array $numericColumns, int $limit = 3): array
    {
        if (empty($dataRows) || empty($numericColumns)) {
            return [];
        }
        
        // Find the main value column (usually total, revenue, etc.)
        $valueCol = null;
        foreach (['total_belanja', 'total_pendapatan', 'total_terjual', 'total_revenue', 'total'] as $keyword) {
            foreach (array_keys($numericColumns) as $col) {
                if (stripos($col, $keyword) !== false) {
                    $valueCol = $col;
                    break 2;
                }
            }
        }
        
        if ($valueCol === null) {
            $valueCol = array_keys($numericColumns)[0];
        }
        
        // Sort by value column
        usort($dataRows, function($a, $b) use ($valueCol) {
            $valA = (float)str_replace(',', '', str_replace('.', '', $a[$valueCol] ?? 0));
            $valB = (float)str_replace(',', '', str_replace('.', '', $b[$valueCol] ?? 0));
            return $valB <=> $valA;
        });
        
        $topPerformers = [];
        foreach (array_slice($dataRows, 0, $limit) as $row) {
            // Find name column
            $name = null;
            foreach (['nama_pelanggan', 'nama_barang', 'nama_cabang', 'nama', 'produk', 'cabang'] as $key) {
                foreach (array_keys($row) as $rowKey) {
                    if (stripos($rowKey, $key) !== false) {
                        $name = $row[$rowKey];
                        break 2;
                    }
                }
            }
            if ($name === null) {
                $name = reset($row);
            }
            $topPerformers[] = $name;
        }
        
        return $topPerformers;
    }
    
    // ── Get bottom performers ─────────────────────────────────────────────────
    private function getBottomPerformers(array $dataRows, array $numericColumns, int $limit = 3): array
    {
        if (empty($dataRows) || empty($numericColumns)) {
            return [];
        }
        
        // Find the main value column
        $valueCol = null;
        foreach (['total_belanja', 'total_pendapatan', 'total_terjual', 'total_revenue', 'total'] as $keyword) {
            foreach (array_keys($numericColumns) as $col) {
                if (stripos($col, $keyword) !== false) {
                    $valueCol = $col;
                    break 2;
                }
            }
        }
        
        if ($valueCol === null) {
            $valueCol = array_keys($numericColumns)[0];
        }
        
        // Sort ascending
        usort($dataRows, function($a, $b) use ($valueCol) {
            $valA = (float)str_replace(',', '', str_replace('.', '', $a[$valueCol] ?? 0));
            $valB = (float)str_replace(',', '', str_replace('.', '', $b[$valueCol] ?? 0));
            return $valA <=> $valB;
        });
        
        $bottomPerformers = [];
        foreach (array_slice($dataRows, 0, $limit) as $row) {
            // Find name column
            $name = null;
            foreach (['nama_pelanggan', 'nama_barang', 'nama_cabang', 'nama', 'produk', 'cabang'] as $key) {
                foreach (array_keys($row) as $rowKey) {
                    if (stripos($rowKey, $key) !== false) {
                        $name = $row[$rowKey];
                        break 2;
                    }
                }
            }
            if ($name === null) {
                $name = reset($row);
            }
            $bottomPerformers[] = $name;
        }
        
        return $bottomPerformers;
    }
    
    // ── Analyze percentages ───────────────────────────────────────────────────
    private function analyzePercentages(array $dataRows, array $numericColumns, string $language = 'id'): array
    {
        $insights = [];
        
        if (empty($dataRows) || empty($numericColumns)) {
            return $insights;
        }
        
        // Find percentage column
        $pctCol = null;
        foreach (array_keys($numericColumns) as $col) {
            if (stripos($col, 'persen') !== false || stripos($col, 'percent') !== false) {
                $pctCol = $col;
                break;
            }
        }
        
        if ($pctCol !== null) {
            $values = $numericColumns[$pctCol];
            $topPct = max($values);
            if ($language === 'en') {
                if ($topPct > 20) {
                    $insights[] = "📊 High concentration: Top item controls " . number_format($topPct, 1) . "% of total. Consider diversification.";
                }
            } else {
                if ($topPct > 20) {
                    $insights[] = "📊 Konsentrasi tinggi: Item teratas menguasai " . number_format($topPct, 1) . "% dari total. Pertimbangkan diversifikasi.";
                }
            }
        }
        
        return $insights;
    }
    
    // ── Generate recommendations based on data type ───────────────────────────
    private function generateRecommendations(string $dataType, array $dataRows, array $numericColumns, string $ctx, string $language = 'id'): array
    {
        $recommendations = [];

        if ($language === 'en') {
            // English recommendations
            switch ($dataType) {
                case 'produk':
                    $recommendations[] = "🎯 **Product Strategy**: Focus inventory and marketing on top performer products contributing >60% of total sales";
                    $recommendations[] = "📦 **Inventory Management**: Weekly review for slow-moving products. Consider bundle promotion or clearance sale";
                    $recommendations[] = "💰 **Pricing Strategy**: Analyze margin of bestsellers. If high volume but low margin, consider gradual price adjustment";
                    $recommendations[] = "🔄 **Product Lifecycle**: Identify products in decline phase (sales down 3 consecutive months). Prepare replacement or innovation";
                    break;

                case 'pelanggan':
                    $recommendations[] = "👑 **Customer Retention**: Implement VIP program for top 20% customers contributing >50% revenue. Give exclusive benefits and early access";
                    $recommendations[] = "📈 **Upselling Strategy**: Analyze purchase pattern of best customers. Offer complementary products or upgrades with personalized recommendations";
                    $recommendations[] = "⚠️ **Churn Prevention**: Monitor customers with >30% decrease in purchase frequency. Do proactive outreach with special offers";
                    $recommendations[] = "🎁 **Loyalty Program**: Create tier-based reward system (Silver, Gold, Platinum) to incentivize repeat purchase and increase customer lifetime value";
                    break;

                case 'cabang':
                    $recommendations[] = "🏢 **Performance Optimization**: Top performer branches can be best practice models. Document their strategies and replicate to other branches";
                    $recommendations[] = "📊 **Resource Allocation**: Allocate bigger marketing and inventory budget to branches with highest ROI. Review underperformer branches for turnaround strategy";
                    $recommendations[] = "👥 **Talent Management**: Consider rotation or knowledge sharing between successful branch managers and branches needing improvement";
                    $recommendations[] = "🎯 **Market Penetration**: Analyze market potential in underperformer branch regions. May need local product mix or pricing strategy adjustment";
                    break;

                case 'penjualan':
                    $recommendations[] = "📈 **Revenue Growth**: Identify sales patterns (daily, weekly, monthly). Optimize staffing and inventory based on peak periods";
                    $recommendations[] = "💡 **Sales Drivers**: Analyze products/categories with highest growth. Double down on success factors and replicate to other categories";
                    $recommendations[] = "🎯 **Target Setting**: Use historical data to set realistic but challenging targets. Breakdown targets per period and monitor progress weekly";
                    $recommendations[] = "🔄 **Seasonal Planning**: Identify seasonal patterns and prepare inventory, marketing, and operational capacity accordingly";
                    break;

                case 'stok':
                    $recommendations[] = "📦 **Inventory Optimization**: Apply ABC analysis. Category A (high value) needs tighter control and frequent review";
                    $recommendations[] = "⚡ **Stock Turnover**: Monitor stock turnover ratio. Target >4x turnover per year for fast-moving items. Clearance for slow-moving >90 days";
                    $recommendations[] = "🔔 **Reorder Point**: Setup automated reorder alerts based on supplier lead time and safety stock level";
                    $recommendations[] = "💰 **Working Capital**: Reduce excess inventory to free up cash flow. Negotiate payment terms with suppliers and customers";
                    break;

                case 'target':
                    $recommendations[] = "🎯 **Performance Gap**: Analyze gap between target and actual. Identify root cause (internal vs external factors)";
                    $recommendations[] = "📊 **Forecasting Accuracy**: Review historical forecast accuracy. Adjust forecasting method if consistently over/under estimate";
                    $recommendations[] = "🔄 **Target Calibration**: Quarterly review to adjust targets based on market conditions and business reality";
                    $recommendations[] = "💪 **Action Planning**: Breakdown targets into weekly milestones. Weekly check-in to track progress and course correction";
                    break;

                default:
                    $recommendations[] = "📊 **Data-Driven Decision**: Use these insights as baseline for strategic planning. Monitor key metrics consistently";
                    $recommendations[] = "🎯 **Priority Focus**: Identify 2-3 areas with highest impact. Focus resources and effort there";
                    $recommendations[] = "📈 **Continuous Improvement**: Setup monthly review cadence to track progress and adjust strategy based on results";
                    $recommendations[] = "🔄 **Agile Approach**: Test & learn with small experiments. Scale what works, pivot what doesn't";
                    break;
            }
        } else {
            // Indonesian recommendations
            switch ($dataType) {
                case 'produk':
                    $recommendations[] = "🎯 **Strategi Produk**: Fokuskan inventory dan marketing pada produk top performer yang menyumbang >60% dari total penjualan";
                    $recommendations[] = "📦 **Manajemen Stok**: Lakukan review mingguan untuk produk dengan pergerakan lambat. Pertimbangkan bundle promotion atau clearance sale";
                    $recommendations[] = "💰 **Pricing Strategy**: Analisis margin produk terlaris. Jika volume tinggi tapi margin rendah, pertimbangkan penyesuaian harga bertahap";
                    $recommendations[] = "🔄 **Product Lifecycle**: Identifikasi produk di fase decline (penjualan turun 3 bulan berturut-turut). Siapkan produk pengganti atau inovasi baru";
                    break;

                case 'pelanggan':
                    $recommendations[] = "👑 **Customer Retention**: Implementasi VIP program untuk top 20% pelanggan yang menyumbang >50% revenue. Berikan exclusive benefits dan early access";
                    $recommendations[] = "📈 **Upselling Strategy**: Analisis purchase pattern pelanggan terbaik. Tawarkan produk komplementer atau upgrade dengan personalized recommendation";
                    $recommendations[] = "⚠️ **Churn Prevention**: Monitor pelanggan dengan penurunan frekuensi belanja >30%. Lakukan proactive outreach dengan special offer";
                    $recommendations[] = "🎁 **Loyalty Program**: Buat tier-based reward system (Silver, Gold, Platinum) untuk incentivize repeat purchase dan increase customer lifetime value";
                    break;

                case 'cabang':
                    $recommendations[] = "🏢 **Performance Optimization**: Cabang top performer bisa jadi model best practice. Dokumentasikan strategi mereka dan replicate ke cabang lain";
                    $recommendations[] = "📊 **Resource Allocation**: Alokasikan budget marketing dan inventory lebih besar ke cabang dengan ROI tertinggi. Review cabang underperformer untuk turnaround strategy";
                    $recommendations[] = "👥 **Talent Management**: Pertimbangkan rotation atau knowledge sharing antara manager cabang sukses dan cabang yang perlu improvement";
                    $recommendations[] = "🎯 **Market Penetration**: Analisis market potential di wilayah cabang underperformer. Mungkin perlu adjustment product mix atau pricing strategy lokal";
                    break;

                case 'penjualan':
                    $recommendations[] = "📈 **Revenue Growth**: Identifikasi pattern penjualan (harian, mingguan, bulanan). Optimize staffing dan inventory berdasarkan peak periods";
                    $recommendations[] = "💡 **Sales Drivers**: Analisis produk/kategori dengan growth tertinggi. Double down pada success factors dan replicate ke kategori lain";
                    $recommendations[] = "🎯 **Target Setting**: Gunakan historical data untuk set realistic tapi challenging targets. Breakdown target per periode dan monitor progress weekly";
                    $recommendations[] = "🔄 **Seasonal Planning**: Identifikasi seasonal patterns dan prepare inventory, marketing, dan operational capacity accordingly";
                    break;

                case 'stok':
                    $recommendations[] = "📦 **Inventory Optimization**: Terapkan ABC analysis. Kategori A (high value) perlu tighter control dan frequent review";
                    $recommendations[] = "⚡ **Stock Turnover**: Monitor stock turnover ratio. Targetkan turnover >4x per tahun untuk fast-moving items. Clearance untuk slow-moving >90 hari";
                    $recommendations[] = "🔔 **Reorder Point**: Setup automated reorder alerts berdasarkan lead time supplier dan safety stock level";
                    $recommendations[] = "💰 **Working Capital**: Reduce excess inventory untuk free up cash flow. Negosiasi payment terms dengan supplier dan customer";
                    break;

                case 'target':
                    $recommendations[] = "🎯 **Performance Gap**: Analisis gap antara target dan realisasi. Identify root cause (internal vs external factors)";
                    $recommendations[] = "📊 **Forecasting Accuracy**: Review historical forecast accuracy. Adjust forecasting method jika consistently over/under estimate";
                    $recommendations[] = "🔄 **Target Calibration**: Quarterly review untuk adjust targets berdasarkan market condition dan business reality";
                    $recommendations[] = "💪 **Action Planning**: Breakdown target menjadi weekly milestones. Weekly check-in untuk track progress dan course correction";
                    break;

                default:
                    $recommendations[] = "📊 **Data-Driven Decision**: Gunakan insights ini sebagai baseline untuk strategic planning. Monitor key metrics secara konsisten";
                    $recommendations[] = "🎯 **Priority Focus**: Identifikasi 2-3 area dengan impact tertinggi. Fokuskan resources dan effort di sana";
                    $recommendations[] = "📈 **Continuous Improvement**: Setup monthly review cadence untuk track progress dan adjust strategy berdasarkan results";
                    $recommendations[] = "🔄 **Agile Approach**: Test & learn dengan small experiments. Scale what works, pivot what doesn't";
                    break;
            }
        }

        return $recommendations;
    }

    // ── Generate action items ─────────────────────────────────────────────────
    private function generateActionItems(string $language = 'id'): array
    {
        $actionItems = [];

        if ($language === 'en') {
            $actionItems[] = "✅ **Immediate (This Week)**: Review top 3 and bottom 3 performers. Schedule meeting with related team";
            $actionItems[] = "📋 **Short-term (This Month)**: Implement at least 1 recommendation from the list above. Assign clear owner and deadline";
            $actionItems[] = "📊 **Medium-term (This Quarter)**: Setup dashboard monitoring for key metrics. Monthly review with stakeholders";
        } else {
            $actionItems[] = "✅ **Immediate (This Week)**: Review top 3 dan bottom 3 performers. Schedule meeting dengan team terkait";
            $actionItems[] = "📋 **Short-term (This Month)**: Implementasi minimal 1 rekomendasi dari daftar di atas. Assign owner dan deadline yang jelas";
            $actionItems[] = "📊 **Medium-term (This Quarter)**: Setup dashboard monitoring untuk key metrics. Monthly review dengan stakeholder";
        }

        return $actionItems;
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

    // ── Ekstrak filter tahun dari pesan ───────────────────────────────────────
    private function extractTahunFilter(string $lower): string
    {
        // Cari tahun 4 digit (2020-2030)
        if (preg_match('/\b(202[0-9]|2030)\b/', $lower, $m)) {
            return $m[1];
        }
        
        // Cari pola "tahun 2025", "th 2024", dll
        if (preg_match('/(?:tahun|th|thn|year)\s*\.?\s*(202[0-9]|2030)/', $lower, $m)) {
            return $m[1];
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

            $cacheKey = 'db_schema_context_v6_' . (Auth::user() ? Auth::user()->role : 'guest') . '_' . md5($message);

            return cache()->remember($cacheKey, 300, function () use ($allowedTables, $priorityTables) {
                // Single query to get all tables and their columns in sch_mbi
                $results = DB::connection('pgsql_mbi')->select("
                    SELECT table_name, column_name, data_type
                    FROM information_schema.columns
                    WHERE table_schema = 'sch_mbi'
                    AND table_name NOT IN ('migrations','cache','cache_locks','sessions','jobs','failed_jobs','personal_access_tokens','users','password_reset_tokens')
                    ORDER BY table_name, ordinal_position
                ");

                $tableGroups = [];
                foreach ($results as $row) {
                    if (!in_array($row->table_name, $allowedTables)) continue;
                    if (!isset($tableGroups[$row->table_name])) {
                        $tableGroups[$row->table_name] = [];
                    }
                    $tableGroups[$row->table_name][] = [
                        'column' => $row->column_name,
                        'type' => $row->data_type
                    ];
                }

                // Build comprehensive schema context
                $context = "=== DATABASE SCHEMA (sch_mbi) ===\n\n";
                $context .= "⚠️ CRITICAL: Use ONLY table names listed below. ALWAYS prefix with 'sch_mbi.'\n\n";
                
                $context .= "AVAILABLE TABLES:\n";
                $context .= str_repeat("-", 60) . "\n";
                
                foreach (array_keys($tableGroups) as $tn) {
                    $context .= "• {$tn}\n";
                }
                $context .= str_repeat("-", 60) . "\n\n";
                
                $context .= "TABLE STRUCTURES (table_name(column_name data_type)):\n";
                $context .= str_repeat("=", 60) . "\n\n";

                $count = 0;

                // Add priority tables first with full column details
                foreach ($priorityTables as $tn) {
                    if (isset($tableGroups[$tn])) {
                        $context .= "{$tn}:\n";
                        foreach ($tableGroups[$tn] as $col) {
                            $context .= "  - {$col['column']} ({$col['type']})\n";
                        }
                        $context .= "\n";
                        unset($tableGroups[$tn]);
                        $count++;
                    }
                }

                // Add remaining allowed tables
                foreach ($tableGroups as $tn => $cols) {
                    $context .= "{$tn}:\n";
                    foreach ($cols as $col) {
                        $context .= "  - {$col['column']} ({$col['type']})\n";
                    }
                    $context .= "\n";
                    $count++;
                }

                if ($count === 0) return "No access to database tables.";
                
                $context .= str_repeat("=", 60) . "\n";
                $context .= "\n💡 TIPS:\n";
                $context .= "- For filtering by year: use periode_tahun column or EXTRACT(YEAR FROM tgl_fak_jl)\n";
                $context .= "- For region search: use ILIKE with wildcards (e.g., nama_propinsi_cabang ILIKE '%riau%')\n";
                $context .= "- For aggregations: use SUM(), COUNT(), AVG() with GROUP BY\n";
                $context .= "- Always LIMIT results to 50 rows maximum\n";
                
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
