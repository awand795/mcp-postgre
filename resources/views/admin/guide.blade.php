import json
import os

target_file = r"d:\MCP Versi Web\mcp-postgresql\resources\views\admin\guide.blade.php"

guide_data = """@extends('layouts.admin')

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
                    ['no' => 1, 'text' => 'Halaman login kosong — sorot merah field Email', 'desc' => 'Field Email: masukkan email akun admin.', 'img_text' => 'Step 1: Login Kosong\\nSorot Merah Email'],
                    ['no' => 2, 'text' => 'Mengetik email — sorot merah field Email yang sudah diisi', 'desc' => 'Pastikan format email valid.', 'img_text' => 'Step 2: Isi Email\\nSorot Merah Email'],
                    ['no' => 3, 'text' => 'Mengetik password — sorot merah field Password', 'desc' => 'Field Password: masukkan password akun.', 'img_text' => 'Step 3: Isi Password\\nSorot Merah Password'],
                    ['no' => 4, 'text' => 'Tombol "Login" — sorot merah lingkaran besar di tombol Login', 'desc' => 'Klik tombol untuk masuk.', 'img_text' => 'Step 4: Klik Login\\nLingkaran Merah Tombol'],
                    ['no' => 5, 'text' => 'Setelah login berhasil', 'desc' => 'Sistem akan me-redirect Anda ke halaman Chatbot atau Dashboard.', 'img_text' => 'Step 5: Login Sukses\\nRedirect Dashboard'],
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
                    ['no' => 1, 'text' => 'Halaman dashboard penuh — sorot merah setiap kartu statistik', 'desc' => 'Setiap kartu statistik menunjukkan ringkasan data.', 'img_text' => 'Step 1: Dashboard Penuh\\nSorot Semua Kartu'],
                    ['no' => 2, 'text' => 'Kartu statistik pertama — sorot merah angka dan labelnya', 'desc' => 'Menunjukkan metrik spesifik.', 'img_text' => 'Step 2: Kartu Pertama\\nSorot Angka & Label'],
                    ['no' => 3, 'text' => 'Area grafik/chart', 'desc' => 'Menampilkan tren jika tersedia.', 'img_text' => 'Step 3: Area Grafik\\nSorot Grafik'],
                    ['no' => 4, 'text' => 'Sidebar navigasi — sorot merah setiap menu di sidebar', 'desc' => 'Navigasi semua menu yang tersedia.', 'img_text' => 'Step 4: Sidebar Navigasi\\nSorot Menu'],
                    ['no' => 5, 'text' => 'Tombol dark mode/light mode — sorot merah tombol toggle tema', 'desc' => 'Tombol toggle tema di header.', 'img_text' => 'Step 5: Toggle Tema\\nSorot Ikon Bulan/Matahari'],
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
                    ['no' => 1, 'text' => 'Halaman users lengkap — sorot merah tabel user', 'desc' => 'Menampilkan tabel list user.', 'img_text' => 'Step 1: Halaman Users\\nSorot Tabel Utama'],
                    ['no' => 2, 'text' => 'Tombol "Tambah User" (kanan atas) — sorot merah dengan lingkaran besar', 'desc' => 'Tombol biru di kanan atas.', 'img_text' => 'Step 2: Tambah User\\nLingkaran Tombol Tambah'],
                    ['no' => 3, 'text' => 'Tombol "Template" — sorot merah', 'desc' => 'Download format excel.', 'img_text' => 'Step 3: Tombol Template\\nSorot Tombol'],
                    ['no' => 4, 'text' => 'Tombol "Import" — sorot merah', 'desc' => 'Upload data excel.', 'img_text' => 'Step 4: Tombol Import\\nSorot Tombol'],
                    ['no' => 5, 'text' => 'Tombol "Export" — sorot merah', 'desc' => 'Download data user.', 'img_text' => 'Step 5: Tombol Export\\nSorot Tombol'],
                    ['no' => 6, 'text' => 'Form filter/search — sorot merah input pencarian', 'desc' => 'Kolom cari nama dan filter role.', 'img_text' => 'Step 6: Form Filter\\nSorot Input Search'],
                    ['no' => 7, 'text' => 'Setelah mengisi filter dan klik tombol "Filter"', 'desc' => 'Hasil dari tabel terfilter.', 'img_text' => 'Step 7: Hasil Filter\\nSorot Tabel Terfilter'],
                    ['no' => 8, 'text' => 'Tombol "Reset" filter', 'desc' => 'Mengembalikan ke default.', 'img_text' => 'Step 8: Reset Filter\\nSorot Tombol Reset'],
                    ['no' => 9, 'text' => 'Kolom tabel', 'desc' => 'Kolom Nama, Email, Role, Aksi dll.', 'img_text' => 'Step 9: Kolom Tabel\\nSorot Header Kolom'],
                ]
            ],
            [
                'id' => 'user-add',
                'title' => '3B. Tambah User Baru',
                'steps' => [
                    ['no' => 10, 'text' => 'Modal "Tambah User" yang baru terbuka', 'desc' => 'Judul modal tambah.', 'img_text' => 'Step 10: Modal Tambah User\\nSorot Judul Modal'],
                    ['no' => 11, 'text' => 'Field Nama Lengkap', 'desc' => 'Isi nama lengkap user.', 'img_text' => 'Step 11: Field Nama\\nSorot Input Nama'],
                    ['no' => 12, 'text' => 'Field Email', 'desc' => 'Isi email yang valid.', 'img_text' => 'Step 12: Field Email\\nSorot Input Email'],
                    ['no' => 13, 'text' => 'Field Password', 'desc' => 'Minimal 8 karakter.', 'img_text' => 'Step 13: Field Password\\nSorot Input Password'],
                    ['no' => 14, 'text' => 'Dropdown Role', 'desc' => 'Pilihan role yang tersedia.', 'img_text' => 'Step 14: Dropdown Role\\nSorot Select Role'],
                    ['no' => 15, 'text' => 'Checkbox Is Admin', 'desc' => 'Jelaskan kapan dicentang.', 'img_text' => 'Step 15: Is Admin\\nSorot Checkbox'],
                    ['no' => 16, 'text' => 'Tombol Simpan di modal', 'desc' => 'Menyimpan data.', 'img_text' => 'Step 16: Tombol Simpan\\nLingkaran Besar'],
                    ['no' => 17, 'text' => 'Tombol Batal di modal', 'desc' => 'Membatalkan aksi.', 'img_text' => 'Step 17: Tombol Batal\\nSorot Batal'],
                    ['no' => 18, 'text' => 'Notifikasi sukses', 'desc' => 'User berhasil dibuat.', 'img_text' => 'Step 18: Notifikasi Sukses\\nSorot Toast/Alert'],
                ]
            ],
            [
                'id' => 'user-edit',
                'title' => '3C. Edit User',
                'steps' => [
                    ['no' => 19, 'text' => 'Tombol Edit (ikon pensil)', 'desc' => 'Di kolom aksi.', 'img_text' => 'Step 19: Tombol Edit\\nSorot Ikon Pensil'],
                    ['no' => 20, 'text' => 'Modal Edit User terbuka', 'desc' => 'Modal edit.', 'img_text' => 'Step 20: Modal Edit\\nSorot Form'],
                    ['no' => 21, 'text' => 'Field terisi data lama', 'desc' => 'Form dengan data eksisting.', 'img_text' => 'Step 21: Data Lama\\nSorot Input Terisi'],
                    ['no' => 22, 'text' => 'Tombol Update', 'desc' => 'Menyimpan pembaruan.', 'img_text' => 'Step 22: Tombol Update\\nSorot Tombol'],
                    ['no' => 23, 'text' => 'Notifikasi sukses', 'desc' => 'Edit berhasil.', 'img_text' => 'Step 23: Notif Edit\\nSorot Alert'],
                ]
            ],
            [
                'id' => 'user-delete',
                'title' => '3D. Hapus User',
                'steps' => [
                    ['no' => 24, 'text' => 'Tombol Hapus (ikon tempat sampah)', 'desc' => 'Di kolom aksi.', 'img_text' => 'Step 24: Tombol Hapus\\nLingkaran Merah Besar'],
                    ['no' => 25, 'text' => 'Dialog konfirmasi "Apakah Anda yakin?"', 'desc' => 'Sorot tombol "Ya, Hapus".', 'img_text' => 'Step 25: Dialog Hapus\\nSorot Tombol Ya'],
                    ['no' => 26, 'text' => 'Setelah user berhasil dihapus', 'desc' => 'User hilang dari tabel.', 'img_text' => 'Step 26: User Hilang\\nSorot Tabel'],
                ]
            ],
            [
                'id' => 'user-ai',
                'title' => '3E. AI Config per User',
                'steps' => [
                    ['no' => 27, 'text' => 'Tombol konfigurasi AI', 'desc' => 'Ikon otak di kolom Aksi.', 'img_text' => 'Step 27: Tombol AI Config\\nSorot Ikon Otak'],
                    ['no' => 28, 'text' => 'Modal "AI Config" terbuka', 'desc' => 'Sorot seluruh modal.', 'img_text' => 'Step 28: Modal AI\\nSorot Modal'],
                    ['no' => 29, 'text' => 'Daftar AI Models', 'desc' => 'Bisa di-toggle per user.', 'img_text' => 'Step 29: AI Models\\nSorot Toggle Switch'],
                    ['no' => 30, 'text' => 'Daftar API Keys', 'desc' => 'Bisa di-toggle per user.', 'img_text' => 'Step 30: API Keys\\nSorot Toggle Switch'],
                    ['no' => 31, 'text' => 'Tombol Save Config', 'desc' => 'Simpan setting AI.', 'img_text' => 'Step 31: Save Config\\nSorot Tombol'],
                ]
            ],
            [
                'id' => 'user-mcp',
                'title' => '3F. MCP Token Management',
                'steps' => [
                    ['no' => 32, 'text' => 'Tombol "Generate MCP Token"', 'desc' => 'Sorot ikon kunci.', 'img_text' => 'Step 32: Generate Token\\nSorot Ikon Kunci'],
                    ['no' => 33, 'text' => 'Modal konfirmasi generate', 'desc' => 'Konfirmasi.', 'img_text' => 'Step 33: Konfirmasi Token\\nSorot Modal'],
                    ['no' => 34, 'text' => 'Hasil token yang baru dibuat', 'desc' => 'Tampilkan token dan sorot.', 'img_text' => 'Step 34: Token Tampil\\nSorot Area Token'],
                    ['no' => 35, 'text' => 'Tombol "Revoke Token"', 'desc' => 'Mencabut token.', 'img_text' => 'Step 35: Revoke Token\\nSorot Tombol Revoke'],
                    ['no' => 36, 'text' => 'Dialog konfirmasi revoke', 'desc' => 'Membatalkan fungsi token.', 'img_text' => 'Step 36: Konfirmasi Revoke\\nSorot Tombol Ya'],
                ]
            ],
            [
                'id' => 'user-rls',
                'title' => '3G. Table Filters (Row Level Security)',
                'steps' => [
                    ['no' => 37, 'text' => 'Tombol "Table Filters"', 'desc' => 'Ikon filter.', 'img_text' => 'Step 37: Tombol RLS\\nSorot Ikon Filter'],
                    ['no' => 38, 'text' => 'Modal Table Filters terbuka', 'desc' => 'Seluruh area.', 'img_text' => 'Step 38: Modal RLS\\nSorot Modal'],
                    ['no' => 39, 'text' => 'Dropdown memilih tabel', 'desc' => 'Memilih nama tabel.', 'img_text' => 'Step 39: Pilih Tabel\\nSorot Dropdown'],
                    ['no' => 40, 'text' => 'Field filter (kolom, operator, nilai)', 'desc' => 'Masing-masing input field.', 'img_text' => 'Step 40: Aturan Filter\\nSorot Kolom/Operator/Nilai'],
                    ['no' => 41, 'text' => 'Tombol "Tambah Filter"', 'desc' => 'Menambahkan kondisional ekstra.', 'img_text' => 'Step 41: Tambah Aturan\\nSorot Tombol Tambah'],
                    ['no' => 42, 'text' => 'Tombol "Preview Filter"', 'desc' => 'Uji hasil query filter.', 'img_text' => 'Step 42: Preview Filter\\nSorot Tombol Preview'],
                    ['no' => 43, 'text' => 'Tombol "Copy Filter dari User Lain"', 'desc' => 'Duplikasi setingan.', 'img_text' => 'Step 43: Copy Filter\\nSorot Tombol Copy'],
                    ['no' => 44, 'text' => 'Tombol "Simpan Filters"', 'desc' => 'Menetapkan RLS permanen.', 'img_text' => 'Step 44: Simpan RLS\\nSorot Tombol Simpan'],
                ]
            ],
            [
                'id' => 'user-import',
                'title' => '3H. Import/Export User',
                'steps' => [
                    ['no' => 45, 'text' => 'Modal Import User', 'desc' => 'Area upload file.', 'img_text' => 'Step 45: Modal Import\\nSorot Area Upload'],
                    ['no' => 46, 'text' => 'Pilih file Excel', 'desc' => 'Tombol Choose File.', 'img_text' => 'Step 46: Choose File\\nSorot Tombol'],
                    ['no' => 47, 'text' => 'Tombol "Import"', 'desc' => 'Menjalankan impor.', 'img_text' => 'Step 47: Tombol Import\\nSorot Tombol'],
                    ['no' => 48, 'text' => 'Notifikasi hasil import', 'desc' => 'Berhasil/error.', 'img_text' => 'Step 48: Notifikasi Import\\nSorot Alert'],
                    ['no' => 49, 'text' => 'Proses Export', 'desc' => 'Tombol Export diklik.', 'img_text' => 'Step 49: Export Data\\nSorot Tombol Export'],
                    ['no' => 50, 'text' => 'File Excel terdownload', 'desc' => 'Notifikasi unduhan browser.', 'img_text' => 'Step 50: Excel Terunduh\\nSorot Bar Unduhan'],
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
                    ['no' => 1, 'text' => 'Halaman roles lengkap', 'desc' => 'Sidebar kiri dan panel kanan.', 'img_text' => 'Step 1: Halaman Roles\\nSorot Kiri Kanan'],
                    ['no' => 2, 'text' => 'Tombol "Tambah Role" (kanan atas)', 'desc' => 'Lingkaran besar.', 'img_text' => 'Step 2: Tambah Role\\nLingkaran Besar'],
                    ['no' => 3, 'text' => 'Daftar role di sidebar kiri', 'desc' => 'Setiap role dalam daftar.', 'img_text' => 'Step 3: Daftar Role\\nSorot Setiap Role'],
                    ['no' => 4, 'text' => 'Panel permissions di kanan', 'desc' => 'Tabel permissions.', 'img_text' => 'Step 4: Panel Permissions\\nSorot Tabel Kanan'],
                ]
            ],
            [
                'id' => 'role-add',
                'title' => '4B. Tambah Role Baru',
                'steps' => [
                    ['no' => 5, 'text' => 'Modal "Tambah Role"', 'desc' => 'Judul modal.', 'img_text' => 'Step 5: Modal Role\\nSorot Judul'],
                    ['no' => 6, 'text' => 'Field Nama Role', 'desc' => 'Contoh: Operator, Manager.', 'img_text' => 'Step 6: Field Nama\\nSorot Input'],
                    ['no' => 7, 'text' => 'Field Deskripsi', 'desc' => 'Input deskripsi.', 'img_text' => 'Step 7: Field Deskripsi\\nSorot Input'],
                    ['no' => 8, 'text' => 'Tombol Simpan', 'desc' => 'Menyimpan.', 'img_text' => 'Step 8: Simpan Role\\nLingkaran Besar'],
                    ['no' => 9, 'text' => 'Role baru muncul di sidebar kiri', 'desc' => 'Hasil pembuatan role.', 'img_text' => 'Step 9: Role Terbuat\\nSorot Sidebar Kiri'],
                ]
            ],
            [
                'id' => 'role-edit',
                'title' => '4C. Edit Role',
                'steps' => [
                    ['no' => 10, 'text' => 'Ikon Edit (pensil kuning)', 'desc' => 'Di setiap baris role.', 'img_text' => 'Step 10: Tombol Edit\\nSorot Ikon'],
                    ['no' => 11, 'text' => 'Modal Edit Role dengan data lama', 'desc' => 'Field yang bisa diubah.', 'img_text' => 'Step 11: Edit Modal\\nSorot Form'],
                    ['no' => 12, 'text' => 'Tombol Update', 'desc' => 'Simpan perubahan.', 'img_text' => 'Step 12: Tombol Update\\nSorot Tombol'],
                    ['no' => 13, 'text' => 'Notifikasi sukses', 'desc' => 'Muncul di pojok.', 'img_text' => 'Step 13: Sukses Update\\nSorot Alert'],
                ]
            ],
            [
                'id' => 'role-delete',
                'title' => '4D. Hapus Role',
                'steps' => [
                    ['no' => 14, 'text' => 'Ikon Hapus (tong merah)', 'desc' => 'Untuk mendelete grup.', 'img_text' => 'Step 14: Tombol Hapus\\nLingkaran Besar'],
                    ['no' => 15, 'text' => 'Dialog konfirmasi', 'desc' => 'Tombol Ya Hapus.', 'img_text' => 'Step 15: Konfirmasi\\nSorot Ya Hapus'],
                    ['no' => 16, 'text' => 'Setelah role terhapus', 'desc' => 'Data hilang dari UI.', 'img_text' => 'Step 16: Role Hilang\\nSorot Sidebar Kiri'],
                ]
            ],
            [
                'id' => 'role-permissions',
                'title' => '4E. Atur Permissions Tabel per Role',
                'steps' => [
                    ['no' => 17, 'text' => 'Saat klik salah satu role', 'desc' => 'Highlight role terpilih di kiri.', 'img_text' => 'Step 17: Pilih Role\\nSorot Role Kiri'],
                    ['no' => 18, 'text' => 'Panel permissions terbuka', 'desc' => 'Daftar tabel.', 'img_text' => 'Step 18: Tabel Permission\\nSorot Panel Kanan'],
                    ['no' => 19, 'text' => 'Kolom-kolom permission', 'desc' => 'SELECT, INSERT, UPDATE, DELETE.', 'img_text' => 'Step 19: Header Kolom\\nSorot Header'],
                    ['no' => 20, 'text' => 'Checkbox permission dicentang', 'desc' => 'Mencentang izin.', 'img_text' => 'Step 20: Checkbox\\nSorot Checkbox'],
                    ['no' => 21, 'text' => 'Tombol "Select All" / "Clear All"', 'desc' => 'Pilih semua otomatis.', 'img_text' => 'Step 21: Select All\\nSorot Tombol'],
                    ['no' => 22, 'text' => 'Filter tabel permission', 'desc' => 'Input search tabel.', 'img_text' => 'Step 22: Search Tabel\\nSorot Input Search'],
                    ['no' => 23, 'text' => 'Tombol "Simpan Akses"', 'desc' => 'Lingkaran BESAR di bawah.', 'img_text' => 'Step 23: Simpan Akses\\nLingkaran BESAR'],
                    ['no' => 24, 'text' => 'Notifikasi sukses', 'desc' => 'Permission berhasil disave.', 'img_text' => 'Step 24: Notif Sukses\\nSorot Alert'],
                    ['no' => 25, 'text' => 'Indikator "Ada perubahan belum disimpan"', 'desc' => 'Peringatan visual.', 'img_text' => 'Step 25: Unsaved Warning\\nSorot Indikator'],
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
                    ['no' => 1, 'text' => 'Halaman databases lengkap', 'desc' => 'Grid/list database.', 'img_text' => 'Step 1: Halaman DB\\nSorot Grid DB'],
                    ['no' => 2, 'text' => 'Tombol "Tambah Database" (kanan atas)', 'desc' => 'Lingkaran besar.', 'img_text' => 'Step 2: Tambah DB\\nLingkaran Besar'],
                    ['no' => 3, 'text' => 'Tombol "Test All"', 'desc' => 'Test semua koneksi sekaligus.', 'img_text' => 'Step 3: Test All\\nSorot Tombol'],
                    ['no' => 4, 'text' => 'Toolbar filter', 'desc' => 'Input search, filter driver, list view.', 'img_text' => 'Step 4: Toolbar\\nSorot Semua Elemen'],
                    ['no' => 5, 'text' => 'Setelah klik "Test All"', 'desc' => 'Health bar yang muncul.', 'img_text' => 'Step 5: Health Bar\\nSorot Bar Progress'],
                    ['no' => 6, 'text' => 'Card database dalam grid view', 'desc' => 'Setiap elemen card.', 'img_text' => 'Step 6: Card DB\\nSorot Card'],
                    ['no' => 7, 'text' => 'Badge status koneksi', 'desc' => 'Connected/Failed/Not Tested.', 'img_text' => 'Step 7: Badge Status\\nSorot Badge'],
                ]
            ],
            [
                'id' => 'db-add',
                'title' => '5B. Tambah Database Baru',
                'steps' => [
                    ['no' => 8, 'text' => 'Modal "Tambah Database" terbuka', 'desc' => 'Judul modal.', 'img_text' => 'Step 8: Modal Tambah DB\\nSorot Judul'],
                    ['no' => 9, 'text' => 'Field Nama Database', 'desc' => 'Nama alias/label.', 'img_text' => 'Step 9: Field Nama\\nSorot Input'],
                    ['no' => 10, 'text' => 'Field Kode Unik', 'desc' => 'Identifier unik huruf kecil.', 'img_text' => 'Step 10: Kode Unik\\nSorot Input'],
                    ['no' => 11, 'text' => 'Dropdown Driver', 'desc' => 'PostgreSQL, MySQL, MariaDB.', 'img_text' => 'Step 11: Dropdown Driver\\nSorot Select'],
                    ['no' => 12, 'text' => 'Field Host', 'desc' => 'IP/hostname server.', 'img_text' => 'Step 12: Field Host\\nSorot Input'],
                    ['no' => 13, 'text' => 'Field Port', 'desc' => 'Default 5432/3306.', 'img_text' => 'Step 13: Field Port\\nSorot Input'],
                    ['no' => 14, 'text' => 'Field Database Name', 'desc' => 'Nama database.', 'img_text' => 'Step 14: Field DB Name\\nSorot Input'],
                    ['no' => 15, 'text' => 'Field Username', 'desc' => 'Akun database.', 'img_text' => 'Step 15: Username\\nSorot Input'],
                    ['no' => 16, 'text' => 'Field Password', 'desc' => 'Password database.', 'img_text' => 'Step 16: Password\\nSorot Input'],
                    ['no' => 17, 'text' => 'Toggle Active', 'desc' => 'Aktifkan koneksi.', 'img_text' => 'Step 17: Toggle Active\\nSorot Switch'],
                    ['no' => 18, 'text' => 'Field Schema', 'desc' => 'Untuk PostgreSQL, default: public.', 'img_text' => 'Step 18: Field Schema\\nSorot Input'],
                    ['no' => 19, 'text' => 'Tombol Test Connection (di dalam modal)', 'desc' => 'Test sebelum simpan.', 'img_text' => 'Step 19: Test Koneksi\\nSorot Tombol Test'],
                    ['no' => 20, 'text' => 'Hasil test connection', 'desc' => 'Sukses/Gagal notifikasi.', 'img_text' => 'Step 20: Hasil Test\\nSorot Alert Box'],
                    ['no' => 21, 'text' => 'Tombol Simpan', 'desc' => 'Lingkaran besar.', 'img_text' => 'Step 21: Tombol Simpan\\nLingkaran Besar'],
                    ['no' => 22, 'text' => 'Database baru muncul di grid', 'desc' => 'Card baru.', 'img_text' => 'Step 22: DB Baru Tampil\\nSorot Grid Baru'],
                ]
            ],
            [
                'id' => 'db-edit',
                'title' => '5C. Edit Database',
                'steps' => [
                    ['no' => 23, 'text' => 'Tombol Edit di card database', 'desc' => 'Ikon pensil.', 'img_text' => 'Step 23: Edit Card\\nSorot Ikon Pensil'],
                    ['no' => 24, 'text' => 'Modal Edit Database dengan data lama', 'desc' => 'Semua field terisi.', 'img_text' => 'Step 24: Modal Edit\\nSorot Form'],
                    ['no' => 25, 'text' => 'Tombol Update', 'desc' => 'Kirim form.', 'img_text' => 'Step 25: Tombol Update\\nSorot Tombol'],
                    ['no' => 26, 'text' => 'Notifikasi sukses', 'desc' => 'Berhasil diedit.', 'img_text' => 'Step 26: Notif Update\\nSorot Alert'],
                ]
            ],
            [
                'id' => 'db-test',
                'title' => '5D. Test Koneksi Individual',
                'steps' => [
                    ['no' => 27, 'text' => 'Tombol Test Connection', 'desc' => 'Ikon heartbeat di card.', 'img_text' => 'Step 27: Ikon Ping\\nSorot Ikon'],
                    ['no' => 28, 'text' => 'Loading state', 'desc' => 'Animasi loading test.', 'img_text' => 'Step 28: Loading Spinner\\nSorot Spinner'],
                    ['no' => 29, 'text' => 'Hasil test sukses', 'desc' => 'Badge Connected hijau.', 'img_text' => 'Step 29: Connected Hijau\\nSorot Badge'],
                    ['no' => 30, 'text' => 'Hasil test gagal', 'desc' => 'Badge Failed merah.', 'img_text' => 'Step 30: Failed Merah\\nSorot Badge'],
                    ['no' => 31, 'text' => 'Detail error message', 'desc' => 'Teks error koneksi database.', 'img_text' => 'Step 31: Error Message\\nSorot Teks Error'],
                ]
            ],
            [
                'id' => 'db-delete',
                'title' => '5E. Hapus Database',
                'steps' => [
                    ['no' => 32, 'text' => 'Tombol Hapus di card database', 'desc' => 'Ikon tempat sampah.', 'img_text' => 'Step 32: Hapus Card\\nSorot Ikon'],
                    ['no' => 33, 'text' => 'Dialog konfirmasi', 'desc' => 'Tombol Ya Hapus.', 'img_text' => 'Step 33: Konfirmasi Hapus\\nSorot Ya'],
                    ['no' => 34, 'text' => 'Setelah database terhapus', 'desc' => 'Hilang dari view.', 'img_text' => 'Step 34: DB Terhapus\\nSorot Grid'],
                ]
            ],
            [
                'id' => 'db-schema',
                'title' => '5F. Lihat Schema Database',
                'steps' => [
                    ['no' => 35, 'text' => 'Tombol "Lihat Schema"', 'desc' => 'Melihat list tabel.', 'img_text' => 'Step 35: Tombol Schema\\nSorot Ikon'],
                    ['no' => 36, 'text' => 'Daftar schema/tabel yang tampil', 'desc' => 'Hierarki struktur db.', 'img_text' => 'Step 36: Daftar Tabel\\nSorot Modal Tabel'],
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
                    ['no' => 1, 'text' => 'Halaman AI Management penuh', 'desc' => 'Semua elemen layout.', 'img_text' => 'Step 1: Halaman Penuh\\nSorot Seluruh Elemen'],
                    ['no' => 2, 'text' => '4 Kartu statistik', 'desc' => 'Providers, API Keys, Rate Limited, Active Models.', 'img_text' => 'Step 2: Kartu Statistik\\nSorot ke-4 Kartu'],
                    ['no' => 3, 'text' => 'Tombol "Add Provider"', 'desc' => 'Kanan atas, lingkaran BESAR.', 'img_text' => 'Step 3: Tambah Provider\\nLingkaran BESAR'],
                ]
            ],
            [
                'id' => 'ai-card',
                'title' => '6B. Provider Card',
                'steps' => [
                    ['no' => 4, 'text' => 'Provider card lengkap dengan nomor penjelasan', 'desc' => 'Emoji, Nama, Kode, Status, Toggle, dll.', 'img_text' => 'Step 4: Provider Card\\nBeri Nomor Tiap Elemen'],
                ]
            ],
            [
                'id' => 'ai-provider-add',
                'title' => '6C. Tambah Provider Baru',
                'steps' => [
                    ['no' => 5, 'text' => 'Modal "Add Provider AI Baru"', 'desc' => 'Judul modal.', 'img_text' => 'Step 5: Modal Tambah Provider\\nSorot Judul'],
                    ['no' => 6, 'text' => 'Field Nama Provider', 'desc' => 'Contoh: Groq, Ollama.', 'img_text' => 'Step 6: Field Nama Provider\\nSorot Input'],
                    ['no' => 7, 'text' => 'Field Kode Unik', 'desc' => 'Contoh: groq.', 'img_text' => 'Step 7: Kode Unik Provider\\nSorot Input'],
                    ['no' => 8, 'text' => 'Field Base URL API', 'desc' => 'Endpoint provider eksternal.', 'img_text' => 'Step 8: Base URL\\nSorot Input'],
                    ['no' => 9, 'text' => 'Tombol Tambah Provider', 'desc' => 'Lingkaran besar simpan.', 'img_text' => 'Step 9: Simpan Provider\\nLingkaran Besar'],
                    ['no' => 10, 'text' => 'Tombol Batal', 'desc' => 'Membatalkan form.', 'img_text' => 'Step 10: Tombol Batal\\nSorot Tombol'],
                    ['no' => 11, 'text' => 'Provider baru muncul di grid', 'desc' => 'Masuk dashboard AI.', 'img_text' => 'Step 11: Card Muncul\\nSorot Card Baru'],
                ]
            ],
            [
                'id' => 'ai-provider-toggle',
                'title' => '6D. Toggle Provider (Aktif/Nonaktif)',
                'steps' => [
                    ['no' => 12, 'text' => 'Toggle switch ON (biru/aktif)', 'desc' => 'Menghidupkan.', 'img_text' => 'Step 12: Toggle ON\\nSorot Switch Biru'],
                    ['no' => 13, 'text' => 'Toggle switch OFF (abu-abu)', 'desc' => 'Mematikan sementara.', 'img_text' => 'Step 13: Toggle OFF\\nSorot Switch Abu'],
                    ['no' => 14, 'text' => 'Card menjadi transparan/redup', 'desc' => 'Visual dinonaktifkan.', 'img_text' => 'Step 14: Card Redup\\nSorot Card Transparan'],
                ]
            ],
            [
                'id' => 'ai-provider-delete',
                'title' => '6E. Hapus Provider',
                'steps' => [
                    ['no' => 15, 'text' => 'Tombol hapus (ikon tempat sampah)', 'desc' => 'Di header card.', 'img_text' => 'Step 15: Tombol Hapus\\nSorot Ikon Sampah'],
                    ['no' => 16, 'text' => 'Provider built-in terkunci (🔒)', 'desc' => 'OpenAI, Gemini tidak bisa dihapus.', 'img_text' => 'Step 16: Ikon Gembok\\nSorot Ikon 🔒'],
                    ['no' => 17, 'text' => 'Dialog konfirmasi Hapus', 'desc' => 'Ya, Hapus.', 'img_text' => 'Step 17: Konfirmasi Hapus\\nSorot Tombol Ya'],
                    ['no' => 18, 'text' => 'Provider berhasil dihapus', 'desc' => 'Layar setelah card hilang.', 'img_text' => 'Step 18: Provider Hilang\\nSorot Grid'],
                ]
            ],
            [
                'id' => 'ai-keys-main',
                'title' => '6F. Tab Keys — Kelola API Key',
                'steps' => [
                    ['no' => 19, 'text' => 'Tab "🔑 Keys" aktif', 'desc' => 'Berada di tab Keys.', 'img_text' => 'Step 19: Tab Keys\\nSorot Tab'],
                    ['no' => 20, 'text' => 'Daftar key di panel body card', 'desc' => 'List API Keys.', 'img_text' => 'Step 20: Daftar Key\\nSorot Baris Key'],
                    ['no' => 21, 'text' => 'Setiap elemen di key row', 'desc' => 'Health dot, tombol aksi.', 'img_text' => 'Step 21: Elemen Baris\\nSorot Tiap Ikon'],
                    ['no' => 22, 'text' => 'Badge status key', 'desc' => 'OK, OFF, LIMIT.', 'img_text' => 'Step 22: Badge Status\\nSorot Titik Warna'],
                    ['no' => 23, 'text' => 'Badge usage count', 'desc' => 'Berapa kali dipakai.', 'img_text' => 'Step 23: Usage Count\\nSorot Badge ↗'],
                    ['no' => 24, 'text' => 'Badge token count', 'desc' => 'Total token konsumsi.', 'img_text' => 'Step 24: Token Count\\nSorot Badge ◈'],
                    ['no' => 25, 'text' => 'Ditambahkan oleh', 'desc' => 'Nama admin.', 'img_text' => 'Step 25: Added By\\nSorot Tooltip'],
                ]
            ],
            [
                'id' => 'ai-keys-add',
                'title' => '6G. Tambah API Key Baru',
                'steps' => [
                    ['no' => 26, 'text' => 'Tombol "Add Key"', 'desc' => 'Footer card.', 'img_text' => 'Step 26: Add Key Button\\nSorot Tombol Bawah'],
                    ['no' => 27, 'text' => 'Modal "Add API Key" terbuka', 'desc' => 'Membuka form.', 'img_text' => 'Step 27: Modal Add Key\\nSorot Judul Modal'],
                    ['no' => 28, 'text' => 'Field Nama Key (alias)', 'desc' => 'Contoh: Key Utama Production.', 'img_text' => 'Step 28: Field Nama Key\\nSorot Input Nama'],
                    ['no' => 29, 'text' => 'Field API Key', 'desc' => 'Isi token sebenarnya.', 'img_text' => 'Step 29: Field API Key\\nSorot Input Token'],
                    ['no' => 30, 'text' => 'Tombol show/hide password', 'desc' => 'Ikon mata.', 'img_text' => 'Step 30: Ikon Mata\\nSorot Toggle Visibility'],
                    ['no' => 31, 'text' => 'Tombol Simpan', 'desc' => 'Lingkaran besar.', 'img_text' => 'Step 31: Simpan Key\\nLingkaran Besar'],
                    ['no' => 32, 'text' => 'Tombol Batal', 'desc' => 'Batal simpan.', 'img_text' => 'Step 32: Batal Key\\nSorot Tombol'],
                    ['no' => 33, 'text' => 'Key baru muncul', 'desc' => 'Ada di dalam daftar list.', 'img_text' => 'Step 33: Key Muncul\\nSorot List Baru'],
                ]
            ],
            [
                'id' => 'ai-keys-edit',
                'title' => '6H. Edit API Key',
                'steps' => [
                    ['no' => 34, 'text' => 'Tombol Edit (ikon pensil)', 'desc' => 'Ubah data key.', 'img_text' => 'Step 34: Edit Key\\nSorot Ikon Pensil'],
                    ['no' => 35, 'text' => 'Modal "Edit API Key"', 'desc' => 'Semua field.', 'img_text' => 'Step 35: Modal Edit Key\\nSorot Modal'],
                    ['no' => 36, 'text' => 'Field Nama Key', 'desc' => 'Ubah alias.', 'img_text' => 'Step 36: Edit Nama Key\\nSorot Input'],
                    ['no' => 37, 'text' => 'Field API Key dengan hint', 'desc' => 'Kosongkan jika tidak diubah.', 'img_text' => 'Step 37: Hint Kosongkan\\nSorot Teks Hint'],
                    ['no' => 38, 'text' => 'Checkbox Aktifkan Key', 'desc' => 'Uncheck menonaktifkan sementara.', 'img_text' => 'Step 38: Checkbox Aktif\\nSorot Checkbox'],
                    ['no' => 39, 'text' => 'Tombol Simpan', 'desc' => 'Menyimpan update.', 'img_text' => 'Step 39: Update Key\\nSorot Tombol'],
                ]
            ],
            [
                'id' => 'ai-keys-delete',
                'title' => '6I. Hapus API Key',
                'steps' => [
                    ['no' => 40, 'text' => 'Tombol hapus (tempat sampah)', 'desc' => 'Lingkaran besar.', 'img_text' => 'Step 40: Hapus Key\\nLingkaran Besar'],
                    ['no' => 41, 'text' => 'Dialog konfirmasi Hapus API Key', 'desc' => 'Klik yes.', 'img_text' => 'Step 41: Konfirm Hapus Key\\nSorot Tombol Ya'],
                    ['no' => 42, 'text' => 'Setelah key terhapus', 'desc' => 'Hilang dari view.', 'img_text' => 'Step 42: Key Hilang\\nSorot Daftar Kosong'],
                ]
            ],
            [
                'id' => 'ai-keys-limit',
                'title' => '6J. Reset Limit API Key',
                'steps' => [
                    ['no' => 43, 'text' => 'Tombol Reset Limit', 'desc' => 'Ikon refresh kuning.', 'img_text' => 'Step 43: Reset Limit\\nSorot Ikon Putar'],
                    ['no' => 44, 'text' => 'Alert bar merah di atas daftar', 'desc' => 'X key kena rate limit.', 'img_text' => 'Step 44: Banner Merah\\nSorot Banner'],
                    ['no' => 45, 'text' => 'Dialog konfirmasi reset', 'desc' => 'Reset ulang threshold limit.', 'img_text' => 'Step 45: Konfirm Reset\\nSorot Tombol Ya'],
                    ['no' => 46, 'text' => 'Setelah reset: badge kembali ● OK', 'desc' => 'Status kembali normal.', 'img_text' => 'Step 46: Kembali OK\\nSorot Titik Hijau'],
                ]
            ],
            [
                'id' => 'ai-health',
                'title' => '6K. Health Check API Key',
                'steps' => [
                    ['no' => 47, 'text' => 'Tombol Health Check', 'desc' => 'Ikon gelombang/sinyal.', 'img_text' => 'Step 47: Health Check\\nSorot Ikon Sinyal'],
                    ['no' => 48, 'text' => 'Modal API Key Health Check', 'desc' => 'Judul modal.', 'img_text' => 'Step 48: Modal Health\\nSorot Judul'],
                    ['no' => 49, 'text' => 'Dropdown Model untuk diuji', 'desc' => 'Auto-detect atau model manual.', 'img_text' => 'Step 49: Dropdown Model Test\\nSorot Select'],
                    ['no' => 50, 'text' => 'Checkbox "Ketik model manual"', 'desc' => 'Opsi input string manual.', 'img_text' => 'Step 50: Checkbox Manual\\nSorot Checkbox'],
                    ['no' => 51, 'text' => 'Input manual model', 'desc' => 'Ketik string (misal gemini-2.0).', 'img_text' => 'Step 51: Input Manual\\nSorot Input Teks'],
                    ['no' => 52, 'text' => 'Tombol Cek Sekarang', 'desc' => 'Lingkaran besar aksi ping.', 'img_text' => 'Step 52: Tombol Cek\\nLingkaran Besar'],
                    ['no' => 53, 'text' => 'Loading state', 'desc' => 'Spinner menghubungi provider.', 'img_text' => 'Step 53: Loading Ping\\nSorot Spinner'],
                    ['no' => 54, 'text' => 'Hasil Sukses (Banner hijau)', 'desc' => 'HTTP 200.', 'img_text' => 'Step 54: Hasil Sukses\\nSorot Banner Hijau'],
                    ['no' => 55, 'text' => 'Hasil Gagal/Rate Limited', 'desc' => 'Banner merah dan error detail.', 'img_text' => 'Step 55: Hasil Gagal\\nSorot Banner Merah'],
                    ['no' => 56, 'text' => 'Tombol Cek Ulang', 'desc' => 'Muncul setelah result keluar.', 'img_text' => 'Step 56: Tombol Ulang\\nSorot Tombol'],
                    ['no' => 57, 'text' => 'Tombol Tutup', 'desc' => 'Menutup dialog.', 'img_text' => 'Step 57: Tombol Tutup\\nSorot Tombol'],
                ]
            ],
            [
                'id' => 'ai-models-main',
                'title' => '6L. Tab Models — Kelola Model AI',
                'steps' => [
                    ['no' => 58, 'text' => 'Tab "🧠 Models"', 'desc' => 'Berada di tab Models.', 'img_text' => 'Step 58: Tab Models\\nSorot Tab Kedua'],
                    ['no' => 59, 'text' => 'Daftar model sebagai chip/badge', 'desc' => 'Tampilan chip list.', 'img_text' => 'Step 59: Badge Model\\nSorot Chip Kapsul'],
                    ['no' => 60, 'text' => 'Model chip AKTIF', 'desc' => 'Warna indigo/biru.', 'img_text' => 'Step 60: Model Aktif\\nSorot Warna Biru'],
                    ['no' => 61, 'text' => 'Model chip NONAKTIF', 'desc' => 'Warna abu-abu.', 'img_text' => 'Step 61: Model Mati\\nSorot Warna Abu'],
                    ['no' => 62, 'text' => 'Cara klik chip untuk toggle', 'desc' => 'Aktifkan/nonaktifkan dari UI.', 'img_text' => 'Step 62: Klik Toggle\\nSorot Tindakan Klik'],
                    ['no' => 63, 'text' => 'Tombol × (hapus)', 'desc' => 'Tombol silang pada chip.', 'img_text' => 'Step 63: Tombol Silang\\nSorot × di Chip'],
                ]
            ],
            [
                'id' => 'ai-models-add',
                'title' => '6M. Tambah Model AI Baru',
                'steps' => [
                    ['no' => 64, 'text' => 'Tombol "Add Model"', 'desc' => 'Di footer provider card.', 'img_text' => 'Step 64: Add Model Button\\nSorot Tombol Bawah'],
                    ['no' => 65, 'text' => 'Modal "Add Model AI"', 'desc' => 'Menambah entitas model.', 'img_text' => 'Step 65: Modal Add Model\\nSorot Modal'],
                    ['no' => 66, 'text' => 'Field ID Model (system name)', 'desc' => 'Contoh: gpt-4o, gemini-1.5-pro.', 'img_text' => 'Step 66: ID Model Tepat\\nSorot Input ID'],
                    ['no' => 67, 'text' => 'Field Display Name', 'desc' => 'Nama tampilan ramah pengguna.', 'img_text' => 'Step 67: Display Name\\nSorot Input Display'],
                    ['no' => 68, 'text' => 'Tombol Simpan Model', 'desc' => 'Simpan model ke database.', 'img_text' => 'Step 68: Simpan Model\\nLingkaran Besar'],
                    ['no' => 69, 'text' => 'Model baru muncul', 'desc' => 'Menjadi chip biru.', 'img_text' => 'Step 69: Chip Baru\\nSorot Chip Terbuat'],
                ]
            ],
            [
                'id' => 'ai-models-delete',
                'title' => '6N. Hapus Model AI',
                'steps' => [
                    ['no' => 70, 'text' => 'Tombol × di model chip', 'desc' => 'Hapus selamanya.', 'img_text' => 'Step 70: Klik Silang Hapus\\nSorot Tanda Silang'],
                    ['no' => 71, 'text' => 'Dialog konfirmasi Hapus Model', 'desc' => 'Klik ok.', 'img_text' => 'Step 71: Konfirm Hapus Model\\nSorot Ya Hapus'],
                    ['no' => 72, 'text' => 'Model terhapus dari chip list', 'desc' => 'Hilang dari UI.', 'img_text' => 'Step 72: Chip Hilang\\nSorot Area Kosong'],
                ]
            ]
        ]
    ]
];
@endphp

<style>
    .guide-container { max-width: 1400px; margin: 0 auto; font-family: 'Outfit', sans-serif; position: relative; }
    .progress-container { width: 100%; height: 6px; background: var(--glass-border); position: fixed; top: 0; left: 0; z-index: 9999; }
    .progress-bar { height: 100%; background: var(--primary); width: 0%; transition: width 0.1s; }
    
    .sidebar-guide { width: 280px; position: sticky; top: 90px; height: calc(100vh - 120px); overflow-y: auto; background: var(--card-bg); border-right: 1px solid var(--glass-border); padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-sm); }
    .sidebar-guide::-webkit-scrollbar { width: 6px; }
    .sidebar-guide::-webkit-scrollbar-thumb { background: var(--glass-border2); border-radius: 10px; }
    .nav-pills .nav-link { color: var(--text-muted); padding: 0.5rem 1rem; border-radius: 8px; font-weight: 500; font-size: 0.95rem; margin-bottom: 0.25rem; }
    .nav-pills .nav-link.active { background-color: rgba(99, 102, 241, 0.15); color: var(--primary); font-weight: 600; }
    .nav-pills .nav-link:hover { background-color: var(--glass-border); color: var(--text-main); }
    
    .content-guide { flex: 1; padding: 1rem 2rem; }
    .menu-section { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: 20px; padding: 2.5rem; margin-bottom: 3rem; box-shadow: var(--shadow-md); scroll-margin-top: 100px; }
    .menu-title { font-size: 1.8rem; font-weight: 700; color: var(--primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 15px; }
    .subsection { scroll-margin-top: 120px; }
    .subsection h4 { color: var(--text-main); font-weight: 600; padding-bottom: 0.5rem; margin-top: 3rem; border-bottom: 2px dashed var(--glass-border2); }
    
    .guide-step { display: flex; gap: 24px; padding: 2rem 0; border-bottom: 1px solid var(--glass-border2); align-items: flex-start; }
    .guide-step:last-child { border-bottom: none; padding-bottom: 0; }
    .step-number { width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.3rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4); }
    .step-title { font-size: 1.25rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem; }
    
    .screenshot-wrapper { margin-top: 1.5rem; border-radius: 12px; overflow: hidden; border: 3px solid rgba(239, 68, 68, 0.6); box-shadow: 0 8px 25px rgba(0,0,0,0.15); position: relative; background: #1e293b; text-align: center; }
    .mockup-img { width: 100%; max-height: 500px; object-fit: contain; background: #1e293b; display: block; transition: transform 0.3s ease; }
    .screenshot-wrapper:hover .mockup-img { transform: scale(1.02); }
    
    .print-btn { position: fixed; bottom: 30px; right: 30px; z-index: 1000; border-radius: 50px; padding: 12px 24px; font-weight: bold; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.5); }
    
    /* Search Highlight */
    .highlight-search { background-color: rgba(234, 179, 8, 0.4); padding: 0 4px; border-radius: 4px; }
    .d-none-search { display: none !important; }
    
    @media print {
        .sidebar-guide, .progress-container, .print-btn, header, footer, .navbar { display: none !important; }
        .content-guide { width: 100%; padding: 0; }
        .menu-section { break-inside: avoid; border: none; box-shadow: none; margin-bottom: 2rem; padding: 0; }
        .guide-step { break-inside: avoid; }
    }
</style>

<div class="progress-container">
    <div class="progress-bar" id="progressBar"></div>
</div>

<div class="guide-container">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <a href="{{ route('chatbot') }}" class="btn btn-secondary btn-sm mb-2"><i class="fas fa-arrow-left"></i> Kembali ke Chatbot</a>
            <h1 style="color: var(--text-main); font-weight: 800; font-size: 2.2rem;">Panduan Administrator Lengkap</h1>
            <p class="text-muted">Dokumentasi Exhaustive 193 Langkah (v3.0 - Full UI/UX Mockup)</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary print-btn">
                <i class="fas fa-print"></i> Cetak PDF
            </button>
        </div>
    </div>

    <div class="d-flex" style="gap: 2.5rem;">
        <!-- SIDEBAR NAVIGATION -->
        <div class="sidebar-guide d-none d-lg-block">
            <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Navigasi Panduan</h6>
            <div class="input-group mb-4 shadow-sm">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-search"></i></span>
                <input type="text" id="guideSearch" class="form-control border-start-0" placeholder="Cari langkah..." style="font-size: 0.9rem;">
            </div>
            
            <nav id="navbar-guide" class="nav flex-column nav-pills" style="font-size: 0.95rem;">
                @foreach($guideData as $menu)
                    <a class="nav-link fw-bold mb-1 mt-2 text-wrap" href="#{{ $menu['id'] }}">
                        <i class="{{ $menu['icon'] }} w-20px text-center me-1"></i> {{ explode('(', $menu['title'])[0] }}
                    </a>
                    <div class="ps-3 border-start border-2 ms-2 mb-2" style="border-color: var(--glass-border) !important;">
                        @foreach($menu['sections'] as $sec)
                            <a class="nav-link py-1 text-wrap" style="font-size: 0.85rem;" href="#{{ $sec['id'] }}">{{ $sec['title'] }}</a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </div>

        <!-- MAIN CONTENT -->
        <div class="content-guide flex-grow-1" id="mainGuideContent">
            @foreach($guideData as $menu)
                <section id="{{ $menu['id'] }}" class="menu-section searchable-section">
                    <h2 class="menu-title border-bottom border-light pb-3">
                        <i class="{{ $menu['icon'] }} text-primary"></i> {{ $menu['title'] }}
                    </h2>
                    <p class="text-muted mb-4 fs-5">{{ $menu['desc'] }}</p>

                    @foreach($menu['sections'] as $sec)
                        <div id="{{ $sec['id'] }}" class="subsection mb-5 searchable-subsection">
                            <h4 class="mb-4 text-info"><i class="fas fa-bookmark me-2 fs-6"></i>{{ $sec['title'] }}</h4>
                            
                            @foreach($sec['steps'] as $step)
                                <div class="guide-step searchable-step">
                                    <div class="step-number">{{ $step['no'] }}</div>
                                    <div class="step-details flex-grow-1 w-100">
                                        <h5 class="step-title">{{ $step['text'] }}</h5>
                                        <p class="text-muted" style="font-size: 1.05rem;">{!! $step['desc'] !!}</p>
                                        
                                        @php
                                            $encodedText = urlencode($step['img_text']);
                                            $mockupUrl = "https://placehold.co/1280x720/1e293b/ef4444?text=" . $encodedText;
                                        @endphp
                                        
                                        <div class="screenshot-wrapper">
                                            <img src="{{ $mockupUrl }}" class="mockup-img" alt="Step {{ $step['no'] }}" loading="lazy">
                                            <div class="position-absolute bottom-0 start-0 w-100 bg-danger text-white text-center py-2 opacity-75" style="font-size: 0.85rem; font-weight: bold; letter-spacing: 2px;">
                                                <i class="fas fa-crosshairs fa-spin me-2"></i> AREA ANOTASI MERAH DITERAPKAN PADA UI
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </section>
            @endforeach
            
            <div class="text-center py-5 mt-5 border-top" style="border-color: var(--glass-border) !important;">
                <h3 class="text-success mb-3"><i class="fas fa-check-circle fa-2x"></i></h3>
                <h4>Panduan Selesai</h4>
                <p class="text-muted">Anda telah membaca seluruh dokumentasi Exhaustive (193 Langkah).</p>
                <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="btn btn-outline-primary mt-3">
                    <i class="fas fa-arrow-up"></i> Kembali ke Atas
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Scroll Progress Bar
    window.addEventListener('scroll', function() {
        let winScroll = document.body.scrollTop || document.documentElement.scrollTop;
        let height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        let scrolled = (winScroll / height) * 100;
        document.getElementById("progressBar").style.width = scrolled + "%";
    });

    // 2. Scroll Spy Navigation
    const sections = document.querySelectorAll('.subsection, .menu-section');
    const navLinks = document.querySelectorAll('.nav-pills .nav-link');
    
    window.addEventListener('scroll', function() {
        let current = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (pageYOffset >= (sectionTop - 200)) {
                current = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href').substring(1) === current) {
                link.classList.add('active');
                // Auto scroll sidebar to keep active item in view
                const sidebar = document.querySelector('.sidebar-guide');
                const activeRect = link.getBoundingClientRect();
                const sidebarRect = sidebar.getBoundingClientRect();
                if (activeRect.top < sidebarRect.top || activeRect.bottom > sidebarRect.bottom) {
                    sidebar.scrollTop = link.offsetTop - sidebarRect.height / 2;
                }
            }
        });
    });

    // 3. Live Search Filter
    const searchInput = document.getElementById('guideSearch');
    const allSteps = document.querySelectorAll('.searchable-step');
    const allSubsections = document.querySelectorAll('.searchable-subsection');
    const allSections = document.querySelectorAll('.searchable-section');

    searchInput.addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase().trim();
        
        if (term === '') {
            allSteps.forEach(el => el.classList.remove('d-none-search'));
            allSubsections.forEach(el => el.classList.remove('d-none-search'));
            allSections.forEach(el => el.classList.remove('d-none-search'));
            return;
        }

        allSections.forEach(section => {
            let sectionHasMatch = false;
            
            const subsections = section.querySelectorAll('.searchable-subsection');
            subsections.forEach(sub => {
                let subHasMatch = false;
                const steps = sub.querySelectorAll('.searchable-step');
                
                steps.forEach(step => {
                    const text = step.querySelector('.step-title').textContent.toLowerCase() + 
                                 ' ' + step.querySelector('.text-muted').textContent.toLowerCase();
                    
                    if (text.includes(term)) {
                        step.classList.remove('d-none-search');
                        subHasMatch = true;
                        sectionHasMatch = true;
                    } else {
                        step.classList.add('d-none-search');
                    }
                });

                if (subHasMatch) {
                    sub.classList.remove('d-none-search');
                } else {
                    sub.classList.add('d-none-search');
                }
            });

            if (sectionHasMatch) {
                section.classList.remove('d-none-search');
            } else {
                section.classList.add('d-none-search');
            }
        });
    });
});
</script>
@endsection
"""

with open(target_file, "w", encoding="utf-8") as f:
    f.write(guide_data)

print(f"Successfully generated exhaustive guide in {target_file}")
