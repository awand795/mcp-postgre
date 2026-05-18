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
                'id' => 'menu-auth',
                'title' => '1. AUTENTIKASI & KEAMANAN',
                'icon' => 'fas fa-shield-alt',
                'color' => 'linear-gradient(135deg,#6366f1,#4f46e5)',
                'desc' => 'Prosedur masuk ke sistem Admin Panel secara aman, reset password via OTP, dan verifikasi identitas pengguna.',
                'sections' => [
                    [
                        'id' => 'auth-login',
                        'title' => '1A. Login ke Sistem',
                        'steps' => [
                            [
                                'no' => 1,
                                'text' => 'Buka Halaman Login',
                                'desc' => 'Akses URL sistem di browser. Akan muncul form kartu bertajuk <strong>Sign In</strong>. Pastikan URL sudah benar dan koneksi internet tersedia.',
                                'img' => 'real_login_page.png',
                                'label' => 'HALAMAN LOGIN UTAMA'
                            ],

                            [
                                'no' => 2,
                                'text' => 'Isi Field Email',
                                'desc' => 'Klik kolom <strong>Email</strong> yang ditandai kotak merah, lalu ketik alamat email akun Anda. Contoh: <code>admin@darkotech.id</code>. Email tidak bersifat case-sensitive.',
                                'img' => 'real_login_email.png',
                                'label' => 'FIELD EMAIL'
                            ],

                            [
                                'no' => 3,
                                'text' => 'Isi Field Password',
                                'desc' => 'Klik kolom <strong>Password</strong> (kotak merah) lalu ketik password Anda. Gunakan ikon mata di ujung kanan field untuk menampilkan/menyembunyikan karakter.',
                                'img' => 'real_login_password.png',
                                'label' => 'FIELD PASSWORD'
                            ],

                            [
                                'no' => 4,
                                'text' => 'Klik Tombol LOGIN',
                                'desc' => 'Klik tombol biru <strong>"Login"</strong> (kotak merah). Sistem akan memvalidasi kredensial. Jika berhasil → langsung masuk ke Chatbot/Dashboard. Jika gagal → pesan error merah tampil di atas form.',
                                'img' => 'real_login_button.png',
                                'label' => 'TOMBOL LOGIN'
                            ],

                            [
                                'no' => 5,
                                'text' => 'Berhasil Masuk ke Sistem',
                                'desc' => 'Setelah login berhasil, Anda diarahkan ke halaman utama Chatbot. Sidebar kiri menampilkan menu Admin jika akun Anda adalah Administrator.',
                                'img' => 'real_login_success.png',
                                'label' => 'LOGIN BERHASIL'
                            ],
                        ],
                    ],
                    [
                        'id' => 'auth-forgot',
                        'title' => '1B. Lupa Password & Reset via OTP',
                        'steps' => [
                            [
                                'no' => 6,
                                'text' => 'Klik Link "Lupa Password?"',
                                'desc' => 'Jika tidak ingat password, klik tautan <strong>"Lupa Password?"</strong> (kotak merah) di bawah form login. Anda akan diarahkan ke halaman pemulihan akun.',
                                'img' => 'real_login_forgot_link.png',
                                'label' => 'LINK LUPA PASSWORD'
                            ],

                            [
                                'no' => 7,
                                'text' => 'Masukkan Email Pemulihan',
                                'desc' => 'Pada halaman Forgot Password, isi kolom email (kotak merah) dengan email terdaftar Anda, lalu klik <strong>"Kirim Kode OTP"</strong>. Sistem mengirimkan 6-digit kode ke inbox email dalam beberapa detik.',
                                'img' => 'real_forgot_email_field.png',
                                'label' => 'EMAIL PEMULIHAN'
                            ],

                            [
                                'no' => 8,
                                'text' => 'Verifikasi Kode OTP 6 Digit',
                                'desc' => 'Buka inbox email Anda, salin kode 6 digit yang diterima, lalu masukkan ke kotak verifikasi (kotak merah) secara berurutan. <strong>⚠ Kode hanya berlaku 10 menit.</strong> Periksa folder Spam jika tidak masuk Inbox.',
                                'img' => 'real_verify_otp_page.png',
                                'label' => 'VERIFIKASI OTP'
                            ],

                            [
                                'no' => 9,
                                'text' => 'Buat Password Baru',
                                'desc' => 'Setelah OTP terverifikasi, masukkan password baru (min. 8 karakter), ulangi di kolom konfirmasi, lalu klik <strong>"Simpan Password Baru"</strong>.',
                                'img' => 'real_reset_password_page.png',
                                'label' => 'BUAT PASSWORD BARU'
                            ],
                        ],
                    ],
                ],
            ],

            /* ═══════════════════════════════════════════════════════════
               2. CHATBOT AI
            ═══════════════════════════════════════════════════════════ */
            [
                'id' => 'menu-chatbot',
                'title' => '2. CHATBOT AI',
                'icon' => 'fas fa-robot',
                'color' => 'linear-gradient(135deg,#10b981,#059669)',
                'desc' => 'Halaman utama interaksi dengan AI. Gunakan chatbot untuk menganalisis data database, ekspor tabel ke Excel/PDF, dan kelola riwayat percakapan.',
                'sections' => [
                    [
                        'id' => 'chat-ui',
                        'title' => '2A. Antarmuka Chat & Cara Bertanya',
                        'steps' => [
                            [
                                'no' => 1,
                                'text' => 'Tampilan Utama Chatbot',
                                'desc' => 'Setelah login, halaman ini yang pertama muncul. Area tengah adalah percakapan dengan AI. Ketik pertanyaan pada kolom input bawah (kotak merah) — contoh: <em>"Tampilkan 10 transaksi terbesar bulan ini"</em> — lalu tekan <kbd>Enter</kbd> atau klik tombol kirim.',
                                'img' => 'real_chatbot_page.png',
                                'label' => 'HALAMAN UTAMA CHATBOT'
                            ],

                            [
                                'no' => 2,
                                'text' => 'Membuka Sidebar Riwayat Chat',
                                'desc' => 'Klik ikon <i class="fas fa-bars"></i> hamburger di pojok kiri atas (kotak merah) untuk membuka panel Sidebar Riwayat. Di sini Anda bisa melihat semua sesi percakapan lama. Klik judul chat untuk membuka kembali.',
                                'img' => 'real_chatbot_sidebar.png',
                                'label' => 'SIDEBAR RIWAYAT CHAT'
                            ],

                            [
                                'no' => 3,
                                'text' => 'Tampilan Halaman riwayat chat',
                                'desc' => 'Setelah klik hamburger icon maka akan muncul semua riwayat chat yang telah anda lakukan sebelumnya, jika riwayat chat kosong berarti anda belum melakukan chat',
                                'img' => 'real_history_chat.png',
                                'label' => 'LIST HISTORY CHAT'
                            ],

                            [
                                'no' => 4,
                                'text' => 'Menghapus Sesi Chat',
                                'desc' => 'Klik ikon <i class="fas fa-trash"></i> sampah pada judul chat di sidebar. Dialog konfirmasi (kotak merah) akan muncul. Klik <strong>"Ya, Hapus"</strong> untuk menghapus permanen, atau <strong>"Batal"</strong> untuk membatalkan.',
                                'img' => 'real_delete_history_chat.png',
                                'label' => 'KONFIRMASI HAPUS CHAT'
                            ],

                            [
                                'no' => 5,
                                'text' => 'Confirm Delete Chat',
                                'desc' => 'Setelah klik "Ya, Hapus" maka akan muncul dialog konfirmasi untuk menghapus chat, klik "Ya, Hapus" untuk menghapus chat permanen, atau "Batal" untuk membatalkan.',
                                'img' => 'real_confirm_delete_chat.png',
                                'label' => 'KONFIRMASI HAPUS CHAT'
                            ],
                        ],
                    ],
                ],
            ],

            /* ═══════════════════════════════════════════════════════════
               3. DASHBOARD
            ═══════════════════════════════════════════════════════════ */
            [
                'id' => 'menu-dashboard',
                'title' => '3. MONITORING DASHBOARD',
                'icon' => 'fas fa-chart-pie',
                'color' => 'linear-gradient(135deg,#8b5cf6,#7c3aed)',
                'desc' => 'Halaman pertama yang dilihat Admin. Menampilkan statistik sistem real-time, navigasi sidebar, dan pengaturan tema tampilan.',
                'sections' => [
                    [
                        'id' => 'dash-stats',
                        'title' => '3A. Kartu Statistik & Navigasi',
                        'steps' => [
                            [
                                'no' => 1,
                                'text' => 'Dashboard Overview — 4 Kartu Statistik',
                                'desc' => 'Dashboard menampilkan 4 kartu (kotak merah): <strong>Total Users</strong>, <strong>Total Roles</strong>, <strong>Total Databases</strong>, dan <strong>Total Tables</strong>. Angka diperbarui real-time.',
                                'img' => 'real_dashboard.png',
                                'label' => 'KARTU STATISTIK SISTEM'
                            ],

                            [
                                'no' => 2,
                                'text' => 'Sidebar Navigasi Admin',
                                'desc' => 'Sidebar kiri (kotak merah) adalah navigasi utama Admin Panel. Berisi menu: <strong>Dashboard, Management Database, AI Management, Management Role, Management User,</strong> dan <strong>Panduan</strong>. Di bagian bawah terdapat info user yang sedang login.',
                                'img' => 'real_sidebar.png',
                                'label' => 'NAVIGASI SIDEBAR ADMIN'
                            ],
                        ],
                    ],
                    [
                        'id' => 'dash-theme',
                        'title' => '3B. Fitur Dark Mode',
                        'steps' => [
                            [
                                'no' => 3,
                                'text' => 'Tombol Toggle Dark/Light Mode',
                                'desc' => 'Temukan toggle tema (kotak merah) di bagian atas header. Klik sekali untuk beralih dari Light Mode ke Dark Mode. Preferensi disimpan otomatis di browser.',
                                'img' => 'real_dash_darkmode.png',
                                'label' => 'TOGGLE DARK MODE'
                            ],

                            [
                                'no' => 4,
                                'text' => 'Tampilan Dark Mode Aktif',
                                'desc' => 'Saat Dark Mode aktif, seluruh antarmuka berubah ke palet warna gelap (kotak merah). Sangat nyaman untuk penggunaan di kondisi pencahayaan rendah.',
                                'img' => 'real_dashboard_dark.png',
                                'label' => 'TAMPILAN DARK MODE'
                            ],
                        ],
                    ],
                ],
            ],

            /* ═══════════════════════════════════════════════════════════
               4. DATABASE MANAGEMENT
            ═══════════════════════════════════════════════════════════ */
            [
                'id' => 'menu-database',
                'title' => '4. DATABASE MANAGEMENT',
                'icon' => 'fas fa-database',
                'color' => 'linear-gradient(135deg,#f59e0b,#d97706)',
                'desc' => 'Modul untuk menghubungkan server database eksternal (PostgreSQL, MySQL, MariaDB) ke sistem AI. Setiap koneksi dapat ditambah, diedit, diuji, dan dihapus.',
                'sections' => [
                    [
                        'id' => 'db-overview',
                        'title' => '4A. Halaman Daftar & Toolbar Database',
                        'steps' => [
                            [
                                'no' => 1,
                                'text' => 'Halaman Daftar Database',
                                'desc' => 'Semua koneksi database yang terdaftar ditampilkan sebagai kartu (kotak merah). Setiap kartu memuat: nama alias, driver (PostgreSQL/MySQL/MariaDB), host:port, status koneksi, dan tombol aksi.',
                                'img' => 'real_db_list.png',
                                'label' => 'DAFTAR KONEKSI DATABASE'
                            ],

                            [
                                'no' => 2,
                                'text' => 'Toolbar: Pencarian, Filter, & View Toggle',
                                'desc' => 'Toolbar (kotak merah) berisi: <strong>Kolom Pencarian</strong>, <strong>Filter Driver</strong>, <strong>Filter Status</strong>, dan <strong>Toggle View</strong> Grid/List.',
                                'img' => 'real_db_toolbar.png',
                                'label' => 'TOOLBAR PENCARIAN & FILTER'
                            ],

                            [
                                'no' => 3,
                                'text' => 'Tombol "Test All Connections"',
                                'desc' => 'Klik tombol <strong>"Test All"</strong> (kotak merah) di pojok kanan atas untuk menguji semua koneksi database sekaligus. Hasilnya ditampilkan dalam health bar: Total, <span style="color:#10b981">Connected</span>, dan <span style="color:#ef4444">Failed</span>.',
                                'img' => 'real_db_test_all.png',
                                'label' => 'TOMBOL TEST ALL CONNECTIONS'
                            ],

                            [
                                'no' => 4,
                                'text' => 'Tombol "Tambah Database"',
                                'desc' => 'Klik tombol <strong>"+ Tambah Database"</strong> (kotak merah) untuk membuka wizard penambahan koneksi baru. Wizard terdiri dari 3 langkah.',
                                'img' => 'real_db_tambah_btn.png',
                                'label' => 'TOMBOL TAMBAH DATABASE'
                            ],
                        ],
                    ],
                    [
                        'id' => 'db-add',
                        'title' => '4B. Wizard Tambah Database (3 Langkah)',
                        'steps' => [
                            [
                                'no' => 5,
                                'text' => 'Step 1 — Identitas: Nama, Kode & Driver',
                                'desc' => 'Wizard langkah pertama meminta: <br>• <strong>Nama Koneksi/Alias</strong> — nama tampilan (contoh: "Production DB") <br>• <strong>Kode</strong> — pengenal unik huruf kecil & underscore <br>• <strong>Driver</strong> — pilih: PostgreSQL, MySQL, atau MariaDB <br>• Centang <strong>Aktif</strong> dan/atau <strong>Default</strong> jika perlu.',
                                'img' => 'real_db_modal_step1.png',
                                'label' => 'WIZARD STEP 1: IDENTITAS'
                            ],

                            [
                                'no' => 6,
                                'text' => 'Step 2 — Koneksi: Host, Port, Kredensial & Schema',
                                'desc' => 'Isi detail koneksi server: <br>• <strong>Host</strong> — IP atau hostname server database <br>• <strong>Port</strong> — otomatis terisi sesuai driver (5432/3306) <br>• <strong>Nama Database</strong> — nama database asli di server <br>• <strong>Username & Password</strong> — kredensial akses database <br>• <strong>Schema</strong> — klik Load untuk deteksi otomatis',
                                'img' => 'real_db_modal_step2.png',
                                'label' => 'WIZARD STEP 2: KONEKSI'
                            ],

                            [
                                'no' => 7,
                                'text' => 'Step 3 — Test Koneksi Sebelum Simpan',
                                'desc' => 'Klik <strong>"Test Sekarang"</strong> untuk memverifikasi parameter yang diisi. Hasil muncul di bawah tombol: hijau = berhasil, merah = gagal. Setelah berhasil, klik <strong>"Simpan Database"</strong>.',
                                'img' => 'real_db_modal_step3.png',
                                'label' => 'WIZARD STEP 3: TEST & SIMPAN'
                            ],
                        ],
                    ],
                    [
                        'id' => 'db-manage',
                        'title' => '4C. Mengelola Koneksi yang Sudah Ada',
                        'steps' => [
                            [
                                'no' => 8,
                                'text' => 'Tombol Edit Database',
                                'desc' => 'Pada setiap kartu database, klik ikon <i class="fas fa-edit" style="color:#f59e0b"></i> (kotak merah) untuk membuka modal Edit. Semua data koneksi yang tersimpan akan terisi otomatis.',
                                'img' => 'real_db_edit_btn.png',
                                'label' => 'TOMBOL EDIT DATABASE'
                            ],

                            [
                                'no' => 9,
                                'text' => 'Modal Edit Database',
                                'desc' => 'Modal edit menampilkan semua field yang sudah terisi (kotak merah). Ubah field yang perlu diperbarui, lalu klik <strong>"Simpan Database"</strong>.',
                                'img' => 'real_db_edit_modal.png',
                                'label' => 'MODAL EDIT DATABASE'
                            ],

                            [
                                'no' => 10,
                                'text' => 'Badge Status Koneksi',
                                'desc' => 'Di bagian bawah setiap kartu terdapat badge status (kotak merah): <span style="background:rgba(16,185,129,.1);color:#047857;padding:2px 8px;border-radius:6px;font-size:.8rem"><i class="fas fa-check-circle"></i> Connected</span>, <span style="background:rgba(239,68,68,.1);color:#b91c1c;padding:2px 8px;border-radius:6px;font-size:.8rem"><i class="fas fa-times-circle"></i> Failed</span>, atau <span style="background:rgba(245,158,11,.1);color:#b45309;padding:2px 8px;border-radius:6px;font-size:.8rem"><i class="fas fa-question-circle"></i> Not Tested</span>.',
                                'img' => 'real_db_status_badge.png',
                                'label' => 'BADGE STATUS KONEKSI'
                            ],

                            [
                                'no' => 11,
                                'text' => 'Copy Host & Nama Database',
                                'desc' => 'Pada detail koneksi di kartu, terdapat tombol copy <i class="fas fa-copy"></i> (kotak merah) di samping Host:Port dan Nama Database. Klik untuk menyalin nilai ke clipboard. Notifikasi toast akan muncul sebagai konfirmasi.',
                                'img' => 'real_db_copy_btn.png',
                                'label' => 'TOMBOL COPY HOST/DATABASE'
                            ],

                            [
                                'no' => 12,
                                'text' => 'Menghapus Koneksi Database',
                                'desc' => 'Klik ikon <i class="fas fa-trash" style="color:#ef4444"></i> (kotak merah) pada kartu database yang ingin dihapus. Dialog konfirmasi SweetAlert akan muncul. <strong>⚠ Database bertanda Default tidak dapat dihapus.</strong>',
                                'img' => 'real_db_delete_confirm.png',
                                'label' => 'KONFIRMASI HAPUS DATABASE'
                            ],
                        ],
                    ],
                ],
            ],

            /* ═══════════════════════════════════════════════════════════
               5. AI MANAGEMENT
            ═══════════════════════════════════════════════════════════ */
            [
                'id' => 'menu-ai',
                'title' => '5. AI INFRASTRUCTURE',
                'icon' => 'fas fa-brain',
                'color' => 'linear-gradient(135deg,#06b6d4,#0284c7)',
                'desc' => 'Pusat kendali seluruh infrastruktur AI: mendaftarkan provider (OpenAI, Gemini, dll), mengelola API Key & Model, serta memantau kesehatan key via Health Check.',
                'sections' => [
                    [
                        'id' => 'ai-overview',
                        'title' => '5A. Halaman AI Management & Statistik',
                        'steps' => [
                            [
                                'no' => 1,
                                'text' => 'Halaman AI Management — Statistik Utama',
                                'desc' => 'Bagian atas menampilkan 4 kartu statistik AI (kotak merah): <strong>Total Provider</strong>, <strong>API Keys Aktif</strong>, <strong>Total Model</strong>, dan <strong>Provider Aktif</strong>. Di bawahnya terdapat grid kartu provider.',
                                'img' => 'real_ai_management.png',
                                'label' => 'HALAMAN AI MANAGEMENT'
                            ],

                            [
                                'no' => 2,
                                'text' => 'Grid Kartu Provider AI',
                                'desc' => 'Setiap provider AI ditampilkan sebagai kartu terpisah (kotak merah). Kartu menampilkan: logo provider, jumlah key aktif, jumlah model, toggle aktif/nonaktif, dan tab Keys/Models.',
                                'img' => 'real_ai_providers.png',
                                'label' => 'GRID PROVIDER AI'
                            ],
                        ],
                    ],
                    [
                        'id' => 'ai-provider',
                        'title' => '5B. Mengelola Provider AI',
                        'steps' => [
                            [
                                'no' => 3,
                                'text' => 'Tombol Tambah Provider Baru',
                                'desc' => 'Klik tombol <strong>"+ Tambah Provider"</strong> (kotak merah) di header untuk mendaftarkan penyedia AI baru.',
                                'img' => 'real_ai_add_provider_btn.png',
                                'label' => 'TOMBOL TAMBAH PROVIDER'
                            ],

                            [
                                'no' => 4,
                                'text' => 'Modal Form Tambah Provider',
                                'desc' => 'Form (kotak merah) meminta: <strong>Nama Provider</strong>, <strong>Kode Unik</strong> (huruf kecil, misal "openai"), <strong>Base URL API</strong>, dan <strong>Status Aktif</strong>. Klik <strong>"Simpan"</strong> setelah semua terisi.',
                                'img' => 'real_ai_provider_modal.png',
                                'label' => 'FORM TAMBAH PROVIDER AI'
                            ],

                            [
                                'no' => 5,
                                'text' => 'Toggle Aktif/Nonaktif Provider',
                                'desc' => 'Pada kartu provider, terdapat toggle switch (kotak merah). Klik untuk mengaktifkan atau menonaktifkan provider. Provider nonaktif tidak digunakan chatbot meskipun punya key yang valid.',
                                'img' => 'real_ai_toggle_provider.png',
                                'label' => 'TOGGLE AKTIF PROVIDER'
                            ],

                            [
                                'no' => 6,
                                'text' => 'Menghapus Provider',
                                'desc' => 'Klik tombol <i class="fas fa-trash" style="color:#ef4444"></i> Hapus (kotak merah). Dialog konfirmasi akan muncul. Menghapus provider juga menghapus semua API Key dan Model di bawahnya — tidak dapat dibatalkan.',
                                'img' => 'real_ai_delete_provider_btn.png',
                                'label' => 'HAPUS PROVIDER AI'
                            ],
                        ],
                    ],
                    [
                        'id' => 'ai-keys',
                        'title' => '5C. Mengelola API Keys',
                        'steps' => [
                            [
                                'no' => 7,
                                'text' => 'Tab "Keys" pada Kartu Provider',
                                'desc' => 'Klik tab <strong>"Keys"</strong> (kotak merah) pada kartu provider untuk melihat semua API Key yang terdaftar.',
                                'img' => 'real_ai_keys_tab.png',
                                'label' => 'TAB API KEYS'
                            ],

                            [
                                'no' => 8,
                                'text' => 'Tombol Tambah API Key',
                                'desc' => 'Klik tombol <strong>"+ Tambah Key"</strong> (kotak merah) di dalam tab Keys untuk mendaftarkan token API baru.',
                                'img' => 'real_ai_add_key_btn.png',
                                'label' => 'TOMBOL TAMBAH API KEY'
                            ],

                            [
                                'no' => 9,
                                'text' => 'Modal Form Tambah API Key',
                                'desc' => 'Form tambah key (kotak merah) meminta: <strong>Nama Key</strong> (label deskriptif), <strong>Nilai API Key</strong> (token rahasia dari provider — disamarkan), <strong>Batas Token/Bulan</strong> (opsional), dan <strong>Status Aktif</strong>.',
                                'img' => 'real_ai_key_modal.png',
                                'label' => 'FORM TAMBAH API KEY'
                            ],

                            [
                                'no' => 10,
                                'text' => 'Tombol Edit API Key',
                                'desc' => 'Klik ikon <i class="fas fa-edit" style="color:#f59e0b"></i> Edit (kotak merah) di samping nama key untuk memperbarui label, batas token, atau status aktif. Nilai key asli tidak ditampilkan ulang demi keamanan.',
                                'img' => 'real_ai_edit_key_btn.png',
                                'label' => 'TOMBOL EDIT API KEY'
                            ],

                            [
                                'no' => 11,
                                'text' => 'Tombol Reset Limit Token',
                                'desc' => 'Jika key mencapai batas token bulanan, klik <strong>"Reset Limit"</strong> (kotak merah) untuk mereset counter penggunaan ke nol.',
                                'img' => 'real_ai_reset_limit_btn.png',
                                'label' => 'RESET LIMIT TOKEN'
                            ],
                        ],
                    ],
                    [
                        'id' => 'ai-models',
                        'title' => '5D. Mengelola Model AI',
                        'steps' => [
                            [
                                'no' => 12,
                                'text' => 'Tab "Models" pada Kartu Provider',
                                'desc' => 'Klik tab <strong>"Models"</strong> (kotak merah) untuk melihat daftar model AI yang didukung provider ini.',
                                'img' => 'real_ai_models_tab.png',
                                'label' => 'TAB MODELS AI'
                            ],

                            [
                                'no' => 13,
                                'text' => 'Tombol Tambah Model AI',
                                'desc' => 'Klik tombol <strong>"+ Tambah Model"</strong> (kotak merah) untuk mendaftarkan model baru.',
                                'img' => 'real_ai_add_model_btn.png',
                                'label' => 'TOMBOL TAMBAH MODEL'
                            ],

                            [
                                'no' => 14,
                                'text' => 'Modal Form Tambah Model AI',
                                'desc' => 'Form model (kotak merah) meminta: <strong>Model ID</strong> (identifier teknis dari provider — contoh: <code>gpt-4o-mini</code>, <code>gemini-1.5-flash</code>), <strong>Nama Tampilan</strong>, <strong>Tipe Model</strong>, <strong>Max Token</strong>, dan <strong>Status Aktif</strong>.',
                                'img' => 'real_ai_model_modal.png',
                                'label' => 'FORM TAMBAH MODEL AI'
                            ],
                        ],
                    ],
                    [
                        'id' => 'ai-health',
                        'title' => '5E. Health Check — Uji Validitas API Key',
                        'steps' => [
                            [
                                'no' => 15,
                                'text' => 'Tombol Health Check',
                                'desc' => 'Klik tombol <strong>"Health Check"</strong> <i class="fas fa-heartbeat"></i> (kotak merah) pada baris API Key. Tombol ini memanggil API provider secara langsung untuk memverifikasi key masih valid dan tidak melampaui rate-limit.',
                                'img' => 'real_ai_health_btn.png',
                                'label' => 'TOMBOL HEALTH CHECK'
                            ],

                            [
                                'no' => 16,
                                'text' => 'Pop UP untuk mengecek Kesehatan',
                                'desc' => 'Pilih model yang ingin anda gunakan untuk mengecek AI atau biarkan saja untuk mode auto biar AI sendiri yang memilih model yang akan digunakan untuk mengecek kondisi kesehatan AI dan setelah itu anda bisa klik tombol cek sekarang',
                                'img' => 'real_ai_health_modal.png',
                                'label' => 'TOMBOL HEALTH CHECK'
                            ],

                            [
                                'no' => 17,
                                'text' => 'Hasil Health Check',
                                'desc' => 'Modal Health Check (kotak merah) menampilkan hasil pengujian: status key (Valid/Invalid/Rate Limited/Expired), waktu respons, pesan error jika ada, dan rekomendasi tindakan.',
                                'img' => 'real_ai_health_modal2.png',
                                'label' => 'HASIL HEALTH CHECK'
                            ],
                        ],
                    ],
                ],
            ],

            /* ═══════════════════════════════════════════════════════════
               6. ROLE MANAGEMENT
            ═══════════════════════════════════════════════════════════ */
            [
                'id' => 'menu-roles',
                'title' => '6. ROLE MANAGEMENT',
                'icon' => 'fas fa-user-shield',
                'color' => 'linear-gradient(135deg,#ec4899,#be185d)',
                'desc' => 'Mengatur grup hak akses (Role) yang menentukan tabel database mana yang boleh dibaca AI untuk sekelompok pengguna.',
                'sections' => [
                    [
                        'id' => 'role-list',
                        'title' => '6A. Daftar Role & Tambah Role Baru',
                        'steps' => [
                            [
                                'no' => 1,
                                'text' => 'Tampilan Halaman Role Management',
                                'desc' => 'Halaman terbagi dua (kotak merah): <strong>Kiri</strong> — daftar semua role; <strong>Kanan</strong> — area pengaturan izin tabel. Klik nama role di kiri untuk menampilkan izin tabelnya di kanan.',
                                'img' => 'real_role_list.png',
                                'label' => 'HALAMAN ROLE MANAGEMENT'
                            ],

                            [
                                'no' => 2,
                                'text' => 'Tombol Tambah Role',
                                'desc' => 'Klik tombol <strong>"+ Tambah Role"</strong> (kotak merah) di pojok kanan atas untuk membuka form pembuatan role baru.',
                                'img' => 'real_role_tambah_btn.png',
                                'label' => 'TOMBOL TAMBAH ROLE'
                            ],

                            [
                                'no' => 3,
                                'text' => 'Modal Form Tambah Role',
                                'desc' => 'Isi form (kotak merah): <strong>Nama Role</strong> (contoh: "Finance Team", "HRD") dan <strong>Deskripsi</strong> (opsional). Klik <strong>"Simpan"</strong>. Role baru awalnya tidak memiliki akses ke tabel mana pun.',
                                'img' => 'real_role_modal.png',
                                'label' => 'FORM TAMBAH ROLE'
                            ],
                        ],
                    ],
                    [
                        'id' => 'role-permissions',
                        'title' => '6B. Mengatur Izin Akses Tabel',
                        'steps' => [
                            [
                                'no' => 4,
                                'text' => 'Area Pengaturan Permissions',
                                'desc' => 'Setelah memilih role, panel kanan (kotak merah) menampilkan semua tabel dari semua database. Centang tabel yang boleh diakses AI untuk role ini.',
                                'img' => 'real_role_permissions.png',
                                'label' => 'AREA PENGATURAN IZIN TABEL'
                            ],

                            [
                                'no' => 5,
                                'text' => 'Filter Bar Pencarian Tabel',
                                'desc' => 'Gunakan filter bar (kotak merah) untuk mempersempit daftar: <strong>Cari</strong> nama tabel, <strong>Filter Database</strong>, <strong>Filter Schema</strong>, dan <strong>Filter Status</strong> (Semua / Diizinkan / Belum Diizinkan).',
                                'img' => 'real_role_filter_bar.png',
                                'label' => 'FILTER PENCARIAN TABEL'
                            ],

                            [
                                'no' => 6,
                                'text' => 'Tombol Pilih Semua & Hapus Semua',
                                'desc' => 'Dua tombol cepat (kotak merah): <strong>"Pilih Semua"</strong> — centang semua tabel yang tampil; <strong>"Hapus Semua"</strong> — hapus semua centang. Berguna untuk manajemen izin massal.',
                                'img' => 'real_role_bulk_select.png',
                                'label' => 'TOMBOL PILIH/HAPUS SEMUA'
                            ],

                            [
                                'no' => 7,
                                'text' => 'Tombol Simpan Izin Akses',
                                'desc' => 'Setelah selesai mencentang tabel, klik <strong>"Simpan Akses"</strong> (kotak merah). Jika ada perubahan yang belum disimpan, indikator kuning <i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i> akan muncul sebagai pengingat.',
                                'img' => 'real_role_save_permissions.png',
                                'label' => 'TOMBOL SIMPAN AKSES'
                            ],
                        ],
                    ],
                    [
                        'id' => 'role-edit-del',
                        'title' => '6C. Edit & Hapus Role',
                        'steps' => [
                            [
                                'no' => 8,
                                'text' => 'Tombol Edit Role',
                                'desc' => 'Pada panel daftar role, setiap item memiliki ikon <i class="fas fa-edit" style="color:#f59e0b"></i> (kotak merah). Klik untuk membuka modal edit — mengubah nama atau deskripsi tidak mempengaruhi izin tabel yang sudah diset.',
                                'img' => 'real_role_edit_btn.png',
                                'label' => 'TOMBOL EDIT ROLE'
                            ],

                            [
                                'no' => 9,
                                'text' => 'Modal Edit Role',
                                'desc' => 'Modal edit (kotak merah) menampilkan form dengan nilai nama dan deskripsi yang sudah ada. Ubah sesuai kebutuhan lalu klik <strong>"Simpan"</strong> atau <strong>"Batal"</strong> untuk membatalkan.',
                                'img' => 'real_role_edit_modal.png',
                                'label' => 'FORM EDIT ROLE'
                            ],

                            [
                                'no' => 10,
                                'text' => 'Menghapus Role',
                                'desc' => 'Klik ikon <i class="fas fa-trash" style="color:#ef4444"></i> (kotak merah). Dialog konfirmasi SweetAlert akan muncul. <strong>⚠ Pengguna yang memiliki role ini akan kehilangan hak aksesnya.</strong>',
                                'img' => 'real_role_delete_confirm.png',
                                'label' => 'KONFIRMASI HAPUS ROLE'
                            ],
                        ],
                    ],
                ],
            ],

            /* ═══════════════════════════════════════════════════════════
               7. USER MANAGEMENT
            ═══════════════════════════════════════════════════════════ */
            [
                'id' => 'menu-users',
                'title' => '7. USER MANAGEMENT',
                'icon' => 'fas fa-users-cog',
                'color' => 'linear-gradient(135deg,#14b8a6,#0f766e)',
                'desc' => 'Pengaturan paling mendalam untuk setiap akun pengguna: tambah/edit/hapus akun, impor massal via CSV, ekspor data, konfigurasi AI per user, dan pembatasan baris data (Row Level Security).',
                'sections' => [
                    [
                        'id' => 'user-list',
                        'title' => '7A. Tampilan Tabel User & Aksi Header',
                        'steps' => [
                            [
                                'no' => 1,
                                'text' => 'Halaman Daftar User (Tabel)',
                                'desc' => 'Semua pengguna ditampilkan dalam tabel (kotak merah) dengan kolom: <strong>Nama, Email, Role, Hak Akses, AI Models, API Keys, Cakupan, Dibuat,</strong> dan <strong>Aksi</strong>.',
                                'img' => 'real_user_list.png',
                                'label' => 'TABEL DAFTAR USER'
                            ],

                            [
                                'no' => 2,
                                'text' => 'Header Aksi: Template, Import, Export & Tambah User',
                                'desc' => 'Di pojok kanan atas terdapat 4 tombol (kotak merah): <br>• <i class="fas fa-download" style="color:#10b981"></i> <strong>Template</strong> — unduh file CSV contoh <br>• <i class="fas fa-file-import" style="color:#0ea5e9"></i> <strong>Import</strong> — impor user massal dari CSV <br>• <i class="fas fa-file-export"></i> <strong>Export</strong> — ekspor semua data user <br>• <i class="fas fa-plus" style="color:#6366f1"></i> <strong>Tambah User</strong> — tambah akun manual',
                                'img' => 'real_user_header_btns.png',
                                'label' => 'TOMBOL AKSI HEADER'
                            ],

                            [
                                'no' => 3,
                                'text' => 'Filter & Pencarian User',
                                'desc' => 'Di bawah header terdapat form filter (kotak merah): <strong>Kolom Cari</strong> (nama atau email) dan <strong>Dropdown Filter Role</strong>. Klik <strong>"Filter"</strong> untuk menerapkan atau <strong>"Reset"</strong> untuk membersihkan.',
                                'img' => 'real_user_filter_form.png',
                                'label' => 'FORM FILTER USER'
                            ],
                        ],
                    ],
                    [
                        'id' => 'user-add',
                        'title' => '7B. Menambah & Mengedit User',
                        'steps' => [
                            [
                                'no' => 4,
                                'text' => 'Tombol "Tambah User"',
                                'desc' => 'Klik tombol <strong>"+ Tambah User"</strong> biru (kotak merah) di header untuk membuka form tambah akun baru.',
                                'img' => 'real_user_tambah_btn.png',
                                'label' => 'TOMBOL TAMBAH USER'
                            ],

                            [
                                'no' => 5,
                                'text' => 'Modal Form Tambah User',
                                'desc' => 'Form tambah user (kotak merah) berisi: <strong>Nama Lengkap</strong>, <strong>Email</strong> (wajib unik), <strong>Password</strong> (min. 8 karakter), <strong>Konfirmasi Password</strong>, <strong>Role</strong>, dan opsi <strong>Is Admin / Is Super Admin</strong>.',
                                'img' => 'real_user_modal.png',
                                'label' => 'FORM TAMBAH USER'
                            ],

                            [
                                'no' => 6,
                                'text' => 'Field Nama & Email (Wajib Diisi)',
                                'desc' => 'Field <strong>Nama Lengkap</strong> dan <strong>Email</strong> (kotak merah) adalah field wajib. Email harus berformat valid dan belum terdaftar di sistem.',
                                'img' => 'real_user_field_name.png',
                                'label' => 'FIELD NAMA & EMAIL'
                            ],

                            [
                                'no' => 7,
                                'text' => 'Dropdown Pilih Role',
                                'desc' => 'Dropdown <strong>Role</strong> (kotak merah) menampilkan semua role dari modul Role Management. Pilih role yang sesuai. User tanpa role tidak bisa menggunakan chatbot dengan data spesifik.',
                                'img' => 'real_user_field_role.png',
                                'label' => 'DROPDOWN PILIH ROLE'
                            ],
                            [
                                'no' => 8,
                                'text' => 'Admin & Super Admin',
                                'desc' => 'Dibagian ini anda bisa centang Admin untuk mengeset user menjadi Admin atau Super Admin. dan jika anda tidak centang keduanya maka user tersebut akan menjadi user biasa',
                                'img' => 'real_user_admin_superadmin.png',
                                'label' => 'ADMIN & SUPER ADMIN'
                            ],
                            [
                                'no' => 9,
                                'text' => 'Save User',
                                'desc' => 'Jika semua data user sudah terisi semua maka anda tinggal save user tersebut dengan klik tombol simpan seperti di gambar di bawah ini',
                                'img' => 'real_user_save_user.png',
                                'label' => 'SAVE USER'
                            ],

                            [
                                'no' => 10,
                                'text' => 'Tombol Edit User & Modal Edit',
                                'desc' => 'Pada baris user, klik tombol <i class="fas fa-edit" style="color:#f59e0b"></i> Edit (kotak merah) untuk membuka modal edit. Semua data user terisi otomatis. Kolom password kosong — isi hanya jika ingin mengganti password.',
                                'img' => 'real_user_edit_btn.png',
                                'label' => 'TOMBOL EDIT USER'
                            ],

                            [
                                'no' => 11,
                                'text' => 'Modal Edit User — Data Terisi Otomatis',
                                'desc' => 'Modal edit (kotak merah) identik dengan modal tambah, namun semua field sudah terisi. Ubah field yang perlu diperbarui, lalu klik <strong>"Simpan"</strong>.',
                                'img' => 'real_edit_user_modal.png',
                                'label' => 'FORM EDIT USER'
                            ],
                        ],
                    ],
                    [
                        'id' => 'user-import',
                        'title' => '7C. Import & Export Data User',
                        'steps' => [
                            [
                                'no' => 12,
                                'text' => 'Unduh Template CSV',
                                'desc' => 'Klik tombol <strong>"Template"</strong> (kotak merah) untuk mengunduh file CSV contoh dengan header: name, email, password, role, is_admin. Gunakan file ini sebagai dasar sebelum impor massal.',
                                'img' => 'real_user_template_btn.png',
                                'label' => 'UNDUH TEMPLATE CSV'
                            ],

                            [
                                'no' => 13,
                                'text' => 'Import User dari File Excel/CSV',
                                'desc' => 'Klik tombol <strong>"Import"</strong> (kotak merah) untuk membuka modal impor. Unggah file Excel/CSV sesuai template. Sistem memvalidasi setiap baris dan membuat akun secara massal. Error per baris akan dilaporkan.',
                                'img' => 'real_user_import_modal.png',
                                'label' => 'MODAL IMPORT FILE'
                            ],

                            [
                                'no' => 14,
                                'text' => 'Export Data User',
                                'desc' => 'Klik tombol <strong>"Export"</strong> (kotak merah) untuk mengunduh seluruh data user. Data yang diekspor mencakup semua informasi user kecuali password.',
                                'img' => 'real_user_export_btn.png',
                                'label' => 'EXPORT DATA USER'
                            ],
                        ],
                    ],
                    [
                        'id' => 'user-advanced',
                        'title' => '7D. Konfigurasi AI per User (Delegasi)',
                        'steps' => [
                            [
                                'no' => 15,
                                'text' => 'Tombol AI Config (per User)',
                                'desc' => 'Pada kolom <strong>Aksi</strong> setiap baris user, klik tombol biru berbentuk robot/chip <i class="fas fa-microchip"></i> (kotak merah). Fitur ini memungkinkan Admin mendelegasikan model dan API key tertentu khusus for satu user.',
                                'img' => 'real_user_ai_config2.png',
                                'label' => 'TOMBOL AI CONFIG PER USER'
                            ],

                            [
                                'no' => 16,
                                'text' => 'Modal Konfigurasi AI per User',
                                'desc' => 'Modal AI Config menampilkan daftar semua model dan API key yang tersedia. Centang model dan yang ingin didelegasikan untuk user ini sehingga penggunaan AI dapat dikontrol per individu.',
                                'img' => 'real_model_ai_user_management.png',
                                'label' => 'KONFIGURASI AI PER USER'
                            ],

                            [
                                'no' => 17,
                                'text' => 'Setting API Key per User',
                                'desc' => 'Untuk menyetting api key user anda tinggal click yang saya lingkari di gambar, yang bertuliskan API keys di modal agar memunculkan semua api key yang sudah kita input, dan pastikan API Key Sesuai dengan Provider dari model yang sudah anda pilih',
                                'img' => 'real_click_api_key_per_user.png',
                                'label' => 'API KEY MENU'
                            ],

                            [
                                'no' => 18,
                                'text' => 'Setting API Key Per User',
                                'desc' => 'Centang API key yang ingin didelegasikan untuk user ini. API key yang dipilih akan digunakan oleh AI untuk user tersebut saat melakukan analisis data.',
                                'img' => 'real_api_key_list.png',
                                'label' => 'LIST API KEY'
                            ],
                        ],
                    ],
                    [
                        'id' => 'user-rls',
                        'title' => '7E. Row Level Security (RLS) — Filter Data Baris',
                        'steps' => [
                            [
                                'no' => 19,
                                'text' => 'Apa itu Row Level Security?',
                                'desc' => 'Row Level Security (RLS) adalah fitur pembatasan data di level baris. Dengan RLS, Admin dapat membatasi baris data yang bisa dianalisis AI untuk user tertentu. <br><br>Contoh kasus: <br>• User <strong>Cabang Jakarta</strong> → hanya bisa melihat data di mana <code>kode_cabang = \'JKT\'</code> <br>• User <strong>Salesman A</strong> → hanya bisa melihat data di mana <code>id_salesman = 12</code> <br>• User <strong>Divisi Finance</strong> → hanya bisa melihat data di mana <code>divisi = \'finance\'</code> <br><br>Tanpa RLS, user dapat melihat seluruh isi tabel yang diizinkan rolenya.',
                                'img' => 'real_user_rls_open.png',
                                'label' => 'KONSEP ROW LEVEL SECURITY'
                            ],

                            [
                                'no' => 20,
                                'text' => 'Tombol RLS — Buka Modal Filter Data',
                                'desc' => 'Pada kolom <strong>Aksi</strong> setiap baris user, klik tombol <i class="fas fa-filter" style="color:#10b981"></i> hijau (kotak merah). Jika user sudah memiliki filter aktif, badge angka kecil berwarna akan muncul di atas tombol menunjukkan jumlah filter yang terpasang.',
                                'img' => 'real_button_rls_per_user.png',
                                'label' => 'TOMBOL BUKA RLS'
                            ],

                            [
                                'no' => 21,
                                'text' => 'Modal RLS — Daftar Tabel Terdeteksi',
                                'desc' => 'Modal <strong>Pembatasan Data (Row-Level Security)</strong> terbuka menampilkan daftar tabel yang terdeteksi di panel kiri (kotak merah). Setiap tabel yang sudah memiliki filter menampilkan badge <span style="background:#d1fae5;color:#065f46;padding:1px 7px;border-radius:10px;font-size:.75rem">N filter</span>. <br><br>Di pojok kanan atas terdapat tombol <strong>"Salin"</strong> untuk menyalin konfigurasi RLS dari user lain.',
                                'img' => 'real_user_rls_modal.png',
                                'label' => 'DAFTAR TABEL RLS'
                            ],

                            [
                                'no' => 22,
                                'text' => 'Pilih Tabel & Tambah Kondisi Filter',
                                'desc' => 'Klik nama tabel di panel kiri untuk membuka area pengaturan kondisi di panel kanan. Panel kanan menampilkan: <br>• <strong>Nama tabel</strong> yang dipilih <br>• <strong>Daftar aturan filter</strong> yang sudah ada <br>• <strong>Tombol "+ Tambah Kondisi"</strong> (kotak merah) untuk menambah rule baru <br><br>Setiap aturan filter terdiri dari 3 bagian: <br>1. <strong>Kolom</strong> — pilih kolom dari dropdown (beserta tipe data) <br>2. <strong>Operator</strong> — pilih dari: <code>=</code> sama dengan, <code>!=</code> tidak sama, <code>&gt;</code> lebih besar, <code>&lt;</code> lebih kecil, <code>LIKE</code> mengandung teks, <code>IN</code> dalam daftar <br>3. <strong>Nilai</strong> — ketik nilai yang menjadi batasan (contoh: <code>1271</code>, <code>Jakarta</code>)',
                                'img' => 'real_rls_add_rule.png',
                                'label' => 'TAMBAH KONDISI FILTER'
                            ],

                            [
                                'no' => 23,
                                'text' => 'Preview Data & Simpan Perubahan',
                                'desc' => 'Sebelum menyimpan, klik tombol <strong>"Preview Data (5 Baris)"</strong> (kotak merah) untuk melihat contoh 5 baris data yang akan tampil sesuai kondisi filter yang diset. Ini memastikan filter sudah benar sebelum diterapkan ke user. <br><br>Setelah yakin, klik <strong>"Simpan Perubahan"</strong> biru di bawah modal. Klik <strong>"Batal"</strong> untuk menutup tanpa menyimpan. <br><br><strong>Tips penting:</strong><br>• Kosongkan semua kondisi untuk mengizinkan user melihat seluruh data tabel tersebut <br>• Satu tabel bisa memiliki lebih dari satu kondisi (AND logic) <br>• Kondisi berlaku untuk semua pertanyaan AI yang melibatkan tabel tersebut',
                                'img' => 'real_rls_preview.png',
                                'label' => 'PREVIEW DATA & SIMPAN RLS'
                            ],
                            [
                                'no' => 24,
                                'text' => 'Salin User',
                                'desc' => 'Klik tombol Salin User untuk mengambil data settingan rls dari user lain dan akan muncul pilihan form daftar user yang akan disalin',
                                'img' => 'real_rls_copyrls_user.png',
                                'label' => 'SALIN USER'
                            ],
                            [
                                'no' => 25,
                        <style>
        /* ── Modern Design Tokens & Scrollbars ── */
        .guide-wrap {
            display: flex;
            gap: 0;
            align-items: flex-start;
            width: 100%;
        }

        /* ── TABLE OF CONTENTS (TOC) — FLOATING GLASS ── */
        .guide-toc {
            width: 320px;
            min-width: 320px;
            flex-shrink: 0;
            position: sticky;
            top: 90px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem 1.25rem;
            margin-right: 2.5rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        html.dark .guide-toc {
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25);
            background: rgba(17, 24, 39, 0.6);
        }

        /* Custom scrollbar for TOC */
        .guide-toc::-webkit-scrollbar {
            width: 5px;
        }
        .guide-toc::-webkit-scrollbar-track {
            background: transparent;
        }
        .guide-toc::-webkit-scrollbar-thumb {
            background: var(--glass-border2);
            border-radius: 10px;
        }
        .guide-toc::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        .toc-title {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            margin-bottom: 1rem;
            padding-left: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .toc-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, var(--glass-border2), transparent);
        }

        .toc-menu-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 750;
            text-decoration: none;
            margin-top: 14px;
            background: rgba(99, 102, 241, 0.04);
            border: 1px solid var(--glass-border);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .toc-menu-link:hover {
            background: rgba(99, 102, 241, 0.08);
            border-color: rgba(99, 102, 241, 0.25);
            transform: translateX(4px);
            color: var(--primary);
        }

        .toc-link {
            display: block;
            color: var(--text-muted);
            padding: 8px 16px;
            font-size: 0.76rem;
            font-weight: 600;
            text-decoration: none;
            border-left: 2px solid var(--glass-border2);
            margin-left: 6px;
            transition: all 0.25s ease;
        }

        .toc-link:hover {
            color: var(--primary);
            border-left-color: var(--primary);
            background: rgba(99, 102, 241, 0.03);
            padding-left: 20px;
            transform: translateX(4px);
        }

        .toc-step-count {
            font-size: 0.65rem;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: #fff;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 800;
            box-shadow: 0 3px 8px rgba(99, 102, 241, 0.35);
        }

        .guide-content {
            flex: 1;
            min-width: 0;
        }

        /* ── PREMIUM CARDS & TIMELINES ── */
        .menu-section {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem;
            margin-bottom: 4rem;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.02);
            scroll-margin-top: 100px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        html.dark .menu-section {
            background: rgba(17, 24, 39, 0.45);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
        }

        .menu-icon {
            width: 60px;
            height: 60px;
            min-width: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        .menu-section:hover .menu-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .menu-section-title {
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--text-main);
            margin: 0;
            letter-spacing: -0.8px;
            font-family: 'Outfit', sans-serif;
        }

        .menu-section-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 0.5rem 0 0;
            max-width: 640px;
            line-height: 1.6;
        }

        .sub-section {
            margin-top: 3.5rem;
            scroll-margin-top: 100px;
        }

        .sub-section-title {
            font-weight: 850;
            font-size: 1.25rem;
            color: var(--text-main);
            border-left: 5px solid var(--primary);
            padding-left: 1.25rem;
            margin-bottom: 2.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.4px;
        }

        /* Timeline Step Styling */
        .guide-step {
            display: flex;
            gap: 24px;
            padding: 2.5rem 0;
            border-bottom: 1px solid var(--glass-border2);
            position: relative;
        }

        .guide-step:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .step-num {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.1rem;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: #fff;
            border: 3px solid var(--card-bg);
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2;
        }
        
        .guide-step:hover .step-num {
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.6);
        }

        .step-title {
            font-weight: 850;
            font-size: 1.15rem;
            color: var(--text-main);
            margin-bottom: 0.75rem;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.3px;
        }

        .step-desc {
            color: var(--text-muted);
            font-size: 0.88rem;
            line-height: 1.8;
            background: rgba(99, 102, 241, 0.02);
            padding: 1.1rem 1.4rem;
            border-radius: 16px;
            border: 1px solid var(--glass-border2);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.01);
        }

        html.dark .step-desc {
            background: rgba(255, 255, 255, 0.02);
        }

        /* ── WINDOW BROWSER MOCKUP CONTAINER ── */
        .screenshot-frame {
            margin-top: 1.75rem;
            border-radius: 16px;
            overflow: hidden;
            border: 1.5px solid var(--glass-border);
            background: rgba(15, 23, 42, 0.02);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        html.dark .screenshot-frame {
            background: rgba(15, 23, 42, 0.4);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.3);
            border-color: var(--glass-border2);
        }
        
        .screenshot-frame:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px rgba(99, 102, 241, 0.12);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .window-header {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 12px 18px;
            background: rgba(0, 0, 0, 0.02);
            border-bottom: 1px solid var(--glass-border2);
        }
        
        html.dark .window-header {
            background: rgba(255, 255, 255, 0.02);
        }

        .window-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
        }
        .window-dot.red { background: #ef4444; }
        .window-dot.yellow { background: #f59e0b; }
        .window-dot.green { background: #10b981; }
        
        .window-title {
            margin-left: 8px;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            font-family: 'Outfit', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            opacity: 0.8;
        }

        .screenshot-wrap {
            position: relative;
            background: #000;
            cursor: zoom-in;
            overflow: hidden;
            display: block;
        }

        .screenshot-img {
            width: 100%;
            max-height: 600px;
            object-fit: contain;
            display: block;
            transition: transform 0.4s ease;
        }

        .screenshot-img:hover {
            transform: scale(1.02);
        }

        .screenshot-badge {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 12px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-align: center;
            color: #fff;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            text-transform: uppercase;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            transition: background 0.3s;
        }
        .screenshot-wrap:hover .screenshot-badge {
            background: rgba(99, 102, 241, 0.95);
        }

        /* ── LIGHTBOX (FROSTED GLASS BLUR) ── */
        .img-lightbox {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
            flex-direction: column;
            gap: 1.5rem;
            opacity: 0;
            transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .img-lightbox.show {
            display: flex;
            opacity: 1;
        }

        .img-lightbox img {
            max-width: 90vw;
            max-height: 85vh;
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
            transform: scale(0.95);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .img-lightbox.show img {
            transform: scale(1);
        }

        .lightbox-close {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: rgba(225, 29, 72, 0.9);
            border: none;
            color: #fff;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(225, 29, 72, 0.4);
            transition: all 0.25s ease;
        }
        .lightbox-close:hover {
            background: #be123c;
            transform: rotate(90deg) scale(1.1);
        }

        /* FLOATING PRINT BUTTON */
        .print-btn {
            position: fixed;
            bottom: 2.5rem;
            right: 2.5rem;
            z-index: 1000;
            padding: 16px 32px;
            border-radius: 50px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: #fff !important;
            border: none;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            font-family: 'Outfit', sans-serif;
            text-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }

        .print-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.6);
        }

        .guide-progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            z-index: 9999;
            background: linear-gradient(90deg, var(--primary), #10b981, #8b5cf6);
            transition: width 0.1s;
        }

        /* ── RLS REFERENCE BOX ── */
        .rls-info-box {
            background: linear-gradient(135deg, rgba(20, 184, 166, 0.05), rgba(99, 102, 241, 0.03));
            border: 1px solid rgba(20, 184, 166, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.01);
        }

        .rls-info-box .rls-title {
            font-weight: 800;
            color: #0f766e;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        html.dark .rls-info-box .rls-title {
            color: #2dd4bf;
        }

        .rls-operator-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin-top: 0.75rem;
            border-radius: 8px;
            overflow: hidden;
        }

        .rls-operator-table th {
            background: rgba(20, 184, 166, 0.12);
            color: #0f766e;
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
        }
        
        html.dark .rls-operator-table th {
            color: #2dd4bf;
            background: rgba(20, 184, 166, 0.2);
        }

        .rls-operator-table td {
            padding: 10px 14px;
            border-bottom: 1px solid rgba(20, 184, 166, 0.08);
            color: var(--text-muted);
        }

        .rls-operator-table code {
            background: rgba(20, 184, 166, 0.08);
            padding: 2px 6px;
            border-radius: 4px;
            color: #0f766e;
            font-weight: 700;
        }
        
        html.dark .rls-operator-table code {
            color: #2dd4bf;
            background: rgba(20, 184, 166, 0.15);
        }

        @media print {
            .guide-toc,
            .print-btn,
            .chatbot-back-btn,
            .guide-progress-bar {
                display: none !important;
            }

            .guide-content {
                width: 100%;
            }

            .menu-section {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #e2e8f0;
            }
        }

        @media (max-width: 1100px) {
            .guide-toc {
                width: 250px;
                min-width: 250px;
                margin-right: 1.5rem;
            }
        }

        @media (max-width: 900px) {
            .guide-wrap {
                flex-direction: column;
            }

            .guide-toc {
                width: 100%;
                min-width: 0;
                position: static;
                max-height: none;
                margin: 0 0 2rem;
            }

            .menu-section {
                padding: 2rem;
            }

            .guide-step {
                gap: 16px;
            }
        }
    </style>

    {{-- Lightbox --}}
    <div class="img-lightbox" id="imgLightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
        <img id="lightboxImg" src="" alt="">
    </div>

    {{-- Progress bar --}}
    <div class="guide-progress-bar" id="progressBar" style="width:0%"></div>

    {{-- Header Banner Redesigned --}}
    <div class="guide-header-banner" style="position: relative; padding: 2.5rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.06), rgba(139, 92, 246, 0.03)); border: 1px solid var(--glass-border); border-radius: 24px; margin-bottom: 2.5rem; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);">
        <div style="position: absolute; top: -50px; right: -50px; width: 250px; height: 250px; background: radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, rgba(0,0,0,0) 70%); filter: blur(30px); pointer-events: none;"></div>
        <div style="position: relative; z-index: 1;">
            <a href="{{ route('chatbot') }}" class="btn chatbot-back-btn" style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 700; color: var(--primary); background: rgba(99, 102, 241, 0.06); border: 1px solid rgba(99, 102, 241, 0.12); padding: 6px 16px; border-radius: 20px; text-decoration: none; transition: all 0.25s ease; margin-bottom: 1.25rem;">
                <i class="fas fa-arrow-left"></i> Kembali ke Chatbot
            </a>
            <h1 style="color: var(--text-main); font-weight: 900; font-size: 2.4rem; margin: 0; letter-spacing: -1.5px; line-height: 1.1; font-family: 'Outfit', sans-serif;">
                Buku Panduan Admin Panel
            </h1>
            <p class="text-muted mt-2 mb-0" style="font-size: 0.95rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(99, 102, 241, 0.08); color: var(--primary); padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">
                    <i class="fas fa-book-open"></i> Dokumentasi Operasional
                </span>
                <span style="color: var(--text-muted); opacity: 0.5;">•</span>
                <span><strong>{{ collect($guideData)->sum(fn($m) => collect($m['sections'])->sum(fn($s) => count($s['steps']))) }}</strong> Langkah Teruji</span>
                <span style="color: var(--text-muted); opacity: 0.5;">•</span>
                <span>Standar Operasional Prosedur (SOP)</span>
            </p>
        </div>
    </div>

    {{-- Floating Print Button --}}
    <button onclick="window.print()" class="btn print-btn">
        <i class="fas fa-print"></i> Cetak PDF
    </button>

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

                            {{-- Kotak info khusus untuk seksi RLS --}}
                            @if($sec['id'] === 'user-rls')
                                <div class="rls-info-box mb-4">
                                    <div class="rls-title"><i class="fas fa-info-circle me-2"></i>Referensi Cepat: Operator Kondisi
                                        Filter</div>
                                    <table class="rls-operator-table">
                                        <tr>
                                            <th>Operator</th>
                                            <th>Arti</th>
                                            <th>Contoh Penggunaan</th>
                                        </tr>
                                        <tr>
                                            <td><code>=</code></td>
                                            <td>Sama persis dengan nilai</td>
                                            <td>kode_cabang <code>=</code> 1271</td>
                                        </tr>
                                        <tr>
                                            <td><code>!=</code></td>
                                            <td>Tidak sama dengan nilai</td>
                                            <td>status <code>!=</code> inactive</td>
                                        </tr>
                                        <tr>
                                            <td><code>&gt;</code></td>
                                            <td>Lebih besar dari nilai</td>
                                            <td>total_penjualan <code>&gt;</code> 1000000</td>
                                        </tr>
                                        <tr>
                                            <td><code>&lt;</code></td>
                                            <td>Lebih kecil dari nilai</td>
                                            <td>umur <code>&lt;</code> 30</td>
                                        </tr>
                                        <tr>
                                            <td><code>LIKE</code></td>
                                            <td>Mengandung teks tertentu</td>
                                            <td>nama_produk <code>LIKE</code> Beras</td>
                                        </tr>
                                        <tr>
                                            <td><code>IN</code></td>
                                            <td>Nilainya ada dalam daftar</td>
                                            <td>kota <code>IN</code> Jakarta,Bandung,Surabaya</td>
                                        </tr>
                                    </table>
                                </div>
                            @endif

                            @foreach($sec['steps'] as $step)
                                <div class="guide-step">
                                    <div class="step-num">{{ $step['no'] }}</div>
                                    <div class="flex-grow-1">
                                        <div class="step-title">{{ $step['text'] }}</div>
                                        <div class="step-desc">{!! $step['desc'] !!}</div>
                                        
                                        {{-- HIGH-FIDELITY MACOS WINDOW BROWSER MOCKUP --}}
                                        <div class="screenshot-frame">
                                            <div class="window-header">
                                                <span class="window-dot red"></span>
                                                <span class="window-dot yellow"></span>
                                                <span class="window-dot green"></span>
                                                <span class="window-title">{{ $step['label'] }}</span>
                                            </div>
                                            <div class="screenshot-wrap"
                                                onclick="openLightbox('{{ asset('admin_guide/' . $step['img']) }}')">
                                                <img src="{{ asset('admin_guide/' . $step['img']) }}" class="screenshot-img"
                                                    alt="{{ $step['text'] }}"
                                                    onerror="this.src='https://placehold.co/1280x720/1e293b/ef4444?text={{ urlencode($step['img']) }}'">
                                                <div class="screenshot-badge">
                                                    <i class="fas fa-magnifying-glass-plus me-1"></i> Klik untuk Memperbesar Tampilan Panduan
                                                </div>
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
                <div
                    style="width:72px;height:72px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;box-shadow:0 8px 24px rgba(16,185,129,.35);">
                    <i class="fas fa-check fa-2x text-white"></i>
                </div>
                <h3 style="font-weight:800;color:var(--text-main);">Panduan Selesai</h3>
                <p class="text-muted">Dokumentasi ini mencakup seluruh fitur Admin Panel DarkoAI.<br>Gunakan sebagai Standar
                    Operasional Prosedur (SOP) administrasi sistem.</p>
                <button onclick="window.scrollTo({top:0,behavior:'smooth'})" class="btn btn-outline-secondary mt-3">
                    <i class="fas fa-arrow-up me-1"></i> Kembali ke Atas
                </button>
            </div>
        </div>
    </div>

    <script>
        function openLightbox(src) {
            document.getElementById('lightboxImg').src = src;
            const lightbox = document.getElementById('imgLightbox');
            lightbox.style.display = 'flex';
            // Force browser reflow to animate opacity
            lightbox.offsetHeight;
            lightbox.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeLightbox() {
            const lightbox = document.getElementById('imgLightbox');
            lightbox.classList.remove('show');
            setTimeout(() => {
                if (!lightbox.classList.contains('show')) {
                    lightbox.style.display = 'none';
                }
            }, 300);
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

        document.querySelectorAll('.toc-link, .toc-menu-link').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const target = document.querySelector(a.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        window.addEventListener('scroll', () => {
            const scrolled = window.scrollY;
            const total = document.documentElement.scrollHeight - window.innerHeight;
            const pct = total > 0 ? (scrolled / total * 100).toFixed(1) : 0;
            document.getElementById('progressBar').style.width = pct + '%';
        });

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    document.querySelectorAll('.toc-menu-link, .toc-link').forEach(a => {
                        a.style.background = ''; a.style.color = '';
                    });
                    const el = document.querySelector(`.toc-menu-link[href="#${entry.target.id}"], .toc-link[href="#${entry.target.id}"]`);
                    if (el) { 
                        el.style.background = 'rgba(99,102,241,.12)'; 
                        el.style.color = 'var(--primary)'; 
                    }
                }
            });
        }, { threshold: 0.15, rootMargin: '-80px 0px -60% 0px' });

        document.querySelectorAll('[id^="menu-"],[id^="auth-"],[id^="chat-"],[id^="dash-"],[id^="db-"],[id^="ai-"],[id^="role-"],[id^="user-"]')
            .forEach(el => observer.observe(el));
    </script>
@endsection