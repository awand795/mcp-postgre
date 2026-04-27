<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\AiModel;
use App\Models\AiApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    public function index()
    {
        $providers = AiProvider::with(['models', 'apiKeys'])->get();
        return view('admin.ai_management', compact('providers'));
    }

    public function storeKey(Request $request)
    {
        $request->validate([
            'provider_id' => 'required|exists:ai_providers,id',
            'key_name'    => 'required|string|max:255',
            'api_key'     => 'required|string',
        ]);

        AiApiKey::create($request->all());

        return back()->with('success', 'API Key berhasil ditambahkan.');
    }

    public function storeModel(Request $request)
    {
        $request->validate([
            'provider_id'  => 'required|exists:ai_providers,id',
            'model_name'   => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
        ]);

        AiModel::create($request->all());

        return back()->with('success', 'Model AI berhasil ditambahkan.');
    }

    public function deleteModel(AiModel $model)
    {
        $model->delete();
        return back()->with('success', 'Model AI berhasil dihapus.');
    }

    public function updateKey(Request $request, AiApiKey $key)
    {
        $request->validate([
            'key_name'  => 'required|string|max:255',
            'api_key'   => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['key_name', 'is_active']);
        if ($request->filled('api_key')) {
            $data['api_key'] = $request->api_key;
        }

        $key->update($data);

        return back()->with('success', 'API Key berhasil diperbarui.');
    }

    public function deleteKey(AiApiKey $key)
    {
        $key->delete();
        return back()->with('success', 'API Key berhasil dihapus.');
    }

    public function toggleModel(AiModel $model)
    {
        $model->update(['is_active' => !$model->is_active]);
        return response()->json(['success' => true, 'is_active' => $model->is_active]);
    }

    public function toggleProvider(AiProvider $provider)
    {
        $provider->update(['is_active' => !$provider->is_active]);
        return response()->json(['success' => true, 'is_active' => $provider->is_active]);
    }

    public function resetLimit(AiApiKey $key)
    {
        $key->update(['limit_reached' => false]);
        return back()->with('success', 'Limit API Key berhasil di-reset.');
    }

    /**
     * Polling endpoint — kembalikan status terbaru semua API keys
     * Dipanggil setiap 30 detik dari halaman AI Management via JS
     */
    public function pollKeyStatus(): \Illuminate\Http\JsonResponse
    {
        $keys = AiApiKey::with('provider')
            ->select('id', 'provider_id', 'key_name', 'is_active', 'limit_reached', 'last_used_at', 'usage_count')
            ->get()
            ->map(function ($key) {
                return [
                    'id'            => $key->id,
                    'is_active'     => $key->is_active,
                    'limit_reached' => $key->limit_reached,
                    'last_used_at'  => $key->last_used_at?->diffForHumans(),
                    'usage_count'   => $key->usage_count,
                    'provider_id'   => $key->provider_id,
                ];
            });

        // Hitung limit per provider untuk update banner
        $limitPerProvider = $keys
            ->where('limit_reached', true)
            ->groupBy('provider_id')
            ->map->count();

        return response()->json([
            'keys'               => $keys->keyBy('id'),
            'limit_per_provider' => $limitPerProvider,
            'total_limit'        => $keys->where('limit_reached', true)->count(),
        ]);
    }

    public function storeProvider(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'code'     => 'required|string|max:50|unique:ai_providers,code|regex:/^[a-z0-9_]+$/',
            'base_url' => 'nullable|url|max:500',
        ], [
            'code.unique'  => 'Kode provider sudah digunakan.',
            'code.regex'   => 'Kode provider hanya boleh huruf kecil, angka, dan underscore.',
            'base_url.url' => 'Format Base URL tidak valid.',
        ]);

        AiProvider::create([
            'name'      => $request->name,
            'code'      => $request->code,
            'base_url'  => $request->base_url ?: null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Provider AI "' . $request->name . '" berhasil ditambahkan.');
    }

    public function deleteProvider(AiProvider $provider)
    {
        $builtIn = ['openai', 'gemini', 'claude', 'mistral'];
        if (in_array($provider->code, $builtIn)) {
            return back()->with('error', 'Provider built-in tidak dapat dihapus.');
        }

        if ($provider->apiKeys()->count() > 0) {
            return back()->with('error', 'Hapus semua API Key provider ini terlebih dahulu sebelum menghapus provider.');
        }

        $provider->models()->delete();
        $provider->delete();

        return back()->with('success', 'Provider "' . $provider->name . '" berhasil dihapus.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HEALTH CHECK — Ping provider dengan request minimal, baca header & response
    // Mengembalikan JSON: status, latency_ms, info dari header, dan error jika ada
    // ──────────────────────────────────────────────────────────────────────────
    /**
     * Ambil model aktif pertama dari DB untuk provider ini.
     * Fallback ke $default jika tidak ada.
     */
    private function pickModel(AiApiKey $key, string $default): string
    {
        return $key->provider
            ->models()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('model_name') ?? $default;
    }

    public function healthCheck(AiApiKey $key): \Illuminate\Http\JsonResponse
    {
        $key->load('provider');
        $providerCode = strtolower($key->provider->code ?? '');
        $baseUrl      = rtrim($key->provider->base_url ?? '', '/');

        $startTime = microtime(true);
        $result    = [];

        try {
            switch ($providerCode) {

                // ── OpenAI ────────────────────────────────────────────────────
                case 'openai':
                    $model    = request()->input('model') ?: $this->pickModel($key, 'gpt-4o-mini');
                    $response = Http::timeout(15)
                        ->withToken($key->api_key)
                        ->post('https://api.openai.com/v1/chat/completions', [
                            'model'      => $model,
                            'max_tokens' => 1,
                            'messages'   => [['role' => 'user', 'content' => 'hi']],
                        ]);

                    $latency = round((microtime(true) - $startTime) * 1000);
                    $headers = $response->headers();
                    $status  = $response->status();

                    $result = $this->buildResult($status, $latency, [
                        'Model diuji'            => $model,
                        'Requests Limit/min'     => $headers['x-ratelimit-limit-requests'][0]     ?? null,
                        'Requests Remaining/min' => $headers['x-ratelimit-remaining-requests'][0] ?? null,
                        'Tokens Limit/min'       => $headers['x-ratelimit-limit-tokens'][0]       ?? null,
                        'Tokens Remaining/min'   => $headers['x-ratelimit-remaining-tokens'][0]   ?? null,
                        'Reset Requests'         => $headers['x-ratelimit-reset-requests'][0]     ?? null,
                        'Reset Tokens'           => $headers['x-ratelimit-reset-tokens'][0]       ?? null,
                        'Organization'           => $headers['openai-organization'][0]            ?? null,
                    ], $response->json());
                    break;

                // ── Anthropic Claude ─────────────────────────────────────────
                case 'claude':
                    $model    = request()->input('model') ?: $this->pickModel($key, 'claude-haiku-4-5-20251001');
                    $response = Http::timeout(15)
                        ->withHeaders([
                            'x-api-key'         => $key->api_key,
                            'anthropic-version' => '2023-06-01',
                            'Content-Type'      => 'application/json',
                        ])
                        ->post('https://api.anthropic.com/v1/messages', [
                            'model'      => $model,
                            'max_tokens' => 1,
                            'messages'   => [['role' => 'user', 'content' => 'hi']],
                        ]);

                    $latency = round((microtime(true) - $startTime) * 1000);
                    $headers = $response->headers();
                    $status  = $response->status();

                    $result = $this->buildResult($status, $latency, [
                        'Model diuji'            => $model,
                        'Requests Limit/min'     => $headers['anthropic-ratelimit-requests-limit'][0]     ?? null,
                        'Requests Remaining/min' => $headers['anthropic-ratelimit-requests-remaining'][0] ?? null,
                        'Tokens Limit/min'       => $headers['anthropic-ratelimit-tokens-limit'][0]       ?? null,
                        'Tokens Remaining/min'   => $headers['anthropic-ratelimit-tokens-remaining'][0]   ?? null,
                        'Reset Requests'         => $headers['anthropic-ratelimit-requests-reset'][0]     ?? null,
                        'Reset Tokens'           => $headers['anthropic-ratelimit-tokens-reset'][0]       ?? null,
                        'Request ID'             => $headers['request-id'][0]                             ?? null,
                    ], $response->json());
                    break;

                // ── Google Gemini ─────────────────────────────────────────────
                case 'gemini':
                    // Prioritas model: 1) request param, 2) DB aktif, 3) auto-detect dari API
                    $requestedModel = request()->input('model');

                    if ($requestedModel) {
                        $model = $requestedModel;
                    } else {
                        $model = $this->pickModel($key, '');
                    }

                    // Kalau model kosong atau perlu verifikasi, ambil list dari Gemini API
                    if (empty($model)) {
                        $listResp   = Http::timeout(10)->get(
                            'https://generativelanguage.googleapis.com/v1beta/models?key=' . $key->api_key
                        );
                        $allModels  = collect($listResp->json('models') ?? []);
                        // Filter hanya yang support generateContent
                        $supported  = $allModels
                            ->filter(fn($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? []))
                            ->pluck('name') // format: "models/gemini-2.0-flash"
                            ->map(fn($n) => str_replace('models/', '', $n))
                            ->values();

                        // Prefer flash (lebih murah & cepat untuk ping)
                        $model = $supported->first(fn($n) => str_contains($n, 'flash'))
                              ?? $supported->first()
                              ?? 'gemini-2.0-flash';
                    }

                    $response = Http::timeout(15)
                        ->withBody(json_encode([
                            'contents'         => [['parts' => [['text' => 'hi']], 'role' => 'user']],
                            'generationConfig' => ['maxOutputTokens' => 1],
                        ]), 'application/json')
                        ->post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $key->api_key);

                    $latency = round((microtime(true) - $startTime) * 1000);
                    $status  = $response->status();
                    $body    = $response->json();

                    $bodyLower    = strtolower(json_encode($body));
                    $isQuotaError = str_contains($bodyLower, 'quota') ||
                                   str_contains($bodyLower, 'resource_exhausted') ||
                                   str_contains($bodyLower, 'rate_limit');

                    $info = [
                        'Model diuji'   => $model,
                        'Quota Status'  => $isQuotaError ? '⚠️ Quota/Rate limit terdeteksi' : '✅ Normal',
                        'Note'          => 'Gemini tidak menyediakan sisa kuota via API — pantau di Google AI Studio',
                        'AI Studio URL' => 'https://aistudio.google.com/',
                    ];

                    if ($isQuotaError && $status !== 429) {
                        $status = 429;
                    }

                    $result = $this->buildResult($status, $latency, $info, $body);
                    break;

                // ── Mistral ───────────────────────────────────────────────────
                case 'mistral':
                    $model    = request()->input('model') ?: $this->pickModel($key, 'mistral-small-latest');
                    $response = Http::timeout(15)
                        ->withToken($key->api_key)
                        ->post('https://api.mistral.ai/v1/chat/completions', [
                            'model'      => $model,
                            'max_tokens' => 1,
                            'messages'   => [['role' => 'user', 'content' => 'hi']],
                        ]);

                    $latency = round((microtime(true) - $startTime) * 1000);
                    $headers = $response->headers();
                    $status  = $response->status();

                    $result = $this->buildResult($status, $latency, [
                        'Model diuji'           => $model,
                        'X-RateLimit-Limit'     => $headers['x-ratelimit-limit'][0]     ?? null,
                        'X-RateLimit-Remaining' => $headers['x-ratelimit-remaining'][0] ?? null,
                        'X-RateLimit-Reset'     => $headers['x-ratelimit-reset'][0]     ?? null,
                        'Retry-After'           => $headers['retry-after'][0]           ?? null,
                    ], $response->json());
                    break;

                // ── Groq ─────────────────────────────────────────────────────
                case 'groq':
                    $groqBase = str_contains($baseUrl, 'groq') ? $baseUrl : 'https://api.groq.com/openai/v1';
                    $model    = request()->input('model') ?: $this->pickModel($key, 'llama3-8b-8192');
                    $response = Http::timeout(15)
                        ->withToken($key->api_key)
                        ->post($groqBase . '/chat/completions', [
                            'model'      => $model,
                            'max_tokens' => 1,
                            'messages'   => [['role' => 'user', 'content' => 'hi']],
                        ]);

                    $latency = round((microtime(true) - $startTime) * 1000);
                    $headers = $response->headers();
                    $status  = $response->status();

                    $result = $this->buildResult($status, $latency, [
                        'Model diuji'            => $model,
                        'Requests Limit/day'     => $headers['x-ratelimit-limit-requests'][0]              ?? null,
                        'Requests Remaining/day' => $headers['x-ratelimit-remaining-requests'][0]          ?? null,
                        'Tokens Limit/min'        => $headers['x-ratelimit-limit-tokens'][0]               ?? null,
                        'Tokens Remaining/min'    => $headers['x-ratelimit-remaining-tokens'][0]           ?? null,
                        'Tokens Limit/day'        => $headers['x-ratelimit-limit-tokens-per-day'][0]       ?? null,
                        'Tokens Remaining/day'    => $headers['x-ratelimit-remaining-tokens-per-day'][0]   ?? null,
                        'Reset Requests'          => $headers['x-ratelimit-reset-requests'][0]             ?? null,
                        'Reset Tokens'            => $headers['x-ratelimit-reset-tokens'][0]               ?? null,
                    ], $response->json());
                    break;

                // ── OpenRouter ────────────────────────────────────────────────
                case 'openrouter':
                    // OpenRouter punya /api/v1/auth/key endpoint khusus untuk info key
                    $orBase = str_contains($baseUrl, 'openrouter') ? 'https://openrouter.ai' : 'https://openrouter.ai';
                    $infoResp = Http::timeout(15)
                        ->withToken($key->api_key)
                        ->get($orBase . '/api/v1/auth/key');

                    $latency = round((microtime(true) - $startTime) * 1000);
                    $status  = $infoResp->status();
                    $body    = $infoResp->json();
                    $data    = $body['data'] ?? [];

                    $result = $this->buildResult($status, $latency, [
                        'Label'          => $data['label']            ?? null,
                        'Usage (tokens)' => isset($data['usage'])     ? number_format($data['usage']) : null,
                        'Limit (tokens)' => isset($data['limit'])     ? ($data['limit'] === null ? 'Unlimited' : number_format($data['limit'])) : null,
                        'Is Free Tier'   => isset($data['is_free_tier']) ? ($data['is_free_tier'] ? 'Yes (free)' : 'No (paid)') : null,
                        'Rate Limit RPD' => isset($data['rate_limit']['requests'])  ? $data['rate_limit']['requests']  : null,
                        'Rate Limit Int' => isset($data['rate_limit']['interval'])  ? $data['rate_limit']['interval']  : null,
                    ], $body);
                    break;

                // ── DeepSeek ──────────────────────────────────────────────────
                case 'deepseek':
                    // DeepSeek punya /user/balance endpoint
                    $dsBase = str_contains($baseUrl, 'deepseek') ? 'https://api.deepseek.com' : 'https://api.deepseek.com';
                    $balResp = Http::timeout(15)
                        ->withToken($key->api_key)
                        ->get($dsBase . '/user/balance');

                    $latency = round((microtime(true) - $startTime) * 1000);
                    $status  = $balResp->status();
                    $body    = $balResp->json();
                    $bal     = $body['balance_infos'][0] ?? [];

                    $result = $this->buildResult($status, $latency, [
                        'Currency'       => $bal['currency']        ?? null,
                        'Total Balance'  => isset($bal['total_balance'])    ? $bal['total_balance']    : null,
                        'Granted Balance'=> isset($bal['granted_balance'])  ? $bal['granted_balance']  : null,
                        'Topped Up'      => isset($bal['topped_up_balance'])? $bal['topped_up_balance']: null,
                    ], $body);
                    break;

                // ── Generic / Custom Provider ─────────────────────────────
                default:
                    if (empty($baseUrl)) {
                        return response()->json([
                            'status'      => 'error',
                            'message'     => '❌ Base URL belum dikonfigurasi untuk provider ini.',
                            'latency_ms'  => 0,
                            'info'        => ['Tip' => 'Tambahkan Base URL di halaman provider, contoh: https://api.example.com/v1'],
                            'error_detail'=> null,
                        ]);
                    }

                    // Ambil semua model aktif dari DB untuk provider ini
                    $activeModels = $key->provider
                        ->models()
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->pluck('model_name')
                        ->toArray();

                    // Jika tidak ada model di DB, coba deteksi dari /models endpoint dulu
                    if (empty($activeModels)) {
                        $modelsResp = Http::timeout(10)
                            ->withToken($key->api_key)
                            ->get($baseUrl . '/models');

                        if ($modelsResp->successful()) {
                            $modelsBody = $modelsResp->json();
                            // Format OpenAI: {data: [{id: ...}]}
                            // Format lain: [{id: ...}] atau [{name: ...}]
                            $modelsList = $modelsBody['data'] ?? $modelsBody['models'] ?? $modelsBody;
                            if (is_array($modelsList) && count($modelsList) > 0) {
                                $firstModel = $modelsList[0];
                                $detectedModel = $firstModel['id'] ?? $firstModel['name'] ?? $firstModel['model'] ?? null;
                                if ($detectedModel) {
                                    $activeModels = [$detectedModel];
                                }
                            }
                        }
                    }

                    // Fallback jika masih kosong
                    if (empty($activeModels)) {
                        $activeModels = ['gpt-3.5-turbo'];
                    }

                    // Coba tiap model sampai ada yang berhasil (200)
                    $response      = null;
                    $usedModel     = null;
                    $triedModels   = [];

                    foreach ($activeModels as $tryModel) {
                        $triedModels[] = $tryModel;
                        $tryResp = Http::timeout(15)
                            ->withToken($key->api_key)
                            ->post($baseUrl . '/chat/completions', [
                                'model'      => $tryModel,
                                'max_tokens' => 1,
                                'messages'   => [['role' => 'user', 'content' => 'hi']],
                            ]);

                        if ($tryResp->status() !== 404) {
                            // Bukan 404 = model ditemukan (bisa 200, 429, 401, dll)
                            $response  = $tryResp;
                            $usedModel = $tryModel;
                            break;
                        }
                        // 404 = model tidak ada di provider ini, coba model berikutnya
                    }

                    // Kalau semua 404, pakai response terakhir
                    if ($response === null) {
                        $response  = $tryResp ?? null;
                        $usedModel = end($triedModels);
                    }

                    $latency = round((microtime(true) - $startTime) * 1000);
                    $headers = $response->headers();
                    $status  = $response->status();

                    // Kumpulkan semua header rate-limit yang ada
                    $rateLimitHeaders = [];
                    foreach ($headers as $name => $vals) {
                        $nameLower = strtolower($name);
                        if (str_starts_with($nameLower, 'x-ratelimit') ||
                            str_starts_with($nameLower, 'ratelimit') ||
                            str_starts_with($nameLower, 'retry-after')) {
                            $rateLimitHeaders[$name] = $vals[0] ?? null;
                        }
                    }

                    $infoCustom = array_merge(
                        [
                            'Model diuji' => $usedModel,
                            'Model dicoba'=> count($triedModels) > 1 ? implode(', ', $triedModels) : null,
                        ],
                        $rateLimitHeaders ?: ['Rate Limit Info' => 'Provider tidak mengembalikan header rate-limit']
                    );

                    $result = $this->buildResult($status, $latency, $infoCustom, $response->json());
                    break;
            }

            // Auto-update limit_reached di DB berdasarkan hasil check
            if (in_array($result['status'], ['ok', 'warning'])) {
                // Kalau sebelumnya di-flag limit_reached tapi sekarang OK → reset otomatis
                if ($key->limit_reached) {
                    $key->update(['limit_reached' => false]);
                    $result['auto_reset'] = true;
                }
            } elseif ($result['status'] === 'rate_limited') {
                if (!$key->limit_reached) {
                    $key->update(['limit_reached' => true]);
                    $result['auto_flagged'] = true;
                }
            }

            $key->update(['last_used_at' => now()]);

            Log::info("[HealthCheck] key_id={$key->id} provider={$providerCode} status={$result['status']} latency={$result['latency_ms']}ms");

            return response()->json($result);

        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $startTime) * 1000);
            Log::error("[HealthCheck] key_id={$key->id} error: " . $e->getMessage());
            return response()->json([
                'status'     => 'error',
                'message'    => 'Koneksi gagal: ' . $e->getMessage(),
                'latency_ms' => $latency,
                'info'       => [],
            ]);
        }
    }

    /**
     * Bangun array hasil health check yang konsisten dari HTTP response.
     */
    private function buildResult(int $httpStatus, int $latencyMs, array $info, ?array $body = null): array
    {
        // Filter null values dari info
        $info = array_filter($info, fn($v) => $v !== null && $v !== '');

        // Tentukan status berdasarkan HTTP code
        if ($httpStatus === 200 || $httpStatus === 201) {
            $status  = 'ok';
            $message = '✅ API Key valid dan berfungsi normal';
        } elseif ($httpStatus === 429) {
            $status  = 'rate_limited';
            $message = '⚠️ Rate limit / quota habis';
        } elseif ($httpStatus === 401) {
            $status  = 'invalid';
            $message = '❌ API Key tidak valid atau sudah expired';
        } elseif ($httpStatus === 403) {
            $status  = 'forbidden';
            $message = '❌ Akses ditolak — periksa permission atau billing';
        } elseif ($httpStatus === 400) {
            // Untuk beberapa provider, 400 bisa berarti kredit habis
            $bodyStr = strtolower(json_encode($body));
            if (str_contains($bodyStr, 'credit') || str_contains($bodyStr, 'billing') || str_contains($bodyStr, 'quota')) {
                $status  = 'rate_limited';
                $message = '⚠️ Kredit habis atau billing bermasalah';
            } else {
                $status  = 'warning';
                $message = '⚠️ Respon tidak terduga (HTTP 400) — key mungkin valid';
            }
        } elseif ($httpStatus >= 500) {
            $status  = 'server_error';
            $message = '⚠️ Server provider sedang bermasalah (HTTP ' . $httpStatus . ')';
        } else {
            $status  = 'warning';
            $message = '⚠️ HTTP ' . $httpStatus . ' — tidak dikenali';
        }

        // Cek error message dari body untuk override status
        $errorMsg = $body['error']['message'] ?? $body['message'] ?? null;

        return [
            'status'      => $status,
            'message'     => $message,
            'http_status' => $httpStatus,
            'latency_ms'  => $latencyMs,
            'info'        => $info,
            'error_detail'=> $errorMsg,
        ];
    }
}
