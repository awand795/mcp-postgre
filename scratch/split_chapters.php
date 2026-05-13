<?php
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
                     'desc'=>'Akses URL sistem di browser. Akan muncul form kartu bertajuk <strong>Sign In</strong>. Pastikan URL sudah benar dan koneksi internet tersedia.',
                     'img'=>'real_login_page.png', 'label'=>'HALAMAN LOGIN UTAMA'],

                    ['no'=>2, 'text'=>'Isi Field Email',
                     'desc'=>'Klik kolom <strong>Email</strong> yang ditandai kotak merah, lalu ketik alamat email akun Anda. Contoh: <code>admin@darkotech.id</code>. Email tidak bersifat case-sensitive.',
                     'img'=>'real_login_email.png', 'label'=>'FIELD EMAIL'],

                    ['no'=>3, 'text'=>'Isi Field Password',
                     'desc'=>'Klik kolom <strong>Password</strong> (kotak merah) lalu ketik password Anda. Gunakan ikon mata di ujung kanan field untuk menampilkan/menyembunyikan karakter.',
                     'img'=>'real_login_password.png', 'label'=>'FIELD PASSWORD'],

                    ['no'=>4, 'text'=>'Klik Tombol LOGIN',
                     'desc'=>'Klik tombol biru <strong>"Login"</strong> (kotak merah). Sistem akan memvalidasi kredensial. Jika berhasil → langsung masuk ke Chatbot/Dashboard. Jika gagal → pesan error merah tampil di atas form.',
                     'img'=>'real_login_button.png', 'label'=>'TOMBOL LOGIN'],

                    ['no'=>5, 'text'=>'Berhasil Masuk ke Sistem',
                     'desc'=>'Setelah login berhasil, Anda diarahkan ke halaman utama Chatbot. Sidebar kiri menampilkan menu Admin jika akun Anda adalah Administrator.',
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
                     'desc'=>'Pada halaman Forgot Password, isi kolom email (kotak merah) dengan email terdaftar Anda, lalu klik <strong>"Kirim Kode OTP"</strong>. Sistem mengirimkan 6-digit kode ke inbox email dalam beberapa detik.',
                     'img'=>'real_forgot_email_field.png', 'label'=>'EMAIL PEMULIHAN'],

                    ['no'=>8, 'text'=>'Verifikasi Kode OTP 6 Digit',
                     'desc'=>'Buka inbox email Anda, salin kode 6 digit yang diterima, lalu masukkan ke kotak verifikasi (kotak merah) secara berurutan. <strong>⚠ Kode hanya berlaku 10 menit.</strong> Periksa folder Spam jika tidak masuk Inbox.',
                     'img'=>'real_verify_otp_page.png', 'label'=>'VERIFIKASI OTP'],

                    ['no'=>9, 'text'=>'Buat Password Baru',
                     'desc'=>'Setelah OTP terverifikasi, masukkan password baru (min. 8 karakter), ulangi di kolom konfirmasi, lalu klik <strong>"Simpan Password Baru"</strong>.',
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
                     'desc'=>'Setelah login, halaman ini yang pertama muncul. Area tengah adalah percakapan dengan AI. Ketik pertanyaan pada kolom input bawah (kotak merah) — contoh: <em>"Tampilkan 10 transaksi terbesar bulan ini"</em> — lalu tekan <kbd>Enter</kbd> atau klik tombol kirim.',
                     'img'=>'real_chatbot_page.png', 'label'=>'HALAMAN UTAMA CHATBOT'],

                    ['no'=>11, 'text'=>'Membuka Sidebar Riwayat Chat',
                     'desc'=>'Klik ikon <i class="fas fa-bars"></i> hamburger di pojok kiri atas (kotak merah) untuk membuka panel Sidebar Riwayat. Di sini Anda bisa melihat semua sesi percakapan lama. Klik judul chat untuk membuka kembali.',
                     'img'=>'real_chatbot_sidebar.png', 'label'=>'SIDEBAR RIWAYAT CHAT'],

                    ['no'=>12, 'text'=>'Menghapus Sesi Chat',
                     'desc'=>'Klik ikon <i class="fas fa-trash"></i> sampah pada judul chat di sidebar. Dialog konfirmasi (kotak merah) akan muncul. Klik <strong>"Ya, Hapus"</strong> untuk menghapus permanen, atau <strong>"Batal"</strong> untuk membatalkan.',
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
        'desc'  => 'Halaman pertama yang dilihat Admin. Menampilkan statistik sistem real-time, navigasi sidebar, dan pengaturan tema tampilan.',
        'sections' => [
            [
                'id'    => 'dash-stats',
                'title' => '3A. Kartu Statistik & Navigasi',
                'steps' => [
                    ['no'=>13, 'text'=>'Dashboard Overview — 4 Kartu Statistik',
                     'desc'=>'Dashboard menampilkan 4 kartu (kotak merah): <strong>Total Users</strong>, <strong>Total Roles</strong>, <strong>Total Databases</strong>, dan <strong>Total Tables</strong>. Angka diperbarui real-time.',
                     'img'=>'real_dashboard.png', 'label'=>'KARTU STATISTIK SISTEM'],

                    ['no'=>14, 'text'=>'Sidebar Navigasi Admin',
                     'desc'=>'Sidebar kiri (kotak merah) adalah navigasi utama Admin Panel. Berisi menu: <strong>Dashboard, Management Database, AI Management, Management Role, Management User,</strong> dan <strong>Panduan</strong>. Di bagian bawah terdapat info user yang sedang login.',
                     'img'=>'real_sidebar.png', 'label'=>'NAVIGASI SIDEBAR ADMIN'],
                ],
            ],
            [
                'id'    => 'dash-theme',
                'title' => '3B. Fitur Dark Mode',
                'steps' => [
                    ['no'=>15, 'text'=>'Tombol Toggle Dark/Light Mode',
                     'desc'=>'Temukan toggle tema (kotak merah) di bagian atas header. Klik sekali untuk beralih dari Light Mode ke Dark Mode. Preferensi disimpan otomatis di browser.',
                     'img'=>'real_dash_darkmode.png', 'label'=>'TOGGLE DARK MODE'],

                    ['no'=>16, 'text'=>'Tampilan Dark Mode Aktif',
                     'desc'=>'Saat Dark Mode aktif, seluruh antarmuka berubah ke palet warna gelap (kotak merah). Sangat nyaman untuk penggunaan di kondisi pencahayaan rendah.',
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
                     'desc'=>'Semua koneksi database yang terdaftar ditampilkan sebagai kartu (kotak merah). Setiap kartu memuat: nama alias, driver (PostgreSQL/MySQL/MariaDB), host:port, status koneksi, dan tombol aksi.',
                     'img'=>'real_db_list.png', 'label'=>'DAFTAR KONEKSI DATABASE'],

                    ['no'=>18, 'text'=>'Toolbar: Pencarian, Filter, & View Toggle',
                     'desc'=>'Toolbar (kotak merah) berisi: <strong>Kolom Pencarian</strong>, <strong>Filter Driver</strong>, <strong>Filter Status</strong>, dan <strong>Toggle View</strong> Grid/List.',
                     'img'=>'real_db_toolbar.png', 'label'=>'TOOLBAR PENCARIAN & FILTER'],

                    ['no'=>19, 'text'=>'Tombol "Test All Connections"',
                     'desc'=>'Klik tombol <strong>"Test All"</strong> (kotak merah) di pojok kanan atas untuk menguji semua koneksi database sekaligus. Hasilnya ditampilkan dalam health bar: Total, <span style="color:#10b981">Connected</span>, dan <span style="color:#ef4444">Failed</span>.',
                     'img'=>'real_db_test_all.png', 'label'=>'TOMBOL TEST ALL CONNECTIONS'],

                    ['no'=>20, 'text'=>'Tombol "Tambah Database"',
                     'desc'=>'Klik tombol <strong>"+ Tambah Database"</strong> (kotak merah) untuk membuka wizard penambahan koneksi baru. Wizard terdiri dari 3 langkah.',
                     'img'=>'real_db_tambah_btn.png', 'label'=>'TOMBOL TAMBAH DATABASE'],
                ],
            ],
            [
                'id'    => 'db-add',
                'title' => '4B. Wizard Tambah Database (3 Langkah)',
                'steps' => [
                    ['no'=>21, 'text'=>'Step 1 — Identitas: Nama, Kode & Driver',
                     'desc'=>'Wizard langkah pertama meminta: <br>• <strong>Nama Koneksi/Alias</strong> — nama tampilan (contoh: "Production DB") <br>• <strong>Kode</strong> — pengenal unik huruf kecil & underscore <br>• <strong>Driver</strong> — pilih: PostgreSQL, MySQL, atau MariaDB <br>• Centang <strong>Aktif</strong> dan/atau <strong>Default</strong> jika perlu.',
                     'img'=>'real_db_modal_step1.png', 'label'=>'WIZARD STEP 1: IDENTITAS'],

                    ['no'=>22, 'text'=>'Step 2 — Koneksi: Host, Port, Kredensial & Schema',
                     'desc'=>'Isi detail koneksi server: <br>• <strong>Host</strong> — IP atau hostname server database <br>• <strong>Port</strong> — otomatis terisi sesuai driver (5432/3306) <br>• <strong>Nama Database</strong> — nama database asli di server <br>• <strong>Username & Password</strong> — kredensial akses database <br>• <strong>Schema</strong> — klik Load untuk deteksi otomatis',
                     'img'=>'real_db_modal_step2.png', 'label'=>'WIZARD STEP 2: KONEKSI'],

                    ['no'=>23, 'text'=>'Step 3 — Test Koneksi Sebelum Simpan',
                     'desc'=>'Klik <strong>"Test Sekarang"</strong> untuk memverifikasi parameter yang diisi. Hasil muncul di bawah tombol: hijau = berhasil, merah = gagal. Setelah berhasil, klik <strong>"Simpan Database"</strong>.',
                     'img'=>'real_db_modal_step3.png', 'label'=>'WIZARD STEP 3: TEST & SIMPAN'],
                ],
            ],
            [
                'id'    => 'db-manage',
                'title' => '4C. Mengelola Koneksi yang Sudah Ada',
                'steps' => [
                    ['no'=>24, 'text'=>'Tombol Edit Database',
                     'desc'=>'Pada setiap kartu database, klik ikon <i class="fas fa-edit" style="color:#f59e0b"></i> (kotak merah) untuk membuka modal Edit. Semua data koneksi yang tersimpan akan terisi otomatis.',
                     'img'=>'real_db_edit_btn.png', 'label'=>'TOMBOL EDIT DATABASE'],

                    ['no'=>25, 'text'=>'Modal Edit Database',
                     'desc'=>'Modal edit menampilkan semua field yang sudah terisi (kotak merah). Ubah field yang perlu diperbarui, lalu klik <strong>"Simpan Database"</strong>.',
                     'img'=>'real_db_edit_modal.png', 'label'=>'MODAL EDIT DATABASE'],

                    ['no'=>26, 'text'=>'Badge Status Koneksi',
                     'desc'=>'Di bagian bawah setiap kartu terdapat badge status (kotak merah): <span style="background:rgba(16,185,129,.1);color:#047857;padding:2px 8px;border-radius:6px;font-size:.8rem"><i class="fas fa-check-circle"></i> Connected</span>, <span style="background:rgba(239,68,68,.1);color:#b91c1c;padding:2px 8px;border-radius:6px;font-size:.8rem"><i class="fas fa-times-circle"></i> Failed</span>, atau <span style="background:rgba(245,158,11,.1);color:#b45309;padding:2px 8px;border-radius:6px;font-size:.8rem"><i class="fas fa-question-circle"></i> Not Tested</span>.',
                     'img'=>'real_db_status_badge.png', 'label'=>'BADGE STATUS KONEKSI'],

                    ['no'=>27, 'text'=>'Copy Host & Nama Database',
                     'desc'=>'Pada detail koneksi di kartu, terdapat tombol copy <i class="fas fa-copy"></i> (kotak merah) di samping Host:Port dan Nama Database. Klik untuk menyalin nilai ke clipboard. Notifikasi toast akan muncul sebagai konfirmasi.',
                     'img'=>'real_db_copy_btn.png', 'label'=>'TOMBOL COPY HOST/DATABASE'],

                    ['no'=>28, 'text'=>'Menghapus Koneksi Database',
                     'desc'=>'Klik ikon <i class="fas fa-trash" style="color:#ef4444"></i> (kotak merah) pada kartu database yang ingin dihapus. Dialog konfirmasi SweetAlert akan muncul. <strong>⚠ Database bertanda Default tidak dapat dihapus.</strong>',
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
                    ['no'=>29, 'text'=>'Halaman AI Management — Statistik Utama',
                     'desc'=>'Bagian atas menampilkan 4 kartu statistik AI (kotak merah): <strong>Total Provider</strong>, <strong>API Keys Aktif</strong>, <strong>Total Model</strong>, dan <strong>Provider Aktif</strong>. Di bawahnya terdapat grid kartu provider.',
                     'img'=>'real_ai_management.png', 'label'=>'HALAMAN AI MANAGEMENT'],

                    ['no'=>30, 'text'=>'Grid Kartu Provider AI',
                     'desc'=>'Setiap provider AI ditampilkan sebagai kartu terpisah (kotak merah). Kartu menampilkan: logo provider, jumlah key aktif, jumlah model, toggle aktif/nonaktif, dan tab Keys/Models.',
                     'img'=>'real_ai_providers.png', 'label'=>'GRID PROVIDER AI'],
                ],
            ],
            [
                'id'    => 'ai-provider',
                'title' => '5B. Mengelola Provider AI',
                'steps' => [
                    ['no'=>31, 'text'=>'Tombol Tambah Provider Baru',
                     'desc'=>'Klik tombol <strong>"+ Tambah Provider"</strong> (kotak merah) di header untuk mendaftarkan penyedia AI baru.',
                     'img'=>'real_ai_add_provider_btn.png', 'label'=>'TOMBOL TAMBAH PROVIDER'],

                    ['no'=>32, 'text'=>'Modal Form Tambah Provider',
                     'desc'=>'Form (kotak merah) meminta: <strong>Nama Provider</strong>, <strong>Kode Unik</strong> (huruf kecil, misal "openai"), <strong>Base URL API</strong>, dan <strong>Status Aktif</strong>. Klik <strong>"Simpan"</strong> setelah semua terisi.',
                     'img'=>'real_ai_provider_modal.png', 'label'=>'FORM TAMBAH PROVIDER AI'],

                    ['no'=>33, 'text'=>'Toggle Aktif/Nonaktif Provider',
                     'desc'=>'Pada kartu provider, terdapat toggle switch (kotak merah). Klik untuk mengaktifkan atau menonaktifkan provider. Provider nonaktif tidak digunakan chatbot meskipun punya key yang valid.',
                     'img'=>'real_ai_toggle_provider.png', 'label'=>'TOGGLE AKTIF PROVIDER'],

                    ['no'=>34, 'text'=>'Menghapus Provider',
                     'desc'=>'Klik tombol <i class="fas fa-trash" style="color:#ef4444"></i> Hapus (kotak merah). Dialog konfirmasi akan muncul. Menghapus provider juga menghapus semua API Key dan Model di bawahnya — tidak dapat dibatalkan.',
                     'img'=>'real_ai_delete_provider_btn.png', 'label'=>'HAPUS PROVIDER AI'],
                ],
            ],
            [
                'id'    => 'ai-keys',
                'title' => '5C. Mengelola API Keys',
                'steps' => [
                    ['no'=>35, 'text'=>'Tab "Keys" pada Kartu Provider',
                     'desc'=>'Klik tab <strong>"Keys"</strong> (kotak merah) pada kartu provider untuk melihat semua API Key yang terdaftar.',
                     'img'=>'real_ai_keys_tab.png', 'label'=>'TAB API KEYS'],

                    ['no'=>36, 'text'=>'Tombol Tambah API Key',
                     'desc'=>'Klik tombol <strong>"+ Tambah Key"</strong> (kotak merah) di dalam tab Keys untuk mendaftarkan token API baru.',
                     'img'=>'real_ai_add_key_btn.png', 'label'=>'TOMBOL TAMBAH API KEY'],

                    ['no'=>37, 'text'=>'Modal Form Tambah API Key',
                     'desc'=>'Form tambah key (kotak merah) meminta: <strong>Nama Key</strong> (label deskriptif), <strong>Nilai API Key</strong> (token rahasia dari provider — disamarkan), <strong>Batas Token/Bulan</strong> (opsional), dan <strong>Status Aktif</strong>.',
                     'img'=>'real_ai_key_modal.png', 'label'=>'FORM TAMBAH API KEY'],

                    ['no'=>38, 'text'=>'Tombol Edit API Key',
                     'desc'=>'Klik ikon <i class="fas fa-edit" style="color:#f59e0b"></i> Edit (kotak merah) di samping nama key untuk memperbarui label, batas token, atau status aktif. Nilai key asli tidak ditampilkan ulang demi keamanan.',
                     'img'=>'real_ai_edit_key_btn.png', 'label'=>'TOMBOL EDIT API KEY'],

                    ['no'=>39, 'text'=>'Tombol Reset Limit Token',
                     'desc'=>'Jika key mencapai batas token bulanan, klik <strong>"Reset Limit"</strong> (kotak merah) untuk mereset counter penggunaan ke nol.',
                     'img'=>'real_ai_reset_limit_btn.png', 'label'=>'RESET LIMIT TOKEN'],
                ],
            ],
            [
                'id'    => 'ai-models',
                'title' => '5D. Mengelola Model AI',
                'steps' => [
                    ['no'=>40, 'text'=>'Tab "Models" pada Kartu Provider',
                     'desc'=>'Klik tab <strong>"Models"</strong> (kotak merah) untuk melihat daftar model AI yang didukung provider ini.',
                     'img'=>'real_ai_models_tab.png', 'label'=>'TAB MODELS AI'],

                    ['no'=>41, 'text'=>'Tombol Tambah Model AI',
                     'desc'=>'Klik tombol <strong>"+ Tambah Model"</strong> (kotak merah) untuk mendaftarkan model baru.',
                     'img'=>'real_ai_add_model_btn.png', 'label'=>'TOMBOL TAMBAH MODEL'],

                    ['no'=>42, 'text'=>'Modal Form Tambah Model AI',
                     'desc'=>'Form model (kotak merah) meminta: <strong>Model ID</strong> (identifier teknis dari provider — contoh: <code>gpt-4o-mini</code>, <code>gemini-1.5-flash</code>), <strong>Nama Tampilan</strong>, <strong>Tipe Model</strong>, <strong>Max Token</strong>, dan <strong>Status Aktif</strong>.',
                     'img'=>'real_ai_model_modal.png', 'label'=>'FORM TAMBAH MODEL AI'],
                ],
            ],
            [
                'id'    => 'ai-health',
                'title' => '5E. Health Check — Uji Validitas API Key',
                'steps' => [
                    ['no'=>43, 'text'=>'Tombol Health Check',
                     'desc'=>'Klik tombol <strong>"Health Check"</strong> <i class="fas fa-heartbeat"></i> (kotak merah) pada baris API Key. Tombol ini memanggil API provider secara langsung untuk memverifikasi key masih valid dan tidak melampaui rate-limit.',
                     'img'=>'real_ai_health_btn.png', 'label'=>'TOMBOL HEALTH CHECK'],

                    ['no'=>44, 'text'=>'Hasil Health Check',
                     'desc'=>'Modal Health Check (kotak merah) menampilkan hasil pengujian: status key (Valid/Invalid/Rate Limited/Expired), waktu respons, pesan error jika ada, dan rekomendasi tindakan.',
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
        'desc'  => 'Mengatur grup hak akses (Role) yang menentukan tabel database mana yang boleh dibaca AI untuk sekelompok pengguna.',
        'sections' => [
            [
                'id'    => 'role-list',
                'title' => '6A. Daftar Role & Tambah Role Baru',
                'steps' => [
                    ['no'=>45, 'text'=>'Tampilan Halaman Role Management',
                     'desc'=>'Halaman terbagi dua (kotak merah): <strong>Kiri</strong> — daftar semua role; <strong>Kanan</strong> — area pengaturan izin tabel. Klik nama role di kiri untuk menampilkan izin tabelnya di kanan.',
                     'img'=>'real_role_list.png', 'label'=>'HALAMAN ROLE MANAGEMENT'],

                    ['no'=>46, 'text'=>'Tombol Tambah Role',
                     'desc'=>'Klik tombol <strong>"+ Tambah Role"</strong> (kotak merah) di pojok kanan atas untuk membuka form pembuatan role baru.',
                     'img'=>'real_role_tambah_btn.png', 'label'=>'TOMBOL TAMBAH ROLE'],

                    ['no'=>47, 'text'=>'Modal Form Tambah Role',
                     'desc'=>'Isi form (kotak merah): <strong>Nama Role</strong> (contoh: "Finance Team", "HRD") dan <strong>Deskripsi</strong> (opsional). Klik <strong>"Simpan"</strong>. Role baru awalnya tidak memiliki akses ke tabel mana pun.',
                     'img'=>'real_role_modal.png', 'label'=>'FORM TAMBAH ROLE'],
                ],
            ],
            [
                'id'    => 'role-permissions',
                'title' => '6B. Mengatur Izin Akses Tabel',
                'steps' => [
                    ['no'=>48, 'text'=>'Area Pengaturan Permissions',
                     'desc'=>'Setelah memilih role, panel kanan (kotak merah) menampilkan semua tabel dari semua database. Centang tabel yang boleh diakses AI untuk role ini.',
                     'img'=>'real_role_permissions.png', 'label'=>'AREA PENGATURAN IZIN TABEL'],

                    ['no'=>49, 'text'=>'Filter Bar Pencarian Tabel',
                     'desc'=>'Gunakan filter bar (kotak merah) untuk mempersempit daftar: <strong>Cari</strong> nama tabel, <strong>Filter Database</strong>, <strong>Filter Schema</strong>, dan <strong>Filter Status</strong> (Semua / Diizinkan / Belum Diizinkan).',
                     'img'=>'real_role_filter_bar.png', 'label'=>'FILTER PENCARIAN TABEL'],

                    ['no'=>50, 'text'=>'Tombol Pilih Semua & Hapus Semua',
                     'desc'=>'Dua tombol cepat (kotak merah): <strong>"Pilih Semua"</strong> — centang semua tabel yang tampil; <strong>"Hapus Semua"</strong> — hapus semua centang. Berguna untuk manajemen izin massal.',
                     'img'=>'real_role_bulk_select.png', 'label'=>'TOMBOL PILIH/HAPUS SEMUA'],

                    ['no'=>51, 'text'=>'Tombol Simpan Izin Akses',
                     'desc'=>'Setelah selesai mencentang tabel, klik <strong>"Simpan Akses"</strong> (kotak merah). Jika ada perubahan yang belum disimpan, indikator kuning <i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> akan muncul sebagai pengingat.',
                     'img'=>'real_role_save_permissions.png', 'label'=>'TOMBOL SIMPAN AKSES'],
                ],
            ],
            [
                'id'    => 'role-edit-del',
                'title' => '6C. Edit & Hapus Role',
                'steps' => [
                    ['no'=>52, 'text'=>'Tombol Edit Role',
                     'desc'=>'Pada panel daftar role, setiap item memiliki ikon <i class="fas fa-edit" style="color:#f59e0b"></i> (kotak merah). Klik untuk membuka modal edit — mengubah nama atau deskripsi tidak mempengaruhi izin tabel yang sudah diset.',
                     'img'=>'real_role_edit_btn.png', 'label'=>'TOMBOL EDIT ROLE'],

                    ['no'=>53, 'text'=>'Modal Edit Role',
                     'desc'=>'Modal edit (kotak merah) menampilkan form dengan nilai nama dan deskripsi yang sudah ada. Ubah sesuai kebutuhan lalu klik <strong>"Simpan"</strong> atau <strong>"Batal"</strong> untuk membatalkan.',
                     'img'=>'real_role_edit_modal.png', 'label'=>'FORM EDIT ROLE'],

                    ['no'=>54, 'text'=>'Menghapus Role',
                     'desc'=>'Klik ikon <i class="fas fa-trash" style="color:#ef4444"></i> (kotak merah). Dialog konfirmasi SweetAlert akan muncul. <strong>⚠ Pengguna yang memiliki role ini akan kehilangan hak aksesnya.</strong>',
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
                    ['no'=>55, 'text'=>'Halaman Daftar User (Tabel)',
                     'desc'=>'Semua pengguna ditampilkan dalam tabel (kotak merah) dengan kolom: <strong>Nama, Email, Role, Hak Akses, AI Models, API Keys, Cakupan, Dibuat,</strong> dan <strong>Aksi</strong>.',
                     'img'=>'real_user_list.png', 'label'=>'TABEL DAFTAR USER'],

                    ['no'=>56, 'text'=>'Header Aksi: Template, Import, Export & Tambah User',
                     'desc'=>'Di pojok kanan atas terdapat 4 tombol (kotak merah): <br>• <i class="fas fa-download" style="color:#10b981"></i> <strong>Template</strong> — unduh file CSV contoh <br>• <i class="fas fa-file-import" style="color:#0ea5e9"></i> <strong>Import</strong> — impor user massal dari CSV <br>• <i class="fas fa-file-export"></i> <strong>Export</strong> — ekspor semua data user <br>• <i class="fas fa-plus" style="color:#6366f1"></i> <strong>Tambah User</strong> — tambah akun manual',
                     'img'=>'real_user_header_btns.png', 'label'=>'TOMBOL AKSI HEADER'],

                    ['no'=>57, 'text'=>'Filter & Pencarian User',
                     'desc'=>'Di bawah header terdapat form filter (kotak merah): <strong>Kolom Cari</strong> (nama atau email) dan <strong>Dropdown Filter Role</strong>. Klik <strong>"Filter"</strong> untuk menerapkan atau <strong>"Reset"</strong> untuk membersihkan.',
                     'img'=>'real_user_filter_form.png', 'label'=>'FORM FILTER USER'],
                ],
            ],
            [
                'id'    => 'user-add',
                'title' => '7B. Menambah & Mengedit User',
                'steps' => [
                    ['no'=>58, 'text'=>'Tombol "Tambah User"',
                     'desc'=>'Klik tombol <strong>"+ Tambah User"</strong> biru (kotak merah) di header untuk membuka form tambah akun baru.',
                     'img'=>'real_user_tambah_btn.png', 'label'=>'TOMBOL TAMBAH USER'],

                    ['no'=>59, 'text'=>'Modal Form Tambah User',
                     'desc'=>'Form tambah user (kotak merah) berisi: <strong>Nama Lengkap</strong>, <strong>Email</strong> (wajib unik), <strong>Password</strong> (min. 8 karakter), <strong>Konfirmasi Password</strong>, <strong>Role</strong>, dan opsi <strong>Is Admin / Is Super Admin</strong>.',
                     'img'=>'real_user_modal.png', 'label'=>'FORM TAMBAH USER'],

                    ['no'=>60, 'text'=>'Field Nama & Email (Wajib Diisi)',
                     'desc'=>'Field <strong>Nama Lengkap</strong> dan <strong>Email</strong> (kotak merah) adalah field wajib. Email harus berformat valid dan belum terdaftar di sistem.',
                     'img'=>'real_user_field_name.png', 'label'=>'FIELD NAMA & EMAIL'],

                    ['no'=>61, 'text'=>'Dropdown Pilih Role',
                     'desc'=>'Dropdown <strong>Role</strong> (kotak merah) menampilkan semua role dari modul Role Management. Pilih role yang sesuai. User tanpa role tidak bisa menggunakan chatbot dengan data spesifik.',
                     'img'=>'real_user_field_role.png', 'label'=>'DROPDOWN PILIH ROLE'],

                    ['no'=>62, 'text'=>'Tombol Edit User & Modal Edit',
                     'desc'=>'Pada baris user, klik tombol <i class="fas fa-edit" style="color:#f59e0b"></i> Edit (kotak merah) untuk membuka modal edit. Semua data user terisi otomatis. Kolom password kosong — isi hanya jika ingin mengganti password.',
                     'img'=>'real_user_edit_btn.png', 'label'=>'TOMBOL EDIT USER'],

                    ['no'=>63, 'text'=>'Modal Edit User — Data Terisi Otomatis',
                     'desc'=>'Modal edit (kotak merah) identik dengan modal tambah, namun semua field sudah terisi. Ubah field yang perlu diperbarui, lalu klik <strong>"Simpan"</strong>.',
                     'img'=>'real_edit_user_modal.png', 'label'=>'FORM EDIT USER'],
                ],
            ],
            [
                'id'    => 'user-import',
                'title' => '7C. Import & Export Data User',
                'steps' => [
                    ['no'=>64, 'text'=>'Unduh Template CSV',
                     'desc'=>'Klik tombol <strong>"Template"</strong> (kotak merah) untuk mengunduh file CSV contoh dengan header: name, email, password, role, is_admin. Gunakan file ini sebagai dasar sebelum impor massal.',
                     'img'=>'real_user_template_btn.png', 'label'=>'UNDUH TEMPLATE CSV'],

                    ['no'=>65, 'text'=>'Import User dari File Excel/CSV',
                     'desc'=>'Klik tombol <strong>"Import"</strong> (kotak merah) untuk membuka modal impor. Unggah file Excel/CSV sesuai template. Sistem memvalidasi setiap baris dan membuat akun secara massal. Error per baris akan dilaporkan.',
                     'img'=>'real_user_import_modal.png', 'label'=>'MODAL IMPORT FILE'],

                    ['no'=>66, 'text'=>'Export Data User',
                     'desc'=>'Klik tombol <strong>"Export"</strong> (kotak merah) untuk mengunduh seluruh data user. Data yang diekspor mencakup semua informasi user kecuali password.',
                     'img'=>'real_user_export_btn.png', 'label'=>'EXPORT DATA USER'],
                ],
            ],
            [
                'id'    => 'user-advanced',
                'title' => '7D. Konfigurasi AI per User (Delegasi)',
                'steps' => [
                    ['no'=>67, 'text'=>'Tombol AI Config (per User)',
                     'desc'=>'Pada kolom <strong>Aksi</strong> setiap baris user, klik tombol biru berbentuk robot/chip <i class="fas fa-microchip"></i> (kotak merah). Fitur ini memungkinkan Admin mendelegasikan model dan API key tertentu khusus untuk satu user.',
                     'img'=>'real_user_ai_config2.png', 'label'=>'TOMBOL AI CONFIG PER USER'],

                    ['no'=>68, 'text'=>'Modal Konfigurasi AI per User',
                     'desc'=>'Modal AI Config menampilkan daftar semua model dan API key yang tersedia. Centang model dan key yang ingin didelegasikan untuk user ini sehingga penggunaan AI dapat dikontrol per individu.',
                     'img'=>'real_ai_config_modal.png', 'label'=>'KONFIGURASI AI PER USER'],
                ],
            ],
            [
                'id'    => 'user-rls',
                'title' => '7E. Row Level Security (RLS) — Filter Data Baris',
                'steps' => [
                    ['no'=>69, 'text'=>'Apa itu Row Level Security?',
                     'desc'=>'Row Level Security (RLS) adalah fitur pembatasan data di level baris. Dengan RLS, Admin dapat membatasi baris data yang bisa dianalisis AI untuk user tertentu. <br><br>Contoh kasus: <br>• User <strong>Cabang Jakarta</strong> → hanya bisa melihat data di mana <code>kode_cabang = \'JKT\'</code> <br>• User <strong>Salesman A</strong> → hanya bisa melihat data di mana <code>id_salesman = 12</code> <br>• User <strong>Divisi Finance</strong> → hanya bisa melihat data di mana <code>divisi = \'finance\'</code> <br><br>Tanpa RLS, user dapat melihat seluruh isi tabel yang diizinkan rolenya.',
                     'img'=>'real_user_rls_open.png', 'label'=>'KONSEP ROW LEVEL SECURITY'],

                    ['no'=>70, 'text'=>'Tombol RLS — Buka Modal Filter Data',
                     'desc'=>'Pada kolom <strong>Aksi</strong> setiap baris user, klik tombol <i class="fas fa-filter" style="color:#10b981"></i> hijau (kotak merah). Jika user sudah memiliki filter aktif, badge angka kecil berwarna akan muncul di atas tombol menunjukkan jumlah filter yang terpasang.',
                     'img'=>'real_user_rls_btn.png', 'label'=>'TOMBOL BUKA RLS'],

                    ['no'=>71, 'text'=>'Modal RLS — Daftar Tabel Terdeteksi',
                     'desc'=>'Modal <strong>Pembatasan Data (Row-Level Security)</strong> terbuka menampilkan daftar tabel yang terdeteksi di panel kiri (kotak merah). Setiap tabel yang sudah memiliki filter menampilkan badge <span style="background:#d1fae5;color:#065f46;padding:1px 7px;border-radius:10px;font-size:.75rem">N filter</span>. <br><br>Di pojok kanan atas terdapat tombol <strong>"Salin"</strong> untuk menyalin konfigurasi RLS dari user lain.',
                     'img'=>'real_user_rls_modal.png', 'label'=>'DAFTAR TABEL RLS'],

                    ['no'=>72, 'text'=>'Pilih Tabel & Tambah Kondisi Filter',
                     'desc'=>'Klik nama tabel di panel kiri untuk membuka area pengaturan kondisi di panel kanan. Panel kanan menampilkan: <br>• <strong>Nama tabel</strong> yang dipilih <br>• <strong>Daftar aturan filter</strong> yang sudah ada <br>• <strong>Tombol "+ Tambah Kondisi"</strong> (kotak merah) untuk menambah rule baru <br><br>Setiap aturan filter terdiri dari 3 bagian: <br>1. <strong>Kolom</strong> — pilih kolom dari dropdown (beserta tipe data) <br>2. <strong>Operator</strong> — pilih dari: <code>=</code> sama dengan, <code>!=</code> tidak sama, <code>&gt;</code> lebih besar, <code>&lt;</code> lebih kecil, <code>LIKE</code> mengandung teks, <code>IN</code> dalam daftar <br>3. <strong>Nilai</strong> — ketik nilai yang menjadi batasan (contoh: <code>1271</code>, <code>Jakarta</code>)',
                     'img'=>'real_rls_add_rule.png', 'label'=>'TAMBAH KONDISI FILTER'],

                    ['no'=>73, 'text'=>'Preview Data & Simpan Perubahan',
                     'desc'=>'Sebelum menyimpan, klik tombol <strong>"Preview Data (5 Baris)"</strong> (kotak merah) untuk melihat contoh 5 baris data yang akan tampil sesuai kondisi filter yang diset. Ini memastikan filter sudah benar sebelum diterapkan ke user. <br><br>Setelah yakin, klik <strong>"Simpan Perubahan"</strong> biru di bawah modal. Klik <strong>"Batal"</strong> untuk menutup tanpa menyimpan. <br><br><strong>Tips penting:</strong><br>• Kosongkan semua kondisi untuk mengizinkan user melihat seluruh data tabel tersebut <br>• Satu tabel bisa memiliki lebih dari satu kondisi (AND logic) <br>• Kondisi berlaku untuk semua pertanyaan AI yang melibatkan tabel tersebut',
                     'img'=>'real_rls_preview.png', 'label'=>'PREVIEW DATA & SIMPAN RLS'],
                ],
            ],
            [
                'id'    => 'user-del',
                'title' => '7F. Menghapus User',
                'steps' => [
                    ['no'=>74, 'text'=>'Tombol Hapus User',
                     'desc'=>'Klik tombol <i class="fas fa-trash" style="color:#ef4444"></i> merah (kotak merah) di kolom Aksi pada baris user yang ingin dihapus. Dialog konfirmasi SweetAlert akan muncul.',
                     'img'=>'real_user_delete_btn.png', 'label'=>'TOMBOL HAPUS USER'],

                    ['no'=>75, 'text'=>'Konfirmasi Hapus User',
                     'desc'=>'Dialog SweetAlert (kotak merah) menampilkan nama user yang akan dihapus. Klik <strong>"Ya, Hapus"</strong> untuk menghapus akun secara permanen beserta seluruh data terkait (konfigurasi AI, filter RLS, riwayat chat). Tindakan ini <strong>tidak dapat dibatalkan</strong>.',
                     'img'=>'real_user_delete_confirm.png', 'label'=>'KONFIRMASI HAPUS USER'],
                ],
            ],
        ],
    ],

];

$baseUrl = "http://74.48.112.31:5000/admin_guide/";

$fileNames = [
    1 => 'bab1_autentikasi.html',
    2 => 'bab2_chatbot.html',
    3 => 'bab3_dashboard.html',
    4 => 'bab4_database.html',
    5 => 'bab5_ai_infra.html',
    6 => 'bab6_role.html',
    7 => 'bab7_user_rls.html'
];

foreach ($guideData as $idx => $menu) {
    $babNo = $idx + 1;
    $fileName = $fileNames[$babNo];
    
    // Per bab, reset nomor langkah ke 1
    $stepCounter = 1;
    
    ob_start();
    ?>
<p><?php echo $menu['desc']; ?></p>
<ul>
<?php 
// Ringkasan fitur di bagian atas
foreach ($menu['sections'] as $sec): 
    // Ambil kalimat pertama dari deskripsi langkah pertama sebagai ringkasan singkat
    $summaryText = strtolower(explode('.', $sec['steps'][0]['desc'])[0]);
?>
    <li><span style="font-size: 16px;"><strong><?php echo $sec['title']; ?></strong>, <?php echo $summaryText; ?>.</span></li>
<?php endforeach; ?>
</ul>

<p>Berikut ini adalah tampilan antarmuka menu dari modul <strong><?php echo $menu['title']; ?></strong> :</p>
<p>
    <a href="<?php echo $baseUrl . $menu['sections'][0]['steps'][0]['img']; ?>" class="fancybox image">
        <img class="alignnone size-full" src="<?php echo $baseUrl . $menu['sections'][0]['steps'][0]['img']; ?>" alt="<?php echo $menu['title']; ?>" width="1354" />
    </a>
</p>

<hr />

<h3>Petunjuk Pemakaian :</h3>
<ul>
<?php foreach ($menu['sections'] as $sec): ?>
    <li style="margin-bottom: 30px;">
        <span style="font-size: 18px;"><strong><?php echo $sec['title']; ?></strong></span>
        <br />
        
        <?php if($sec['id'] === 'user-rls'): ?>
            <div style="margin: 20px 0; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; background: #f8fafc;">
                <strong>Referensi Cepat: Operator Kondisi Filter</strong>
                <table border="1" style="width:100%; border-collapse:collapse; font-size:14px; margin-top: 10px;">
                    <tr style="background:#f1f5f9;">
                        <th style="padding: 8px;">Operator</th>
                        <th style="padding: 8px;">Arti</th>
                        <th style="padding: 8px;">Contoh</th>
                    </tr>
                    <tr><td style="padding: 8px; text-align:center;"><code>=</code></td><td style="padding: 8px;">Sama persis</td><td style="padding: 8px;">kode_cabang <code>=</code> 1271</td></tr>
                    <tr><td style="padding: 8px; text-align:center;"><code>!=</code></td><td style="padding: 8px;">Tidak sama</td><td style="padding: 8px;">status <code>!=</code> inactive</td></tr>
                    <tr><td style="padding: 8px; text-align:center;"><code>&gt;</code></td><td style="padding: 8px;">Lebih besar</td><td style="padding: 8px;">total <code>&gt;</code> 1000</td></tr>
                    <tr><td style="padding: 8px; text-align:center;"><code>&lt;</code></td><td style="padding: 8px;">Lebih kecil</td><td style="padding: 8px;">umur <code>&lt;</code> 30</td></tr>
                    <tr><td style="padding: 8px; text-align:center;"><code>LIKE</code></td><td style="padding: 8px;">Mengandung teks</td><td style="padding: 8px;">nama <code>LIKE</code> Beras</td></tr>
                    <tr><td style="padding: 8px; text-align:center;"><code>IN</code></td><td style="padding: 8px;">Dalam daftar</td><td style="padding: 8px;">kota <code>IN</code> Jakarta,Bandung</td></tr>
                </table>
            </div>
        <?php endif; ?>

        <div style="margin-top: 15px;">
        <?php foreach ($sec['steps'] as $sIdx => $step): ?>
            <?php if ($sIdx > 0): ?>
                <hr style="border: 0; border-top: 1px dashed #cbd5e1; margin: 20px 0;" />
            <?php endif; ?>
            
            <p><strong><?php echo $stepCounter++; ?>. <?php echo $step['text']; ?></strong></p>
            <p><?php echo $step['desc']; ?></p>
            <p>
                <a href="<?php echo $baseUrl . $step['img']; ?>" class="fancybox image">
                    <img class="alignnone" src="<?php echo $baseUrl . $step['img']; ?>" alt="<?php echo $step['text']; ?>" width="800" />
                </a>
            </p>
        <?php endforeach; ?>
        </div>
    </li>
<?php endforeach; ?>
</ul>
    <?php
    $html = ob_get_clean();
    file_put_contents(__DIR__ . '/' . $fileName, $html);
}
echo "Successfully generated 7 CLEAN & TIDY chapter files with reset numbering.";
?>
