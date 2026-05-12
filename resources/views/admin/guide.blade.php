@extends('layouts.admin')

@section('page-title', 'Panduan Lengkap Administrator (Exhaustive Guide)')

@section('content')
@php
$guideData = [
    [
        'id' => 'menu-1-login',
        'title' => 'MENU 1: HALAMAN LOGIN (/login)',
        'icon' => 'fas fa-sign-in-alt',
        'desc' => 'Halaman login adalah gerbang utama masuk ke sistem Admin Panel MCP Chatbot. Hanya pengguna dengan akun yang terdaftar dan aktif yang dapat mengakses panel administrasi. Pastikan Anda menggunakan email dan password yang benar sesuai akun yang diberikan oleh Super Admin.',
        'sections' => [
            [
                'id' => 'login-auth',
                'title' => 'Langkah Login ke Admin Panel',
                'steps' => [
                    ['no' => 1, 'text' => 'Buka Halaman Login', 'desc' => 'Akses URL <strong>http://[domain]/login</strong> melalui browser. Anda akan melihat form login dengan dua field: Email dan Password. Pastikan koneksi internet stabil sebelum melanjutkan.', 'real_img' => 'real_login_page.png', 'img_text' => 'Step 1: Halaman Login'],
                    ['no' => 2, 'text' => 'Isi Field Email', 'desc' => 'Klik pada field <strong>Email</strong> dan ketik alamat email akun admin Anda. Contoh: <code>admin@perusahaan.com</code>. Pastikan format email benar (mengandung @ dan domain). Jika email salah, sistem akan menampilkan pesan error.', 'real_img' => 'real_login_email.png', 'img_text' => 'Step 2: Isi Email'],
                    ['no' => 3, 'text' => 'Isi Field Password', 'desc' => 'Klik pada field <strong>Password</strong> dan ketik password akun Anda. Password ditampilkan sebagai titik-titik (••••) untuk keamanan. Anda dapat klik ikon mata (👁) di kanan field untuk melihat/sembunyikan password. Password minimal 8 karakter.', 'real_img' => 'real_login_password.png', 'img_text' => 'Step 3: Isi Password'],
                    ['no' => 4, 'text' => 'Klik Tombol Login', 'desc' => 'Klik tombol biru <strong>"Login"</strong> untuk mengirim data autentikasi. Sistem akan memverifikasi email dan password Anda. Proses ini biasanya memakan waktu 1-2 detik. Jika gagal, periksa kembali email/password dan pastikan Caps Lock tidak aktif.', 'real_img' => 'real_login_button.png', 'img_text' => 'Step 4: Klik Login'],
                    ['no' => 5, 'text' => 'Login Berhasil — Masuk ke Halaman Utama', 'desc' => 'Setelah login berhasil, sistem otomatis mengarahkan Anda ke <strong>halaman Chatbot</strong> sebagai halaman utama. Dari sini Anda bisa mengakses menu Admin melalui sidebar atau langsung buka URL <code>/admin/</code>. Jika muncul pesan "These credentials do not match", berarti email atau password salah.', 'real_img' => 'real_login_success.png', 'img_text' => 'Step 5: Login Sukses'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-2-dashboard',
        'title' => 'MENU 2: DASHBOARD (/admin/)',
        'icon' => 'fas fa-chart-pie',
        'desc' => 'Dashboard adalah halaman utama Admin Panel yang menampilkan ringkasan statistik sistem secara real-time. Dari halaman ini, admin dapat memantau jumlah user aktif, database yang terhubung, provider AI yang tersedia, dan aktivitas chatbot. Akses via menu sidebar atau URL <code>/admin/</code>.',
        'sections' => [
            [
                'id' => 'dashboard-overview',
                'title' => 'Membaca dan Memahami Dashboard',
                'steps' => [
                    ['no' => 1, 'text' => 'Tampilan Halaman Dashboard', 'desc' => 'Halaman dashboard menampilkan <strong>4 kartu statistik utama</strong> di bagian atas: Total Users, Total Databases, Total AI Providers, dan Total API Keys. Di bawahnya terdapat grafik aktivitas dan tabel ringkasan. Semua data diperbarui secara otomatis setiap kali halaman dibuka.', 'real_img' => 'real_dashboard.png', 'img_text' => 'Step 1: Dashboard Penuh'],
                    ['no' => 2, 'text' => 'Kartu Statistik — Total Users', 'desc' => 'Kartu <strong>Total Users</strong> menampilkan jumlah seluruh pengguna yang terdaftar di sistem (aktif maupun nonaktif). Klik kartu ini untuk langsung diarahkan ke halaman Management User. Angka ini mencakup semua role: Super Admin, Admin, dan user biasa.', 'real_img' => 'real_dashboard.png', 'img_text' => 'Step 2: Kartu Total Users'],
                    ['no' => 3, 'text' => 'Kartu Statistik — Total Database & AI', 'desc' => 'Kartu <strong>Total Databases</strong> menampilkan jumlah koneksi database yang terdaftar (terlepas dari status connected/failed). Kartu <strong>AI Providers</strong> menampilkan jumlah provider AI aktif seperti OpenAI, Gemini, Groq. Kartu <strong>API Keys</strong> menampilkan total key yang tersedia.', 'real_img' => 'real_dashboard.png', 'img_text' => 'Step 3: Kartu DB & AI'],
                    ['no' => 4, 'text' => 'Sidebar Navigasi Admin', 'desc' => 'Sidebar di sisi kiri berisi semua menu navigasi Admin Panel: <strong>Dashboard, Management User, Management Role, Management Database, AI Management,</strong> dan <strong>Panduan</strong>. Klik menu mana pun untuk berpindah halaman. Menu yang sedang aktif ditandai dengan highlight warna berbeda. Sidebar dapat diciutkan dengan klik tombol toggle di pojok atas.', 'real_img' => 'real_dashboard.png', 'img_text' => 'Step 4: Sidebar Navigasi'],
                    ['no' => 5, 'text' => 'Toggle Tema Terang/Gelap', 'desc' => 'Di header atas terdapat tombol ikon <strong>bulan 🌙</strong> atau <strong>matahari ☀️</strong> untuk beralih antara mode gelap (Dark Mode) dan mode terang (Light Mode). Preferensi tema tersimpan di browser dan akan diingat saat Anda kembali membuka halaman. Mode gelap sangat disarankan untuk penggunaan malam hari.', 'real_img' => 'real_dash_darkmode.png', 'img_text' => 'Step 5: Toggle Tema'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-3-user',
        'title' => 'MENU 3: MANAGEMENT USER (/admin/users)',
        'icon' => 'fas fa-users',
        'desc' => 'Halaman Management User adalah pusat pengelolaan seluruh akun pengguna sistem. Admin dapat membuat user baru, mengubah data user, mengatur hak akses AI (model & API key per user), mengelola MCP Token, dan mengonfigurasi Row Level Security (RLS) untuk membatasi data yang dapat dilihat AI per user. Akses melalui sidebar menu <strong>Management User</strong> atau URL <code>/admin/users</code>.',
        'sections' => [
            [
                'id' => 'user-main',
                'title' => '3A. Halaman Utama Management User',
                'steps' => [
                    ['no' => 1, 'text' => 'Tampilan Tabel Daftar User', 'desc' => 'Halaman ini menampilkan <strong>tabel semua pengguna</strong> yang terdaftar. Setiap baris menampilkan: No, Nama, Email, Role, Status Admin, dan kolom Aksi. Data dapat diurutkan dengan klik header kolom. Tabel mendukung pagination jika jumlah user banyak.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 1: Halaman Users'],
                    ['no' => 2, 'text' => 'Tombol "Tambah User"', 'desc' => 'Tombol <strong>hijau/biru "+ Tambah User"</strong> berada di pojok kanan atas. Klik tombol ini untuk membuka form modal pembuatan user baru. Hanya Super Admin dan Admin yang memiliki akses untuk membuat user baru.', 'real_img' => 'real_user_tambah_btn.png', 'img_text' => 'Step 2: Tambah User'],
                    ['no' => 3, 'text' => 'Tombol "Template Excel"', 'desc' => 'Tombol <strong>"Template"</strong> mengunduh file Excel berformat khusus yang dapat diisi dengan data user secara massal. Template ini berisi kolom: Nama, Email, Password, Role. Gunakan file ini sebagai panduan untuk import data user dalam jumlah besar.', 'real_img' => 'real_user_template_btn.png', 'img_text' => 'Step 3: Template Excel'],
                    ['no' => 4, 'text' => 'Tombol "Import User"', 'desc' => 'Tombol <strong>"Import"</strong> membuka dialog upload file Excel. Upload file template yang sudah diisi data user. Sistem akan memvalidasi format dan membuat akun user secara otomatis. Cocok untuk migrasi data awal atau penambahan user massal.', 'real_img' => 'real_user_tambah_btn.png', 'img_text' => 'Step 4: Import User'],
                    ['no' => 5, 'text' => 'Tombol "Export User"', 'desc' => 'Tombol <strong>"Export"</strong> mengunduh seluruh data user yang tampil saat ini (termasuk filter aktif) ke dalam file Excel. File unduhan berisi: Nama, Email, Role, Status, dan tanggal pembuatan akun. Berguna untuk laporan atau backup data user.', 'real_img' => 'real_user_export_btn.png', 'img_text' => 'Step 5: Export User'],
                    ['no' => 6, 'text' => 'Form Filter / Pencarian User', 'desc' => 'Di atas tabel terdapat form filter dengan field <strong>Cari Nama/Email</strong> dan dropdown <strong>Filter Role</strong>. Ketik nama atau email untuk mencari user tertentu secara real-time. Pilih role dari dropdown untuk memfilter berdasarkan role. Gunakan kedua filter bersamaan untuk pencarian lebih spesifik.', 'real_img' => 'real_user_filter_form.png', 'img_text' => 'Step 6: Filter User'],
                    ['no' => 7, 'text' => 'Hasil Filter Tabel', 'desc' => 'Setelah mengisi filter dan menekan Enter atau klik tombol <strong>"Filter"</strong>, tabel akan menampilkan hanya user yang sesuai kriteria. Di atas tabel muncul info jumlah hasil. Tombol <strong>"Reset"</strong> menghapus semua filter dan menampilkan kembali seluruh user.', 'real_img' => 'real_user_filter_form.png', 'img_text' => 'Step 7: Hasil Filter'],
                    ['no' => 8, 'text' => 'Reset Filter', 'desc' => 'Klik tombol <strong>"Reset"</strong> atau hapus teks di field pencarian untuk menghapus semua filter aktif. Tabel akan kembali menampilkan seluruh daftar user tanpa filter. Fungsi ini berguna saat ingin beralih dari pencarian spesifik ke tampilan lengkap.', 'real_img' => 'real_user_filter_form.png', 'img_text' => 'Step 8: Reset Filter'],
                    ['no' => 9, 'text' => 'Kolom-Kolom Tabel User', 'desc' => 'Tabel user memiliki kolom: <strong>No</strong> (nomor urut), <strong>Nama</strong> (nama lengkap user), <strong>Email</strong> (email login), <strong>Role</strong> (badge role yang ditetapkan), <strong>Admin</strong> (centang jika Is Admin), dan <strong>Aksi</strong> (tombol Edit, Hapus, AI Config, Token, RLS). Lebar kolom menyesuaikan konten secara otomatis.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 9: Kolom Tabel'],
                ]
            ],
            [
                'id' => 'user-add',
                'title' => '3B. Tambah User Baru',
                'steps' => [
                    ['no' => 10, 'text' => 'Buka Modal Tambah User', 'desc' => 'Klik tombol <strong>"+ Tambah User"</strong> di kanan atas halaman. Modal dialog akan muncul di tengah layar berisi form pembuatan akun baru. Modal ini memiliki beberapa field yang wajib diisi sebelum bisa menyimpan.', 'real_img' => 'real_tambah_user_modal.png', 'img_text' => 'Step 10: Modal Tambah User'],
                    ['no' => 11, 'text' => 'Isi Field Nama Lengkap', 'desc' => 'Ketik <strong>nama lengkap</strong> user di field pertama. Nama ini akan tampil di tabel user, profil, dan riwayat aktivitas. Nama harus menggunakan karakter alfabet dan spasi, minimal 3 karakter. Contoh: <code>Budi Santoso</code>.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 11: Isi Nama'],
                    ['no' => 12, 'text' => 'Isi Field Email', 'desc' => 'Ketik <strong>alamat email</strong> yang akan digunakan sebagai username login. Email harus unik (belum terdaftar di sistem). Sistem akan menolak jika email sudah digunakan oleh akun lain. Format harus valid: <code>nama@domain.com</code>.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 12: Isi Email'],
                    ['no' => 13, 'text' => 'Isi Field Password', 'desc' => 'Buat <strong>password</strong> untuk akun user ini. Password minimal 8 karakter, disarankan kombinasi huruf besar, huruf kecil, angka, dan simbol untuk keamanan. Password ini akan langsung aktif saat user pertama kali login. Informasikan password kepada user yang bersangkutan.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 13: Isi Password'],
                    ['no' => 14, 'text' => 'Pilih Role dari Dropdown', 'desc' => 'Klik dropdown <strong>"Pilih Role"</strong> untuk memilih jabatan/akses group user. Role menentukan tabel database mana yang bisa diakses AI saat user chatting. Role dibuat di menu <strong>Management Role</strong>. Jika belum ada role yang sesuai, buat terlebih dahulu di halaman Role.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 14: Pilih Role'],
                    ['no' => 15, 'text' => 'Centang Opsi "Is Admin"', 'desc' => 'Checkbox <strong>"Is Admin"</strong> menentukan apakah user ini mendapat akses ke Admin Panel. Centang jika user adalah administrator sistem. Jangan centang untuk user biasa (non-admin). User dengan Is Admin = true dapat mengakses semua menu di <code>/admin/</code>.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 15: Is Admin'],
                    ['no' => 16, 'text' => 'Klik Tombol Simpan', 'desc' => 'Setelah semua field diisi dengan benar, klik tombol <strong>"Simpan"</strong> berwarna hijau/biru di bagian bawah modal. Sistem akan memvalidasi semua input. Jika ada field kosong atau format salah, akan muncul pesan validasi merah di bawah field terkait. Jika sukses, modal tertutup dan user baru muncul di tabel.', 'real_img' => 'real_user_save_btn.png', 'img_text' => 'Step 16: Simpan User'],
                    ['no' => 17, 'text' => 'Tombol Batal', 'desc' => 'Jika ingin membatalkan pembuatan user, klik tombol <strong>"Batal"</strong> atau ikon <strong>X</strong> di pojok kanan atas modal. Semua data yang sudah diisi akan hilang dan modal tertutup tanpa menyimpan apapun. Konfirmasi pembatalan tidak diperlukan.', 'real_img' => 'real_user_tambah_modal2.png', 'img_text' => 'Step 17: Tombol Batal'],
                    ['no' => 18, 'text' => 'Notifikasi Sukses Pembuatan User', 'desc' => 'Setelah berhasil menyimpan, muncul <strong>notifikasi hijau</strong> di pojok kanan atas layar bertuliskan "User berhasil dibuat" atau sejenisnya. User baru langsung muncul di tabel. User dapat langsung login menggunakan email dan password yang baru dibuat.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 18: User Berhasil Dibuat'],
                ]
            ],
            [
                'id' => 'user-edit',
                'title' => '3C. Edit / Perbarui Data User',
                'steps' => [
                    ['no' => 19, 'text' => 'Klik Ikon Edit (Pensil)', 'desc' => 'Di kolom <strong>Aksi</strong> setiap baris user, klik ikon <strong>pensil kuning ✏️</strong> untuk membuka form edit user tersebut. Semua data user yang sudah ada akan otomatis terisi di form edit sehingga Anda hanya perlu mengubah field yang diperlukan saja.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 19: Tombol Edit'],
                    ['no' => 20, 'text' => 'Modal Edit User Terbuka', 'desc' => 'Modal <strong>"Edit User"</strong> muncul dengan semua field sudah terisi data user yang dipilih. Anda dapat mengubah: Nama Lengkap, Email, Password (kosongkan jika tidak ingin mengubah), Role, dan status Is Admin. Perhatikan bahwa mengubah email user akan mengubah kredensial login mereka.', 'real_img' => 'real_edit_user_modal.png', 'img_text' => 'Step 20: Modal Edit User'],
                    ['no' => 21, 'text' => 'Ubah Data yang Diperlukan', 'desc' => 'Klik field yang ingin diubah dan ketik data baru. Untuk <strong>Password</strong>: biarkan kosong jika tidak ingin mengganti password lama. Isi password baru jika ingin mengubah password user. Untuk <strong>Role</strong>: klik dropdown dan pilih role baru sesuai kebutuhan. Semua perubahan belum tersimpan hingga tombol Update diklik.', 'real_img' => 'real_user_edit_modal2.png', 'img_text' => 'Step 21: Ubah Data'],
                    ['no' => 22, 'text' => 'Klik Tombol Update', 'desc' => 'Setelah selesai mengubah data, klik tombol <strong>"Update"</strong> atau <strong>"Simpan Perubahan"</strong> berwarna hijau di bagian bawah modal. Sistem akan memvalidasi input dan menyimpan perubahan. Jika email baru sudah digunakan akun lain, akan muncul pesan error.', 'real_img' => 'real_user_edit_modal2.png', 'img_text' => 'Step 22: Klik Update'],
                    ['no' => 23, 'text' => 'Notifikasi Edit Berhasil', 'desc' => 'Setelah update sukses, modal tertutup otomatis dan muncul <strong>notifikasi hijau</strong> di pojok kanan atas. Data user di tabel langsung diperbarui tanpa perlu refresh halaman. Jika user sedang login saat datanya diubah (terutama email), mereka perlu login ulang.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 23: Edit Berhasil'],
                ]
            ],
            [
                'id' => 'user-delete',
                'title' => '3D. Hapus User',
                'steps' => [
                    ['no' => 24, 'text' => 'Klik Ikon Hapus (Tempat Sampah)', 'desc' => 'Di kolom <strong>Aksi</strong>, klik ikon <strong>tempat sampah merah 🗑️</strong> di baris user yang ingin dihapus. Proses penghapusan bersifat permanen dan tidak dapat dibatalkan, jadi pastikan Anda memilih user yang tepat. Hindari menghapus akun Super Admin yang masih aktif digunakan.', 'real_img' => 'real_user_row_btns.png', 'img_text' => 'Step 24: Tombol Hapus'],
                    ['no' => 25, 'text' => 'Dialog Konfirmasi Hapus (SweetAlert)', 'desc' => 'Muncul dialog konfirmasi <strong>SweetAlert</strong> di tengah layar yang menanyakan: <em>"Apakah Anda yakin ingin menghapus user ini?"</em>. Dialog memiliki dua tombol: <strong>"Ya, Hapus!"</strong> (merah) untuk melanjutkan penghapusan, dan <strong>"Batal"</strong> untuk membatalkan. Klik di luar dialog atau tekan Escape juga membatalkan.', 'real_img' => 'real_hapus_user.png', 'img_text' => 'Step 25: Konfirmasi Hapus'],
                    ['no' => 26, 'text' => 'User Berhasil Dihapus', 'desc' => 'Setelah konfirmasi, sistem menghapus akun user beserta semua data terkait (AI config, RLS filters, MCP token). Muncul <strong>notifikasi hijau</strong> "User berhasil dihapus". Baris user hilang dari tabel. Jika user yang dihapus sedang login, sesi mereka akan otomatis diakhiri.', 'real_img' => 'real_user_list.png', 'img_text' => 'Step 26: User Terhapus'],
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
        'desc' => 'Halaman Management Role digunakan untuk membuat dan mengelola <strong>grup hak akses (role)</strong>. Setiap role menentukan tabel database mana yang boleh diakses oleh AI saat menjawab pertanyaan user. Contoh: Role <em>"Keuangan"</em> hanya dapat mengakses tabel transaksi dan laporan, sedangkan Role <em>"HRD"</em> hanya dapat mengakses tabel karyawan. Akses via sidebar <strong>Management Role</strong> atau URL <code>/admin/roles</code>.',
        'sections' => [
            [
                'id' => 'role-main',
                'title' => '4A. Halaman Utama Management Role',
                'steps' => [
                    ['no' => 1, 'text' => 'Tampilan Halaman Role', 'desc' => 'Halaman Role terbagi menjadi dua area: <strong>sidebar kiri</strong> berisi daftar semua role yang ada, dan <strong>panel kanan</strong> menampilkan tabel permissions untuk role yang dipilih. Klik salah satu role di sidebar kiri untuk melihat dan mengatur permissions-nya.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 1: Halaman Roles'],
                    ['no' => 2, 'text' => 'Tombol "Tambah Role"', 'desc' => 'Klik tombol <strong>"+ Tambah Role"</strong> di pojok kanan atas untuk membuat grup role baru. Role adalah kelompok akses yang nanti ditetapkan ke user. Buat role sesuai struktur organisasi seperti: Manajer, Operator, Keuangan, HRD, dll.', 'real_img' => 'real_role_tambah_btn.png', 'img_text' => 'Step 2: Tambah Role'],
                    ['no' => 3, 'text' => 'Daftar Role di Sidebar Kiri', 'desc' => 'Sidebar kiri menampilkan semua role yang sudah dibuat. Setiap item menampilkan nama role dan tombol aksi (edit/hapus). Klik nama role untuk memuat tabel permission-nya di panel kanan. Role yang sedang aktif/dipilih akan di-highlight dengan warna berbeda.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 3: Daftar Role'],
                    ['no' => 4, 'text' => 'Panel Permissions di Kanan', 'desc' => 'Panel kanan menampilkan <strong>tabel permissions</strong> berisi daftar semua tabel yang ada di database. Setiap tabel memiliki kolom: <strong>SELECT</strong> (baca), <strong>INSERT</strong> (tambah), <strong>UPDATE</strong> (ubah), <strong>DELETE</strong> (hapus). Centang kolom yang sesuai untuk memberikan izin akses ke role tersebut.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 4: Panel Permissions'],
                ]
            ],
            [
                'id' => 'role-add',
                'title' => '4B. Tambah Role Baru',
                'steps' => [
                    ['no' => 5, 'text' => 'Buka Modal Tambah Role', 'desc' => 'Klik tombol <strong>"+ Tambah Role"</strong>. Modal form akan muncul berisi field Nama Role dan Deskripsi. Form ini sederhana karena permissions diatur secara terpisah setelah role dibuat.', 'real_img' => 'real_tambah_role_modal.png', 'img_text' => 'Step 5: Modal Tambah Role'],
                    ['no' => 6, 'text' => 'Isi Field Nama Role', 'desc' => 'Ketik <strong>nama role</strong> yang deskriptif dan mudah dipahami. Contoh: <code>Keuangan</code>, <code>HRD</code>, <code>Manajer Operasional</code>, <code>Staf IT</code>. Nama role akan muncul di dropdown saat membuat atau mengedit user. Gunakan nama yang mencerminkan fungsi/jabatan di organisasi.', 'real_img' => 'real_role_tambah_modal2.png', 'img_text' => 'Step 6: Nama Role'],
                    ['no' => 7, 'text' => 'Isi Field Deskripsi', 'desc' => 'Isi <strong>deskripsi singkat</strong> mengenai role ini. Contoh: <em>"Akses untuk divisi keuangan, dapat melihat tabel transaksi dan laporan keuangan"</em>. Deskripsi membantu admin lain memahami tujuan role tanpa harus membuka detail permissions-nya.', 'real_img' => 'real_role_tambah_modal2.png', 'img_text' => 'Step 7: Deskripsi Role'],
                    ['no' => 8, 'text' => 'Klik Tombol Simpan', 'desc' => 'Klik tombol <strong>"Simpan"</strong> untuk membuat role baru. Role akan langsung muncul di sidebar kiri. <strong>Penting:</strong> Role yang baru dibuat belum memiliki permissions apapun. Langkah berikutnya adalah mengatur permissions tabel untuk role ini di panel kanan.', 'real_img' => 'real_role_tambah_modal2.png', 'img_text' => 'Step 8: Simpan Role'],
                    ['no' => 9, 'text' => 'Role Baru Muncul di Sidebar', 'desc' => 'Setelah disimpan, <strong>nama role baru</strong> langsung muncul di sidebar kiri. Klik nama role tersebut untuk mulai mengatur permissions tabel. Sebelum role ditetapkan ke user, pastikan permissions sudah dikonfigurasi dengan benar agar AI dapat mengakses tabel yang sesuai.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 9: Role Terbuat'],
                ]
            ],
            [
                'id' => 'role-edit',
                'title' => '4C. Edit Nama & Deskripsi Role',
                'steps' => [
                    ['no' => 10, 'text' => 'Klik Ikon Edit di Sidebar', 'desc' => 'Arahkan kursor ke nama role di sidebar kiri, lalu klik ikon <strong>pensil kuning ✏️</strong> yang muncul. Modal edit akan terbuka berisi nama dan deskripsi role saat ini yang siap untuk diubah.', 'real_img' => 'real_role_edit_modal.png', 'img_text' => 'Step 10: Edit Role'],
                    ['no' => 11, 'text' => 'Modal Edit Role Terbuka', 'desc' => 'Modal berisi field <strong>Nama Role</strong> dan <strong>Deskripsi</strong> yang sudah terisi data lama. Ubah sesuai kebutuhan. Perubahan nama role akan otomatis diperbarui di semua user yang menggunakan role tersebut.', 'real_img' => 'real_role_edit_modal.png', 'img_text' => 'Step 11: Form Edit Role'],
                    ['no' => 12, 'text' => 'Klik Tombol Update', 'desc' => 'Klik tombol <strong>"Update"</strong> untuk menyimpan perubahan nama/deskripsi. Permissions yang sudah diatur tidak akan terpengaruh oleh perubahan nama role.', 'real_img' => 'real_role_edit_modal.png', 'img_text' => 'Step 12: Update Role'],
                    ['no' => 13, 'text' => 'Notifikasi Sukses Edit Role', 'desc' => 'Muncul notifikasi <strong>hijau</strong> di pojok kanan atas. Nama role di sidebar langsung diperbarui. Semua user yang memiliki role ini tetap menggunakan permissions yang sama, hanya nama tampilannya yang berubah.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 13: Edit Berhasil'],
                ]
            ],
            [
                'id' => 'role-delete',
                'title' => '4D. Hapus Role',
                'steps' => [
                    ['no' => 14, 'text' => 'Klik Ikon Hapus di Sidebar', 'desc' => '<strong>Peringatan:</strong> Hapus role hanya jika tidak ada user yang menggunakannya. Klik ikon <strong>tempat sampah merah 🗑️</strong> di sebelah nama role di sidebar kiri. Jika role masih digunakan oleh user aktif, sistem mungkin menolak penghapusan atau meminta Anda mengalihkan user ke role lain terlebih dahulu.', 'real_img' => 'real_role_hapus_dialog.png', 'img_text' => 'Step 14: Hapus Role'],
                    ['no' => 15, 'text' => 'Dialog Konfirmasi Hapus Role', 'desc' => 'Muncul dialog SweetAlert: <em>"Apakah Anda yakin ingin menghapus role ini?"</em>. Klik <strong>"Ya, Hapus!"</strong> untuk melanjutkan. Penghapusan bersifat permanen — semua permissions yang sudah dikonfigurasi untuk role ini akan ikut terhapus.', 'real_img' => 'real_role_hapus_dialog.png', 'img_text' => 'Step 15: Konfirmasi Hapus'],
                    ['no' => 16, 'text' => 'Role Berhasil Dihapus', 'desc' => 'Role hilang dari sidebar kiri. Muncul notifikasi hijau konfirmasi. Jika ada user yang sebelumnya menggunakan role yang dihapus, role mereka akan menjadi kosong dan perlu diassign ulang melalui halaman Management User.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 16: Role Terhapus'],
                ]
            ],
            [
                'id' => 'role-permissions',
                'title' => '4E. Atur Permissions Tabel per Role',
                'steps' => [
                    ['no' => 17, 'text' => 'Pilih Role di Sidebar Kiri', 'desc' => 'Klik nama role di sidebar kiri untuk memilihnya. Role yang terpilih akan di-highlight dan panel kanan akan memuat <strong>tabel permissions</strong> dari database yang terhubung. Setiap baris tabel mewakili satu tabel database.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 17: Pilih Role'],
                    ['no' => 18, 'text' => 'Panel Permissions di Kanan', 'desc' => 'Panel kanan menampilkan daftar lengkap tabel database beserta kolom permission: <strong>SELECT</strong> (AI dapat membaca data), <strong>INSERT</strong> (AI dapat menambah data), <strong>UPDATE</strong> (AI dapat mengubah data), <strong>DELETE</strong> (AI dapat menghapus data). Untuk keamanan, umumnya hanya berikan akses SELECT.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 18: Panel Permissions'],
                    ['no' => 19, 'text' => 'Memahami Kolom Permissions', 'desc' => '<strong>SELECT</strong>: Izinkan AI membaca/query data dari tabel ini. <strong>INSERT</strong>: Izinkan AI memasukkan data baru (gunakan dengan hati-hati). <strong>UPDATE</strong>: Izinkan AI mengubah data existing (gunakan dengan sangat hati-hati). <strong>DELETE</strong>: Izinkan AI menghapus data (sangat tidak disarankan kecuali benar-benar perlu).', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 19: Kolom Permissions'],
                    ['no' => 20, 'text' => 'Centang Checkbox Permission', 'desc' => 'Klik checkbox di persilangan <strong>baris tabel</strong> dan <strong>kolom permission</strong> yang ingin diberikan. Tanda centang ✓ berarti izin diberikan. Kotak kosong berarti izin ditolak. Anda dapat mengubah banyak checkbox sekaligus sebelum menekan Simpan.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 20: Centang Permission'],
                    ['no' => 21, 'text' => 'Tombol "Select All" / "Clear All"', 'desc' => 'Gunakan tombol <strong>"Select All"</strong> untuk mencentang semua permission sekaligus (berguna sebagai titik awal lalu dikurangi). Gunakan <strong>"Clear All"</strong> untuk menghapus semua centang sekaligus dan mulai dari awal. Tersedia per-kolom permission maupun untuk seluruh tabel.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 21: Select All'],
                    ['no' => 22, 'text' => 'Filter/Cari Nama Tabel', 'desc' => 'Jika database memiliki banyak tabel, gunakan field <strong>pencarian tabel</strong> di atas panel permissions untuk menyaring tampilan. Ketik nama tabel atau sebagian nama, daftar akan langsung terfilter. Berguna saat database memiliki ratusan tabel.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 22: Cari Tabel'],
                    ['no' => 23, 'text' => 'Klik Tombol "Simpan Akses"', 'desc' => 'Setelah selesai mengatur semua checkbox, klik tombol <strong>"Simpan Akses"</strong> di bagian bawah panel. Perubahan permissions langsung berlaku untuk semua user yang memiliki role ini. Jika ada perubahan yang belum disimpan, sistem akan menampilkan indikator peringatan.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 23: Simpan Akses'],
                    ['no' => 24, 'text' => 'Notifikasi Permissions Tersimpan', 'desc' => 'Muncul notifikasi <strong>hijau</strong> konfirmasi permissions berhasil disimpan. Perubahan langsung aktif — user dengan role ini akan mendapat/kehilangan akses tabel sesuai konfigurasi baru pada sesi chatbot berikutnya.', 'real_img' => 'real_role_list.png', 'img_text' => 'Step 24: Akses Tersimpan'],
                    ['no' => 25, 'text' => 'Indikator Perubahan Belum Disimpan', 'desc' => 'Jika Anda mengubah checkbox tapi belum menekan Simpan, muncul <strong>indikator kuning/oranye</strong> bertuliskan "Ada perubahan yang belum disimpan". Jangan berpindah halaman atau klik role lain sebelum menyimpan, karena perubahan yang belum disimpan akan hilang.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Step 25: Peringatan Unsaved'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-5-db',
        'title' => 'MENU 5: MANAGEMENT DATABASE (/admin/databases)',
        'icon' => 'fas fa-database',
        'desc' => 'Halaman Management Database digunakan untuk mendaftarkan dan mengelola <strong>koneksi ke server database eksternal</strong>. Database yang terdaftar akan menjadi sumber data bagi AI Chatbot saat menjawab pertanyaan user. Mendukung berbagai driver: <strong>PostgreSQL, MySQL, MariaDB</strong>, dan SQL Server. Akses via sidebar <strong>Management Database</strong> atau URL <code>/admin/databases</code>.',
        'sections' => [
            [
                'id' => 'db-main',
                'title' => '5A. Halaman Utama Management Database',
                'steps' => [
                    ['no' => 1, 'text' => 'Tampilan Grid Database', 'desc' => 'Halaman ini menampilkan semua database yang terdaftar dalam bentuk <strong>card grid</strong>. Setiap card menampilkan: nama database, driver (PostgreSQL/MySQL), host, port, status koneksi (Connected/Failed/Not Tested), dan tombol aksi. Admin dapat melihat sekilas kondisi semua koneksi database dari halaman ini.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 1: Grid Database'],
                    ['no' => 2, 'text' => 'Tombol "Tambah Database"', 'desc' => 'Klik tombol <strong>"+ Tambah Database"</strong> di pojok kanan atas untuk mendaftarkan koneksi database baru. Anda perlu menyiapkan informasi: host server, port, nama database, username, dan password database yang ingin dihubungkan.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 2: Tambah Database'],
                    ['no' => 3, 'text' => 'Tombol "Test All" — Uji Semua Koneksi', 'desc' => 'Klik tombol <strong>"Test All"</strong> untuk menguji koneksi ke semua database terdaftar sekaligus. Sistem akan mencoba terhubung ke setiap database dan memperbarui badge status masing-masing card. Proses ini membutuhkan beberapa detik tergantung jumlah database.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 3: Test All'],
                    ['no' => 4, 'text' => 'Toolbar Filter Database', 'desc' => 'Di atas grid terdapat toolbar filter berisi: <strong>input pencarian</strong> (cari berdasarkan nama database), <strong>dropdown filter driver</strong> (tampilkan hanya PostgreSQL/MySQL/dll), dan <strong>toggle tampilan</strong> (grid/list). Gunakan filter ini saat jumlah database sudah banyak.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 4: Filter Database'],
                    ['no' => 5, 'text' => 'Hasil Test All — Status Koneksi', 'desc' => 'Setelah Test All selesai, setiap card database menampilkan badge status terbaru: <strong>🟢 Connected</strong> (koneksi berhasil), <strong>🔴 Failed</strong> (koneksi gagal — periksa host/password), atau <strong>⚪ Not Tested</strong> (belum pernah diuji). Badge Failed menampilkan tooltip pesan error jika di-hover.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 5: Status Koneksi'],
                    ['no' => 6, 'text' => 'Anatomi Card Database', 'desc' => 'Setiap card database berisi: <strong>Nama Database</strong> (alias yang ditetapkan admin), <strong>Driver</strong> (PostgreSQL/MySQL), <strong>Host:Port</strong> (alamat server), <strong>Database Name</strong> (nama database di server), <strong>Badge Status</strong> (hasil test koneksi), dan <strong>tombol aksi</strong> (Edit, Test, Hapus, Lihat Schema).', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 6: Card Database'],
                    ['no' => 7, 'text' => 'Badge Status Koneksi', 'desc' => 'Badge status di setiap card menunjukkan kondisi koneksi terakhir: <strong style="color:#22c55e">● Connected</strong> — database dapat diakses, AI siap menggunakannya. <strong style="color:#ef4444">● Failed</strong> — koneksi gagal, AI tidak dapat mengakses database ini, periksa kredensial dan pastikan server database aktif. <strong style="color:#94a3b8">● Not Tested</strong> — belum pernah diuji, lakukan test connection terlebih dahulu.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 7: Badge Status'],
                ]
            ],
            [
                'id' => 'db-add',
                'title' => '5B. Tambah Koneksi Database Baru',
                'steps' => [
                    ['no' => 8, 'text' => 'Buka Modal Tambah Database', 'desc' => 'Klik tombol <strong>"+ Tambah Database"</strong>. Modal form multi-step akan muncul. Form dibagi beberapa bagian: <strong>Identitas</strong> (nama & driver), <strong>Koneksi</strong> (host, port, credentials), dan <strong>Schema</strong>. Isi semua field dengan benar sebelum menyimpan.', 'real_img' => 'real_tambah_db_modal.png', 'img_text' => 'Step 8: Modal Tambah DB'],
                    ['no' => 9, 'text' => 'Isi Nama Database (Alias)', 'desc' => 'Ketik <strong>nama alias</strong> yang mudah diingat. Nama ini hanya untuk tampilan di Admin Panel dan Chatbot, bukan nama asli database di server. Contoh: <code>DB Penjualan Utama</code>.', 'real_img' => 'real_db_tambah_modal_s1.png', 'img_text' => 'Step 9: Field Nama'],
                    ['no' => 10, 'text' => 'Isi Kode Unik', 'desc' => 'Masukkan <strong>kode unik</strong> tanpa spasi. Identifier ini digunakan secara internal oleh sistem. Contoh: <code>db_penjualan_prod</code>. Sistem akan memvalidasi agar tidak ada kode ganda.', 'real_img' => 'real_db_tambah_modal_s1.png', 'img_text' => 'Step 10: Kode Unik'],
                    ['no' => 11, 'text' => 'Pilih Dropdown Driver', 'desc' => 'Pilih <strong>jenis sistem database</strong> yang digunakan server target. Pilihan yang tersedia saat ini: PostgreSQL, MySQL, dan MariaDB. Driver menentukan format query yang digunakan AI nantinya.', 'real_img' => 'real_db_tambah_modal_s1.png', 'img_text' => 'Step 11: Dropdown Driver'],
                    ['no' => 12, 'text' => 'Isi Field Host', 'desc' => 'Masukkan <strong>IP address atau hostname</strong> dari server database. Contoh: <code>192.168.1.100</code> atau <code>db.perusahaan.com</code>. Pastikan server admin panel dapat menjangkau alamat ini.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 12: Field Host'],
                    ['no' => 13, 'text' => 'Isi Field Port', 'desc' => 'Masukkan <strong>port koneksi</strong>. Default PostgreSQL adalah 5432, MySQL/MariaDB adalah 3306. Sesuaikan jika server Anda menggunakan port custom.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 13: Field Port'],
                    ['no' => 14, 'text' => 'Isi Field Database Name', 'desc' => 'Ketik <strong>nama asli database</strong> yang ada di dalam server target. Huruf besar/kecil berpengaruh tergantung sistem operasi server.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 14: Field DB Name'],
                    ['no' => 15, 'text' => 'Isi Field Username', 'desc' => 'Masukkan <strong>username/akun login</strong> database. Disarankan menggunakan akun yang memiliki akses Read-Only untuk keamanan.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 15: Username'],
                    ['no' => 16, 'text' => 'Isi Field Password', 'desc' => 'Masukkan <strong>password</strong> untuk user database tersebut. Password akan disimpan dengan aman. Klik ikon mata untuk melihat password.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 16: Password'],
                    ['no' => 17, 'text' => 'Toggle Status Active', 'desc' => 'Gunakan <strong>switch Active</strong> untuk mengaktifkan koneksi. Jika dimatikan (abu-abu), database tidak akan ditampilkan ke user Chatbot meskipun koneksi berhasil.', 'real_img' => 'real_db_tambah_modal_s2.png', 'img_text' => 'Step 17: Toggle Active'],
                    ['no' => 18, 'text' => 'Isi Field Schema (PostgreSQL)', 'desc' => 'Khusus untuk driver PostgreSQL, isi <strong>nama schema</strong>. Defaultnya adalah <code>public</code>. Kosongkan atau abaikan jika menggunakan MySQL.', 'real_img' => 'real_db_tambah_modal_s3.png', 'img_text' => 'Step 18: Field Schema'],
                    ['no' => 19, 'text' => 'Klik Tombol Test Connection', 'desc' => 'Sangat disarankan untuk mengklik tombol <strong>"Test Connection"</strong> sebelum menyimpan. Sistem akan mencoba melakukan ping ke server menggunakan data yang baru saja diisi.', 'real_img' => 'real_db_tambah_modal_s3.png', 'img_text' => 'Step 19: Test Koneksi'],
                    ['no' => 20, 'text' => 'Lihat Hasil Test Connection', 'desc' => 'Jika berhasil, muncul pesan hijau <strong>"Koneksi Sukses"</strong>. Jika gagal, muncul pesan error merah yang berisi detail penolakan koneksi dari server. Perbaiki data jika terjadi error.', 'real_img' => 'real_db_tambah_modal_s3.png', 'img_text' => 'Step 20: Hasil Test'],
                    ['no' => 21, 'text' => 'Klik Tombol Simpan', 'desc' => 'Jika semua data sudah benar, klik tombol <strong>"Simpan"</strong> di bawah. Modal akan tertutup dan koneksi database didaftarkan ke sistem.', 'real_img' => 'real_db_save_btn.png', 'img_text' => 'Step 21: Tombol Simpan'],
                    ['no' => 22, 'text' => 'Database Baru Muncul di Grid', 'desc' => 'Card database baru langsung muncul di halaman utama grid dengan status koneksi terakhirnya. Database ini sekarang siap dikonfigurasi permissions-nya di Menu Role.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 22: DB Baru Tampil'],
                ]
            ],
            [
                'id' => 'db-edit',
                'title' => '5C. Edit Konfigurasi Database',
                'steps' => [
                    ['no' => 23, 'text' => 'Klik Tombol Edit (Ikon Pensil)', 'desc' => 'Di card database, klik <strong>ikon pensil kuning ✏️</strong>. Modal "Edit Database" akan terbuka dengan seluruh data lama terisi.', 'real_img' => 'real_db_edit_modal.png', 'img_text' => 'Step 23: Edit Card'],
                    ['no' => 24, 'text' => 'Ubah Data yang Diperlukan', 'desc' => 'Ubah kredensial (host/port/user/password) jika terjadi migrasi server atau perubahan password di sisi database sumber.', 'real_img' => 'real_db_edit_modal.png', 'img_text' => 'Step 24: Modal Edit'],
                    ['no' => 25, 'text' => 'Klik Tombol Update', 'desc' => 'Klik tombol <strong>"Update"</strong> untuk menyimpan. Lakukan Test All setelah update untuk memastikan konfigurasi baru berfungsi.', 'real_img' => 'real_db_edit_modal.png', 'img_text' => 'Step 25: Tombol Update'],
                    ['no' => 26, 'text' => 'Notifikasi Update Berhasil', 'desc' => 'Muncul notifikasi hijau konfirmasi perubahan. Data di card akan otomatis diperbarui.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 26: Notif Update'],
                ]
            ],
            [
                'id' => 'db-test',
                'title' => '5D. Test Koneksi Individual',
                'steps' => [
                    ['no' => 27, 'text' => 'Klik Ikon Refresh/Heartbeat', 'desc' => 'Untuk menguji satu database spesifik tanpa menguji yang lain, klik <strong>ikon refresh/sinyal 🔄</strong> di bagian bawah card database tersebut.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 27: Ikon Ping'],
                    ['no' => 28, 'text' => 'Animasi Loading Test', 'desc' => 'Badge status akan berubah menjadi spinner <strong>"Testing..."</strong> selama sistem mencoba membangun koneksi.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 28: Loading Spinner'],
                    ['no' => 29, 'text' => 'Hasil Test: Connected', 'desc' => 'Jika berhasil, badge berubah kembali menjadi hijau <strong>"Connected"</strong> dan muncul notifikasi sukses.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 29: Connected Hijau'],
                    ['no' => 30, 'text' => 'Hasil Test: Failed', 'desc' => 'Jika gagal, badge berubah menjadi merah <strong>"Failed"</strong>. Ini mengindikasikan database sedang down atau kredensial salah.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 30: Failed Merah'],
                    ['no' => 31, 'text' => 'Cek Detail Error', 'desc' => 'Jika Failed, hover mouse di atas badge merah untuk memunculkan tooltip yang berisi <strong>pesan error SQL teknis</strong> (contoh: "Connection refused" atau "Password authentication failed").', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Step 31: Error Message'],
                ]
            ],
            [
                'id' => 'db-delete',
                'title' => '5E. Hapus Database',
                'steps' => [
                    ['no' => 32, 'text' => 'Klik Ikon Hapus (Tempat Sampah)', 'desc' => 'Di sudut card database, klik ikon <strong>tempat sampah merah 🗑️</strong> untuk menghapus pendaftaran database. Penghapusan ini HANYA menghapus konfigurasi dari Admin Panel, <strong>BUKAN</strong> menghapus data asli di server target.', 'real_img' => 'real_db_hapus_dialog.png', 'img_text' => 'Step 32: Hapus Card'],
                    ['no' => 33, 'text' => 'Konfirmasi Dialog SweetAlert', 'desc' => 'Muncul dialog konfirmasi bahaya. Klik <strong>"Ya, Hapus!"</strong> jika sudah yakin. Permissions terkait database ini juga akan dihapus dari semua role.', 'real_img' => 'real_db_hapus_dialog.png', 'img_text' => 'Step 33: Konfirmasi Hapus'],
                    ['no' => 34, 'text' => 'Database Hilang dari Grid', 'desc' => 'Setelah terhapus, card akan hilang dari halaman dan AI tidak akan bisa lagi mengakses data dari server tersebut.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 34: DB Terhapus'],
                ]
            ],
            [
                'id' => 'db-schema',
                'title' => '5F. Lihat Schema Database',
                'steps' => [
                    ['no' => 35, 'text' => 'Klik Ikon Mata (Lihat Schema)', 'desc' => 'Klik ikon <strong>mata 👁️</strong> di card database untuk melihat struktur tabel-tabel yang berhasil dibaca oleh sistem dari database tersebut.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 35: Tombol Schema'],
                    ['no' => 36, 'text' => 'Daftar Tabel dan Kolom Muncul', 'desc' => 'Modal akan terbuka menampilkan <strong>daftar tabel</strong>. Anda dapat mengkliknya untuk melihat nama-nama kolom dan tipe datanya. Berguna untuk memastikan AI dapat mengenali struktur data Anda.', 'real_img' => 'real_db_list.png', 'img_text' => 'Step 36: Daftar Tabel'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-6-ai',
        'title' => 'MENU 6: AI MANAGEMENT (/admin/ai-management)',
        'icon' => 'fas fa-brain',
        'desc' => 'Halaman AI Management adalah pusat kendali seluruh infrastruktur kecerdasan buatan sistem. Di sini admin mengelola <strong>Provider AI</strong> (OpenAI, Gemini, Groq, dll), <strong>API Keys</strong> untuk setiap provider, <strong>Model AI</strong> yang tersedia untuk dipilih user, serta fitur <strong>Health Check</strong> untuk memverifikasi kondisi setiap key. Rate limiting dan monitoring penggunaan token juga dikelola dari halaman ini. Akses via sidebar <strong>AI Management</strong> atau URL <code>/admin/ai-management</code>.',
        'sections' => [
            [
                'id' => 'ai-main',
                'title' => '6A. Halaman Utama AI Management',
                'steps' => [
                    ['no' => 1, 'text' => 'Tampilan Halaman AI Management', 'desc' => 'Halaman terdiri dari <strong>4 kartu statistik</strong> di bagian atas (Total Providers, Total Keys, Rate Limited Keys, Active Models) dan di bawahnya grid card untuk setiap Provider AI. Setiap provider card memiliki 3 tab: <strong>Keys</strong> (daftar API key), <strong>Models</strong> (daftar model), dan informasi provider.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 1: AI Management'],
                    ['no' => 2, 'text' => '4 Kartu Statistik AI', 'desc' => '<strong>Total Providers</strong>: jumlah provider AI terdaftar. <strong>Total API Keys</strong>: total key dari semua provider. <strong>Rate Limited</strong>: jumlah key yang sedang kena batas rate limit. <strong>Active Models</strong>: jumlah model AI yang aktif dan siap digunakan user. Monitor kartu ini untuk memastikan sistem AI berjalan normal.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 2: Statistik AI'],
                    ['no' => 3, 'text' => 'Tombol "Add Provider"', 'desc' => 'Klik tombol <strong>"+ Add Provider"</strong> di pojok kanan atas untuk menambahkan provider AI baru (selain yang sudah built-in). Provider custom berguna jika organisasi menggunakan layanan AI lokal (Ollama) atau provider pihak ketiga (Groq, Anthropic, dll).', 'real_img' => 'real_ai_add_provider_btn.png', 'img_text' => 'Step 3: Tambah Provider'],
                ]
            ],
            [
                'id' => 'ai-card',
                'title' => '6B. Anatomi Provider Card',
                'steps' => [
                    ['no' => 4, 'text' => 'Bagian Header Card', 'desc' => 'Header card menampilkan <strong>logo/emoji provider</strong>, nama provider (misal: OpenAI), dan kode uniknya. Di pojok kanan atas terdapat <strong>toggle switch</strong> untuk menghidupkan/mematikan seluruh akses ke provider ini secara instan, dan <strong>ikon tempat sampah</strong> untuk menghapusnya.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 4: Provider Card'],
                ]
            ],
            [
                'id' => 'ai-provider-add',
                'title' => '6C. Tambah Provider Baru',
                'steps' => [
                    ['no' => 5, 'text' => 'Buka Modal Add Provider', 'desc' => 'Klik tombol <strong>"+ Add Provider"</strong>. Modal form akan terbuka di tengah layar.', 'real_img' => 'real_add_provider_modal.png', 'img_text' => 'Step 5: Modal Tambah Provider'],
                    ['no' => 6, 'text' => 'Isi Nama Provider', 'desc' => 'Ketik nama provider yang mudah dikenali (contoh: <code>HuggingFace</code>, <code>Local Ollama</code>). Nama ini akan tampil di UI Chatbot saat user memilih AI.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 6: Field Nama Provider'],
                    ['no' => 7, 'text' => 'Isi Kode Unik', 'desc' => 'Masukkan kode pendek tanpa spasi (contoh: <code>huggingface</code>). Kode ini digunakan oleh sistem di backend untuk merutekan request API.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 7: Kode Unik Provider'],
                    ['no' => 8, 'text' => 'Isi Base URL API', 'desc' => 'Masukkan endpoint utama dari provider tersebut (contoh: <code>https://api.huggingface.co/v1/</code>). Jika menggunakan Ollama lokal, isi dengan IP lokal (misal: <code>http://127.0.0.1:11434/v1/</code>). <strong>Pastikan URL berakhiran /v1/</strong> jika provider kompatibel dengan standar OpenAI.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 8: Base URL'],
                    ['no' => 9, 'text' => 'Klik Tombol Simpan', 'desc' => 'Klik <strong>"Simpan Provider"</strong>. Jika berhasil, card baru akan muncul di dashboard.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 9: Simpan Provider'],
                    ['no' => 10, 'text' => 'Tombol Batal', 'desc' => 'Klik "Batal" atau ikon X jika ingin membatalkan pembuatan provider.', 'real_img' => 'real_ai_add_provider_modal2.png', 'img_text' => 'Step 10: Tombol Batal'],
                    ['no' => 11, 'text' => 'Provider Baru Tampil di Grid', 'desc' => 'Card untuk provider baru kini muncul. Anda sekarang dapat menambahkan API Key dan Model ke dalam provider ini.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 11: Card Muncul'],
                ]
            ],
            [
                'id' => 'ai-provider-toggle',
                'title' => '6D. Toggle Provider (Aktif/Nonaktif)',
                'steps' => [
                    ['no' => 12, 'text' => 'Status Aktif (Switch ON)', 'desc' => 'Saat switch berwarna biru (menyala), AI dari provider ini dapat digunakan oleh Chatbot.', 'real_img' => 'real_ai_toggle_on.png', 'img_text' => 'Step 12: Toggle ON'],
                    ['no' => 13, 'text' => 'Matikan Provider (Switch OFF)', 'desc' => 'Klik switch untuk mematikannya (abu-abu). Jika dimatikan, semua model dan key dari provider ini <strong>tidak dapat diakses sementara</strong>, tapi data konfigurasi tetap aman.', 'real_img' => 'real_ai_toggle_off.png', 'img_text' => 'Step 13: Toggle OFF'],
                    ['no' => 14, 'text' => 'Visual Card Nonaktif', 'desc' => 'Saat OFF, opacity card akan sedikit menurun/redup sebagai indikasi visual bahwa provider sedang dinonaktifkan.', 'real_img' => 'real_ai_toggle_off.png', 'img_text' => 'Step 14: Card Redup'],
                ]
            ],
            [
                'id' => 'ai-provider-delete',
                'title' => '6E. Hapus Provider',
                'steps' => [
                    ['no' => 15, 'text' => 'Klik Ikon Tempat Sampah', 'desc' => 'Di sudut kanan atas card, klik ikon <strong>tempat sampah merah 🗑️</strong> untuk menghapus provider beserta semua key dan model di dalamnya.', 'real_img' => 'real_ai_delete_provider_btn.png', 'img_text' => 'Step 15: Tombol Hapus'],
                    ['no' => 16, 'text' => 'Provider Built-in Terkunci', 'desc' => 'Provider inti sistem (OpenAI, Gemini, Groq, Anthropic) menampilkan <strong>ikon gembok 🔒</strong> dan tidak memiliki ikon hapus. Provider ini dikunci di database dan tidak dapat dihapus.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 16: Ikon Gembok'],
                    ['no' => 17, 'text' => 'Konfirmasi Dialog Hapus', 'desc' => 'Peringatan SweetAlert akan muncul menanyakan konfirmasi. Penghapusan akan menghapus semua API Keys dan Models milik provider tersebut secara permanen.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 17: Konfirmasi Hapus'],
                    ['no' => 18, 'text' => 'Provider Hilang', 'desc' => 'Setelah konfirmasi, provider akan dihapus dan card-nya hilang dari layar.', 'real_img' => 'real_ai_management.png', 'img_text' => 'Step 18: Provider Hilang'],
                ]
            ],
            [
                'id' => 'ai-keys-main',
                'title' => '6F. Mengelola API Keys',
                'steps' => [
                    ['no' => 19, 'text' => 'Buka Tab "🔑 Keys"', 'desc' => 'Di setiap provider card, pastikan Anda berada di tab <strong>"🔑 Keys"</strong>. Tab ini memuat daftar semua kredensial/token untuk provider tersebut.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 19: Tab Keys'],
                    ['no' => 20, 'text' => 'Daftar API Keys', 'desc' => 'Setiap baris mewakili satu API Key. Sistem mendukung multi-key per provider dengan mekanisme <strong>Round-Robin Auto-Rotation</strong>. Jika satu key habis kuota (rate limit), sistem akan otomatis mencoba key berikutnya yang berstatus OK.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 20: Daftar Key'],
                    ['no' => 21, 'text' => 'Tombol Aksi di Baris Key', 'desc' => 'Di sebelah kanan setiap key terdapat tombol aksi: <strong>Edit</strong> (pensil kuning), <strong>Health Check</strong> (sinyal hijau), dan <strong>Hapus</strong> (tempat sampah merah).', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 21: Elemen Baris'],
                    ['no' => 22, 'text' => 'Badge Status Key', 'desc' => 'Titik warna indikator di sebelah nama key: <strong>🟢 Hijau (OK)</strong> key aktif dan siap dipakai. <strong>⚪ Abu-abu (OFF)</strong> key dinonaktifkan manual oleh admin. <strong>🔴 Merah (LIMIT)</strong> key terdeteksi habis kuota/rate limited.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 22: Badge Status'],
                    ['no' => 23, 'text' => 'Badge Total Penggunaan (Usage)', 'desc' => 'Badge kecil berikon panah atas ↗ menampilkan <strong>jumlah request</strong> (prompt/chat) yang telah menggunakan key ini. Angka ini membantu Anda mendistribusikan beban secara merata.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 23: Usage Count'],
                    ['no' => 24, 'text' => 'Badge Estimasi Token', 'desc' => 'Badge kecil berikon wajik ◈ menampilkan estimasi total <strong>konsumsi token</strong> (input + output). Angka ini berguna untuk memantau biaya pemakaian per API Key.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 24: Token Count'],
                    ['no' => 25, 'text' => 'Pencatat Pembuat Key (Added By)', 'desc' => 'Arahkan kursor (hover) ke nama key untuk melihat <strong>tooltip</strong> yang menampilkan email admin yang pertama kali menambahkan key tersebut.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 25: Added By'],
                ]
            ],
            [
                'id' => 'ai-keys-add',
                'title' => '6G. Tambah API Key Baru',
                'steps' => [
                    ['no' => 26, 'text' => 'Klik Tombol "+ Add Key"', 'desc' => 'Di bagian bawah daftar key dalam card provider, klik tombol <strong>"+ Add Key"</strong>.', 'real_img' => 'real_ai_add_key_btn.png', 'img_text' => 'Step 26: Add Key Button'],
                    ['no' => 27, 'text' => 'Buka Modal "Add API Key"', 'desc' => 'Modal akan terbuka. Form ini spesifik terhubung dengan provider yang card-nya Anda klik.', 'real_img' => 'real_add_key_modal.png', 'img_text' => 'Step 27: Modal Add Key'],
                    ['no' => 28, 'text' => 'Isi Nama Alias Key', 'desc' => 'Berikan <strong>nama label/alias</strong> yang jelas untuk key ini. Sangat berguna jika Anda memasukkan banyak key. Contoh: <code>Akun Billing Tim Sales</code> atau <code>Key Free Tier 1</code>.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 28: Field Nama Key'],
                    ['no' => 29, 'text' => 'Paste Nilai API Key Asli', 'desc' => 'Paste <strong>API Key / Token rahasia</strong> yang Anda dapatkan dari dashboard provider (misal: console.cloud.google.com untuk Gemini). Nilainya akan ditutupi demi keamanan.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 29: Field API Key'],
                    ['no' => 30, 'text' => 'Gunakan Ikon Mata (Show/Hide)', 'desc' => 'Klik ikon mata di dalam field API Key untuk memeriksa kembali apakah token yang Anda paste sudah benar dan tidak ada spasi tambahan/terpotong.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 30: Ikon Mata'],
                    ['no' => 31, 'text' => 'Klik Simpan', 'desc' => 'Klik tombol biru <strong>"Simpan"</strong> untuk mendaftarkan key. Key langsung disimpan dengan enkripsi di database.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 31: Simpan Key'],
                    ['no' => 32, 'text' => 'Klik Batal', 'desc' => 'Klik tombol abu-abu "Batal" atau ikon silang jika ingin membatalkan pendaftaran.', 'real_img' => 'real_ai_add_key_modal2.png', 'img_text' => 'Step 32: Batal Key'],
                    ['no' => 33, 'text' => 'Key Terdaftar di List', 'desc' => 'Key baru otomatis muncul di urutan paling bawah dalam daftar tab Keys, dengan status default <strong>🟢 OK</strong>.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 33: Key Muncul'],
                ]
            ],
            [
                'id' => 'ai-keys-edit',
                'title' => '6H. Edit Data API Key',
                'steps' => [
                    ['no' => 34, 'text' => 'Klik Ikon Edit (Pensil)', 'desc' => 'Di baris key yang ingin diubah, klik <strong>ikon pensil kuning ✏️</strong>.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 34: Edit Key'],
                    ['no' => 35, 'text' => 'Buka Modal Edit API Key', 'desc' => 'Modal akan menampilkan nama key saat ini. Nilai token aslinya <strong>tidak akan ditampilkan</strong> demi keamanan.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 35: Modal Edit Key'],
                    ['no' => 36, 'text' => 'Ubah Nama Alias', 'desc' => 'Ketik di field Nama Key untuk mengubah labelnya.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 36: Edit Nama Key'],
                    ['no' => 37, 'text' => 'Ganti Token (Opsional)', 'desc' => 'Terdapat petunjuk <em>"Kosongkan jika tidak ingin mengubah token aslinya"</em>. Jika Anda ingin menimpa token lama dengan yang baru, paste token baru di field ini. Jika dibiarkan kosong, sistem tetap menggunakan token yang lama.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 37: Hint Kosongkan'],
                    ['no' => 38, 'text' => 'Checkbox Aktifkan/Nonaktifkan', 'desc' => 'Centang/hapus centang pada kotak <strong>"Aktif"</strong>. Menghapus centang (OFF) berguna jika Anda ingin menyetop penggunaan suatu key sementara (misal: sedang billing cycle) tanpa perlu menghapusnya secara permanen.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 38: Checkbox Aktif'],
                    ['no' => 39, 'text' => 'Klik Simpan Perubahan', 'desc' => 'Klik tombol <strong>"Simpan"</strong> untuk menerapkan pembaruan. Perubahan akan langsung efektif pada request API berikutnya.', 'real_img' => 'real_ai_edit_key_modal.png', 'img_text' => 'Step 39: Update Key'],
                ]
            ],
            [
                'id' => 'ai-keys-delete',
                'title' => '6I. Hapus API Key',
                'steps' => [
                    ['no' => 40, 'text' => 'Klik Ikon Hapus', 'desc' => 'Di sebelah kanan baris key, klik ikon <strong>tempat sampah merah 🗑️</strong>.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 40: Hapus Key'],
                    ['no' => 41, 'text' => 'Konfirmasi Dialog SweetAlert', 'desc' => 'Dialog peringatan akan muncul. Klik tombol <strong>"Ya, Hapus!"</strong> jika Anda yakin key ini sudah tidak digunakan. Key akan dihapus permanen dari sistem.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 41: Konfirm Hapus Key'],
                    ['no' => 42, 'text' => 'Key Hilang dari Daftar', 'desc' => 'Setelah terhapus, notifikasi sukses muncul dan key hilang dari list. Chatbot akan secara otomatis mencari key lain yang masih aktif.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 42: Key Hilang'],
                ]
            ],
            [
                'id' => 'ai-keys-limit',
                'title' => '6J. Fitur Reset Rate Limit',
                'steps' => [
                    ['no' => 43, 'text' => 'Mengenal Indikator Rate Limit', 'desc' => 'Sistem dilengkapi fitur <strong>Auto Fallback</strong>. Jika saat melakukan query API terdeteksi balasan error 429 (Too Many Requests), sistem otomatis memberi label <strong>🔴 LIMIT</strong> pada key tersebut dan mengabaikannya pada query berikutnya untuk mencegah error berkelanjutan.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 43: Reset Limit'],
                    ['no' => 44, 'text' => 'Banner Peringatan Merah', 'desc' => 'Jika ada satu saja key yang berstatus LIMIT dalam suatu provider, sebuah <strong>banner peringatan berwarna merah</strong> akan muncul di bagian bawah card, menginformasikan bahwa sebagian kuota API mungkin habis.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 44: Banner Merah'],
                    ['no' => 45, 'text' => 'Klik Tombol Reset Limit (Ikon Refresh Kuning)', 'desc' => 'Di baris key yang berstatus merah (LIMIT), Anda akan melihat tombol baru berupa <strong>ikon refresh/putar warna kuning/oranye 🔄</strong>. Klik tombol ini jika Anda yakin kuota limit dari provider sudah direfresh (misal: ganti hari baru atau kuota diisi ulang).', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 45: Konfirm Reset'],
                    ['no' => 46, 'text' => 'Status Kembali Normal (OK)', 'desc' => 'Setelah di-reset, status key akan kembali menjadi <strong>🟢 OK</strong>. Pada request chatbot berikutnya, sistem akan kembali mempertimbangkan key ini untuk digunakan.', 'real_img' => 'real_ai_keys_tab.png', 'img_text' => 'Step 46: Kembali OK'],
                ]
            ],
            [
                'id' => 'ai-health',
                'title' => '6K. Fitur Health Check API Key',
                'steps' => [
                    ['no' => 47, 'text' => 'Klik Tombol Health Check (Ikon Sinyal Hijau)', 'desc' => 'Tombol ini adalah fitur terpenting! Di baris API Key manapun, klik <strong>ikon sinyal / gelombang berwarna hijau</strong> untuk menguji validitas API Key tersebut langsung ke server provider.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 47: Health Check'],
                    ['no' => 48, 'text' => 'Buka Modal API Key Health Check', 'desc' => 'Modal akan terbuka. Sistem butuh mengetahui <strong>Model</strong> apa yang harus di-ping (dites). Misalnya untuk menguji key OpenAI, kita harus mengirim ping ke model <code>gpt-3.5-turbo</code> atau <code>gpt-4o</code>.', 'real_img' => 'real_health_check_modal.png', 'img_text' => 'Step 48: Modal Health'],
                    ['no' => 49, 'text' => 'Pilih Model Pengujian', 'desc' => 'Sistem mencoba menampilkan dropdown berisi model-model yang sudah Anda daftarkan di tab Models. Pilih salah satu model yang relevan untuk provider tersebut.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 49: Dropdown Model Test'],
                    ['no' => 50, 'text' => 'Opsi Input Model Manual', 'desc' => 'Jika model belum didaftarkan di sistem, <strong>centang opsi "Ketik model manual"</strong>. Ini memungkinkan Anda menguji model apa saja.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 50: Checkbox Manual'],
                    ['no' => 51, 'text' => 'Ketik Nama Model (Jika Manual)', 'desc' => 'Ketik secara manual identifier spesifik model. Contoh: <code>gemini-2.5-flash</code> atau <code>llama3-8b-8192</code>. Pastikan namanya akurat sesuai dokumentasi provider.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 51: Input Manual'],
                    ['no' => 52, 'text' => 'Klik "Cek Sekarang"', 'desc' => 'Klik tombol biru <strong>"Cek Sekarang"</strong>. Sistem akan menembakkan request API nyata menggunakan token tersebut ke server provider dengan mengirim prompt sederhana ("ping").', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 52: Tombol Cek'],
                    ['no' => 53, 'text' => 'Tunggu Loading State', 'desc' => 'Tombol akan berubah menjadi <strong>animasi spinner / memuat</strong>. Proses ini memakan waktu 1-5 detik tergantung respon server tujuan.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 53: Loading Ping'],
                    ['no' => 54, 'text' => 'Hasil Sukses (Banner Hijau)', 'desc' => 'Jika API key valid dan memiliki kuota, provider merespon HTTP 200. Modal akan memunculkan banner besar berwarna <strong>hijau (Health Check Berhasil)</strong> beserta detail durasi respon (misal: 1.2s). API key ini dijamin siap 100% dipakai user.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 54: Hasil Sukses'],
                    ['no' => 55, 'text' => 'Hasil Gagal (Banner Merah)', 'desc' => 'Jika API key salah, expired, saldo habis, atau rate limited, sistem mendeteksi error HTTP (contoh: 401 Unauthorized, 429 Too Many Requests). Muncul banner berwarna <strong>merah (Health Check Gagal)</strong> disertai pesan error spesifik dari provider (misal: <em>"Incorrect API key provided"</em>).', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 55: Hasil Gagal'],
                    ['no' => 56, 'text' => 'Tombol Cek Ulang', 'desc' => 'Setelah hasil muncul, Anda dapat mengklik tombol abu-abu <strong>"Cek Ulang"</strong> untuk mengulang proses ping dengan model yang berbeda tanpa perlu menutup modal.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 56: Tombol Ulang'],
                    ['no' => 57, 'text' => 'Tombol Tutup Modal', 'desc' => 'Klik ikon silang <strong>X</strong> di sudut kanan atas modal untuk keluar kembali ke daftar key.', 'real_img' => 'real_ai_health_modal2.png', 'img_text' => 'Step 57: Tombol Tutup'],
                ]
            ],
            [
                'id' => 'ai-models-main',
                'title' => '6L. Tab Models — Kelola Model AI',
                'steps' => [
                    ['no' => 58, 'text' => 'Buka Tab "🧠 Models"', 'desc' => 'Di provider card, beralih ke tab <strong>"🧠 Models"</strong>. Tab ini memuat daftar identifier model AI spesifik yang disediakan oleh provider tersebut (misal: gpt-4o, gpt-3.5-turbo, claude-3-opus). Model-model yang aktif di sini adalah opsi yang akan tampil di dropdown Chatbot bagi pengguna.', 'real_img' => 'real_models_tab.png', 'img_text' => 'Step 58: Tab Models'],
                    ['no' => 59, 'text' => 'Daftar Model (Format Chip)', 'desc' => 'Model direpresentasikan sebagai <strong>kapsul/chip</strong> yang berjajar rapi. Ini memudahkan admin melihat puluhan model sekaligus dalam ruang yang ringkas.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 59: Badge Model'],
                    ['no' => 60, 'text' => 'Model Aktif (Biru/Indigo)', 'desc' => 'Chip berwarna <strong>biru/indigo mencolok</strong> berarti model tersebut berstatus AKTIF. Model ini dapat digunakan oleh user (tergantung konfigurasi akses di pengaturan User/Role).', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 60: Model Aktif'],
                    ['no' => 61, 'text' => 'Model Nonaktif (Abu-abu)', 'desc' => 'Chip berwarna <strong>abu-abu redup</strong> berarti model dinonaktifkan (OFF). Meskipun datanya tersimpan, Chatbot akan menyembunyikan opsi ini dari end-user.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 61: Model Mati'],
                    ['no' => 62, 'text' => 'Klik Chip untuk Toggle Cepat', 'desc' => 'Fitur <strong>Quick Toggle</strong>: Anda hanya perlu meng-klik area teks pada chip untuk langsung menyalakan (biru) atau mematikan (abu) model tersebut tanpa perlu membuka form edit. Ini sangat menghemat waktu saat mematikan massal model yang sudah usang (deprecated).', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 62: Klik Toggle'],
                    ['no' => 63, 'text' => 'Klik Ikon Silang (×)', 'desc' => 'Di ujung kanan setiap chip terdapat ikon silang kecil <strong>(×)</strong>. Ini adalah tombol khusus untuk <strong>menghapus</strong> model dari database.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 63: Tombol Silang'],
                ]
            ],
            [
                'id' => 'ai-models-add',
                'title' => '6M. Tambah Model AI Baru',
                'steps' => [
                    ['no' => 64, 'text' => 'Klik Tombol "+ Add Model"', 'desc' => 'Di bagian paling bawah tab Models, temukan tombol <strong>"+ Add Model"</strong> dan klik.', 'real_img' => 'real_ai_add_model_btn.png', 'img_text' => 'Step 64: Add Model Button'],
                    ['no' => 65, 'text' => 'Buka Modal Tambah Model', 'desc' => 'Modal <strong>"Add Model AI"</strong> akan muncul. Form ini meminta identitas teknis dan tampilan model.', 'real_img' => 'real_add_model_modal.png', 'img_text' => 'Step 65: Modal Add Model'],
                    ['no' => 66, 'text' => 'Isi ID Model (System Name)', 'desc' => '<strong>SANGAT PENTING:</strong> Isi field "ID Model" dengan identitas teknis persis 100% seperti yang dirilis di dokumentasi API provider. Kesalahan 1 huruf/tanda hubung akan membuat health check gagal. Contoh benar: <code>gemini-1.5-pro-latest</code>, <code>gpt-4o-mini</code>, <code>llama-3.1-70b-versatile</code>.', 'real_img' => 'real_ai_add_model_modal2.png', 'img_text' => 'Step 66: ID Model Tepat'],
                    ['no' => 67, 'text' => 'Isi Nama Tampilan (Display Name)', 'desc' => 'Isi field "Nama Model (Display)" dengan versi ramah pengguna yang akan dilihat oleh user di UI chatbot. Bebas menggunakan kapital dan spasi. Contoh: <code>Gemini 1.5 PRO</code>, <code>GPT-4o Mini Tercepat</code>.', 'real_img' => 'real_ai_add_model_modal2.png', 'img_text' => 'Step 67: Display Name'],
                    ['no' => 68, 'text' => 'Klik Simpan', 'desc' => 'Klik tombol <strong>"Simpan"</strong>. Sistem akan mendaftarkan model dan memetakannya secara otomatis ke provider terkait.', 'real_img' => 'real_ai_add_model_modal2.png', 'img_text' => 'Step 68: Simpan Model'],
                    ['no' => 69, 'text' => 'Model Muncul Sebagai Chip', 'desc' => 'Model baru langsung tampil berupa <strong>chip warna biru (aktif)</strong> di bagian akhir barisan model. Anda dapat langsung mengujinya menggunakan tombol Health Check (di tab Keys).', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 69: Chip Baru'],
                ]
            ],
            [
                'id' => 'ai-models-delete',
                'title' => '6N. Hapus Model AI',
                'steps' => [
                    ['no' => 70, 'text' => 'Klik Ikon (×) di Chip', 'desc' => 'Cari model yang sudah usang/deprecated, arahkan kursor ke chip-nya, dan klik tanda silang <strong>(×)</strong> merah.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 70: Klik Silang Hapus'],
                    ['no' => 71, 'text' => 'Konfirmasi Dialog SweetAlert', 'desc' => 'Klik <strong>"Ya, Hapus!"</strong> pada peringatan. Harap dicatat bahwa model yang sudah terhapus tidak akan bisa lagi dipilih oleh semua user Chatbot.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 71: Konfirm Hapus Model'],
                    ['no' => 72, 'text' => 'Chip Model Lenyap', 'desc' => 'Setelah konfirmasi, notifikasi berhasil muncul dan chip model langsung hilang secara real-time dari UI.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Step 72: Chip Hilang'],
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
