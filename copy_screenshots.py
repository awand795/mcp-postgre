import os
import shutil
import sys
sys.stdout.reconfigure(encoding='utf-8')

src = r"C:\Users\awand\.gemini\antigravity\brain\7bfc3f10-753a-4663-b412-cb8e1a4a97f0\.system_generated\click_feedback"
dest = r"d:\MCP Versi Web\mcp-postgresql\public\admin_guide"

# Map screenshot timestamps to meaningful names
# Based on the order subagent took them
mapping = {
    # Dashboard
    "click_feedback_1778571818469.png": "real_dash_darkmode.png",
    # Users
    "click_feedback_1778571859111.png": "real_user_tambah_btn.png",
    "click_feedback_1778571861721.png": "real_user_tambah_modal2.png",
    "click_feedback_1778571870220.png": "real_user_edit_modal2.png",
    "click_feedback_1778571873431.png": "real_user_row_btns.png",
    "click_feedback_1778571881787.png": "real_user_ai_config2.png",
    "click_feedback_1778571884496.png": "real_user_ai_config_open.png",
    "click_feedback_1778571894149.png": "real_user_rls_open.png",
    "click_feedback_1778571896729.png": "real_user_rls_closed.png",
    "click_feedback_1778571915051.png": "real_user_filter_form.png",
    # Role
    "click_feedback_1778571936813.png": "real_role_tambah_btn.png",
    "click_feedback_1778571939572.png": "real_role_tambah_modal2.png",
    "click_feedback_1778571947130.png": "real_role_edit_batal.png",
    "click_feedback_1778571949585.png": "real_role_edit_modal.png",
    "click_feedback_1778571952102.png": "real_role_permissions.png",
    "click_feedback_1778571959869.png": "real_role_hapus_dialog.png",
    "click_feedback_1778571962345.png": "real_role_hapus_cancel.png",
    "click_feedback_1778571971644.png": "real_role_hapus_batal.png",
    # Database
    "click_feedback_1778571991162.png": "real_db_test_all.png",
    "click_feedback_1778572001904.png": "real_db_tambah_modal_s1.png",
    "click_feedback_1778572014142.png": "real_db_tambah_modal_s2.png",
    "click_feedback_1778572017059.png": "real_db_tambah_modal_s3.png",
    "click_feedback_1778572020344.png": "real_db_tambah_closed.png",
    "click_feedback_1778572039423.png": "real_db_edit_modal.png",
    "click_feedback_1778572043008.png": "real_db_edit_closed.png",
    "click_feedback_1778572045337.png": "real_db_hapus_dialog.png",
    # AI Management
    "click_feedback_1778572066284.png": "real_ai_toggle_off.png",
    "click_feedback_1778572069680.png": "real_ai_toggle_on.png",
    "click_feedback_1778572082586.png": "real_ai_models_tab.png",
    "click_feedback_1778572086907.png": "real_ai_keys_tab.png",
    "click_feedback_1778572089775.png": "real_ai_add_key_btn.png",
    "click_feedback_1778572094104.png": "real_ai_add_key_modal2.png",
    "click_feedback_1778572098542.png": "real_ai_edit_key_modal.png",
    "click_feedback_1778572102825.png": "real_ai_edit_key_closed.png",
    "click_feedback_1778572114402.png": "real_ai_health_modal2.png",
    "click_feedback_1778572118890.png": "real_ai_health_closed.png",
    "click_feedback_1778572122878.png": "real_ai_add_model_btn.png",
    "click_feedback_1778572126872.png": "real_ai_add_model_modal2.png",
    "click_feedback_1778572130278.png": "real_ai_add_provider_btn.png",
    "click_feedback_1778572136034.png": "real_ai_add_provider_modal2.png",
    # User buttons
    "click_feedback_1778572154137.png": "real_user_template_btn.png",
    "click_feedback_1778572157227.png": "real_user_export_btn.png",
}

copied = 0
for src_name, dest_name in mapping.items():
    sp = os.path.join(src, src_name)
    dp = os.path.join(dest, dest_name)
    if os.path.exists(sp):
        shutil.copy2(sp, dp)
        copied += 1
        print(f"OK: {src_name} -> {dest_name}")
    else:
        print(f"MISS: {src_name}")

print(f"\nCopied {copied}/{len(mapping)} screenshots")
