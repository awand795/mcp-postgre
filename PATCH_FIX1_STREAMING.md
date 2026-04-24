# Fix #1 — Streaming Heuristik (Hemat ~12 detik per query)

## Problem
`$useStreaming = !empty($allTurnToolResults)` terlalu agresif:
- Aktif dari Loop #2, padahal model masih mau tool_call sampai Loop #4
- Setiap loop intermediate jadi double API call: streaming → empty → fallback non-streaming
- Waste ~2-4 detik **per loop** (di log: Loop #2, #3, #4 = 3 loop × ~4 detik = ~12 detik buang)

## File yang diubah
`app/Http/Controllers/AgenticChatbotController.php`

---

## LANGKAH 1 — Tambahkan variabel tracking setelah `$allTurnToolResults = [];`

Cari baris:
```php
        $allTurnToolResults = [];

        while ($loopCount < $this->maxToolLoops) {
```

Ganti dengan:
```php
        $allTurnToolResults = [];

        // ── Fix #1: Track tool terakhir yang dieksekusi ──────────────────────
        // Digunakan untuk heuristik streaming: hanya stream jika tool terakhir
        // adalah "terminal tool" (execute_query / get_erp_guidance) yang hampir
        // pasti menghasilkan final answer, bukan tool intermediate seperti
        // get_database_schema_info / search_schema / describe_table.
        $lastExecutedToolName = null;

        // Tool-tool yang hampir pasti menghasilkan final answer setelah dieksekusi.
        $terminalTools = [
            'execute_query',
            'get_erp_guidance',
            'get_erp_menu_navigation',
            'fetch_erp_guidance_from_web',
        ];

        while ($loopCount < $this->maxToolLoops) {
```

---

## LANGKAH 2 — Ganti logika $useStreaming

Cari baris (kurang lebih sekitar baris 200):
```php
            $useStreaming = !empty($allTurnToolResults);
```

Ganti dengan:
```php
            $useStreaming = !empty($allTurnToolResults)
                && $lastExecutedToolName !== null
                && in_array($lastExecutedToolName, $terminalTools, true);
```

---

## LANGKAH 3 — Update $lastExecutedToolName setelah tool dieksekusi

Di dalam blok `foreach ($executedResults as $execItem)`, setelah baris:
```php
                $allTurnToolResults[] = $frontendResult;
```

Tambahkan baris:
```php
                $lastExecutedToolName = $toolName; // Fix #1: track tool terakhir
```

---

## Cara verifikasi setelah perubahan

Jalankan query yang sama ("daftar cabang") dan perhatikan log:
- **Sebelum fix**: WARNING "Streaming returned empty, falling back..." muncul di Loop #2, #3, #4
- **Setelah fix**: WARNING tersebut tidak muncul sama sekali — hanya Loop #5 yang pakai streaming
- Total waktu harus berkurang ~12 detik

## Estimasi dampak
| Sebelum | Sesudah |
|---|---|
| Loop #2: 4 detik sia-sia | Loop #2: ~2 detik (non-streaming, langsung tool call) |
| Loop #3: 4 detik sia-sia | Loop #3: ~2 detik (non-streaming, langsung tool call) |
| Loop #4: 4 detik sia-sia | Loop #4: ~2 detik (non-streaming, langsung tool call) |
| Loop #5: 42 detik (streaming final) | Loop #5: 42 detik (streaming final) |
| **Total: ~54 detik** | **Total: ~42 detik** |

Hemat ~12 detik tanpa mengubah kualitas output apapun.
