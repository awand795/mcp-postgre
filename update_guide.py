
import re

blade = r"d:\MCP Versi Web\mcp-postgresql\resources\views\admin\guide.blade.php"

with open(blade, "r", encoding="utf-8") as f:
    content = f.read()

# Map: 'real_img' keys to inject into the @php $guideData array
# We'll do targeted replacements — for each step that currently uses img_text only,
# insert real_img if we have a matching screenshot.

replacements = [
    # LOGIN
    ("'img_text' => 'Step 1: Login Kosong",
     "'real_img' => 'real_login_page.png', 'img_text' => 'Step 1: Login Kosong"),
    ("'img_text' => 'Step 2: Isi Email",
     "'real_img' => 'real_login_email.png', 'img_text' => 'Step 2: Isi Email"),
    ("'img_text' => 'Step 3: Isi Password",
     "'real_img' => 'real_login_password.png', 'img_text' => 'Step 3: Isi Password"),
    ("'img_text' => 'Step 4: Klik Login",
     "'real_img' => 'real_login_button.png', 'img_text' => 'Step 4: Klik Login"),
    # DASHBOARD
    ("'img_text' => 'Step 1: Dashboard Penuh",
     "'real_img' => 'real_dashboard.png', 'img_text' => 'Step 1: Dashboard Penuh"),
    # USERS
    ("'img_text' => 'Step 1: Halaman Users",
     "'real_img' => 'real_user_list.png', 'img_text' => 'Step 1: Halaman Users"),
    ("'img_text' => 'Step 10: Modal Tambah User",
     "'real_img' => 'real_tambah_user_modal.png', 'img_text' => 'Step 10: Modal Tambah User"),
    ("'img_text' => 'Step 20: Modal Edit",
     "'real_img' => 'real_edit_user_modal.png', 'img_text' => 'Step 20: Modal Edit"),
    ("'img_text' => 'Step 25: Dialog Hapus",
     "'real_img' => 'real_hapus_user.png', 'img_text' => 'Step 25: Dialog Hapus"),
    ("'img_text' => 'Step 28: Modal AI",
     "'real_img' => 'real_ai_config_modal.png', 'img_text' => 'Step 28: Modal AI"),
    ("'img_text' => 'Step 38: Modal RLS",
     "'real_img' => 'real_rls_modal.png', 'img_text' => 'Step 38: Modal RLS"),
    # ROLES
    ("'img_text' => 'Step 1: Halaman Roles",
     "'real_img' => 'real_role_list.png', 'img_text' => 'Step 1: Halaman Roles"),
    ("'img_text' => 'Step 5: Modal Role",
     "'real_img' => 'real_tambah_role_modal.png', 'img_text' => 'Step 5: Modal Role"),
    # DATABASE
    ("'img_text' => 'Step 1: Halaman DB",
     "'real_img' => 'real_db_list.png', 'img_text' => 'Step 1: Halaman DB"),
    ("'img_text' => 'Step 8: Modal Tambah DB",
     "'real_img' => 'real_tambah_db_modal.png', 'img_text' => 'Step 8: Modal Tambah DB"),
    # AI MANAGEMENT
    ("'img_text' => 'Step 1: Halaman Penuh",
     "'real_img' => 'real_ai_management.png', 'img_text' => 'Step 1: Halaman Penuh"),
    ("'img_text' => 'Step 5: Modal Tambah Provider",
     "'real_img' => 'real_add_provider_modal.png', 'img_text' => 'Step 5: Modal Tambah Provider"),
    ("'img_text' => 'Step 27: Modal Add Key",
     "'real_img' => 'real_add_key_modal.png', 'img_text' => 'Step 27: Modal Add Key"),
    ("'img_text' => 'Step 58: Tab Models",
     "'real_img' => 'real_models_tab.png', 'img_text' => 'Step 58: Tab Models"),
    ("'img_text' => 'Step 65: Modal Add Model",
     "'real_img' => 'real_add_model_modal.png', 'img_text' => 'Step 65: Modal Add Model"),
    ("'img_text' => 'Step 48: Modal Health",
     "'real_img' => 'real_health_check_modal.png', 'img_text' => 'Step 48: Modal Health"),
]

count = 0
for old, new in replacements:
    if old in content and new not in content:
        content = content.replace(old, new, 1)
        count += 1
        print(f"  OK Injected real_img for: {old[:50]}")
    elif new in content:
        print(f"  OK Already has real_img: {old[:50]}")
    else:
        print(f"  MISS NOT FOUND: {old[:50]}")

with open(blade, "w", encoding="utf-8") as f:
    f.write(content)

print(f"\nDone! {count} real screenshots injected into guide.blade.php")
