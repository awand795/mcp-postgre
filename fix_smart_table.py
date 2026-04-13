import re

with open('app/Http/Controllers/AgenticChatbotController.php', 'r', encoding='utf-8') as f:
    c = f.read()

c = c.replace('{"tool_index": 0}', '{}')
c = re.sub(r'Jika hasil query HANYA berupa satu angka total agregat tanpa GROUP BY.*?DILARANG menggunakan Smart Table', 'Jika hasil query HANYA berupa 1 baris (misal: ringkasan 1 cabang, atau angka total agregat), Anda DILARANG menggunakan Smart Table.', c)
c = re.sub(r'If the result is ONLY a single aggregate number \(no table\), SKIP THIS SECTION', 'If the result is ONLY 1 row (e.g. single branch summary or total aggregate), SKIP THIS SECTION and do NOT use a table', c)
c = c.replace('If the query returns ONLY a single aggregate number (e.g., results of `COUNT(*)`, `SUM()`, or `AVG()` without GROUP BY), you are **FORBIDDEN** from using a Smart Table', 'If the query returns ONLY 1 row (e.g. single branch summary or single aggregate number), you are **FORBIDDEN** from using a Smart Table')

with open('app/Http/Controllers/AgenticChatbotController.php', 'w', encoding='utf-8') as f:
    f.write(c)

print("Done")
