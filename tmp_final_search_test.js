import fs from 'fs';
const d = JSON.parse(fs.readFileSync('d:/MCP Versi Web/mcp-postgresql/config/erp_guidance.json', 'utf8'));
const guides = d.guides;

function search(keyword) {
    const keywordLower = keyword.toLowerCase().trim();
    const results = [];

    for (const guide of guides) {
        let score = 0;
        const gTitle = (guide.title || '').toLowerCase();
        const gDetail = (guide.detail_panduan_lengkap || '').toLowerCase();
        const gKeys = (guide.keywords || []).map(k => k.toLowerCase());

        if (keywordLower) {
            // Tier 1: Title
            if (gTitle.includes(keywordLower)) {
                score += 300;
                if (gTitle.indexOf(keywordLower) === 0) score += 200;
                if (gTitle === keywordLower) score += 500;
            }

            // Tier 2: Keywords
            for (const key of gKeys) {
                if (key.includes(keywordLower)) {
                    score += 50;
                    if (key === keywordLower) score += 150;
                    break;
                }
            }

            // Tier 3: Detail
            if (gDetail.includes(keywordLower)) {
                score += 1;
            }
        }

        if (score > 0) {
            results.push({ ...guide, _score: score });
        }
    }

    results.sort((a, b) => b._score - a._score || guides.indexOf(a) - guides.indexOf(b));
    
    // Noise suppression logic
    if (results.length > 0 && results[0]._score >= 500) {
        return results.filter(r => r._score >= 10);
    }
    return results;
}

const tests = [
    'pembayaran tagihan hutang',
    'terima pembayaran piutang'
];

tests.forEach(query => {
    console.log(`\n--- Test for query: "${query}" ---`);
    const results = search(query);
    results.slice(0, 3).forEach((r, i) => {
        console.log(`${i+1}. [Score: ${r._score}] ${r.title} (${r.category})`);
    });
    
    if (results.length > 0 && results[0].title.toLowerCase().includes('pembayaran') && !results[0].title.toLowerCase().includes('penagihan') && !results[0].title.toLowerCase().includes('tagihan hutang')) {
        // Wait, for 'pembayaran tagihan hutang', the title IS 'Pembayaran Tagihan Hutang'.
        // So checking if it contains 'pembayaran' is good.
    }
});
