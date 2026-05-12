import re
import sys

sys.stdout.reconfigure(encoding='utf-8')

with open(r'd:\MCP Versi Web\mcp-postgresql\resources\views\admin\guide.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

step_blocks = re.findall(r"\['no' => (\d+),\s*'text' => '(.*?)',.*?(?:'real_img' => '(.*?)',\s*)?'img_text'", content, re.DOTALL)

has_screenshot = []
no_screenshot = []

for block in step_blocks:
    no, text, real_img = block
    text_clean = text[:70].replace('\n', ' ')
    if real_img:
        has_screenshot.append((no, text_clean, real_img))
    else:
        no_screenshot.append((no, text_clean))

print('=== STEPS WITH REAL SCREENSHOTS ===')
for no, text, img in has_screenshot:
    print(f'  Step {no:>2}: [{img}] {text}')

print(f'\nTotal WITH screenshot: {len(has_screenshot)}')
print(f'Total WITHOUT screenshot (need filling): {len(no_screenshot)}')

print('\n=== STEPS WITHOUT REAL SCREENSHOT ===')
for no, text in no_screenshot:
    print(f'  Step {no:>2}: {text}')
