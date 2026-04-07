import fs from 'fs';
const data = JSON.parse(fs.readFileSync('config/erp_guidance.json', 'utf8'));
data.guides.forEach(g => {
  if (g.title.toLowerCase().includes('piutang') || g.keywords.some(k => k.toLowerCase().includes('piutang'))) {
    console.log('- ' + g.title);
    console.log('  Keywords: ' + g.keywords.join(', '));
  }
});
