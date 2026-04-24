<?php

namespace App\Helpers;

class LanguageDetector
{
    /**
     * Common Indonesian words and phrases
     */
    private array $indonesianKeywords = [
        // Question words
        'apa', 'siapa', 'kapan', 'di mana', 'kemana', 'darimana', 'mengapa', 'kenapa', 'bagaimana',
        // Common verbs
        'adalah', 'merupakan', 'termasuk', 'yaitu', 'yakni', 'dapat', 'bisa', 'mampu', 'harus', 'wajib',
        'ingin', 'mau', 'akan', 'sedang', 'telah', 'sudah', 'pernah', 'belum',
        // Common nouns
        'saya', 'aku', 'kita', 'kami', 'anda', 'kamu', 'dia', 'ia', 'mereka', 'beliau',
        'ini', 'itu', 'ini', 'tersebut',
        // Greetings and polite words
        'halo', 'hai', 'selamat', 'terima kasih', 'makasih', 'tolong', 'maaf', 'permisi',
        'pagi', 'siang', 'sore', 'malam',
        // Common adjectives
        'baik', 'bagus', 'besar', 'kecil', 'baru', 'lama', 'tinggi', 'rendah', 'banyak', 'sedikit',
        // Business/ERP specific
        'laporan', 'produk', 'penjualan', 'pembelian', 'pelanggan', 'pembeli', 'transaksi',
        'pendapatan', 'omzet', 'keuntungan', 'stok', 'gudang', 'kategori',
        'wilayah', 'provinsi', 'kota', 'daerah', 'cabang', 'toko', 'tampilkan', 'lihat', 'cari',
        'jumlah', 'rata-rata', 'persen', 'persentase', 'grafik', 'tren',
        'terlaris', 'terbaik', 'tertinggi', 'terendah', 'paling', 'sangat', 'sekali',
        // Additional Indonesian business/domain terms
        'baterai', 'dealer', 'motor', 'bengkel', 'servis', 'biaya', 'harga', 'diskon',
        'rekap', 'ringkasan', 'periode', 'berdasarkan', 'tampilan', 'berapa',
        'semua', 'setiap', 'seluruh', 'nomor',
        'terbesar', 'terkecil', 'terbanyak',
        'bandingkan', 'perbandingan', 'naik', 'turun', 'meningkat', 'menurun',
        'januari', 'februari', 'maret', 'april', 'mei', 'juni',
        'juli', 'agustus', 'september', 'oktober', 'november', 'desember',
        // Time expressions
        'hari', 'minggu', 'bulan', 'tahun', 'kemarin', 'besok', 'sekarang', 'saat ini', 'sekarang',
        'lalu', 'depan', 'lalu', 'ini',
        // Connectors
        'dan', 'atau', 'tetapi', 'namun', 'sedangkan', 'melainkan', 'serta', 'bahwa', 'karena',
        'sehingga', 'jika', 'kalau', 'apabila', 'meskipun', 'walaupun', 'untuk', 'dengan', 'tanpa',
        'pada', 'di', 'ke', 'dari', 'dalam', 'luar', 'atas', 'bawah', 'sebelum', 'sesudah', 'setelah',
        // Affirmations
        'ya', 'iya', 'betul', 'benar', 'tidak', 'bukan', 'belum', 'jangan', 'no', 'ok', 'oke', 'siap',
    ];

    /**
     * Common English words and phrases
     */
    private array $englishKeywords = [
        // Question words
        'what', 'who', 'when', 'where', 'why', 'how', 'which', 'whose', 'whom',
        // Common verbs
        'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does',
        'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can',
        'want', 'need', 'like', 'use', 'get', 'got', 'make', 'made', 'know', 'knew',
        'think', 'see', 'saw', 'look', 'find', 'found', 'give', 'gave', 'tell', 'told',
        // Common pronouns
        'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
        'my', 'your', 'his', 'its', 'our', 'their', 'mine', 'yours', 'hers', 'ours', 'theirs',
        'this', 'that', 'these', 'those', 'here', 'there',
        // Greetings and polite words
        'hello', 'hi', 'good', 'morning', 'afternoon', 'evening', 'night', 'thanks', 'thank you',
        'please', 'sorry', 'excuse', 'welcome', 'bye', 'goodbye',
        // Common adjectives
        'good', 'bad', 'big', 'small', 'new', 'old', 'high', 'low', 'many', 'much', 'little', 'few',
        'great', 'important', 'different', 'same', 'other', 'another', 'some', 'any', 'all', 'each',
        'every', 'both', 'either', 'neither', 'enough', 'possible', 'available', 'necessary',
        // Business/ERP specific
        'report', 'product', 'sales', 'purchase', 'customer', 'transaction', 'revenue',
        'income', 'profit', 'stock', 'inventory', 'warehouse', 'category', 'region', 'province',
        'city', 'area', 'branch', 'store', 'shop', 'show', 'display', 'search', 'find', 'total',
        'amount', 'sum', 'average', 'percent', 'percentage', 'graph', 'chart', 'trend', 'trend',
        'bestseller', 'best', 'seller', 'top', 'highest', 'lowest', 'most', 'very', 'really',
        // Time expressions
        'day', 'week', 'month', 'year', 'yesterday', 'tomorrow', 'today', 'now', 'current', 'present',
        'past', 'last', 'next', 'future', 'before', 'after', 'ago', 'later', 'since', 'until',
        // Connectors
        'and', 'or', 'but', 'however', 'yet', 'so', 'because', 'although', 'though', 'while',
        'if', 'then', 'than', 'for', 'with', 'without', 'on', 'in', 'at', 'to', 'from', 'by',
        'of', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below',
        // Affirmations
        'yes', 'no', 'not', 'never', 'okay', 'ok', 'sure', 'right', 'correct', 'wrong', 'true', 'false',
    ];

    /**
     * Detect the language of a given text
     * Returns 'id' for Indonesian, 'en' for English
     */
    public function detect(string $text): string
    {
        $text = mb_strtolower(trim($text));
        
        // Remove punctuation and special characters
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($words)) {
            return 'id'; // Default to Indonesian
        }
        
        $indonesianScore = 0;
        $englishScore = 0;
        
        // Score based on keyword matching
        // Indonesian keywords have higher weight (2 pts) since this app is primarily Indonesian-facing
        foreach ($words as $word) {
            if (in_array($word, $this->indonesianKeywords)) {
                $indonesianScore += 2;
            }
            if (in_array($word, $this->englishKeywords)) {
                $englishScore += 1;
            }
        }
        
        // Check for common Indonesian patterns
        // Pattern dibuat lebih spesifik agar tidak false-positive ke kata-kata pendek / bahasa lain
        $indonesianPatterns = [
            '/\bdi\s+\w+/u',              // di mana, di sini, di cabang, etc.
            '/\bke\s+\w+/u',              // ke sana, ke cabang, etc.
            '/\bdari\s+\w+/u',            // dari mana, dari data, etc.
            '/\bter[a-z]{3,}\b/u',        // terlaris, tertinggi, terbaik (min 3 char setelah 'ter')
            '/\bber[a-z]{3,}\b/u',        // berapa, berikut, berbeda (min 3 char setelah 'ber')
            '/\bme[nm][a-z]{3,}\b/u',     // menampilkan, memberikan, mencari
            '/\bpe[mn][a-z]{3,}\b/u',     // penjualan, pembelian, pencarian
            '/\b\w{4,}kan\b/u',           // tampilkan, berikan, sajikan (min 4 char sebelum 'kan')
            '/\b\w{4,}lah\b/u',           // apakah, buatlah, lihatkan (min 4 char)
            '/\b\w{3,}nya\b/u',           // datanya, produknya, cabangnya
            '/\b\w{4,}an\b/u',            // penjualan, pembelian, laporan (min 4 char → hindari 'man','ran',dll)
        ];
        
        foreach ($indonesianPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $indonesianScore += 2;
            }
        }
        
        // Check for common English patterns
        // PENTING: Pattern dibuat lebih spesifik agar tidak false-positive ke kata Indonesia.
        // Contoh false-positive lama: "paling" cocok /\w+ing/, "laris" cocok /\w+s/
        $englishPatterns = [
            '/\bthe\s+[a-z]{3,}\b/u',              // "the product", "the sales" (min 3 char)
            '/\ba\s+[a-z]{4,}\b/u',                // "a report", "a branch" (min 4 char hindari: "a di")
            '/\ban\s+[a-z]{4,}\b/u',               // "an order", "an item" (min 4 char)
            '/\b[a-z]{5,}ing\b/u',                 // running, showing, filtering (min 8 char total)
            '/\b[a-z]{5,}ed\b/u',                  // created, updated, filtered (min 7 char total)
            '/\b[a-z]{5,}ly\b/u',                  // quickly, actually, currently (min 7 char total)
            '/\b(show|display|get|list|fetch|compare|filter)\s+[a-z]{3,}\b/u', // English imperative verbs
        ];
        
        foreach ($englishPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $englishScore += 2;
            }
        }
        
        // Determine language based on scores
        if ($indonesianScore > $englishScore) {
            return 'id';
        } elseif ($englishScore > $indonesianScore) {
            return 'en';
        }
        
        // If scores are equal, check for specific indicators
        // Indonesian: typically has "di", "ke", "dari", "yang", "apa", "berapa", "tidak"
        if (preg_match('/\b(yang|di|ke|dari|apa|siapa|bagaimana|berapa|tidak|bisa|untuk|dengan|pada)\b/u', $text)) {
            return 'id';
        }
        
        // English: typically has "the", "is", "are", "what", "how"
        if (preg_match('/\b(the|is|are|what|how|where|when)\b/u', $text)) {
            return 'en';
        }
        
        // Default to Indonesian for ambiguous cases
        return 'id';
    }
    
    /**
     * Get language name from code
     */
    public function getLanguageName(string $code): string
    {
        return $code === 'en' ? 'English' : 'Bahasa Indonesia';
    }
    
    /**
     * Detect language and return full info
     */
    public function detectWithInfo(string $text): array
    {
        $code = $this->detect($text);
        return [
            'code' => $code,
            'name' => $this->getLanguageName($code),
            'confidence' => $this->calculateConfidence($text, $code),
        ];
    }
    
    /**
     * Calculate confidence score (0-100)
     */
    private function calculateConfidence(string $text, string $detectedLanguage): int
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($words)) {
            return 50;
        }
        
        $keywordList = $detectedLanguage === 'id' ? $this->indonesianKeywords : $this->englishKeywords;
        $matchCount = 0;
        
        foreach ($words as $word) {
            if (in_array($word, $keywordList)) {
                $matchCount++;
            }
        }
        
        // Calculate confidence based on match ratio
        $ratio = $matchCount / count($words);
        $confidence = min(100, (int) ($ratio * 200));
        
        return max(50, $confidence);
    }
}
