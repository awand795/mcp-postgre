import shutil, os
dst = r"D:\MCP Versi Web\mcp-postgresql\public\admin_guide"

files = [
    "ai_add_key.png","ai_list.png","db_list.png","real_add_provider_modal.png",
    "real_ai_health_modal2.png","real_ai_keys_tab.png","real_ai_management.png",
    "real_dashboard.png","real_db_edit_modal.png","real_db_hapus_dialog.png",
    "real_db_list.png","real_db_test_all.png","real_login_button.png",
    "real_login_email.png","real_login_page.png","real_login_password.png",
    "real_login_success.png","real_role_edit_modal.png","real_role_hapus_dialog.png",
    "real_role_list.png","real_role_tambah_btn.png","real_tambah_db_modal.png",
    "real_tambah_role_modal.png","real_user_export_btn.png","real_user_filter_form.png",
    "real_user_list.png","real_user_row_btns.png","real_user_tambah_btn.png",
    "real_user_template_btn.png","role_list.png","role_permissions.png",
    "user_ai_config.png","user_list.png","user_rls.png","user_rls_select.png",
    "v2_ai_keys_actions.png","v2_ai_models_add.png","v2_ai_provider_add.png",
    "v2_ai_tabs.png","v2_dash_clean.png","v2_dash_theme.png","v2_db_modal_add.png",
    "v2_db_row_actions.png","v2_db_top_actions.png","v2_role_add_btn.png",
    "v2_role_permissions_modal.png","v2_role_row_actions.png","v2_user_add_modal.png",
    "v2_user_ai_config_modal.png","v2_user_rls_rule_builder.png",
    "v2_user_rls_table_select.png","v2_user_row_actions.png","v2_user_top_actions.png",
]

src_dir = r"C:\Users\Public\claude_outputs\admin_guide_fixed"
ok = 0
for f in files:
    src_path = os.path.join(src_dir, f)
    dst_path = os.path.join(dst, f)
    if os.path.exists(src_path):
        shutil.copy2(src_path, dst_path)
        print(f"OK  {f}")
        ok += 1
    else:
        print(f"??  {f}  (tidak ditemukan di {src_path})")

print(f"\nSelesai: {ok}/{len(files)} file disalin ke {dst}")
