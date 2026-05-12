@extends('layouts.admin')

@section('page-title', 'Panduan Lengkap Administrator (Exhaustive Guide)')

@section('content')
@php
$guideData = [
    [
        'id' => 'menu-1-login',
        'title' => 'MENU 1: HALAMAN LOGIN (/login)',
        'icon' => 'fas fa-sign-in-alt',
        'desc' => 'Panduan untuk proses autentikasi masuk ke dalam sistem Admin Panel.',
        'sections' => [
            [
                'id' => 'login-auth',
                'title' => 'Langkah Login',
                'steps' => [
                    ['no' => 1, 'text' => 'Halaman login kosong — sorot merah field Email', 'desc' => 'Field Email: masukkan email akun admin.', 'real_img' => 'real_login_page.png', 'img_text' => 'Step 1: Login Kosong\nSorot Merah Email'],
                    ['no' => 2, 'text' => 'Mengetik email — sorot merah field Email yang sudah diisi', 'desc' => 'Pastikan format email valid.', 'real_img' => 'real_login_email.png', 'img_text' => 'Step 2: Isi Email\nSorot Merah Email'],
                    ['no' => 3, 'text' => 'Mengetik password — sorot merah field Password', 'desc' => 'Field Password: masukkan password akun.', 'real_img' => 'real_login_password.png', 'img_text' => 'Step 3: Isi Password\nSorot Merah Password'],
                    ['no' => 4, 'text' => 'Tombol "Login" — sorot merah lingkaran besar di tombol Login', 'desc' => 'Klik tombol untuk masuk.', 'real_img' => 'real_login_button.png', 'img_text' => 'Step 4: Klik Login\nLingkaran Merah Tombol'],
                    ['no' => 5, 'text' => 'Setelah login berhasil', 'desc' => 'Sistem akan me-redirect Anda ke halaman Chatbot atau Dashboard.', 'real_img' => 'real_login_success.png', 'img_text' => 'Step 5: Login Sukses\nRedirect Dashboard'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-2-dashboard',
        'title' => 'MENU 2: DASHBOARD (/admin/)',
        'icon' => 'fas fa-chart-pie',
        'desc' => 'Pusat informasi dan statistik utama dari seluruh sistem chatbot dan database.',
        'sections' => [
            [
                'id' => 'dashboard-overview',
                'title' => 'Overview Dashboard',
                'steps' => [
                    ['no' => 1, 'text' => 'Halaman dashboard penuh — sorot merah setiap kartu statistik', 'desc' => 'Setiap kartu statistik menunjukkan ringkasan data.', 'real_img' => 'real_dashboard.png', 'img_text' => 'Step 1: Dashboard Penuh\nSorot Semua Kartu', 'real_img' => 'v2_dash_clean.png'],
                    ['no' => 2, 'text' => 'Kartu statistik pertama — sorot merah angka dan labelnya', 'desc' => 'Menunjukkan metrik spesifik.', 'real_img' => 'real_dashboard.png', 'img_text' => 'Step 2: Kartu Pertama\nSorot Angka & Label'],
                    ['no' => 3, 'text' => 'Area grafik/chart', 'desc' => 'Menampilkan tren jika tersedia.', 'real_img' => 'real_dashboard.png', 'img_text' => 'Step 3: Area Grafik\nSorot Grafik'],
                    ['no' => 4, 'text' => 'Sidebar navigasi — sorot merah setiap menu di sidebar', 'desc' => 'Navigasi semua menu yang tersedia.', 'real_img' => 'real_dashboard.png', 'img_text' => 'Step 4: Sidebar Navigasi\nSorot Menu'],
                    ['no' => 5, 'text' => 'Tombol dark mode/light mode — sorot merah tombol toggle tema', 'desc' => 'Tombol toggle tema di header.', 'real_img' => 'real_dash_darkmode.png', 'img_text' => 'Step 5: Toggle Tema\nSorot Ikon Bulan/Matahari', 'real_img' => 'v2_dash_theme.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-3-user',
        'title' => 'MENU 3: MANAGEMENT USER (/admin/users)',
        'icon' => 'fas fa-users',
        'desc' => 'Pengaturan semua akun pengguna, limitasi AI, hingga Row Level Security (RLS).',
        'sections' => [
            [
                'id' => 'user-main',
                'title' => '3A. Halaman Utama User',
                'steps' => [
                    ['no' => 1, 'text' => 'Halaman users lengkap — sorot merah tabel user', 'desc' => 'Menampilkan tabel list user.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 1: Halaman Users\nSorot Tabel Utama', 'real_img' => 'user_list.png'],
                    ['no' => 2, 'text' => 'Tombol "Tambah User" (kanan atas) — sorot merah dengan lingkaran besar', 'desc' => 'Tombol biru di kanan atas.', 'real_img' => 'real_user_tambah_btn.png', 'img_text' => 'Step 2: Tambah User\nLingkaran Tombol Tambah', 'real_img' => 'v2_user_top_actions.png'],
                    ['no' => 3, 'text' => 'Tombol "Template" — sorot merah', 'desc' => 'Download format excel.', 'real_img' => 'real_user_template_btn.png', 'img_text' => 'Step 3: Tombol Template\nSorot Tombol'],
                    ['no' => 4, 'text' => 'Tombol "Import" — sorot merah', 'desc' => 'Upload data excel.', 'real_img' => 'real_user_tambah_btn.png', 'img_text' => 'Step 4: Tombol Import\nSorot Tombol'],
                    ['no' => 5, 'text' => 'Tombol "Export" — sorot merah', 'desc' => 'Download data user.', 'real_img' => 'real_user_export_btn.png', 'img_text' => 'Step 5: Tombol Export\nSorot Tombol'],
                    ['no' => 6, 'text' => 'Form filter/search — sorot merah input pencarian', 'desc' => 'Kolom cari nama dan filter role.', 'real_img' => 'real_user_filter_form.png', 'img_text' => 'Step 6: Form Filter\nSorot Input Search'],
                    ['no' => 7, 'text' => 'Setelah mengisi filter dan klik tombol "Filter"', 'desc' => 'Hasil dari tabel terfilter.', 'real_img' => 'real_user_filter_form.png', 'img_text' => 'Step 7: Hasil Filter\nSorot Tabel Terfilter'],
                    ['no' => 8, 'text' => 'Tombol "Reset" filter', 'desc' => 'Mengembalikan ke default.', 'real_img' => 'real_user_filter_form.png', 'img_text' => 'Step 8: Reset Filter\nSorot Tombol Reset'],
                    ['no' => 9, 'text' => 'Kolom tabel', 'desc' => 'Kolom Nama, Email, Role, Aksi dll.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 9: Kolom Tabel\nSorot Header Kolom'],
                ]
            ],
            [
                'id' => 'user-add',
                'title' => '3B. Tambah User Baru',
                'steps' => [
                    ['no' => 10, 'text' => 'Modal "Tambah User" yang baru terbuka', 'desc' => 'Judul modal tambah.', 'real_img' => 'real_tambah_user_modal.png', 'img_text' => 'Step 10: Modal Tambah User\nSorot Judul Modal', 'real_img' => 'v2_user_add_modal.png'],
                    ['no' => 11, 'text' => 'Field Nama Lengkap', 'desc' => 'Isi nama lengkap user.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 11: Field Nama\nSorot Input Nama'],
                    ['no' => 12, 'text' => 'Field Email', 'desc' => 'Isi email yang valid.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 12: Field Email\nSorot Input Email'],
                    ['no' => 13, 'text' => 'Field Password', 'desc' => 'Minimal 8 karakter.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 13: Field Password\nSorot Input Password'],
                    ['no' => 14, 'text' => 'Dropdown Role', 'desc' => 'Pilihan role yang tersedia.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 14: Dropdown Role\nSorot Select Role'],
                    ['no' => 15, 'text' => 'Checkbox Is Admin', 'desc' => 'Jelaskan kapan dicentang.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 15: Is Admin\nSorot Checkbox'],
                    ['no' => 16, 'text' => 'Tombol Simpan di modal', 'desc' => 'Menyimpan data.', 'real_img' => 'real_user_save_btn.png', 'img_text' => 'Step 16: Tombol Simpan\nLingkaran Besar'],
                    ['no' => 17, 'text' => 'Tombol Batal di modal', 'desc' => 'Membatalkan aksi.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 17: Tombol Batal\nSorot Batal'],
                    ['no' => 18, 'text' => 'Notifikasi sukses', 'desc' => 'User berhasil dibuat.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 18: Notifikasi Sukses\nSorot Toast/Alert'],
                ]
            ],
            [
                'id' => 'user-edit',
                'title' => '3C. Edit User',
                'steps' => [
                    ['no' => 19, 'text' => 'Tombol Edit (ikon pensil)', 'desc' => 'Di kolom aksi.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 19: Tombol Edit\nSorot Ikon Pensil', 'real_img' => 'v2_user_row_actions.png'],
                    ['no' => 20, 'text' => 'Modal Edit User terbuka', 'desc' => 'Modal edit.', 'real_img' => 'real_edit_user_modal.png', 'img_text' => 'Step 20: Modal Edit\nSorot Form'],
                    ['no' => 21, 'text' => 'Field terisi data lama', 'desc' => 'Form dengan data eksisting.', 'real_img' => 'real_user_edit_modal2.png', 'img_text' => 'Step 21: Data Lama\nSorot Input Terisi'],
                    ['no' => 22, 'text' => 'Tombol Update', 'desc' => 'Menyimpan pembaruan.', 'real_img' => 'real_user_edit_modal2.png', 'img_text' => 'Step 22: Tombol Update\nSorot Tombol'],
                    ['no' => 23, 'text' => 'Notifikasi sukses', 'desc' => 'Edit berhasil.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 23: Notif Edit\nSorot Alert'],
                ]
            ],
            [
                'id' => 'user-delete',
                'title' => '3D. Hapus User',
                'steps' => [
                    ['no' => 24, 'text' => 'Tombol Hapus (ikon tempat sampah)', 'desc' => 'Di kolom aksi.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 24: Tombol Hapus\nLingkaran Merah Besar'],
                    ['no' => 25, 'text' => 'Dialog konfirmasi "Apakah Anda yakin?"', 'desc' => 'Sorot tombol "Ya, Hapus".', 'real_img' => 'real_hapus_user.png', 'img_text' => 'Step 25: Dialog Hapus\nSorot Tombol Ya'],
                    ['no' => 26, 'text' => 'Setelah user berhasil dihapus', 'desc' => 'User hilang dari tabel.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 26: User Hilang\nSorot Tabel'],
                ]
            ],
            [
                'id' => 'user-ai',
                'title' => '3E. AI Config per User',
                'steps' => [
                    ['no' => 27, 'text' => 'Tombol konfigurasi AI', 'desc' => 'Ikon otak di kolom Aksi.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 27: Tombol AI Config\nSorot Ikon Otak'],
                    ['no' => 28, 'text' => 'Modal "AI Config" terbuka', 'desc' => 'Sorot seluruh modal.', 'real_img' => 'real_ai_config_modal.png', 'img_text' => 'Step 28: Modal AI\nSorot Modal', 'real_img' => 'v2_user_ai_config_modal.png'],
                    ['no' => 29, 'text' => 'Daftar AI Models', 'desc' => 'Bisa di-toggle per user.', 'real_img' => 'real_user_ai_config_open.png', 'img_text' => 'Step 29: AI Models\nSorot Toggle Switch', 'real_img' => 'user_ai_config.png'],
                    ['no' => 30, 'text' => 'Daftar API Keys', 'desc' => 'Bisa di-toggle per user.', 'real_img' => 'real_user_ai_config_open.png', 'img_text' => 'Step 30: API Keys\nSorot Toggle Switch'],
                    ['no' => 31, 'text' => 'Tombol Save Config', 'desc' => 'Simpan setting AI.', 'real_img' => 'real_user_ai_config2.png', 'img_text' => 'Step 31: Save Config\nSorot Tombol'],
                ]
            ],
            [
                'id' => 'user-mcp',
                'title' => '3F. MCP Token Management',
                'steps' => [
                    ['no' => 32, 'text' => 'Tombol "Generate MCP Token"', 'desc' => 'Sorot ikon kunci.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 32: Generate Token\nSorot Ikon Kunci'],
                    ['no' => 33, 'text' => 'Modal konfirmasi generate', 'desc' => 'Konfirmasi.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 33: Konfirmasi Token\nSorot Modal'],
                    ['no' => 34, 'text' => 'Hasil token yang baru dibuat', 'desc' => 'Tampilkan token dan sorot.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 34: Token Tampil\nSorot Area Token'],
                    ['no' => 35, 'text' => 'Tombol "Revoke Token"', 'desc' => 'Mencabut token.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 35: Revoke Token\nSorot Tombol Revoke'],
                    ['no' => 36, 'text' => 'Dialog konfirmasi revoke', 'desc' => 'Membatalkan fungsi token.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 36: Konfirmasi Revoke\nSorot Tombol Ya'],
                ]
            ],
            [
                'id' => 'user-rls',
                'title' => '3G. Table Filters (Row Level Security)',
                'steps' => [
                    ['no' => 37, 'text' => 'Tombol "Table Filters"', 'desc' => 'Ikon filter.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 37: Tombol RLS\nSorot Ikon Filter', 'real_img' => 'user_rls.png'],
                    ['no' => 38, 'text' => 'Modal Table Filters terbuka', 'desc' => 'Seluruh area.', 'real_img' => 'real_rls_modal.png', 'img_text' => 'Step 38: Modal RLS\nSorot Modal', 'real_img' => 'v2_user_rls_table_select.png'],
                    ['no' => 39, 'text' => 'Dropdown memilih tabel', 'desc' => 'Memilih nama tabel.', 'real_img' => 'real_user_rls_open.png', 'img_text' => 'Step 39: Pilih Tabel\nSorot Dropdown', 'real_img' => 'user_rls_select.png'],
                    ['no' => 40, 'text' => 'Field filter (kolom, operator, nilai)', 'desc' => 'Masing-masing input field.', 'real_img' => 'real_user_rls_open.png', 'img_text' => 'Step 40: Aturan Filter\nSorot Kolom/Operator/Nilai', 'real_img' => 'v2_user_rls_rule_builder.png'],
                    ['no' => 41, 'text' => 'Tombol "Tambah Filter"', 'desc' => 'Menambahkan kondisional ekstra.', 'real_img' => 'real_user_rls_open.png', 'img_text' => 'Step 41: Tambah Aturan\nSorot Tombol Tambah'],
                    ['no' => 42, 'text' => 'Tombol "Preview Filter"', 'desc' => 'Uji hasil query filter.', 'real_img' => 'real_user_rls_open.png', 'img_text' => 'Step 42: Preview Filter\nSorot Tombol Preview'],
                    ['no' => 43, 'text' => 'Tombol "Copy Filter dari User Lain"', 'desc' => 'Duplikasi setingan.', 'real_img' => 'real_user_rls_open.png', 'img_text' => 'Step 43: Copy Filter\nSorot Tombol Copy'],
                    ['no' => 44, 'text' => 'Tombol "Simpan Filters"', 'desc' => 'Menetapkan RLS permanen.', 'real_img' => 'real_user_rls_open.png', 'img_text' => 'Step 44: Simpan RLS\nSorot Tombol Simpan'],
                ]
            ],
            [
                'id' => 'user-import',
                'title' => '3H. Import/Export User',
                'steps' => [
                    ['no' => 45, 'text' => 'Modal Import User', 'desc' => 'Area upload file.', 'real_img' => 'real_user_tambah_btn.png', 'img_text' => 'Step 45: Modal Import\nSorot Area Upload'],
                    ['no' => 46, 'text' => 'Pilih file Excel', 'desc' => 'Tombol Choose File.', 'real_img' => 'real_user_tambah_btn.png', 'img_text' => 'Step 46: Choose File\nSorot Tombol'],
                    ['no' => 47, 'text' => 'Tombol "Import"', 'desc' => 'Menjalankan impor.', 'real_img' => 'real_user_tambah_btn.png', 'img_text' => 'Step 47: Tombol Import\nSorot Tombol'],
                    ['no' => 48, 'text' => 'Notifikasi hasil import', 'desc' => 'Berhasil/error.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 48: Notifikasi Import\nSorot Alert'],
                    ['no' => 49, 'text' => 'Proses Export', 'desc' => 'Tombol Export diklik.', 'real_img' => 'real_user_export_btn.png', 'img_text' => 'Step 49: Export Data\nSorot Tombol Export'],
                    ['no' => 50, 'text' => 'File Excel terdownload', 'desc' => 'Notifikasi unduhan browser.', 'real_img' => 'real_user_export_btn.png', 'img_text' => 'Step 50: Excel Terunduh\nSorot Bar Unduhan'],
                ]
            ],
        ]
    ],
    [
        'id' => 'menu-4-role',
        'title' => 'MENU 4: MANAGEMENT ROLE (/admin/roles)',
        'icon' => 'fas fa-user-shield',
        'desc' => 'Manajemen akses tingkat grup/jabatan untuk mengontrol tabel apa saja yang bisa dilihat AI.',
        'sections' => [
            [
                'id' => 'role-main',
                'title' => '4A. Halaman Utama Role',
                'steps' => [
                    ['no' => 1, 'text' => 'Halaman roles lengkap', 'desc' => 'Sidebar kiri dan panel kanan.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 1: Halaman Roles\nSorot Kiri Kanan', 'real_img' => 'role_list.png'],
                    ['no' => 2, 'text' => 'Tombol "Tambah Role" (kanan atas)', 'desc' => 'Lingkaran besar.', 'real_img' => 'real_role_tambah_btn.png', 'img_text' => 'Step 2: Tambah Role\nLingkaran Besar', 'real_img' => 'v2_role_add_btn.png'],
                    ['no' => 3, 'text' => 'Daftar role di sidebar kiri', 'desc' => 'Setiap role dalam daftar.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 3: Daftar Role\nSorot Setiap Role'],
                    ['no' => 4, 'text' => 'Panel permissions di kanan', 'desc' => 'Tabel permissions.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 4: Panel Permissions\nSorot Tabel Kanan'],
                ]
            ],
            [
                'id' => 'role-add',
                'title' => '4B. Tambah Role Baru',
                'steps' => [
                    ['no' => 5, 'text' => 'Modal "Tambah Role"', 'desc' => 'Judul modal.', 'real_img' => 'real_tambah_role_modal.png', 'img_text' => 'Step 5: Modal Role\nSorot Judul'],
                    ['no' => 6, 'text' => 'Field Nama Role', 'desc' => 'Contoh: Operator, Manager.', 'real_img' => 'real_role_tambah_modal2.png', 'img_text' => 'Step 6: Field Nama\nSorot Input'],
                    ['no' => 7, 'text' => 'Field Deskripsi', 'desc' => 'Input deskripsi.', 'real_img' => 'real_role_tambah_modal2.png', 'img_text' => 'Step 7: Field Deskripsi\nSorot Input'],
                    ['no' => 8, 'text' => 'Tombol Simpan', 'desc' => 'Menyimpan.', 'real_img' => 'real_role_tambah_modal2.png', 'img_text' => 'Step 8: Simpan Role\nLingkaran Besar'],
                    ['no' => 9, 'text' => 'Role baru muncul di sidebar kiri', 'desc' => 'Hasil pembuatan role.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 9: Role Terbuat\nSorot Sidebar Kiri'],
                ]
            ],
            [
                'id' => 'role-edit',
                'title' => '4C. Edit Role',
                'steps' => [
                    ['no' => 10, 'text' => 'Ikon Edit (pensil kuning)', 'desc' => 'Di setiap baris role.', 'real_img' => 'real_role_edit_modal.png', 'img_text' => 'Step 10: Tombol Edit\nSorot Ikon', 'real_img' => 'v2_role_row_actions.png'],
                    ['no' => 11, 'text' => 'Modal Edit Role dengan data lama', 'desc' => 'Field yang bisa diubah.', 'real_img' => 'real_role_edit_modal.png', 'img_text' => 'Step 11: Edit Modal\nSorot Form'],
                    ['no' => 12, 'text' => 'Tombol Update', 'desc' => 'Simpan perubahan.', 'real_img' => 'real_role_edit_modal.png', 'img_text' => 'Step 12: Tombol Update\nSorot Tombol'],
                    ['no' => 13, 'text' => 'Notifikasi sukses', 'desc' => 'Muncul di pojok.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 13: Sukses Update\nSorot Alert'],
                ]
            ],
            [
                'id' => 'role-delete',
                'title' => '4D. Hapus Role',
                'steps' => [
                    ['no' => 14, 'text' => 'Ikon Hapus (tong merah)', 'desc' => 'Untuk mendelete grup.', 'real_img' => 'real_role_hapus_dialog.png', 'img_text' => 'Step 14: Tombol Hapus\nLingkaran Besar'],
                    ['no' => 15, 'text' => 'Dialog konfirmasi', 'desc' => 'Tombol Ya Hapus.', 'real_img' => 'real_role_hapus_dialog.png', 'img_text' => 'Step 15: Konfirmasi\nSorot Ya Hapus'],
                    ['no' => 16, 'text' => 'Setelah role terhapus', 'desc' => 'Data hilang dari UI.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 16: Role Hilang\nSorot Sidebar Kiri'],
                ]
            ],
            [
                'id' => 'role-permissions',
                'title' => '4E. Atur Permissions Tabel per Role',
                'steps' => [
                    ['no' => 17, 'text' => 'Saat klik salah satu role', 'desc' => 'Highlight role terpilih di kiri.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 17: Pilih Role\nSorot Role Kiri'],
                    ['no' => 18, 'text' => 'Panel permissions terbuka', 'desc' => 'Daftar tabel.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 18: Tabel Permission\nSorot Panel Kanan', 'real_img' => 'v2_role_permissions_modal.png'],
                    ['no' => 19, 'text' => 'Kolom-kolom permission', 'desc' => 'SELECT, INSERT, UPDATE, DELETE.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 19: Header Kolom\nSorot Header'],
                    ['no' => 20, 'text' => 'Checkbox permission dicentang', 'desc' => 'Mencentang izin.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 20: Checkbox\nSorot Checkbox', 'real_img' => 'role_permissions.png'],
                    ['no' => 21, 'text' => 'Tombol "Select All" / "Clear All"', 'desc' => 'Pilih semua otomatis.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 21: Select All\nSorot Tombol'],
                    ['no' => 22, 'text' => 'Filter tabel permission', 'desc' => 'Input search tabel.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 22: Search Tabel\nSorot Input Search'],
                    ['no' => 23, 'text' => 'Tombol "Simpan Akses"', 'desc' => 'Lingkaran BESAR di bawah.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 23: Simpan Akses\nLingkaran BESAR'],
                    ['no' => 24, 'text' => 'Notifikasi sukses', 'desc' => 'Permission berhasil disave.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 24: Notif Sukses\nSorot Alert'],
                    ['no' => 25, 'text' => 'Indikator "Ada perubahan belum disimpan"', 'desc' => 'Peringatan visual.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 25: Unsaved Warning\nSorot Indikator'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-5-db',
        'title' => 'MENU 5: MANAGEMENT DATABASE (/admin/databases)',
        'icon' => 'fas fa-database',
        'desc' => 'Pengaturan koneksi ke berbagai server database (PostgreSQL, MySQL, SQL Server) sebagai sumber data AI.',
        'sections' => [
            [
                'id' => 'db-main',
                'title' => '5A. Halaman Utama Database',
                'steps' => [
                    ['no' => 1, 'text' => 'Halaman databases lengkap', 'desc' => 'Grid/list database.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 1: Halaman DB\nSorot Grid DB', 'real_img' => 'db_list.png'],
                    ['no' => 2, 'text' => 'Tombol "Tambah Database" (kanan atas)', 'desc' => 'Lingkaran besar.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 2: Tambah DB\nLingkaran Besar', 'real_img' => 'v2_db_top_actions.png'],
                    ['no' => 3, 'text' => 'Tombol "Test All"', 'desc' => 'Test semua koneksi sekaligus.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 3: Test All\nSorot Tombol'],
                    ['no' => 4, 'text' => 'Toolbar filter', 'desc' => 'Input search, filter driver, list view.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 4: Toolbar\nSorot Semua Elemen'],
                    ['no' => 5, 'text' => 'Setelah klik "Test All"', 'desc' => 'Health bar yang muncul.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 5: Health Bar\nSorot Bar Progress'],
                    ['no' => 6, 'text' => 'Card database dalam grid view', 'desc' => 'Setiap elemen card.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 6: Card DB\nSorot Card'],
                    ['no' => 7, 'text' => 'Badge status koneksi', 'desc' => 'Connected/Failed/Not Tested.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 7: Badge Status\nSorot Badge'],
                ]
            ],
            [
                'id' => 'db-add',
                'title' => '5B. Tambah Database Baru',
                'steps' => [
                    ['no' => 8, 'text' => 'Modal "Tambah Database" terbuka', 'desc' => 'Judul modal.', 'real_img' => 'real_tambah_db_modal.png', 'img_text' => 'Step 8: Modal Tambah DB\nSorot Judul', 'real_img' => 'v2_db_modal_add.png'],
                    ['no' => 9, 'text' => 'Field Nama Database', 'desc' => 'Nama alias/label.', 'real_img' => 'real_db_tambah_modal_s1.png', 'img_text' => 'Step 9: Field Nama\nSorot Input'],
                    ['no' => 10, 'text' => 'Field Kode Unik', 'desc' => 'Identifier unik huruf kecil.', 'real_img' => 'real_db_tambah_modal_s1.png', 'img_text' => 'Step 10: Kode Unik\nSorot Input'],
                    ['no' => 11, 'text' => 'Dropdown Driver', 'desc' => 'PostgreSQL, MySQL, MariaDB.', 'real_img' => 'real_db_tambah_modal_s1.png', 'img_text' => 'Step 11: Dropdown Driver\nSorot Select'],
                    ['no' => 12, 'text' => 'Field Host', 'desc' => 'IP/hostname server.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 12: Field Host\nSorot Input'],
                    ['no' => 13, 'text' => 'Field Port', 'desc' => 'Default 5432/3306.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 13: Field Port\nSorot Input'],
                    ['no' => 14, 'text' => 'Field Database Name', 'desc' => 'Nama database.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 14: Field DB Name\nSorot Input'],
                    ['no' => 15, 'text' => 'Field Username', 'desc' => 'Akun database.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 15: Username\nSorot Input'],
                    ['no' => 16, 'text' => 'Field Password', 'desc' => 'Password database.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 16: Password\nSorot Input'],
                    ['no' => 17, 'text' => 'Toggle Active', 'desc' => 'Aktifkan koneksi.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 17: Toggle Active\nSorot Switch'],
                    ['no' => 18, 'text' => 'Field Schema', 'desc' => 'Untuk PostgreSQL, default: public.', 'real_img' => 'real_db_tambah_modal_s3.png', 'img_text' => 'Step 18: Field Schema\nSorot Input'],
                    ['no' => 19, 'text' => 'Tombol Test Connection (di dalam modal)', 'desc' => 'Test sebelum simpan.', 'real_img' => 'real_db_tambah_modal_s3.png', 'img_text' => 'Step 19: Test Koneksi\nSorot Tombol Test'],
                    ['no' => 20, 'text' => 'Hasil test connection', 'desc' => 'Sukses/Gagal notifikasi.', 'real_img' => 'real_db_tambah_modal_s3.png', 'img_text' => 'Step 20: Hasil Test\nSorot Alert Box'],
                    ['no' => 21, 'text' => 'Tombol Simpan', 'desc' => 'Lingkaran besar.', 'real_img' => 'real_db_save_btn.png', 'img_text' => 'Step 21: Tombol Simpan\nLingkaran Besar'],
                    ['no' => 22, 'text' => 'Database baru muncul di grid', 'desc' => 'Card baru.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 22: DB Baru Tampil\nSorot Grid Baru'],
                ]
            ],
            [
                'id' => 'db-edit',
                'title' => '5C. Edit Database',
                'steps' => [
                    ['no' => 23, 'text' => 'Tombol Edit di card database', 'desc' => 'Ikon pensil.', 'real_img' => 'real_db_edit_modal.png', 'img_text' => 'Step 23: Edit Card\nSorot Ikon Pensil', 'real_img' => 'v2_db_row_actions.png'],
                    ['no' => 24, 'text' => 'Modal Edit Database dengan data lama', 'desc' => 'Semua field terisi.', 'real_img' => 'real_db_edit_modal.png', 'img_text' => 'Step 24: Modal Edit\nSorot Form'],
                    ['no' => 25, 'text' => 'Tombol Update', 'desc' => 'Kirim form.', 'real_img' => 'real_db_edit_modal.png', 'img_text' => 'Step 25: Tombol Update\nSorot Tombol'],
                    ['no' => 26, 'text' => 'Notifikasi sukses', 'desc' => 'Berhasil diedit.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 26: Notif Update\nSorot Alert'],
                ]
            ],
            [
                'id' => 'db-test',
                'title' => '5D. Test Koneksi Individual',
                'steps' => [
                    ['no' => 27, 'text' => 'Tombol Test Connection', 'desc' => 'Ikon heartbeat di card database.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 27: Ikon Ping\nSorot Ikon'],
                    ['no' => 28, 'text' => 'Loading state saat test', 'desc' => 'Animasi loading test.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 28: Loading Spinner\nSorot Spinner'],
                    ['no' => 29, 'text' => 'Hasil test sukses', 'desc' => 'Badge Connected hijau.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 29: Connected Hijau\nSorot Badge'],
                    ['no' => 30, 'text' => 'Hasil test gagal', 'desc' => 'Badge Failed merah.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 30: Failed Merah\nSorot Badge'],
                    ['no' => 31, 'text' => 'Detail error message', 'desc' => 'Menampilkan pesan SQL error untuk debugging.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 31: Error Message\nSorot Teks Error'],
                ]
            ],
            [
                'id' => 'db-delete',
                'title' => '5E. Hapus Database',
                'steps' => [
                    ['no' => 32, 'text' => 'Tombol Hapus di card database', 'desc' => 'Ikon tempat sampah.', 'real_img' => 'real_db_hapus_dialog.png', 'img_text' => 'Step 32: Hapus Card\nSorot Ikon'],
                    ['no' => 33, 'text' => 'Dialog konfirmasi', 'desc' => 'Tombol Ya Hapus.', 'real_img' => 'real_db_hapus_dialog.png', 'img_text' => 'Step 33: Konfirmasi Hapus\nSorot Ya'],
                    ['no' => 34, 'text' => 'Setelah database terhapus', 'desc' => 'Hilang dari view.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 34: DB Terhapus\nSorot Grid'],
                ]
            ],
            [
                'id' => 'db-schema',
                'title' => '5F. Lihat Schema Database',
                'steps' => [
                    ['no' => 35, 'text' => 'Tombol "Lihat Schema"', 'desc' => 'Melihat list tabel.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 35: Tombol Schema\nSorot Ikon'],
                    ['no' => 36, 'text' => 'Daftar schema/tabel yang tampil', 'desc' => 'Hierarki struktur db.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 36: Daftar Tabel\nSorot Modal Tabel'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-6-ai',
        'title' => 'MENU 6: AI MANAGEMENT (/admin/ai-management)',
        'icon' => 'fas fa-brain',
        'desc' => 'Sistem canggih pengelolaan Model AI, API Keys, Health Check, Rate Limiter.',
        'sections' => [
            [
                'id' => 'ai-main',
                'title' => '6A. Halaman Utama AI Management',
                'steps' => [
                    ['no' => 1, 'text' => 'Halaman AI Management penuh', 'desc' => 'Semua elemen layout.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 1: Halaman Penuh\nSorot Seluruh Elemen', 'real_img' => 'ai_list.png'],
                    ['no' => 2, 'text' => '4 Kartu statistik', 'desc' => 'Providers, API Keys, Rate Limited, Active Models.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 2: Kartu Statistik\nSorot ke-4 Kartu'],
                    ['no' => 3, 'text' => 'Tombol "Add Provider"', 'desc' => 'Kanan atas, lingkaran BESAR.', 'real_img' => 'real_ai_add_provider_btn.png', 'img_text' => 'Step 3: Tambah Provider\nLingkaran BESAR'],
                ]
            ],
            [
                'id' => 'ai-card',
                'title' => '6B. Provider Card',
                'steps' => [
                    ['no' => 4, 'text' => 'Provider card lengkap dengan nomor penjelasan', 'desc' => 'Emoji, Nama, Kode, Status, Toggle, dll.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 4: Provider Card\nBeri Nomor Tiap Elemen'],
                ]
            ],
            [
                'id' => 'ai-provider-add',
                'title' => '6C. Tambah Provider Baru',
                'steps' => [
                    ['no' => 5, 'text' => 'Modal "Add Provider AI Baru"', 'desc' => 'Judul modal.', 'real_img' => 'real_add_provider_modal.png', 'img_text' => 'Step 5: Modal Tambah Provider\nSorot Judul', 'real_img' => 'v2_ai_provider_add.png'],
                    ['no' => 6, 'text' => 'Field Nama Provider', 'desc' => 'Contoh: Groq, Ollama.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 6: Field Nama Provider\nSorot Input'],
                    ['no' => 7, 'text' => 'Field Kode Unik', 'desc' => 'Contoh: groq.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 7: Kode Unik Provider\nSorot Input'],
                    ['no' => 8, 'text' => 'Field Base URL API', 'desc' => 'Endpoint provider eksternal.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 8: Base URL\nSorot Input'],
                    ['no' => 9, 'text' => 'Tombol Tambah Provider', 'desc' => 'Lingkaran besar simpan.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 9: Simpan Provider\nLingkaran Besar'],
                    ['no' => 10, 'text' => 'Tombol Batal', 'desc' => 'Membatalkan form.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 10: Tombol Batal\nSorot Tombol'],
                    ['no' => 11, 'text' => 'Provider baru muncul di grid', 'desc' => 'Masuk dashboard AI.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 11: Card Muncul\nSorot Card Baru'],
                ]
            ],
            [
                'id' => 'ai-provider-toggle',
                'title' => '6D. Toggle Provider (Aktif/Nonaktif)',
                'steps' => [
                    ['no' => 12, 'text' => 'Toggle switch ON (biru/aktif)', 'desc' => 'Menghidupkan.', 'real_img' => 'real_ai_toggle_on.png', 'img_text' => 'Step 12: Toggle ON\nSorot Switch Biru'],
                    ['no' => 13, 'text' => 'Toggle switch OFF (abu-abu)', 'desc' => 'Mematikan sementara.', 'real_img' => 'real_ai_toggle_off.png', 'img_text' => 'Step 13: Toggle OFF\nSorot Switch Abu'],
                    ['no' => 14, 'text' => 'Card menjadi transparan/redup', 'desc' => 'Visual dinonaktifkan.', 'real_img' => 'real_ai_toggle_off.png', 'img_text' => 'Step 14: Card Redup\nSorot Card Transparan'],
                ]
            ],
            [
                'id' => 'ai-provider-delete',
                'title' => '6E. Hapus Provider',
                'steps' => [
                    ['no' => 15, 'text' => 'Tombol hapus (ikon tempat sampah)', 'desc' => 'Di header card.', 'real_img' => 'real_ai_delete_provider_btn.png', 'img_text' => 'Step 15: Tombol Hapus\nSorot Ikon Sampah'],
                    ['no' => 16, 'text' => 'Provider built-in terkunci (🔒)', 'desc' => 'OpenAI, Gemini tidak bisa dihapus.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 16: Ikon Gembok\nSorot Ikon 🔒'],
                    ['no' => 17, 'text' => 'Dialog konfirmasi Hapus', 'desc' => 'Ya, Hapus.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 17: Konfirmasi Hapus\nSorot Tombol Ya'],
                    ['no' => 18, 'text' => 'Provider berhasil dihapus', 'desc' => 'Layar setelah card hilang.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 18: Provider Hilang\nSorot Grid'],
                ]
            ],
            [
                'id' => 'ai-keys-main',
                'title' => '6F. Tab Keys — Kelola API Key',
                'steps' => [
                    ['no' => 19, 'text' => 'Tab "🔑 Keys" aktif', 'desc' => 'Berada di tab Keys.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 19: Tab Keys\nSorot Tab', 'real_img' => 'v2_ai_tabs.png'],
                    ['no' => 20, 'text' => 'Daftar key di panel body card', 'desc' => 'List API Keys.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 20: Daftar Key\nSorot Baris Key'],
                    ['no' => 21, 'text' => 'Setiap elemen di key row', 'desc' => 'Health dot, tombol aksi.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 21: Elemen Baris\nSorot Tiap Ikon', 'real_img' => 'v2_ai_keys_actions.png'],
                    ['no' => 22, 'text' => 'Badge status key', 'desc' => 'OK, OFF, LIMIT.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 22: Badge Status\nSorot Titik Warna'],
                    ['no' => 23, 'text' => 'Badge usage count', 'desc' => 'Berapa kali dipakai.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 23: Usage Count\nSorot Badge ↗'],
                    ['no' => 24, 'text' => 'Badge token count', 'desc' => 'Total token konsumsi.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 24: Token Count\nSorot Badge ◈'],
                    ['no' => 25, 'text' => 'Ditambahkan oleh', 'desc' => 'Nama admin.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 25: Added By\nSorot Tooltip'],
                ]
            ],
            [
                'id' => 'ai-keys-add',
                'title' => '6G. Tambah API Key Baru',
                'steps' => [
                    ['no' => 26, 'text' => 'Tombol "Add Key"', 'desc' => 'Footer card.', 'real_img' => 'real_ai_add_key_btn.png', 'img_text' => 'Step 26: Add Key Button\nSorot Tombol Bawah'],
                    ['no' => 27, 'text' => 'Modal "Add API Key" terbuka', 'desc' => 'Membuka form.', 'real_img' => 'real_add_key_modal.png', 'img_text' => 'Step 27: Modal Add Key\nSorot Judul Modal', 'real_img' => 'ai_add_key.png'],
                    ['no' => 28, 'text' => 'Field Nama Key (alias)', 'desc' => 'Contoh: Key Utama Production.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 28: Field Nama Key\nSorot Input Nama'],
                    ['no' => 29, 'text' => 'Field API Key', 'desc' => 'Isi token sebenarnya.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 29: Field API Key\nSorot Input Token'],
                    ['no' => 30, 'text' => 'Tombol show/hide password', 'desc' => 'Ikon mata.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 30: Ikon Mata\nSorot Toggle Visibility'],
                    ['no' => 31, 'text' => 'Tombol Simpan', 'desc' => 'Lingkaran besar.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 31: Simpan Key\nLingkaran Besar'],
                    ['no' => 32, 'text' => 'Tombol Batal', 'desc' => 'Batal simpan.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 32: Batal Key\nSorot Tombol'],
                    ['no' => 33, 'text' => 'Key baru muncul', 'desc' => 'Ada di dalam daftar list.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 33: Key Muncul\nSorot List Baru'],
                ]
            ],
            [
                'id' => 'ai-keys-edit',
                'title' => '6H. Edit API Key',
                'steps' => [
                    ['no' => 34, 'text' => 'Tombol Edit (ikon pensil)', 'desc' => 'Ubah data key.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 34: Edit Key\nSorot Ikon Pensil'],
                    ['no' => 35, 'text' => 'Modal "Edit API Key"', 'desc' => 'Semua field.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 35: Modal Edit Key\nSorot Modal'],
                    ['no' => 36, 'text' => 'Field Nama Key', 'desc' => 'Ubah alias.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 36: Edit Nama Key\nSorot Input'],
                    ['no' => 37, 'text' => 'Field API Key dengan hint', 'desc' => 'Kosongkan jika tidak diubah.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 37: Hint Kosongkan\nSorot Teks Hint'],
                    ['no' => 38, 'text' => 'Checkbox Aktifkan Key', 'desc' => 'Uncheck menonaktifkan sementara.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 38: Checkbox Aktif\nSorot Checkbox'],
                    ['no' => 39, 'text' => 'Tombol Simpan', 'desc' => 'Menyimpan update.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 39: Update Key\nSorot Tombol'],
                ]
            ],
            [
                'id' => 'ai-keys-delete',
                'title' => '6I. Hapus API Key',
                'steps' => [
                    ['no' => 40, 'text' => 'Tombol hapus (tempat sampah)', 'desc' => 'Lingkaran besar.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 40: Hapus Key\nLingkaran Besar'],
                    ['no' => 41, 'text' => 'Dialog konfirmasi Hapus API Key', 'desc' => 'Klik yes.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 41: Konfirm Hapus Key\nSorot Tombol Ya'],
                    ['no' => 42, 'text' => 'Setelah key terhapus', 'desc' => 'Hilang dari view.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 42: Key Hilang\nSorot Daftar Kosong'],
                ]
            ],
            [
                'id' => 'ai-keys-limit',
                'title' => '6J. Reset Limit API Key',
                'steps' => [
                    ['no' => 43, 'text' => 'Tombol Reset Limit', 'desc' => 'Ikon refresh kuning.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 43: Reset Limit\nSorot Ikon Putar'],
                    ['no' => 44, 'text' => 'Alert bar merah di atas daftar', 'desc' => 'X key kena rate limit.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 44: Banner Merah\nSorot Banner'],
                    ['no' => 45, 'text' => 'Dialog konfirmasi reset', 'desc' => 'Reset ulang threshold limit.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 45: Konfirm Reset\nSorot Tombol Ya'],
                    ['no' => 46, 'text' => 'Setelah reset: badge kembali ● OK', 'desc' => 'Status kembali normal.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 46: Kembali OK\nSorot Titik Hijau'],
                ]
            ],
            [
                'id' => 'ai-health',
                'title' => '6K. Health Check API Key',
                'steps' => [
                    ['no' => 47, 'text' => 'Tombol Health Check', 'desc' => 'Ikon gelombang/sinyal.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 47: Health Check\nSorot Ikon Sinyal'],
                    ['no' => 48, 'text' => 'Modal API Key Health Check', 'desc' => 'Judul modal.', 'real_img' => 'real_health_check_modal.png', 'img_text' => 'Step 48: Modal Health\nSorot Judul'],
                    ['no' => 49, 'text' => 'Dropdown Model untuk diuji', 'desc' => 'Auto-detect atau model manual.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 49: Dropdown Model Test\nSorot Select'],
                    ['no' => 50, 'text' => 'Checkbox "Ketik model manual"', 'desc' => 'Opsi input string manual.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 50: Checkbox Manual\nSorot Checkbox'],
                    ['no' => 51, 'text' => 'Input manual model', 'desc' => 'Ketik string (misal gemini-2.0).', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 51: Input Manual\nSorot Input Teks'],
                    ['no' => 52, 'text' => 'Tombol Cek Sekarang', 'desc' => 'Lingkaran besar aksi ping.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 52: Tombol Cek\nLingkaran Besar'],
                    ['no' => 53, 'text' => 'Loading state', 'desc' => 'Spinner menghubungi provider.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 53: Loading Ping\nSorot Spinner'],
                    ['no' => 54, 'text' => 'Hasil Sukses (Banner hijau)', 'desc' => 'HTTP 200.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 54: Hasil Sukses\nSorot Banner Hijau'],
                    ['no' => 55, 'text' => 'Hasil Gagal/Rate Limited', 'desc' => 'Banner merah dan error detail.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 55: Hasil Gagal\nSorot Banner Merah'],
                    ['no' => 56, 'text' => 'Tombol Cek Ulang', 'desc' => 'Muncul setelah result keluar.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 56: Tombol Ulang\nSorot Tombol'],
                    ['no' => 57, 'text' => 'Tombol Tutup', 'desc' => 'Menutup dialog.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 57: Tombol Tutup\nSorot Tombol'],
                ]
            ],
            [
                'id' => 'ai-models-main',
                'title' => '6L. Tab Models — Kelola Model AI',
                'steps' => [
                    ['no' => 58, 'text' => 'Tab "🧠 Models"', 'desc' => 'Berada di tab Models.', 'real_img' => 'real_models_tab.png', 'img_text' => 'Step 58: Tab Models\nSorot Tab Kedua', 'real_img' => 'v2_ai_tabs.png'],
                    ['no' => 59, 'text' => 'Daftar model sebagai chip/badge', 'desc' => 'Tampilan chip list.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 59: Badge Model\nSorot Chip Kapsul'],
                    ['no' => 60, 'text' => 'Model chip AKTIF', 'desc' => 'Warna indigo/biru.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 60: Model Aktif\nSorot Warna Biru'],
                    ['no' => 61, 'text' => 'Model chip NONAKTIF', 'desc' => 'Warna abu-abu.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 61: Model Mati\nSorot Warna Abu'],
                    ['no' => 62, 'text' => 'Cara klik chip untuk toggle', 'desc' => 'Aktifkan/nonaktifkan dari UI.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 62: Klik Toggle\nSorot Tindakan Klik'],
                    ['no' => 63, 'text' => 'Tombol × (hapus)', 'desc' => 'Tombol silang pada chip.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 63: Tombol Silang\nSorot × di Chip'],
                ]
            ],
            [
                'id' => 'ai-models-add',
                'title' => '6M. Tambah Model AI Baru',
                'steps' => [
                    ['no' => 64, 'text' => 'Tombol "Add Model"', 'desc' => 'Di footer provider card.', 'real_img' => 'real_ai_add_model_btn.png', 'img_text' => 'Step 64: Add Model Button\nSorot Tombol Bawah'],
                    ['no' => 65, 'text' => 'Modal "Add Model AI"', 'desc' => 'Menambah entitas model.', 'real_img' => 'real_add_model_modal.png', 'img_text' => 'Step 65: Modal Add Model\nSorot Modal', 'real_img' => 'v2_ai_models_add.png'],
                    ['no' => 66, 'text' => 'Field ID Model (system name)', 'desc' => 'SANGAT PENTING: ID teknis API.', 'real_img' => 'real_ai_add_model_modal2.png', 'img_text' => 'Step 66: ID Model Tepat\nSorot Input ID'],
                    ['no' => 67, 'text' => 'Field Display Name', 'desc' => 'Nama tampilan ramah pengguna.', 'real_img' => 'real_ai_add_model_modal2.png', 'img_text' => 'Step 67: Display Name\nSorot Input Display'],
                    ['no' => 68, 'text' => 'Tombol Simpan Model', 'desc' => 'Simpan model ke database.', 'real_img' => 'real_ai_add_model_modal2.png', 'img_text' => 'Step 68: Simpan Model\nLingkaran Besar'],
                    ['no' => 69, 'text' => 'Model baru muncul', 'desc' => 'Menjadi chip biru.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 69: Chip Baru\nSorot Chip Terbuat'],
                ]
            ],
            [
                'id' => 'ai-models-delete',
                'title' => '6N. Hapus Model AI',
                'steps' => [
                    ['no' => 70, 'text' => 'Tombol × di model chip', 'desc' => 'Hapus selamanya.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 70: Klik Silang Hapus\nSorot Tanda Silang'],
                    ['no' => 71, 'text' => 'Dialog konfirmasi Hapus Model', 'desc' => 'Klik ok.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 71: Konfirm Hapus Model\nSorot Ya Hapus'],
                    ['no' => 72, 'text' => 'Model terhapus dari chip list', 'desc' => 'Hilang dari UI.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 72: Chip Hilang\nSorot Area Kosong'],
                ]
            ]
        ]
    ]
];
@endphp

<style>
    /* ===== GUIDE PAGE LAYOUT ===== */
    .guide-wrap { display: flex; gap: 0; align-items: flex-start; width: 100%; }
    .guide-toc {
        width: 260px;
        min-width: 260px;
        position: sticky;
        top: 80px;
        max-height: calc(100vh - 100px);
        overflow-y: auto;
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        border-radius: 14px;
        padding: 1.25rem;
        box-shadow: var(--shadow-sm);
        margin-right: 1.5rem;
        flex-shrink: 0;
    }
    .guide-toc::-webkit-scrollbar { width: 4px; }
    .guide-toc::-webkit-scrollbar-thumb { background: var(--glass-border2); border-radius: 10px; }
    .guide-toc .toc-link {
        display: block;
        color: var(--text-muted);
        padding: 5px 10px;
        border-radius: 7px;
        font-size: 0.82rem;
        font-weight: 500;
        text-decoration: none;
        line-height: 1.4;
        white-space: normal;
        word-break: break-word;
        transition: all 0.2s;
        margin-bottom: 2px;
    }
    .guide-toc .toc-link:hover { background: var(--glass-border); color: var(--text-main); }
    .guide-toc .toc-link.active { background: rgba(99,102,241,0.15); color: var(--primary); font-weight: 700; }
    .guide-toc .toc-menu-link {
        display: block;
        color: var(--text-main);
        padding: 6px 10px;
        border-radius: 8px;
        font-size: 0.88rem;
        font-weight: 700;
        text-decoration: none;
        margin-top: 8px;
        margin-bottom: 2px;
        transition: all 0.2s;
    }
    .guide-toc .toc-menu-link:hover { background: rgba(99,102,241,0.1); }
    .guide-toc .toc-sub { padding-left: 8px; border-left: 2px solid var(--glass-border2); margin-left: 6px; margin-bottom: 4px; }

    /* ===== MAIN CONTENT ===== */
    .guide-content { flex: 1; min-width: 0; }
    .menu-section {
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2rem 2.5rem;
        margin-bottom: 2.5rem;
        box-shadow: var(--shadow-md);
        scroll-margin-top: 90px;
    }
    .menu-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .subsection { scroll-margin-top: 100px; }
    .subsection-title {
        color: var(--text-main);
        font-weight: 700;
        font-size: 1rem;
        padding: 8px 14px;
        margin-top: 2rem;
        margin-bottom: 1.25rem;
        background: rgba(99,102,241,0.08);
        border-left: 4px solid var(--primary);
        border-radius: 0 8px 8px 0;
    }

    /* ===== STEP CARD ===== */
    .guide-step {
        display: flex;
        gap: 18px;
        padding: 1.5rem 0;
        border-bottom: 1px solid var(--glass-border2);
        align-items: flex-start;
    }
    .guide-step:last-child { border-bottom: none; padding-bottom: 0; }
    .step-number {
        width: 44px;
        height: 44px;
        min-width: 44px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.1rem;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    }
    .step-title { font-size: 1.1rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; }
    .step-desc { color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; }

    /* ===== SCREENSHOT ===== */
    .screenshot-wrapper {
        margin-top: 1.25rem;
        border-radius: 10px;
        overflow: hidden;
        border: 3px solid rgba(239, 68, 68, 0.6);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        position: relative;
        background: #1e293b;
    }
    .screenshot-wrapper.real-shot { border-color: rgba(34, 197, 94, 0.7); }
    .mockup-img { width: 100%; max-height: 480px; object-fit: contain; display: block; transition: transform 0.3s ease; cursor: zoom-in; }
    .screenshot-wrapper:hover .mockup-img { transform: scale(1.01); }
    .screenshot-badge {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 6px 14px;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 1px;
        text-align: center;
        color: white;
    }
    .badge-real { background: rgba(22,163,74,0.85); }
    .badge-mockup { background: rgba(239,68,68,0.7); }

    /* ===== PROGRESS ===== */
    .progress-container { width: 100%; height: 5px; background: var(--glass-border); position: fixed; top: 0; left: 0; z-index: 9999; }
    .progress-bar { height: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary)); width: 0%; transition: width 0.15s; }

    /* ===== TOOLS ===== */
    .guide-search { border-radius: 8px !important; font-size: 0.85rem !important; }
    .d-none-search { display: none !important; }
    .print-btn { position: fixed; bottom: 28px; right: 28px; z-index: 1000; border-radius: 50px; padding: 10px 22px; font-weight: 700; box-shadow: 0 8px 20px rgba(99,102,241,0.45); }

    /* ===== IMAGE LIGHTBOX ===== */
    .img-lightbox { display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.92); align-items:center; justify-content:center; cursor:zoom-out; }
    .img-lightbox.show { display:flex; }
    .img-lightbox img { max-width:95vw; max-height:95vh; border-radius:8px; box-shadow:0 0 60px rgba(0,0,0,0.8); }

    @media (max-width: 1024px) {
        .guide-toc { display: none; }
    }
    @media print {
        .guide-toc, .progress-container, .print-btn, .img-lightbox { display: none !important; }
        .guide-content { width: 100%; }
        .menu-section { break-inside: avoid; border: none; box-shadow: none; }
    }
</style>

<div class="progress-container">
    <div class="progress-bar" id="progressBar"></div>
</div>

<!-- LIGHTBOX -->
<div class="img-lightbox" id="imgLightbox" onclick="this.classList.remove('show')">
    <img id="lightboxImg" src="" alt="Screenshot">
</div>

<div class="mb-4">
    <a href="{{ route('chatbot') }}" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-arrow-left"></i> Kembali ke Chatbot</a>
    <h1 style="color: var(--text-main); font-weight: 800; font-size: 1.9rem;">Panduan Administrator Lengkap</h1>
    <p class="text-muted mb-0">Dokumentasi Exhaustive — 193 Langkah dengan Screenshot Asli dari Admin Panel Langsung</p>
</div>

<div class="guide-wrap">
    <!-- TABLE OF CONTENTS SIDEBAR -->
    <nav class="guide-toc" id="guideToc">
        <p class="text-uppercase fw-bold mb-2" style="font-size:0.75rem; letter-spacing:1px; color:var(--text-muted);">Navigasi Panduan</p>
        <input type="text" id="guideSearch" class="form-control guide-search mb-3" placeholder="&#128269; Cari langkah...">

        @foreach($guideData as $menu)
            <a class="toc-menu-link" href="#{{ $menu['id'] }}">
                <i class="{{ $menu['icon'] }} me-1 opacity-75"></i> {{ explode('(', $menu['title'])[0] }}
            </a>
            <div class="toc-sub">
                @foreach($menu['sections'] as $sec)
                    <a class="toc-link" href="#{{ $sec['id'] }}">{{ $sec['title'] }}</a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <!-- MAIN CONTENT -->
    <div class="guide-content" id="mainGuideContent">
        @foreach($guideData as $menu)
            <section id="{{ $menu['id'] }}" class="menu-section searchable-section">
                <h2 class="menu-title border-bottom pb-3" style="border-color:var(--glass-border)!important;">
                    <i class="{{ $menu['icon'] }}"></i> {{ $menu['title'] }}
                </h2>
                <p class="text-muted mb-4">{!! $menu['desc'] !!}</p>

                @foreach($menu['sections'] as $sec)
                    <div id="{{ $sec['id'] }}" class="subsection searchable-subsection">
                        <div class="subsection-title">
                            <i class="fas fa-layer-group me-2 opacity-75"></i>{{ $sec['title'] }}
                        </div>

                        @foreach($sec['steps'] as $step)
                            <div class="guide-step searchable-step">
                                <div class="step-number">{{ $step['no'] }}</div>
                                <div class="flex-grow-1" style="min-width:0;">
                                    <div class="step-title">{{ $step['text'] }}</div>
                                    <div class="step-desc">{!! $step['desc'] !!}</div>

                                    @if(isset($step['real_img']) && $step['real_img'] != '')
                                        <div class="screenshot-wrapper real-shot" onclick="openLightbox('{{ asset('admin_guide/' . $step['real_img']) }}')">
                                            <img src="{{ asset('admin_guide/' . $step['real_img']) }}" class="mockup-img" alt="Step {{ $step['no'] }}" loading="lazy">
                                            <div class="screenshot-badge badge-real">
                                                <i class="fas fa-check-circle me-1"></i> SCREENSHOT ASLI — Klik untuk perbesar
                                            </div>
                                        </div>
                                    @else
                                        @php
                                            $mockupUrl = "https://placehold.co/1280x720/1e293b/ef4444?text=" . urlencode($step['img_text']);
                                        @endphp
                                        <div class="screenshot-wrapper">
                                            <img src="{{ $mockupUrl }}" class="mockup-img" alt="Step {{ $step['no'] }}" loading="lazy">
                                            <div class="screenshot-badge badge-mockup">
                                                <i class="fas fa-image me-1"></i> MOCKUP — Screenshot menyusul
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @endforeach

        <div class="text-center py-5 mt-3 border-top" style="border-color: var(--glass-border)!important;">
            <i class="fas fa-check-circle fa-2x text-success mb-3 d-block"></i>
            <h4 style="color:var(--text-main);">Panduan Selesai</h4>
            <p class="text-muted">Seluruh 193 langkah panduan telah tersaji lengkap dengan screenshot asli.</p>
            <button onclick="window.scrollTo({top:0,behavior:'smooth'})" class="btn btn-outline-primary mt-2">
                <i class="fas fa-arrow-up me-1"></i> Kembali ke Atas
            </button>
        </div>
    </div>
</div>

<button onclick="window.print()" class="btn btn-primary print-btn">
    <i class="fas fa-print me-1"></i> Cetak PDF
</button>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('imgLightbox').classList.add('show');
}

document.addEventListener('DOMContentLoaded', function () {
    // Progress bar
    window.addEventListener('scroll', function () {
        const scrolled = (document.documentElement.scrollTop / (document.documentElement.scrollHeight - document.documentElement.clientHeight)) * 100;
        document.getElementById('progressBar').style.width = scrolled + '%';
    });

    // Scrollspy
    const toc = document.getElementById('guideToc');
    const sections = document.querySelectorAll('.subsection[id], .menu-section[id]');
    const tocLinks = document.querySelectorAll('.guide-toc .toc-link, .guide-toc .toc-menu-link');

    window.addEventListener('scroll', function () {
        let current = '';
        sections.forEach(sec => {
            if (window.scrollY >= sec.offsetTop - 140) current = sec.id;
        });
        tocLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
                // Auto-scroll toc
                const linkRect = link.getBoundingClientRect();
                const tocRect = toc.getBoundingClientRect();
                if (linkRect.top < tocRect.top + 20 || linkRect.bottom > tocRect.bottom - 20) {
                    toc.scrollTop += linkRect.top - tocRect.top - tocRect.height / 2;
                }
            }
        });
    });

    // Live search
    const search = document.getElementById('guideSearch');
    search.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.searchable-section').forEach(sec => {
            let secMatch = false;
            sec.querySelectorAll('.searchable-subsection').forEach(sub => {
                let subMatch = false;
                sub.querySelectorAll('.searchable-step').forEach(step => {
                    const txt = step.innerText.toLowerCase();
                    const match = !q || txt.includes(q);
                    step.classList.toggle('d-none-search', !match);
                    if (match) subMatch = secMatch = true;
                });
                sub.classList.toggle('d-none-search', !subMatch && q);
            });
            sec.classList.toggle('d-none-search', !secMatch && q);
        });
    });
});
</script>
@endsection
