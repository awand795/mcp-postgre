@extends('layouts.admin')

@section('page-title', 'Buku Panduan Administrator — DarkoAI Admin Panel')

@section('content')
@php
/* ══════════════════════════════════════════════════════════════
   DATA PANDUAN — setiap entry: no, text, desc, img, label
══════════════════════════════════════════════════════════════ */
$guideData = [

    /* ═══════════════════════════════════════════════════════════
       1. AUTENTIKASI
    ═══════════════════════════════════════════════════════════ */
    [
        'id'    => 'menu-auth',
        'title' => '1. AUTENTIKASI & KEAMANAN',
        'icon'  => 'fas fa-shield-alt',
        'color' => 'linear-gradient(135deg,#6366f1,#4f46e5)',
        'desc'  => 'Prosedur masuk ke sistem Admin Panel secara aman, reset password via OTP, dan verifikasi identitas pengguna.',
        'sections' => [
            [
                'id'    => 'auth-login',
                'title' => '1A. Login ke Sistem',
                'steps' => [
                    ['no'=>1, 'text'=>'Buka Halaman Login',
                     'desc'=>'Akses URL sistem di browser (contoh: <code>http://localhost:5000/login</code>). Akan muncul form kartu bertajuk <strong>Sign In</strong>. Pastikan URL sudah benar dan koneksi internet tersedia.',
                     'img'=>'real_login_page.png', 'label'=>'HALAMAN LOGIN UTAMA'],

                    ['no'=>2, 'text'=>'Isi Field Email',
                     'desc'=>'Klik kolom <strong>Email</strong> yang ditandai kotak merah, lalu ketik alamat email akun Anda. Contoh: <code>admin@darkotech.id</code>. Email tidak bersifat case-sensitive.',
                     'img'=>'real_login_email.png', 'label'=>'FIELD EMAIL'],

                    ['no'=>3, 'text'=>'Isi Field Password',
                     'desc'=>'Klik kolom <strong>Password</strong> (kotak merah) lalu ketik password Anda. Gunakan ikon <i class="fas fa-eye"></i> di ujung kanan field untuk menampilkan/menyembunyikan karakter dan memverifikasi ketikan.',
                     'img'=>'real_login_password.png', 'label'=>'FIELD PASSWORD'],

                    ['no'=>4, 'text'=>'Klik Tombol LOGIN',
                     'desc'=>'Klik tombol biru <strong>"Login"</strong> (kotak merah). Sistem akan memvalidasi kredensial. Jika berhasil → langsung masuk ke Chatbot/Dashboard. Jika gagal → pesan error merah tampil di atas form.',
                     'img'=>'real_login_button.png', 'label'=>'TOMBOL LOGIN'],

                    ['no'=>5, 'text'=>'Berhasil Masuk ke Sistem',
                     'desc'=>'Setelah login berhasil, Anda diarahkan ke halaman utama (Chatbot atau Dashboard). Tampilan ini membuktikan autentikasi berhasil. Sidebar kiri akan menampilkan menu Admin jika akun Anda adalah Administrator.',
                     'img'=>'real_login_success.png', 'label'=>'LOGIN BERHASIL'],
                ],
            ],
            [
                'id'    => 'auth-forgot',
                'title' => '1B. Lupa Password & Reset via OTP',
                'steps' => [
                    ['no'=>6, 'text'=>'Klik Link "Lupa Password?"',
                     'desc'=>'Jika tidak ingat password, klik tautan <strong>"Lupa Password?"</strong> (kotak merah) di bawah form login. Anda akan diarahkan ke halaman pemulihan akun.',
                     'img'=>'real_login_forgot_link.png', 'label'=>'LINK LUPA PASSWORD'],

                    ['no'=>7, 'text'=>'Masukkan Email Pemulihan',
                     'desc'=>'Pada halaman Forgot Password, isi kolom email (kotak merah) dengan email terdaftar Anda, lalu klik <strong>"Kirim Kode OTP"</strong>. Sistem mengirimkan 6-digit kode rahasia ke inbox email dalam beberapa detik.',
                     'img'=>'real_forgot_email_field.png', 'label'=>'EMAIL PEMULIHAN'],

                    ['no'=>8, 'text'=>'Verifikasi Kode OTP 6 Digit',
                     'desc'=>'Buka inbox email Anda, salin kode 6 digit yang diterima, lalu masukkan ke kotak-kotak verifikasi (kotak merah) secara berurutan. <strong>⚠ Kode hanya berlaku 10 menit.</strong> Periksa folder Spam jika tidak masuk Inbox.',
                     'img'=>'real_verify_otp_page.png', 'label'=>'VERIFIKASI OTP'],

                    ['no'=>9, 'text'=>'Buat Password Baru',
                     'desc'=>'Setelah OTP terverifikasi, Anda masuk ke halaman Reset Password. Ketik password baru (min. 8 karakter, kombinasi huruf besar, kecil, angka & simbol), ulangi di kolom konfirmasi, lalu klik <strong>"Simpan Password Baru"</strong>.',
                     'img'=>'real_reset_password_page.png', 'label'=>'BUAT PASSWORD BARU'],
                ],
            ],
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
       2. CHATBOT AI
    ═══════════════════════════════════════════════════════════ */
    [
        'id'    => 'menu-chatbot',
        'title' => '2. CHATBOT AI',
        'icon'  => 'fas fa-robot',
        'color' => 'linear-gradient(135deg,#10b981,#059669)',
        'desc'  => 'Halaman utama interaksi dengan AI. Gunakan chatbot untuk menganalisis data database, ekspor tabel ke Excel/PDF, dan kelola riwayat percakapan.',
        'sections' => [
            [
                'id'    => 'chat-ui',
                'title' => '2A. Antarmuka Chat & Cara Bertanya',
                'steps' => [
                    ['no'=>10, 'text'=>'Tampilan Utama Chatbot',
                     'desc'=>'Setelah login, halaman ini yang pertama muncul. Area tengah adalah percakapan dengan AI. Ketik pertanyaan Anda pada kolom input bawah (kotak merah) — contoh: <em>"Tampilkan 10 transaksi terbesar bulan ini"</em> — lalu tekan <kbd>Enter</kbd> atau klik tombol kirim.',
                     'img'=>'real_chatbot_page.png', 'label'=>'HALAMAN UTAMA CHATBOT'],

                    ['no'=>11, 'text'=>'Membuka Sidebar Riwayat Chat',
                     'desc'=>'Klik ikon <i class="fas fa-bars"></i> hamburger di pojok kiri atas (kotak merah) untuk membuka panel Sidebar Riwayat. Di sini Anda bisa melihat semua sesi percakapan lama, dikelompokkan berdasarkan tanggal. Klik judul chat untuk membuka kembali.',
                     'img'=>'real_chatbot_sidebar.png', 'label'=>'SIDEBAR RIWAYAT CHAT'],

                    ['no'=>12, 'text'=>'Menghapus Sesi Chat',
                     'desc'=>'Klik ikon <i class="fas fa-trash"></i> sampah pada judul chat di sidebar. Dialog konfirmasi (kotak merah) akan muncul. Klik <strong>"Ya, Hapus"</strong> untuk menghapus permanen, atau <strong>"Batal"</strong> untuk membatalkan. Penghapusan tidak dapat dibatalkan.',
                     'img'=>'real_chatbot_delete_confirm.png', 'label'=>'KONFIRMASI HAPUS CHAT'],
                ],
            ],
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
       3. DASHBOARD
    ═══════════════════════════════════════════════════════════ */
    [
        'id'    => 'menu-dashboard',
        'title' => '3. MONITORING DASHBOARD',
        'icon'  => 'fas fa-chart-pie',
        'color' => 'linear-gradient(135deg,#8b5cf6,#7c3aed)',
        'desc'  => 'Halaman pertama yang dilihat Admin. Menampilkan statistik sistem real-time, navigasi sidebar, quick-links, dan pengaturan tema tampilan.',
        'sections' => [
            [
                'id'    => 'dash-stats',
                'title' => '3A. Kartu Statistik & Quick Links',
                'steps' => [
                    ['no'=>13, 'text'=>'Dashboard Overview — 4 Kartu Statistik',
                     'desc'=>'Dashboard menampilkan 4 kartu (kotak merah): <strong>Total Users</strong> (jumlah akun terdaftar), <strong>Total Roles</strong> (grup hak akses), <strong>Total Databases</strong> (koneksi aktif), dan <strong>Total Tables</strong> (tabel yang bisa diakses AI). Angka diperbarui real-time.',
                     'img'=>'real_dashboard.png', 'label'=>'KARTU STATISTIK SISTEM'],

                    ['no'=>14, 'text'=>'Sidebar Navigasi Admin',
                     'desc'=>'Sidebar kiri (kotak merah) adalah navigasi utama Admin Panel. Berisi menu: <strong>Dashboard, Management Database, AI Management, Management Role, Management User,</strong> dan <strong>Panduan</strong>. Klik nama menu untuk berpindah halaman. Di bagian bawah terdapat info user yang sedang login.',
                     'img'=>'real_sidebar.png', 'label'=>'NAVIGASI SIDEBAR ADMIN'],
                ],
            ],
            [
                'id'    => 'dash-theme',
                'title' => '3B. Fitur Dark Mode',
                'steps' => [
                    ['no'=>15, 'text'=>'Tombol Toggle Dark/Light Mode',
                     'desc'=>'Temukan tombol toggle tema (kotak merah) di bagian atas sidebar atau header. Klik sekali untuk beralih dari Light Mode ke Dark Mode. Preferensi tema disimpan otomatis di browser sehingga tetap berlaku saat Anda login kembali.',
                     'img'=>'real_dash_darkmode.png', 'label'=>'TOGGLE DARK MODE'],

                    ['no'=>16, 'text'=>'Tampilan Dark Mode Aktif',
                     'desc'=>'Saat Dark Mode aktif, seluruh antarmuka berubah ke palet warna gelap (kotak merah menunjukkan area yang berubah). Sangat nyaman untuk penggunaan di kondisi pencahayaan rendah atau untuk mereduksi kelelahan mata saat bekerja malam.',
                     'img'=>'real_dashboard_dark.png', 'label'=>'TAMPILAN DARK MODE'],
                ],
            ],
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
       4. DATABASE MANAGEMENT
    ═══════════════════════════════════════════════════════════ */
    [
        'id'    => 'menu-database',
        'title' => '4. DATABASE MANAGEMENT',
        'icon'  => 'fas fa-database',
        'color' => 'linear-gradient(135deg,#f59e0b,#d97706)',
        'desc'  => 'Modul untuk menghubungkan server database eksternal (PostgreSQL, MySQL, MariaDB) ke sistem AI. Setiap koneksi dapat ditambah, diedit, diuji, dan dihapus.',
        'sections' => [
            [
                'id'    => 'db-overview',
                'title' => '4A. Halaman Daftar & Toolbar Database',
                'steps' => [
                    ['no'=>17, 'text'=>'Halaman Daftar Database',
                     'desc'=>'Semua koneksi database yang terdaftar ditampilkan sebagai kartu (kotak merah). Setiap kartu memuat: nama alias, kode, driver (PostgreSQL/MySQL/MariaDB), host:port, nama database, schema, status koneksi (Connected/Failed/Not Tested), dan tombol aksi.',
                     'img'=>'real_db_list.png', 'label'=>'DAFTAR KONEKSI DATABASE'],

                    ['no'=>18, 'text'=>'Toolbar: Pencarian, Filter, & View Toggle',
                     'desc'=>'Toolbar (kotak merah) di bawah header berisi: <strong>Kolom Pencarian</strong> (cari berdasarkan nama/kode/host), <strong>Filter Driver</strong> (PostgreSQL/MySQL/MariaDB), <strong>Filter Status</strong> (Active/Inactive/Connected/Failed/Not Tested), dan <strong>Toggle View</strong> (Grid <i class="fas fa-th-large"></i> / List <i class="fas fa-list"></i>).',
                     'img'=>'real_db_toolbar.png', 'label'=>'TOOLBAR PENCARIAN & FILTER'],

                    ['no'=>19, 'text'=>'Tombol "Test All Connections"',
                     'desc'=>'Klik tombol <strong>"Test All"</strong> <i class="fas fa-heartbeat"></i> (kotak merah) di pojok kanan atas untuk menguji semua koneksi database sekaligus. Hasilnya ditampilkan dalam health bar yang muncul di bawah header: Total, <span style="color:#10b981">Connected</span>, dan <span style="color:#ef4444">Failed</span>.',
                     'img'=>'real_db_test_all.png', 'label'=>'TOMBOL TEST ALL CONNECTIONS'],

                    ['no'=>20, 'text'=>'Tombol "Tambah Database"',
                     'desc'=>'Klik tombol <strong>"+ Tambah Database"</strong> (kotak merah) untuk membuka wizard penambahan koneksi baru. Wizard terdiri dari 3 langkah yang harus diisi secara berurutan.',
                     'img'=>'real_db_tambah_btn.png', 'label'=>'TOMBOL TAMBAH DATABASE'],
                ],
            ],
            [
                'id'    => 'db-add',
                'title' => '4B. Wizard Tambah Database (3 Langkah)',
                'steps' => [
                    ['no'=>21, 'text'=>'Step 1 — Identitas: Nama, Kode & Driver',
                     'desc'=>'Wizard dibuka, langkah pertama meminta: <br>• <strong>Nama Koneksi/Alias</strong> — nama tampilan (contoh: "Production DB") <br>• <strong>Kode</strong> — pengenal unik huruf kecil & underscore (contoh: "prod_db") <br>• <strong>Driver</strong> — pilih salah satu: PostgreSQL, MySQL, atau MariaDB <br>• <strong>Deskripsi</strong> (opsional) <br>• Centang <strong>Aktif</strong> dan/atau <strong>Default</strong> jika perlu.',
                     'img'=>'real_db_modal_step1.png', 'label'=>'WIZARD STEP 1: IDENTITAS'],

                    ['no'=>22, 'text'=>'Step 2 — Koneksi: Host, Port, Kredensial & Schema',
                     'desc'=>'Isi detail koneksi server: <br>• <strong>Host</strong> — IP atau hostname server database <br>• <strong>Port</strong> — otomatis terisi sesuai driver (5432/3306) <br>• <strong>Nama Database</strong> — nama database asli di server <br>• <strong>Username & Password</strong> — kredensial akses database <br>• <strong>SSL Mode</strong> — keamanan koneksi (None/Prefer/Require) <br>• <strong>Schema</strong> — klik Load <i class="fas fa-sync-alt"></i> untuk deteksi otomatis',
                     'img'=>'real_db_modal_step2.png', 'label'=>'WIZARD STEP 2: KONEKSI'],

                    ['no'=>23, 'text'=>'Step 3 — Test Koneksi Sebelum Simpan',
                     'desc'=>'Langkah terakhir menyediakan panel uji koneksi (kotak merah). Klik <strong>"Test Sekarang"</strong> untuk memverifikasi parameter yang diisi sudah benar sebelum disimpan. Hasil muncul di bawah tombol: hijau = berhasil, merah = gagal beserta pesan error. Setelah berhasil, klik <strong>"Simpan Database"</strong>.',
                     'img'=>'real_db_modal_step3.png', 'label'=>'WIZARD STEP 3: TEST & SIMPAN'],
                ],
            ],
            [
                'id'    => 'db-manage',
                'title' => '4C. Mengelola Koneksi yang Sudah Ada',
                'steps' => [
                    ['no'=>24, 'text'=>'Tombol Edit Database',
                     'desc'=>'Pada setiap kartu database, klik ikon <i class="fas fa-edit" style="color:#f59e0b"></i> (kotak merah) untuk membuka modal Edit. Semua data koneksi yang tersimpan akan terisi otomatis dan siap diubah. Kolom password dikosongkan — isi hanya jika ingin menggantinya.',
                     'img'=>'real_db_edit_btn.png', 'label'=>'TOMBOL EDIT DATABASE'],

                    ['no'=>25, 'text'=>'Modal Edit Database',
                     'desc'=>'Modal edit menampilkan semua field yang sudah terisi dengan data lama (kotak merah). Ubah field yang perlu diperbarui, lalu klik <strong>"Simpan Database"</strong>. Anda juga dapat menjalankan uji koneksi ulang sebelum menyimpan perubahan.',
                     'img'=>'real_db_edit_modal.png', 'label'=>'MODAL EDIT DATABASE'],

                    ['no'=>26, 'text'=>'Status Badge Koneksi',
                     'desc'=>'Di bagian bawah setiap kartu terdapat badge status (kotak merah): <span style="background:rgba(16,185,129,.1);color:#047857;padding:2px 8px;border-radius:6px;font-size:0.8rem"><i class="fas fa-check-circle"></i> Connected</span> (koneksi aktif), <span style="background:rgba(239,68,68,.1);color:#b91c1c;padding:2px 8px;border-radius:6px;font-size:0.8rem"><i class="fas fa-times-circle"></i> Failed</span> (gagal), atau <span style="background:rgba(245,158,11,.1);color:#b45309;padding:2px 8px;border-radius:6px;font-size:0.8rem"><i class="fas fa-question-circle"></i> Not Tested</span> (belum diuji). Terdapat juga dot animasi di sudut kartu.',
                     'img'=>'real_db_status_badge.png', 'label'=>'BADGE STATUS KONEKSI'],

                    ['no'=>27, 'text'=>'Tombol Test Koneksi Individual',
                     'desc'=>'Pada setiap kartu, klik ikon <i class="fas fa-plug"></i> (kotak merah) untuk menguji satu koneksi database secara individual. Dot status di sudut kartu akan berubah: hijau (berhasil) atau merah (gagal) beserta notifikasi waktu respons dalam milidetik.',
                     'img'=>'real_db_delete_btn.png', 'label'=>'TEST KONEKSI INDIVIDUAL'],

                    ['no'=>28, 'text'=>'Copy Host & Nama Database',
                     'desc'=>'Pada detail koneksi di dalam kartu, terdapat tombol copy <i class="fas fa-copy"></i> (kotak merah) di samping Host:Port dan Nama Database. Klik untuk menyalin nilai ke clipboard secara instan. Notifikasi toast kecil akan muncul sebagai konfirmasi.',
                     'img'=>'real_db_copy_btn.png', 'label'=>'TOMBOL COPY HOST/DATABASE'],

                    ['no'=>29, 'text'=>'Menghapus Koneksi Database',
                     'desc'=>'Klik ikon <i class="fas fa-trash" style="color:#ef4444"></i> (kotak merah) pada kartu database yang ingin dihapus. Dialog konfirmasi SweetAlert akan muncul. Klik <strong>"Ya, Hapus"</strong> untuk melanjutkan. <strong>⚠ Perhatian:</strong> database dengan badge <i class="fas fa-star"></i> Default tidak dapat dihapus.',
                     'img'=>'real_db_delete_confirm.png', 'label'=>'KONFIRMASI HAPUS DATABASE'],
                ],
            ],
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
       5. AI MANAGEMENT
    ═══════════════════════════════════════════════════════════ */
    [
        'id'    => 'menu-ai',
        'title' => '5. AI INFRASTRUCTURE',
        'icon'  => 'fas fa-brain',
        'color' => 'linear-gradient(135deg,#06b6d4,#0284c7)',
        'desc'  => 'Pusat kendali seluruh infrastruktur AI: mendaftarkan provider (OpenAI, Gemini, dll), mengelola API Key & Model, serta memantau kesehatan key via Health Check.',
        'sections' => [
            [
                'id'    => 'ai-overview',
                'title' => '5A. Halaman AI Management & Statistik',
                'steps' => [
                    ['no'=>30, 'text'=>'Halaman AI Management — Statistik Utama',
                     'desc'=>'Bagian atas halaman menampilkan 4 kartu statistik AI (kotak merah): <strong>Total Provider</strong>, <strong>API Keys Aktif</strong>, <strong>Total Model</strong>, dan <strong>Provider Aktif</strong>. Di bawahnya terdapat grid kartu provider yang terdaftar.',
                     'img'=>'real_ai_management.png', 'label'=>'HALAMAN AI MANAGEMENT'],

                    ['no'=>31, 'text'=>'Grid Kartu Provider AI',
                     'desc'=>'Setiap provider AI (OpenAI, Gemini, Claude, Mistral, Groq, dst.) ditampilkan sebagai kartu terpisah (kotak merah). Kartu menampilkan: logo provider, jumlah key aktif, jumlah model, toggle aktif/nonaktif, dan tab untuk navigasi antara Keys dan Models.',
                     'img'=>'real_ai_providers.png', 'label'=>'GRID PROVIDER AI'],
                ],
            ],
            [
                'id'    => 'ai-provider',
                'title' => '5B. Mengelola Provider AI',
                'steps' => [
                    ['no'=>32, 'text'=>'Tombol Tambah Provider Baru',
                     'desc'=>'Klik tombol <strong>"+ Tambah Provider"</strong> (kotak merah) di header halaman untuk mendaftarkan penyedia AI baru yang belum ada dalam daftar (misal: provider lokal atau kustom).',
                     'img'=>'real_ai_add_provider_btn.png', 'label'=>'TOMBOL TAMBAH PROVIDER'],

                    ['no'=>33, 'text'=>'Modal Form Tambah Provider',
                     'desc'=>'Form (kotak merah) meminta: <strong>Nama Provider</strong>, <strong>Kode Unik</strong> (huruf kecil, misal "openai"), <strong>Base URL API</strong> (endpoint API provider), dan <strong>Status Aktif</strong>. Klik <strong>"Simpan"</strong> setelah semua terisi.',
                     'img'=>'real_ai_provider_modal.png', 'label'=>'FORM TAMBAH PROVIDER AI'],

                    ['no'=>34, 'text'=>'Toggle Aktif/Nonaktif Provider',
                     'desc'=>'Pada setiap kartu provider, terdapat toggle switch (kotak merah) di area header kartu. Klik untuk mengaktifkan atau menonaktifkan provider. Provider yang nonaktif tidak akan digunakan chatbot meskipun memiliki key yang valid.',
                     'img'=>'real_ai_toggle_provider.png', 'label'=>'TOGGLE AKTIF PROVIDER'],

                    ['no'=>35, 'text'=>'Menghapus Provider',
                     'desc'=>'Klik tombol <i class="fas fa-trash" style="color:#ef4444"></i> Hapus (kotak merah) di kartu provider. Dialog konfirmasi akan muncul. Menghapus provider akan menghapus juga semua API Key dan Model yang terdaftar di bawah provider tersebut — tindakan ini tidak dapat dibatalkan.',
                     'img'=>'real_ai_delete_provider_btn.png', 'label'=>'HAPUS PROVIDER AI'],
                ],
            ],
            [
                'id'    => 'ai-keys',
                'title' => '5C. Mengelola API Keys',
                'steps' => [
                    ['no'=>36, 'text'=>'Tab "Keys" pada Kartu Provider',
                     'desc'=>'Klik tab <strong>"Keys"</strong> (kotak merah) pada kartu provider untuk melihat semua API Key yang terdaftar. Setiap key ditampilkan dengan nama, nilai (tersembunyi), status, dan tombol aksi. Tab ini aktif secara default.',
                     'img'=>'real_ai_keys_tab.png', 'label'=>'TAB API KEYS'],

                    ['no'=>37, 'text'=>'Tombol Tambah API Key',
                     'desc'=>'Klik tombol <strong>"+ Tambah Key"</strong> <i class="fas fa-plus"></i> (kotak merah) di dalam tab Keys untuk mendaftarkan token API baru dari dashboard provider (OpenAI, Google, Anthropic, dst.).',
                     'img'=>'real_ai_add_key_btn.png', 'label'=>'TOMBOL TAMBAH API KEY'],

                    ['no'=>38, 'text'=>'Modal Form Tambah API Key',
                     'desc'=>'Form tambah key (kotak merah) meminta: <strong>Nama Key</strong> (label deskriptif, misal "OpenAI Production Key"), <strong>Nilai API Key</strong> (token rahasia dari provider — disamarkan saat diketik), <strong>Batas Token/Bulan</strong> (opsional), dan <strong>Status Aktif</strong>.',
                     'img'=>'real_ai_key_modal.png', 'label'=>'FORM TAMBAH API KEY'],

                    ['no'=>39, 'text'=>'Tombol Edit API Key',
                     'desc'=>'Klik ikon <i class="fas fa-edit" style="color:#f59e0b"></i> Edit (kotak merah) di samping nama key untuk memperbarui informasi — misalnya mengubah label, batas token, atau status aktif. Nilai key asli tidak ditampilkan ulang demi keamanan.',
                     'img'=>'real_ai_edit_key_btn.png', 'label'=>'TOMBOL EDIT API KEY'],

                    ['no'=>40, 'text'=>'Tombol Reset Limit Token',
                     'desc'=>'Jika sebuah key mencapai batas token bulanan yang ditetapkan, klik tombol <strong>"Reset Limit"</strong> <i class="fas fa-sync-alt"></i> (kotak merah) untuk mereset counter penggunaan token ke nol agar key bisa digunakan kembali.',
                     'img'=>'real_ai_reset_limit_btn.png', 'label'=>'RESET LIMIT TOKEN'],
                ],
            ],
            [
                'id'    => 'ai-models',
                'title' => '5D. Mengelola Model AI',
                'steps' => [
                    ['no'=>41, 'text'=>'Tab "Models" pada Kartu Provider',
                     'desc'=>'Klik tab <strong>"Models"</strong> (kotak merah) untuk beralih ke daftar model AI yang didukung oleh provider ini. Setiap model ditampilkan dengan nama teknis (model ID), nama tampilan, jenis (chat/completion/embedding), dan tombol aksi.',
                     'img'=>'real_ai_models_tab.png', 'label'=>'TAB MODELS AI'],

                    ['no'=>42, 'text'=>'Tombol Tambah Model AI',
                     'desc'=>'Klik tombol <strong>"+ Tambah Model"</strong> (kotak merah) untuk mendaftarkan model baru. Anda perlu mengetahui identifier teknis model yang ingin didaftarkan dari dokumentasi provider.',
                     'img'=>'real_ai_add_model_btn.png', 'label'=>'TOMBOL TAMBAH MODEL'],

                    ['no'=>43, 'text'=>'Modal Form Tambah Model AI',
                     'desc'=>'Form model (kotak merah) meminta: <strong>Model ID</strong> (identifier teknis dari provider — contoh: <code>gpt-4o-mini</code>, <code>gemini-1.5-flash</code>), <strong>Nama Tampilan</strong> (label ramah pengguna), <strong>Tipe Model</strong>, <strong>Max Token</strong>, dan <strong>Status Aktif</strong>.',
                     'img'=>'real_ai_model_modal.png', 'label'=>'FORM TAMBAH MODEL AI'],
                ],
            ],
            [
                'id'    => 'ai-health',
                'title' => '5E. Health Check — Uji Validitas API Key',
                'steps' => [
                    ['no'=>44, 'text'=>'Tombol Health Check',
                     'desc'=>'Klik tombol <strong>"Health Check"</strong> <i class="fas fa-heartbeat"></i> (kotak merah) yang ada di setiap baris API Key. Tombol ini menjalankan pengujian nyata: memanggil API provider secara langsung untuk memverifikasi key masih valid, saldo masih ada, dan batas rate-limit belum terlampaui.',
                     'img'=>'real_ai_health_btn.png', 'label'=>'TOMBOL HEALTH CHECK'],

                    ['no'=>45, 'text'=>'Hasil Health Check',
                     'desc'=>'Modal Health Check (kotak merah) menampilkan hasil pengujian secara detail: status key (Valid/Invalid/Rate Limited/Expired), waktu respons, pesan error jika ada, dan rekomendasi tindakan yang perlu diambil.',
                     'img'=>'real_ai_health_modal.png', 'label'=>'HASIL HEALTH CHECK'],
                ],
            ],
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
       6. ROLE MANAGEMENT
    ═══════════════════════════════════════════════════════════ */
    [
        'id'    => 'menu-roles',
        'title' => '6. ROLE MANAGEMENT',
        'icon'  => 'fas fa-user-shield',
        'color' => 'linear-gradient(135deg,#ec4899,#be185d)',
        'desc'  => 'Mengatur grup hak akses (Role) yang menentukan tabel database mana saja yang boleh dibaca oleh AI untuk sekelompok pengguna. Halaman ini memiliki layout dua kolom: daftar role di kiri, pengaturan izin tabel di kanan.',
        'sections' => [
            [
                'id'    => 'role-list',
                'title' => '6A. Daftar Role & Tambah Role Baru',
                'steps' => [
                    ['no'=>46, 'text'=>'Tampilan Halaman Role Management',
                     'desc'=>'Halaman terbagi dua bagian (kotak merah): <strong>Kiri</strong> — daftar semua role yang ada; <strong>Kanan</strong> — area pengaturan izin tabel untuk role yang sedang dipilih. Klik nama role di kiri untuk menampilkan izin tabelnya di kanan.',
                     'img'=>'real_role_list.png', 'label'=>'HALAMAN ROLE MANAGEMENT'],

                    ['no'=>47, 'text'=>'Tombol Tambah Role',
                     'desc'=>'Klik tombol <strong>"+ Tambah Role"</strong> (kotak merah) di pojok kanan atas untuk membuka form pembuatan role baru. Setiap role akan menjadi grup yang bisa ditetapkan ke satu atau lebih pengguna.',
                     'img'=>'real_role_tambah_btn.png', 'label'=>'TOMBOL TAMBAH ROLE'],

                    ['no'=>48, 'text'=>'Modal Form Tambah Role',
                     'desc'=>'Isi form (kotak merah): <strong>Nama Role</strong> (contoh: "Finance Team", "HRD", "Staff Gudang") dan <strong>Deskripsi</strong> (opsional, menjelaskan fungsi role). Klik <strong>"Simpan"</strong> untuk membuat role baru. Role yang baru dibuat awalnya tidak memiliki akses ke tabel mana pun.',
                     'img'=>'real_role_modal.png', 'label'=>'FORM TAMBAH ROLE'],
                ],
            ],
            [
                'id'    => 'role-permissions',
                'title' => '6B. Mengatur Izin Akses Tabel',
                'steps' => [
                    ['no'=>49, 'text'=>'Area Pengaturan Permissions',
                     'desc'=>'Setelah memilih role di panel kiri, panel kanan (kotak merah) menampilkan semua tabel dari semua database. Centang tabel yang boleh diakses AI untuk role ini. Kolom info menampilkan: berapa tabel ditampilkan, berapa yang terpilih.',
                     'img'=>'real_role_permissions.png', 'label'=>'AREA PENGATURAN IZIN TABEL'],

                    ['no'=>50, 'text'=>'Filter Bar Pencarian Tabel',
                     'desc'=>'Gunakan filter bar (kotak merah) untuk mempersempit daftar tabel: <strong>Cari</strong> berdasarkan nama tabel, <strong>Filter Database</strong> (pilih database tertentu), <strong>Filter Schema</strong> (muncul setelah pilih database), dan <strong>Filter Status</strong> (Semua / Diizinkan / Belum Diizinkan).',
                     'img'=>'real_role_filter_bar.png', 'label'=>'FILTER PENCARIAN TABEL'],

                    ['no'=>51, 'text'=>'Tombol Pilih Semua & Hapus Semua',
                     'desc'=>'Dua tombol cepat (kotak merah): <strong>"Pilih Semua" <i class="fas fa-check-square"></i></strong> — centang semua tabel yang sedang tampil (sesuai filter aktif); <strong>"Hapus Semua" <i class="fas fa-square"></i></strong> — hapus semua centang. Berguna untuk manajemen izin dalam jumlah besar.',
                     'img'=>'real_role_bulk_select.png', 'label'=>'TOMBOL PILIH/HAPUS SEMUA'],

                    ['no'=>52, 'text'=>'Tombol Simpan Izin Akses',
                     'desc'=>'Setelah selesai mencentang tabel yang diizinkan, klik <strong>"Simpan Akses"</strong> <i class="fas fa-save"></i> (kotak merah). Jika ada perubahan yang belum disimpan, indikator kuning <i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> akan muncul sebagai pengingat.',
                     'img'=>'real_role_save_permissions.png', 'label'=>'TOMBOL SIMPAN AKSES'],
                ],
            ],
            [
                'id'    => 'role-edit-del',
                'title' => '6C. Edit & Hapus Role',
                'steps' => [
                    ['no'=>53, 'text'=>'Tombol Edit Role',
                     'desc'=>'Pada panel daftar role di kiri, setiap item memiliki ikon <i class="fas fa-edit" style="color:#f59e0b"></i> (kotak merah) di samping kanan nama role. Klik ikon ini untuk membuka modal edit dan mengubah nama atau deskripsi role tanpa mempengaruhi izin tabel yang sudah diset.',
                     'img'=>'real_role_edit_btn.png', 'label'=>'TOMBOL EDIT ROLE'],

                    ['no'=>54, 'text'=>'Modal Edit Role',
                     'desc'=>'Modal edit (kotak merah) menampilkan form dengan nilai nama dan deskripsi role yang sudah ada. Ubah sesuai kebutuhan lalu klik <strong>"Simpan"</strong> untuk memperbarui, atau <strong>"Batal"</strong> untuk menutup tanpa perubahan.',
                     'img'=>'real_role_edit_modal.png', 'label'=>'FORM EDIT ROLE'],

                    ['no'=>55, 'text'=>'Menghapus Role',
                     'desc'=>'Klik ikon <i class="fas fa-trash" style="color:#ef4444"></i> (kotak merah) di samping nama role. Dialog konfirmasi SweetAlert akan muncul. Klik <strong>"Ya, Hapus"</strong> untuk menghapus role beserta seluruh izin tabelnya. <strong>⚠ Pengguna yang memiliki role ini akan kehilangan hak aksesnya.</strong>',
                     'img'=>'real_role_delete_confirm.png', 'label'=>'KONFIRMASI HAPUS ROLE'],
                ],
            ],
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
       7. USER MANAGEMENT
    ═══════════════════════════════════════════════════════════ */
    [
        'id'    => 'menu-users',
        'title' => '7. USER MANAGEMENT',
        'icon'  => 'fas fa-users-cog',
        'color' => 'linear-gradient(135deg,#14b8a6,#0f766e)',
        'desc'  => 'Pengaturan paling mendalam untuk setiap akun pengguna: tambah/edit/hapus akun, impor massal via CSV, ekspor data, konfigurasi AI per user, dan pembatasan baris data (Row Level Security).',
        'sections' => [
            [
                'id'    => 'user-list',
                'title' => '7A. Tampilan Tabel User & Aksi Header',
                'steps' => [
                    ['no'=>56, 'text'=>'Halaman Daftar User (Tabel)',
                     'desc'=>'Semua pengguna ditampilkan dalam tabel (kotak merah) dengan kolom: <strong>Nama, Email, Role, Hak Akses</strong> (Super Admin/Admin/User), <strong>AI Models, API Keys</strong> (yang didelegasikan), <strong>Cakupan</strong> (bebas/database), <strong>Dibuat</strong> (tanggal & oleh siapa), dan <strong>Aksi</strong>.',
                     'img'=>'real_user_list.png', 'label'=>'TABEL DAFTAR USER'],

                    ['no'=>57, 'text'=>'Header Aksi: Template, Import, Export & Tambah User',
                     'desc'=>'Di pojok kanan atas terdapat 4 tombol (kotak merah): <br>• <i class="fas fa-download" style="color:#10b981"></i> <strong>Template</strong> — unduh file CSV contoh <br>• <i class="fas fa-file-import" style="color:#0ea5e9"></i> <strong>Import</strong> — impor user massal dari file CSV <br>• <i class="fas fa-file-export"></i> <strong>Export</strong> — ekspor semua data user ke file <br>• <i class="fas fa-plus" style="color:#6366f1"></i> <strong>Tambah User</strong> — tambah akun manual',
                     'img'=>'real_user_header_btns.png', 'label'=>'TOMBOL AKSI HEADER'],

                    ['no'=>58, 'text'=>'Filter & Pencarian User',
                     'desc'=>'Di bawah header terdapat form filter (kotak merah): <strong>Kolom Cari</strong> (nama atau email) dan <strong>Dropdown Filter Role</strong> (tampilkan user berdasarkan role tertentu). Klik <strong>"Filter"</strong> untuk menerapkan atau <strong>"Reset"</strong> untuk membersihkan filter.',
                     'img'=>'real_user_filter_form.png', 'label'=>'FORM FILTER USER'],
                ],
            ],
            [
                'id'    => 'user-add',
                'title' => '7B. Menambah & Mengedit User',
                'steps' => [
                    ['no'=>59, 'text'=>'Tombol "Tambah User"',
                     'desc'=>'Klik tombol <strong>"+ Tambah User"</strong> biru (kotak merah) di header untuk membuka form tambah akun baru secara manual.',
                     'img'=>'real_user_tambah_btn.png', 'label'=>'TOMBOL TAMBAH USER'],

                    ['no'=>60, 'text'=>'Modal Form Tambah User',
                     'desc'=>'Form tambah user (kotak merah) berisi field: <strong>Nama Lengkap</strong>, <strong>Email</strong> (wajib unik), <strong>Password</strong> (min. 8 karakter), <strong>Konfirmasi Password</strong>, <strong>Role</strong> (pilih dari daftar role yang ada), dan opsi <strong>Is Admin / Is Super Admin</strong>.',
                     'img'=>'real_user_modal.png', 'label'=>'FORM TAMBAH USER'],

                    ['no'=>61, 'text'=>'Field Nama & Email (Wajib Diisi)',
                     'desc'=>'Field <strong>Nama Lengkap</strong> dan <strong>Email</strong> (kotak merah) adalah field wajib. Email harus berformat valid dan belum terdaftar di sistem. Jika email sudah digunakan, sistem akan menampilkan pesan error saat menyimpan.',
                     'img'=>'real_user_field_name.png', 'label'=>'FIELD NAMA & EMAIL'],

                    ['no'=>62, 'text'=>'Dropdown Pilih Role',
                     'desc'=>'Dropdown <strong>Role</strong> (kotak merah) menampilkan semua role yang sudah dibuat di modul Role Management. Pilih role yang sesuai untuk menentukan tabel mana yang bisa dianalisis AI untuk user ini. User tanpa role tidak bisa menggunakan chatbot dengan data spesifik.',
                     'img'=>'real_user_field_role.png', 'label'=>'DROPDOWN PILIH ROLE'],

                    ['no'=>63, 'text'=>'Tombol Edit User',
                     'desc'=>'Pada baris user di tabel, klik tombol <i class="fas fa-edit" style="color:#f59e0b"></i> Edit (kotak merah) untuk membuka modal edit. Semua data user terisi otomatis. Kolom password kosong — isi hanya jika ingin mengganti password user tersebut.',
                     'img'=>'real_user_edit_btn.png', 'label'=>'TOMBOL EDIT USER'],

                    ['no'=>64, 'text'=>'Modal Edit User',
                     'desc'=>'Modal edit (kotak merah) identik dengan modal tambah, namun semua field sudah terisi dengan data user yang dipilih. Ubah field yang perlu diperbarui, kosongkan password jika tidak ingin mengubahnya, lalu klik <strong>"Simpan"</strong>.',
                     'img'=>'real_edit_user_modal.png', 'label'=>'FORM EDIT USER'],
                ],
            ],
            [
                'id'    => 'user-import',
                'title' => '7C. Import & Export Data User',
                'steps' => [
                    ['no'=>65, 'text'=>'Unduh Template CSV',
                     'desc'=>'Klik tombol <strong>"Template"</strong> <i class="fas fa-download"></i> (kotak merah) untuk mengunduh file CSV contoh dengan header kolom yang benar: name, email, password, role, is_admin. Gunakan file ini sebagai dasar pengisian data sebelum impor massal.',
                     'img'=>'real_user_template_btn.png', 'label'=>'UNDUH TEMPLATE CSV'],

                    ['no'=>66, 'text'=>'Import User dari CSV',
                     'desc'=>'Klik tombol <strong>"Import"</strong> <i class="fas fa-file-import"></i> (kotak merah) untuk membuka modal impor. Unggah file CSV yang sudah diisi sesuai template. Sistem akan memvalidasi setiap baris dan membuat akun user secara massal. Error per baris akan dilaporkan.',
                     'img'=>'real_user_import_modal.png', 'label'=>'MODAL IMPORT CSV'],

                    ['no'=>67, 'text'=>'Export Data User',
                     'desc'=>'Klik tombol <strong>"Export"</strong> <i class="fas fa-file-export"></i> (kotak merah) untuk mengunduh seluruh data user dalam format file. Data yang diekspor mencakup semua informasi user kecuali password yang dienkripsi.',
                     'img'=>'real_user_export_btn.png', 'label'=>'EXPORT DATA USER'],
                ],
            ],
            [
                'id'    => 'user-advanced',
                'title' => '7D. Konfigurasi AI per User (Delegasi)',
                'steps' => [
                    ['no'=>68, 'text'=>'Tombol AI Config (per User)',
                     'desc'=>'Pada setiap baris user, klik tombol <i class="fas fa-brain"></i> biru AI Config (kotak merah). Fitur ini memungkinkan Admin mendelegasikan model dan API key tertentu secara khusus untuk satu user — terlepas dari pengaturan default sistem.',
                     'img'=>'real_user_ai_btn.png', 'label'=>'TOMBOL AI CONFIG'],

                    ['no'=>69, 'text'=>'Modal Konfigurasi AI per User',
                     'desc'=>'Modal AI Config (kotak merah) menampilkan daftar semua model dan API key yang tersedia. Centang model dan key yang ingin didelegasikan untuk user ini. User hanya bisa menggunakan model dan key yang dicentang, sehingga penggunaan dapat dikontrol per individu.',
                     'img'=>'real_user_ai_modal.png', 'label'=>'KONFIGURASI AI PER USER'],
                ],
            ],
            [
                'id'    => 'user-rls',
                'title' => '7E. Row Level Security (RLS) — Filter Data Baris',
                'steps' => [
                    ['no'=>70, 'text'=>'Tombol RLS / Data Filter',
                     'desc'=>'Klik tombol <i class="fas fa-filter"></i> Filter (kotak merah) pada baris user untuk membuka modal Row Level Security. Jika user sudah memiliki filter aktif, tombol akan berwarna berbeda dan menampilkan badge jumlah filter.',
                     'img'=>'real_user_rls_btn.png', 'label'=>'TOMBOL RLS DATA FILTER'],

                    ['no'=>71, 'text'=>'Modal Row Level Security',
                     'desc'=>'Modal RLS (kotak merah) memungkinkan Admin membatasi baris data yang bisa dianalisis AI untuk user tertentu. Misalnya: user Cabang Jakarta hanya bisa melihat data dengan kolom <code>cabang = \'Jakarta\'</code>. Pilih tabel, kolom, operator, dan nilai filter, lalu klik <strong>"Tambah Rule"</strong>.',
                     'img'=>'real_user_rls_modal.png', 'label'=>'MODAL ROW LEVEL SECURITY'],
                ],
            ],
            [
                'id'    => 'user-del',
                'title' => '7F. Menghapus User',
                'steps' => [
                    ['no'=>72, 'text'=>'Tombol Hapus User',
                     'desc'=>'Klik tombol <i class="fas fa-trash" style="color:#ef4444"></i> merah (kotak merah) di kolom Aksi pada baris user yang ingin dihapus. Dialog konfirmasi SweetAlert akan muncul sebelum penghapusan dilakukan.',
                     'img'=>'real_user_delete_btn.png', 'label'=>'TOMBOL HAPUS USER'],

                    ['no'=>73, 'text'=>'Konfirmasi Hapus User',
                     'desc'=>'Dialog SweetAlert (kotak merah) menampilkan nama user yang akan dihapus dan meminta konfirmasi. Klik <strong>"Ya, Hapus"</strong> untuk menghapus akun secara permanen beserta seluruh data terkait (konfigurasi AI, filter RLS, riwayat chat). Tindakan ini <strong>tidak dapat dibatalkan</strong>.',
                     'img'=>'real_user_delete_confirm.png', 'label'=>'KONFIRMASI HAPUS USER'],
                ],
            ],
        ],
    ],

]; // end $guideData
@endphp

{{-- ══════════════════════════════════════════════════════════════
     CSS
══════════════════════════════════════════════════════════════ --}}
<style>
/* ── Layout ── */
.guide-wrap     { display:flex; gap:0; align-items:flex-start; width:100%; }
.guide-toc      {
    width:300px; min-width:300px; flex-shrink:0;
    position:sticky; top:80px; max-height:calc(100vh - 100px); overflow-y:auto;
    background:var(--card-bg); border:1px solid var(--glass-border);
    border-radius:18px; padding:1.25rem; margin-right:2rem;
    box-shadow:var(--shadow-md);
}
.guide-content  { flex:1; min-width:0; }

/* ── TOC Links ── */
.toc-title      { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--text-muted); margin-bottom:.75rem; }
.toc-menu-link  {
    display:flex; align-items:center; gap:8px;
    color:var(--primary); padding:9px 12px; border-radius:10px;
    font-size:.82rem; font-weight:800; text-decoration:none;
    margin-top:14px; background:rgba(99,102,241,.08);
    border:1px solid rgba(99,102,241,.12); transition:all .2s;
}
.toc-menu-link:hover { background:rgba(99,102,241,.16); }
.toc-link       {
    display:block; color:var(--text-muted); padding:6px 14px;
    font-size:.78rem; font-weight:600; text-decoration:none;
    border-left:2px solid var(--glass-border2); margin-left:4px;
    transition:all .2s;
}
.toc-link:hover { color:var(--primary); border-left-color:var(--primary); background:rgba(99,102,241,.04); padding-left:18px; }
.toc-step-count { font-size:.68rem; background:rgba(99,102,241,.12); color:var(--primary); padding:1px 7px; border-radius:20px; float:right; font-weight:700; }

/* ── Menu Section Card ── */
.menu-section   {
    background:var(--card-bg); border:1px solid var(--glass-border);
    border-radius:24px; padding:2.5rem; margin-bottom:3.5rem;
    box-shadow:var(--shadow-lg); scroll-margin-top:90px;
}
.menu-icon      {
    width:56px; height:56px; min-width:56px; border-radius:16px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.4rem; color:#fff;
    box-shadow:0 8px 20px rgba(0,0,0,.25);
}
.menu-section-title { font-weight:900; font-size:2rem; color:var(--text-main); margin:0; letter-spacing:-1px; }
.menu-section-desc  { color:var(--text-muted); font-size:.95rem; margin:.5rem 0 0; max-width:640px; line-height:1.6; }

/* ── Sub-section ── */
.sub-section    { margin-top:3rem; scroll-margin-top:100px; }
.sub-section-title {
    font-weight:800; font-size:1.25rem; color:var(--text-main);
    border-left:5px solid var(--primary); padding-left:1.25rem;
    margin-bottom:2rem; display:flex; align-items:center; gap:10px;
}

/* ── Step ── */
.guide-step     {
    display:flex; gap:22px; padding:2.25rem 0;
    border-bottom:1px solid var(--glass-border2);
}
.guide-step:last-child { border-bottom:none; padding-bottom:0; }
.step-num       {
    width:46px; height:46px; min-width:46px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-weight:900; font-size:1.2rem; flex-shrink:0;
    background:#0f172a; color:#fff; border:3px solid var(--primary);
    box-shadow:0 4px 16px rgba(99,102,241,.35);
}
html.dark .step-num { background:var(--primary); border-color:#fff; }
.step-title     { font-weight:800; font-size:1.1rem; color:var(--text-main); margin-bottom:.75rem; }
.step-desc      {
    color:var(--text-muted); font-size:.9rem; line-height:1.85;
    background:rgba(0,0,0,.02); padding:.9rem 1.1rem;
    border-radius:12px; border:1px dashed var(--glass-border2);
}
html.dark .step-desc { background:rgba(255,255,255,.02); }

/* ── Screenshot ── */
.screenshot-wrap {
    margin-top:1.5rem; border-radius:14px; overflow:hidden;
    border:4px solid #ef4444; position:relative; background:#000;
    box-shadow:0 12px 35px rgba(0,0,0,.3); cursor:zoom-in;
    transition:box-shadow .3s;
}
.screenshot-wrap:hover { box-shadow:0 16px 48px rgba(239,68,68,.4); }
.screenshot-img {
    width:100%; max-height:680px; object-fit:contain;
    display:block; transition:transform .4s;
}
.screenshot-img:hover { transform:scale(1.01); }
.screenshot-badge {
    position:absolute; bottom:0; left:0; right:0;
    padding:10px; font-size:.75rem; font-weight:900; letter-spacing:.08em;
    text-align:center; color:#fff; background:rgba(239,68,68,.88);
    text-transform:uppercase;
}

/* ── Lightbox ── */
.img-lightbox   {
    display:none; position:fixed; inset:0; z-index:99999;
    background:rgba(0,0,0,.97); align-items:center; justify-content:center;
    cursor:zoom-out; flex-direction:column; gap:1rem;
}
.img-lightbox.show { display:flex; }
.img-lightbox img  { max-width:96vw; max-height:90vh; border:3px solid #fff; border-radius:10px; }
.lightbox-close    {
    position:fixed; top:1.5rem; right:1.5rem;
    background:rgba(239,68,68,.9); border:none; color:#fff;
    width:44px; height:44px; border-radius:50%; font-size:1.2rem;
    cursor:pointer; display:flex; align-items:center; justify-content:center;
}

/* ── Print & floating btn ── */
.print-btn {
    position:fixed; bottom:2.5rem; right:2.5rem; z-index:1000;
    padding:14px 28px; border-radius:60px; font-weight:900;
    box-shadow:0 12px 30px rgba(99,102,241,.45); transition:all .3s;
}
.print-btn:hover { transform:translateY(-4px); box-shadow:0 18px 40px rgba(99,102,241,.55); }

/* ── Progress bar ── */
.guide-progress-bar {
    position:fixed; top:0; left:0; height:3px; z-index:9999;
    background:linear-gradient(90deg,#6366f1,#10b981);
    transition:width .1s;
}

@media print {
    .guide-toc,.print-btn,.chatbot-back-btn,.guide-progress-bar { display:none !important; }
    .guide-content { width:100%; }
    .menu-section  { break-inside:avoid; box-shadow:none; border:1px solid #e2e8f0; }
    .screenshot-wrap { box-shadow:none; }
}
@media (max-width:900px) {
    .guide-wrap   { flex-direction:column; }
    .guide-toc    { width:100%; min-width:0; position:static; max-height:none; margin:0 0 1.5rem; }
    .menu-section { padding:1.5rem; }
    .guide-step   { gap:14px; }
}
</style>

{{-- Lightbox --}}
<div class="img-lightbox" id="imgLightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
    <img id="lightboxImg" src="" alt="">
</div>

{{-- Progress bar --}}
<div class="guide-progress-bar" id="progressBar" style="width:0%"></div>

{{-- Header --}}
<div class="mb-5 d-flex justify-content-between align-items-end flex-wrap gap-3">
    <div>
        <a href="{{ route('chatbot') }}" class="btn btn-outline-secondary btn-sm mb-3 chatbot-back-btn">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Chatbot
        </a>
        <h1 style="color:var(--text-main);font-weight:900;font-size:2.6rem;margin:0;letter-spacing:-1.5px;line-height:1.1;">
            Buku Panduan Admin Panel
        </h1>
        <p class="text-muted mt-2 mb-0" style="font-size:1rem;">
            Dokumentasi operasional lengkap · {{ collect($guideData)->sum(fn($m) => collect($m['sections'])->sum(fn($s) => count($s['steps']))) }} langkah · Semua tombol & fitur diuji
        </p>
    </div>
    <button onclick="window.print()" class="btn btn-primary print-btn">
        <i class="fas fa-print me-2"></i> Cetak PDF
    </button>
</div>

{{-- Wrap: TOC + Content --}}
<div class="guide-wrap">

    {{-- ── TABLE OF CONTENTS ── --}}
    <nav class="guide-toc" id="guideToc">
        <p class="toc-title">Navigasi Panduan</p>
        @foreach($guideData as $menu)
            @php $stepCount = collect($menu['sections'])->sum(fn($s) => count($s['steps'])); @endphp
            <a class="toc-menu-link" href="#{{ $menu['id'] }}">
                <i class="{{ $menu['icon'] }}"></i>
                <span style="flex:1">{{ $menu['title'] }}</span>
                <span class="toc-step-count">{{ $stepCount }}</span>
            </a>
            @foreach($menu['sections'] as $sec)
                <a class="toc-link" href="#{{ $sec['id'] }}">{{ $sec['title'] }}</a>
            @endforeach
        @endforeach
    </nav>

    {{-- ── CONTENT ── --}}
    <div class="guide-content">
        @foreach($guideData as $menu)
            <section id="{{ $menu['id'] }}" class="menu-section">

                {{-- Menu Header --}}
                <div class="d-flex align-items-center gap-4 mb-4">
                    <div class="menu-icon" style="background:{{ $menu['color'] }}">
                        <i class="{{ $menu['icon'] }}"></i>
                    </div>
                    <div>
                        <h2 class="menu-section-title">{{ $menu['title'] }}</h2>
                        <p class="menu-section-desc">{{ $menu['desc'] }}</p>
                    </div>
                </div>

                @foreach($menu['sections'] as $sec)
                    <div id="{{ $sec['id'] }}" class="sub-section">
                        <h4 class="sub-section-title">{{ $sec['title'] }}</h4>

                        @foreach($sec['steps'] as $step)
                            <div class="guide-step">
                                <div class="step-num">{{ $step['no'] }}</div>
                                <div class="flex-grow-1">
                                    <div class="step-title">{{ $step['text'] }}</div>
                                    <div class="step-desc">{!! $step['desc'] !!}</div>
                                    <div class="screenshot-wrap"
                                         onclick="openLightbox('{{ asset('admin_guide/' . $step['img']) }}')">
                                        <img
                                            src="{{ asset('admin_guide/' . $step['img']) }}"
                                            class="screenshot-img"
                                            alt="{{ $step['text'] }}"
                                            onerror="this.src='https://placehold.co/1280x720/1e293b/ef4444?text=Screenshot+{{ urlencode($step['img']) }}'">
                                        <div class="screenshot-badge">
                                            {{ $step['label'] }} — klik untuk tampilan penuh
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @endforeach

        {{-- Footer --}}
        <div class="text-center py-5 mb-4">
            <div style="width:72px;height:72px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;box-shadow:0 8px 24px rgba(16,185,129,.35);">
                <i class="fas fa-check fa-2x text-white"></i>
            </div>
            <h3 style="font-weight:800;color:var(--text-main);">Panduan Selesai</h3>
            <p class="text-muted">Dokumentasi ini mencakup seluruh fitur Admin Panel DarkoAI.<br>Gunakan sebagai Standar Operasional Prosedur (SOP) administrasi sistem.</p>
            <button onclick="window.scrollTo({top:0,behavior:'smooth'})" class="btn btn-outline-secondary mt-3">
                <i class="fas fa-arrow-up me-1"></i> Kembali ke Atas
            </button>
        </div>
    </div>
</div>

<script>
/* ── Lightbox ── */
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('imgLightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('imgLightbox').classList.remove('show');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

/* ── Smooth scroll untuk TOC links ── */
document.querySelectorAll('.toc-link, .toc-menu-link').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        const target = document.querySelector(a.getAttribute('href'));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

/* ── Progress bar saat scroll ── */
window.addEventListener('scroll', () => {
    const scrolled = window.scrollY;
    const total    = document.documentElement.scrollHeight - window.innerHeight;
    const pct      = total > 0 ? (scrolled / total * 100).toFixed(1) : 0;
    document.getElementById('progressBar').style.width = pct + '%';
});

/* ── Highlight TOC link aktif ── */
const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            document.querySelectorAll('.toc-menu-link, .toc-link').forEach(a => {
                a.style.background = '';
                a.style.color = '';
            });
            const id  = entry.target.id;
            const sel = `.toc-menu-link[href="#${id}"], .toc-link[href="#${id}"]`;
            const el  = document.querySelector(sel);
            if (el) {
                el.style.background = 'rgba(99,102,241,.18)';
                el.style.color      = 'var(--primary)';
            }
        }
    });
}, { threshold: 0.15, rootMargin: '-80px 0px -60% 0px' });

document.querySelectorAll('[id^="menu-"], [id^="auth-"],[id^="chat-"],[id^="dash-"],[id^="db-"],[id^="ai-"],[id^="role-"],[id^="user-"]')
    .forEach(el => observer.observe(el));
</script>
@endsection
