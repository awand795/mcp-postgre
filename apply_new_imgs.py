import re

blade = r"d:\MCP Versi Web\mcp-postgresql\resources\views\admin\guide.blade.php"

with open(blade, "r", encoding="utf-8") as f:
    content = f.read()

# All replacements: (step_number, img_text_fragment, new_real_img_filename)
injections = [
    # USERS - filter
    ("6", "Form Filter", "real_user_filter_form.png"),
    ("7", "Hasil Filter", "real_user_filter_form.png"),
    ("8", "Reset Filter", "real_user_filter_form.png"),
    # USERS - actions
    ("19", "Tombol Edit", "real_user_edit_btn.png"),
    ("24", "Tombol Hapus", "real_user_delete_btn.png"),
    ("27", "Tombol AI Config", "real_user_ai_btn.png"),
    ("32", "Generate Token", "real_user_mcp_btn.png"),
    ("37", "Tombol RLS", "real_user_rls_btn.png"),
    # USERS - RLS Modal
    ("41", "Tambah Aturan", "real_user_rls_modal.png"),
    ("42", "Preview Filter", "real_user_rls_modal.png"),
    
    # DATABASE - Tambah DB Tab 2
    ("11", "Dropdown Driver", "real_db_tambah_modal_s2.png"),
    ("12", "Field Host", "real_db_tambah_modal_s2.png"),
    ("13", "Field Port", "real_db_tambah_modal_s2.png"),
    ("14", "Field DB Name", "real_db_tambah_modal_s2.png"),
    ("15", "Username", "real_db_tambah_modal_s2.png"),
    ("16", "Password", "real_db_tambah_modal_s2.png"),
    
    # DATABASE - actions
    ("25", "Tombol Update", "real_db_edit_btn.png"),
    ("29", "Connected Hijau", "real_db_status_badge.png"),
    ("35", "Tombol Schema", "real_db_schema_btn.png"),

    # AI MANAGEMENT
    ("5", "Tombol Tambah", "real_add_provider_modal.png"),
    ("6", "Field Nama Provider", "real_add_provider_modal.png"),
    ("7", "Kode Unik Provider", "real_add_provider_modal.png"),
    ("8", "Base URL", "real_add_provider_modal.png"),
    ("9", "Simpan Provider", "real_add_provider_modal.png"),
    ("10", "Tombol Batal", "real_add_provider_modal.png"),
    ("12", "Toggle ON", "real_ai_toggle_on.png"),
    ("13", "Toggle OFF", "real_ai_toggle_on.png"),
    ("14", "Card Redup", "real_ai_toggle_on.png"),
    ("19", "Tab Keys", "real_ai_keys_tab.png"),
    ("26", "Add Key Button", "real_ai_add_key_btn.png"),
    ("34", "Edit Key", "real_ai_edit_key_btn.png"),
    ("47", "Health Check", "real_ai_health_btn.png"),
    ("59", "Badge Model", "real_models_tab.png"),
    ("60", "Model Aktif", "real_models_tab.png"),
    ("61", "Model Mati", "real_models_tab.png"),
    ("62", "Klik Toggle", "real_models_tab.png"),
    ("64", "Add Model Button", "real_ai_add_model_btn.png"),
]

count = 0

def replace_img(match):
    global count
    prefix = match.group(1)
    step_no = match.group(2)
    middle = match.group(3)
    old_img = match.group(4)
    suffix = match.group(5)
    img_text = match.group(6)
    end = match.group(7)

    for inj_no, inj_text, new_img in injections:
        if step_no == inj_no and inj_text in img_text:
            count += 1
            return f"{prefix}{step_no}{middle}{new_img}{suffix}{img_text}{end}"
    
    return match.group(0)

# Matches: ['no' => 19, ..., 'real_img' => 'old.png', 'img_text' => 'Step 19: Tombol Edit']
pattern = r"(\['no'\s*=>\s*)(\d+)(.*?'real_img'\s*=>\s*')([^']+)('.*?'img_text'\s*=>\s*')([^']+)('\])"
content = re.sub(pattern, replace_img, content)

with open(blade, "w", encoding="utf-8") as f:
    f.write(content)

print(f"Done: {count} images updated.")
