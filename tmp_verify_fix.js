import fs from 'fs';
const d = JSON.parse(fs.readFileSync('d:/MCP Versi Web/mcp-postgresql/config/erp_guidance.json', 'utf8'));
const guides = d.guides;

function search(keyword, category = '') {
    const keywordLower = keyword.toLowerCase().trim();
    const categoryLower = category.toLowerCase().trim();
    const results = [];

    for (const guide of guides) {
        let score = 0;
        const gTitle = (guide.title || '').toLowerCase();
        const gDetail = (guide.detail_panduan_lengkap || '').toLowerCase();
        const gKeys = (guide.keywords || []).map(k => k.toLowerCase());

        if (categoryLower) {
            const gCat = (guide.category || '').toLowerCase();
            if (!gCat.includes(categoryLower)) continue;
        }

        if (keywordLower) {
            // Tier 1: Title
            if (gTitle.includes(keywordLower)) {
                score += 100;
                if (gTitle === keywordLower) score += 50;
                if (gTitle.indexOf(keywordLower) === 0) score += 20;
            }

            // Tier 2: Keywords
            for (const key of gKeys) {
                if (key.includes(keywordLower)) {
                    score += 50;
                    if (key === keywordLower) score += 20;
                    break;
                }
            }

            // Tier 3: Detail
            if (gDetail.includes(keywordLower)) {
                score += 10;
            }
        } else {
            score = 1;
        }

        if (score > 0) {
            results.push({ ...guide, _score: score });
        }
    }

    results.sort((a, b) => b._score - a._score || guides.indexOf(a) - guides.indexOf(b));
    return results;
}

const query = 'terima pembayaran piutang';
const results = search(query);
console.log('Search Results for:', query);
results.slice(0, 5).forEach((r, i) => {
    console.log(`${i+1}. [Score: ${r._score}] ${r.title} (${r.category})`);
});
