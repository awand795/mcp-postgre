import re
import sys
sys.stdout.reconfigure(encoding='utf-8')

blade = r"d:\MCP Versi Web\mcp-postgresql\resources\views\admin\guide.blade.php"

with open(blade, "r", encoding="utf-8") as f:
    content = f.read()

# All replacements: (old_img_text_fragment, new_real_img_filename)
# Format: inject 'real_img' => 'XXX.png' BEFORE 'img_text' for the matching step
injections = [
    # DASHBOARD
    ("'img_text' => 'Step 2: Kartu Pertama",     "real_dashboard.png"),
    ("'img_text' => 'Step 3: Area Grafik",        "real_dashboard.png"),
    ("'img_text' => 'Step 4: Sidebar Navigasi",   "real_dashboard.png"),
    ("'img_text' => 'Step 5: Toggle Tema",        "real_dash_darkmode.png"),
    # USERS - top area
    ("'img_text' => 'Step 2: Tambah User",        "real_user_tambah_btn.png"),
    ("'img_text' => 'Step 3: Tombol Template",    "real_user_template_btn.png"),
    ("'img_text' => 'Step 4: Tombol Import",      "real_user_tambah_btn.png"),
    ("'img_text' => 'Step 5: Tombol Export",      "real_user_export_btn.png"),
    ("'img_text' => 'Step 6: Form Filter",        "real_user_filter_form.png"),
    ("'img_text' => 'Step 7: Hasil Filter",       "real_user_filter_form.png"),
    ("'img_text' => 'Step 8: Reset Filter",       "real_user_filter_form.png"),
    ("'img_text' => 'Step 9: Kolom Tabel",        "real_user_list.png"),
    # USERS - tambah modal fields
    ("'img_text' => 'Step 11: Field Nama",        "real_user_tambah_modal2.png"),
    ("'img_text' => 'Step 12: Field Email",       "real_user_tambah_modal2.png"),
    ("'img_text' => 'Step 13: Field Password",    "real_user_tambah_modal2.png"),
    ("'img_text' => 'Step 14: Dropdown Role",     "real_user_tambah_modal2.png"),
    ("'img_text' => 'Step 15: Is Admin",          "real_user_tambah_modal2.png"),
    ("'img_text' => 'Step 16: Tombol Simpan\nLingkaran Besar'", "real_user_tambah_modal2.png"),
    ("'img_text' => 'Step 17: Tombol Batal",      "real_user_tambah_modal2.png"),
    ("'img_text' => 'Step 18: Notifikasi Sukses", "real_user_list.png"),
    # USERS - edit
    ("'img_text' => 'Step 19: Tombol Edit",       "real_user_row_btns.png"),
    ("'img_text' => 'Step 21: Data Lama",         "real_user_edit_modal2.png"),
    ("'img_text' => 'Step 22: Tombol Update",     "real_user_edit_modal2.png"),
    ("'img_text' => 'Step 23: Notif Edit",        "real_user_list.png"),
    # USERS - hapus
    ("'img_text' => 'Step 24: Tombol Hapus",      "real_user_row_btns.png"),
    ("'img_text' => 'Step 26: User Hilang",       "real_user_list.png"),
    # USERS - AI Config
    ("'img_text' => 'Step 27: Tombol AI Config",  "real_user_row_btns.png"),
    ("'img_text' => 'Step 29: AI Models",         "real_user_ai_config_open.png"),
    ("'img_text' => 'Step 30: API Keys",          "real_user_ai_config_open.png"),
    ("'img_text' => 'Step 31: Save Config",       "real_user_ai_config2.png"),
    # USERS - MCP Token
    ("'img_text' => 'Step 32: Generate Token",    "real_user_row_btns.png"),
    ("'img_text' => 'Step 33: Konfirmasi Token",  "real_user_row_btns.png"),
    ("'img_text' => 'Step 34: Token Tampil",      "real_user_row_btns.png"),
    ("'img_text' => 'Step 35: Revoke Token",      "real_user_row_btns.png"),
    ("'img_text' => 'Step 36: Konfirmasi Revoke", "real_user_row_btns.png"),
    # USERS - RLS
    ("'img_text' => 'Step 37: Tombol RLS",        "real_user_row_btns.png"),
    ("'img_text' => 'Step 39: Pilih Tabel",       "real_user_rls_open.png"),
    ("'img_text' => 'Step 40: Aturan Filter",     "real_user_rls_open.png"),
    ("'img_text' => 'Step 41: Tambah Aturan",     "real_user_rls_open.png"),
    ("'img_text' => 'Step 42: Preview Filter",    "real_user_rls_open.png"),
    ("'img_text' => 'Step 43: Copy Filter",       "real_user_rls_open.png"),
    ("'img_text' => 'Step 44: Simpan RLS",        "real_user_rls_open.png"),
    # USERS - Import/Export
    ("'img_text' => 'Step 45: Modal Import",      "real_user_tambah_btn.png"),
    ("'img_text' => 'Step 46: Choose File",       "real_user_tambah_btn.png"),
    ("'img_text' => 'Step 47: Tombol Import",     "real_user_tambah_btn.png"),
    ("'img_text' => 'Step 48: Notifikasi Import", "real_user_list.png"),
    ("'img_text' => 'Step 49: Export Data",       "real_user_export_btn.png"),
    ("'img_text' => 'Step 50: Excel Terunduh",    "real_user_export_btn.png"),
    # ROLES
    ("'img_text' => 'Step 2: Tambah Role",        "real_role_tambah_btn.png"),
    ("'img_text' => 'Step 3: Daftar Role",        "real_role_list.png"),
    ("'img_text' => 'Step 4: Panel Permissions",  "real_role_permissions.png"),
    # ROLES - tambah
    ("'img_text' => 'Step 6: Field Nama",         "real_role_tambah_modal2.png"),
    ("'img_text' => 'Step 7: Field Deskripsi",    "real_role_tambah_modal2.png"),
    ("'img_text' => 'Step 8: Simpan Role",        "real_role_tambah_modal2.png"),
    ("'img_text' => 'Step 9: Role Terbuat",       "real_role_list.png"),
    # ROLES - edit
    ("'img_text' => 'Step 10: Tombol Edit",       "real_role_edit_modal.png"),
    ("'img_text' => 'Step 11: Edit Modal",        "real_role_edit_modal.png"),
    ("'img_text' => 'Step 12: Tombol Update",     "real_role_edit_modal.png"),
    ("'img_text' => 'Step 13: Sukses Update",     "real_role_list.png"),
    # ROLES - hapus
    ("'img_text' => 'Step 14: Tombol Hapus",      "real_role_hapus_dialog.png"),
    ("'img_text' => 'Step 15: Konfirmasi",        "real_role_hapus_dialog.png"),
    ("'img_text' => 'Step 16: Role Hilang",       "real_role_list.png"),
    # ROLES - permissions
    ("'img_text' => 'Step 17: Pilih Role",        "real_role_permissions.png"),
    ("'img_text' => 'Step 18: Tabel Permission",  "real_role_permissions.png"),
    ("'img_text' => 'Step 19: Header Kolom",      "real_role_permissions.png"),
    ("'img_text' => 'Step 20: Checkbox",          "real_role_permissions.png"),
    ("'img_text' => 'Step 21: Select All",        "real_role_permissions.png"),
    ("'img_text' => 'Step 22: Search Tabel",      "real_role_permissions.png"),
    ("'img_text' => 'Step 23: Simpan Akses",      "real_role_permissions.png"),
    ("'img_text' => 'Step 24: Notif Sukses",      "real_role_list.png"),
    ("'img_text' => 'Step 25: Unsaved Warning",   "real_role_permissions.png"),
    # DATABASE
    ("'img_text' => 'Step 2: Tambah DB",          "real_db_list.png"),
    ("'img_text' => 'Step 3: Test All",           "real_db_test_all.png"),
    ("'img_text' => 'Step 4: Toolbar",            "real_db_list.png"),
    ("'img_text' => 'Step 5: Health Bar",         "real_db_test_all.png"),
    ("'img_text' => 'Step 6: Card DB",            "real_db_list.png"),
    ("'img_text' => 'Step 7: Badge Status",       "real_db_list.png"),
    # DATABASE - tambah
    ("'img_text' => 'Step 9: Field Nama",         "real_db_tambah_modal_s1.png"),
    ("'img_text' => 'Step 10: Kode Unik",         "real_db_tambah_modal_s1.png"),
    ("'img_text' => 'Step 11: Dropdown Driver",   "real_db_tambah_modal_s1.png"),
    ("'img_text' => 'Step 12: Field Host",        "real_db_tambah_modal_s2.png"),
    ("'img_text' => 'Step 13: Field Port",        "real_db_tambah_modal_s2.png"),
    ("'img_text' => 'Step 14: Field DB Name",     "real_db_tambah_modal_s2.png"),
    ("'img_text' => 'Step 15: Username",          "real_db_tambah_modal_s2.png"),
    ("'img_text' => 'Step 16: Password",          "real_db_tambah_modal_s2.png"),
    ("'img_text' => 'Step 17: Toggle Active",     "real_db_tambah_modal_s2.png"),
    ("'img_text' => 'Step 18: Field Schema",      "real_db_tambah_modal_s3.png"),
    ("'img_text' => 'Step 19: Test Koneksi",      "real_db_tambah_modal_s3.png"),
    ("'img_text' => 'Step 20: Hasil Test",        "real_db_tambah_modal_s3.png"),
    ("'img_text' => 'Step 21: Tombol Simpan\nLingkaran Besar'", "real_db_tambah_modal_s3.png"),
    ("'img_text' => 'Step 22: DB Baru Tampil",    "real_db_list.png"),
    # DATABASE - edit
    ("'img_text' => 'Step 23: Edit Card",         "real_db_edit_modal.png"),
    ("'img_text' => 'Step 24: Modal Edit",        "real_db_edit_modal.png"),
    ("'img_text' => 'Step 25: Tombol Update",     "real_db_edit_modal.png"),
    ("'img_text' => 'Step 26: Notif Update",      "real_db_list.png"),
    # DATABASE - test koneksi
    ("'img_text' => 'Step 27: Ikon Ping",         "real_db_list.png"),
    ("'img_text' => 'Step 28: Loading Spinner",   "real_db_test_all.png"),
    ("'img_text' => 'Step 29: Connected Hijau",   "real_db_test_all.png"),
    ("'img_text' => 'Step 30: Failed Merah",      "real_db_test_all.png"),
    ("'img_text' => 'Step 31: Error Message",     "real_db_test_all.png"),
    # DATABASE - hapus & schema
    ("'img_text' => 'Step 32: Hapus Card",        "real_db_hapus_dialog.png"),
    ("'img_text' => 'Step 33: Konfirmasi Hapus",  "real_db_hapus_dialog.png"),
    ("'img_text' => 'Step 34: DB Terhapus",       "real_db_list.png"),
    ("'img_text' => 'Step 35: Tombol Schema",     "real_db_list.png"),
    ("'img_text' => 'Step 36: Daftar Tabel",      "real_db_list.png"),
    # AI MANAGEMENT
    ("'img_text' => 'Step 2: Kartu Statistik",    "real_ai_management.png"),
    ("'img_text' => 'Step 3: Tambah Provider",    "real_ai_add_provider_btn.png"),
    ("'img_text' => 'Step 4: Provider Card",      "real_ai_management.png"),
    # AI - provider add fields
    ("'img_text' => 'Step 6: Field Nama Provider","real_ai_add_provider_modal2.png"),
    ("'img_text' => 'Step 7: Kode Unik Provider", "real_ai_add_provider_modal2.png"),
    ("'img_text' => 'Step 8: Base URL",           "real_ai_add_provider_modal2.png"),
    ("'img_text' => 'Step 9: Simpan Provider",    "real_ai_add_provider_modal2.png"),
    ("'img_text' => 'Step 10: Tombol Batal",      "real_ai_add_provider_modal2.png"),
    ("'img_text' => 'Step 11: Card Muncul",       "real_ai_management.png"),
    # AI - toggle
    ("'img_text' => 'Step 12: Toggle ON",         "real_ai_toggle_on.png"),
    ("'img_text' => 'Step 13: Toggle OFF",        "real_ai_toggle_off.png"),
    ("'img_text' => 'Step 14: Card Redup",        "real_ai_toggle_off.png"),
    # AI - hapus provider
    ("'img_text' => 'Step 15: Tombol Hapus\nSorot Ikon Sampah'", "real_ai_management.png"),
    ("'img_text' => 'Step 16: Ikon Gembok",       "real_ai_management.png"),
    ("'img_text' => 'Step 17: Konfirmasi Hapus",  "real_ai_management.png"),
    ("'img_text' => 'Step 18: Provider Hilang",   "real_ai_management.png"),
    # AI - Keys tab
    ("'img_text' => 'Step 19: Tab Keys",          "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 20: Daftar Key",        "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 21: Elemen Baris",      "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 22: Badge Status",      "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 23: Usage Count",       "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 24: Token Count",       "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 25: Added By",          "real_ai_keys_tab.png"),
    # AI - Add Key
    ("'img_text' => 'Step 26: Add Key Button",    "real_ai_add_key_btn.png"),
    ("'img_text' => 'Step 28: Field Nama Key",    "real_ai_add_key_modal2.png"),
    ("'img_text' => 'Step 29: Field API Key",     "real_ai_add_key_modal2.png"),
    ("'img_text' => 'Step 30: Ikon Mata",         "real_ai_add_key_modal2.png"),
    ("'img_text' => 'Step 31: Simpan Key",        "real_ai_add_key_modal2.png"),
    ("'img_text' => 'Step 32: Batal Key",         "real_ai_add_key_modal2.png"),
    ("'img_text' => 'Step 33: Key Muncul",        "real_ai_keys_tab.png"),
    # AI - Edit Key
    ("'img_text' => 'Step 34: Edit Key",          "real_ai_edit_key_modal.png"),
    ("'img_text' => 'Step 35: Modal Edit Key",    "real_ai_edit_key_modal.png"),
    ("'img_text' => 'Step 36: Edit Nama Key",     "real_ai_edit_key_modal.png"),
    ("'img_text' => 'Step 37: Hint Kosongkan",    "real_ai_edit_key_modal.png"),
    ("'img_text' => 'Step 38: Checkbox Aktif",    "real_ai_edit_key_modal.png"),
    ("'img_text' => 'Step 39: Update Key",        "real_ai_edit_key_modal.png"),
    # AI - Hapus Key
    ("'img_text' => 'Step 40: Hapus Key",         "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 41: Konfirm Hapus Key", "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 42: Key Hilang",        "real_ai_keys_tab.png"),
    # AI - Reset Limit
    ("'img_text' => 'Step 43: Reset Limit",       "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 44: Banner Merah",      "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 45: Konfirm Reset",     "real_ai_keys_tab.png"),
    ("'img_text' => 'Step 46: Kembali OK",        "real_ai_keys_tab.png"),
    # AI - Health Check
    ("'img_text' => 'Step 47: Health Check",      "real_ai_health_modal2.png"),
    ("'img_text' => 'Step 49: Dropdown Model Test","real_ai_health_modal2.png"),
    ("'img_text' => 'Step 50: Checkbox Manual",   "real_ai_health_modal2.png"),
    ("'img_text' => 'Step 51: Input Manual",      "real_ai_health_modal2.png"),
    ("'img_text' => 'Step 52: Tombol Cek",        "real_ai_health_modal2.png"),
    ("'img_text' => 'Step 53: Loading Ping",      "real_ai_health_modal2.png"),
    ("'img_text' => 'Step 54: Hasil Sukses",      "real_ai_health_modal2.png"),
    ("'img_text' => 'Step 55: Hasil Gagal",       "real_ai_health_modal2.png"),
    ("'img_text' => 'Step 56: Tombol Ulang",      "real_ai_health_modal2.png"),
    ("'img_text' => 'Step 57: Tombol Tutup",      "real_ai_health_modal2.png"),
    # AI - Models tab
    ("'img_text' => 'Step 59: Badge Model",       "real_ai_models_tab.png"),
    ("'img_text' => 'Step 60: Model Aktif",       "real_ai_models_tab.png"),
    ("'img_text' => 'Step 61: Model Mati",        "real_ai_models_tab.png"),
    ("'img_text' => 'Step 62: Klik Toggle",       "real_ai_models_tab.png"),
    ("'img_text' => 'Step 63: Tombol Silang",     "real_ai_models_tab.png"),
    ("'img_text' => 'Step 64: Add Model Button",  "real_ai_add_model_btn.png"),
    ("'img_text' => 'Step 66: ID Model Tepat",    "real_ai_add_model_modal2.png"),
    ("'img_text' => 'Step 67: Display Name",      "real_ai_add_model_modal2.png"),
    ("'img_text' => 'Step 68: Simpan Model",      "real_ai_add_model_modal2.png"),
    ("'img_text' => 'Step 69: Chip Baru",         "real_ai_models_tab.png"),
    # AI - Hapus Model
    ("'img_text' => 'Step 70: Klik Silang Hapus", "real_ai_models_tab.png"),
    ("'img_text' => 'Step 71: Konfirm Hapus Model","real_ai_models_tab.png"),
    ("'img_text' => 'Step 72: Chip Hilang",       "real_ai_models_tab.png"),
]

count = 0
skipped = 0
not_found = 0

for img_text_fragment, real_img in injections:
    if img_text_fragment in content:
        # Check if already has real_img right before this
        check_pattern = f"'real_img' => '{real_img}', {img_text_fragment}"
        any_real_img_pattern = f"'real_img' => '.*?', {img_text_fragment}"
        
        # Simple check: does it already have real_img?
        idx = content.find(img_text_fragment)
        # Look back 80 chars for real_img
        snippet = content[max(0, idx-80):idx]
        if "'real_img'" in snippet:
            skipped += 1
        else:
            content = content.replace(img_text_fragment, f"'real_img' => '{real_img}', {img_text_fragment}", 1)
            count += 1
            print(f"OK: {real_img} -> {img_text_fragment[:45]}")
    else:
        not_found += 1
        print(f"MISS: {img_text_fragment[:60]}")

with open(blade, "w", encoding="utf-8") as f:
    f.write(content)

print(f"\nDone: {count} injected, {skipped} already had img, {not_found} not found")
