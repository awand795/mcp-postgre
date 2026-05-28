<?php

namespace App\Services\Web;

use App\Services\BaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WebSearchService
 *
 * Handles general web search queries using the SearXNG instance on port 5100.
 */
class WebSearchService extends BaseService
{
    private string $baseUrl;

    public function __construct()
    {
        // Get SearXNG URL from env, default to local port 5100
        $this->baseUrl = rtrim(env('SEARXNG_URL', 'http://127.0.0.1:5100'), '/');
    }

    /**
     * Perform a web search via SearXNG.
     *
     * @param string $query
     * @return string JSON response
     */
    public function search(string $query): string
    {
        if (empty(trim($query))) {
            return $this->errorResponse('Kata kunci pencarian tidak boleh kosong.');
        }

        try {
            Log::info("[WebSearchService] Searching for query: '{$query}' on {$this->baseUrl}");

            $response = Http::timeout(15)
                ->get("{$this->baseUrl}/search", [
                    'q' => $query,
                    'format' => 'json',
                ]);

            if (!$response->successful()) {
                Log::error("[WebSearchService] Search failed. Status: " . $response->status());
                return $this->errorResponse("Gagal melakukan pencarian web. Status: " . $response->status());
            }

            $data = $response->json();
            $results = $data['results'] ?? [];

            // Extract and clean up the top 6 results to avoid context overflow
            $formattedResults = [];
            $limit = min(count($results), 6);

            for ($i = 0; $i < $limit; $i++) {
                $item = $results[$i];
                $formattedResults[] = [
                    'title' => $item['title'] ?? 'No Title',
                    'url' => $item['url'] ?? '',
                    'content' => $item['content'] ?? '',
                ];
            }

            if (empty($formattedResults)) {
                return $this->safeJsonEncode([
                    'query' => $query,
                    'total_found' => 0,
                    'results' => [],
                    'message' => 'Tidak ditemukan hasil pencarian yang relevan untuk kata kunci tersebut.'
                ]);
            }

            return $this->safeJsonEncode([
                'query' => $query,
                'total_found' => count($formattedResults),
                'results' => $formattedResults,
                'instruction' => 'Gunakan informasi dari hasil pencarian web di atas untuk menjawab pertanyaan pengguna dengan gaya bahasa bisnis yang profesional. Cantumkan rujukan URL sumber jika relevan.'
            ]);

        } catch (\Throwable $e) {
            Log::error("[WebSearchService] Web search failed: " . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat menghubungi layanan pencarian web.');
        }
    }
}
