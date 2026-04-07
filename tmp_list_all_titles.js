import fs from 'fs';
const data = JSON.parse(fs.readFileSync('d:\\MCP Versi Web\\mcp-postgresql\\config\\erp_guidance.json', 'utf8'));
data.guides.forEach((g, i) => {
  console.log(`${i+1}. ${g.title} (${g.category})`);
});
