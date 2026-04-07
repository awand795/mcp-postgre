import fs from 'fs';
const data = JSON.parse(fs.readFileSync('d:\\MCP Versi Web\\mcp-postgresql\\config\\erp_guidance.json', 'utf8'));
data.guides.forEach((g, i) => {
  if (g.title.toLowerCase().includes('hutang') || g.title.toLowerCase().includes('pelunasan')) {
    console.log(`${i+1}. Title: ${g.title} | Category: ${g.category}`);
    console.log(`   Keywords: ${g.keywords.join(', ')}`);
  }
});
