with open('fix_prompt.py', 'r', encoding='utf-8') as f:
    code = f.read()

import re
match = re.search(r'"""(    // ── System prompt.*?)"""', code, re.DOTALL)
new_prompt = match.group(1)

with open('app/Http/Controllers/AgenticChatbotController.php', 'r', encoding='utf-8') as f:
    content = f.read()

start_idx = content.find('    // ── System prompt')
end_idx = content.find('    // ── Build messages')

if start_idx != -1 and end_idx != -1:
    content = content[:start_idx] + new_prompt + "\n\n" + content[end_idx:]
    with open('app/Http/Controllers/AgenticChatbotController.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("Replaced!")
else:
    print("Not found! start:", start_idx, "end:", end_idx)
