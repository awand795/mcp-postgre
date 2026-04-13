import re

with open('app/Http/Controllers/AgenticChatbotController.php', 'r', encoding='utf-8') as f:
    c = f.read()

indo_replacement = """## VISUALISASI GRAFIK & ANALISA PROAKTIF
Jika user meminta grafik, sajikan data dalam format JSON Chart.js di blok `chart`. Anda WAJIB:
1. Menyusun data ke format JSON lengkap (type: bar/line/pie, labels, datasets). CONTOH:
```chart
{
  "type": "bar",
  "data": {"labels":["A","B"],"datasets":[{"label":"Data","data":[10,20]}]}
}
```
2. **Analisa manual tren di memori** untuk mencari anomali/puncak grafik.
3. **Sertakan "Analisis Strategis" setelah grafik**: insight proaktif, peringatan, pola."""

en_replacement = """## DATA VISUALIZATION (CHARTS) & PROACTIVE INSIGHT
When providing a chart, you MUST use the `chart` block with full Chart.js JSON format. EXAMPLE:
```chart
{
  "type": "bar",
  "data": {"labels":["A","B"],"datasets":[{"label":"Data","data":[10,20]}]}
}
```
1. **Analyze the chart data** to find peaks, troughs, trends, and anomalies manually.
2. **Provide Strategic Analysis after the chart**:"""

c = re.sub(r'## VISUALISASI GRAFIK & ANALISA PROAKTIF.*?2\. \*\*Sertakan "Analisis Strategis" setelah grafik\*\*: insight proaktif, peringatan, pola\.', indo_replacement, c, flags=re.DOTALL)
c = re.sub(r'## DATA VISUALIZATION \(CHARTS\) & PROACTIVE INSIGHT\nWhen providing a `chart`, you MUST:\n1\. \*\*Analyze the chart data\*\* to find peaks, troughs, trends, and anomalies manually\.\n2\. \*\*Provide Strategic Analysis after the chart\*\:', en_replacement, c, flags=re.DOTALL)

with open('app/Http/Controllers/AgenticChatbotController.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("Updated Chart Prompt")
