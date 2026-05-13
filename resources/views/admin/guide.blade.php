@extends('layouts.admin')

@section('page-title', 'Panduan Lengkap Administrator (Exhaustive Guide)')

@section('content')
@php
$guideData = [
    [
        'id' => 'menu-1-auth',
        'title' => 'MENU 1: AUTENTIKASI & LOGIN (/login)',
        'icon' => 'fas fa-shield-alt',
        'desc' => 'Sistem menggunakan sistem keamanan berlapis untuk melindungi data perusahaan. Bagian ini menjelaskan proses login, penanganan lupa password menggunakan OTP (One Time Password), dan keamanan sesi.',
        'sections' => [
            [
                'id' => 'auth-login',
                'title' => '1A. Proses Login Utama',
                'steps' => [
                    ['no' => 1, 'text' => 'Buka Halaman Login', 'desc' => 'Akses <code>/login</code>. Gunakan email resmi yang didaftarkan oleh Super Admin.', 'real_img' => 'real_login_page.png', 'img_text' => 'Halaman Login'],
                    ['no' => 2, 'text' => 'Input Email', 'desc' => 'Masukkan email Anda pada field bertanda merah.', 'real_img' => 'real_login_email.png', 'img_text' => 'Field Email'],
                    ['no' => 3, 'text' => 'Input Password', 'desc' => 'Masukkan password Anda. Klik ikon mata untuk melihat karakter.', 'real_img' => 'real_login_password.png', 'img_text' => 'Field Password'],
                    ['no' => 4, 'text' => 'Klik Tombol Login', 'desc' => 'Klik tombol biru untuk masuk ke sistem.', 'real_img' => 'real_login_button.png', 'img_text' => 'Tombol Login'],
                ]
            ],
            [
                'id' => 'auth-forgot',
                'title' => '1B. Lupa Password & OTP',
                'steps' => [
                    ['no' => 5, 'text' => 'Klik "Lupa Password?"', 'desc' => 'Terletak di bawah form login jika Anda lupa kredensial.', 'real_img' => 'real_login_forgot_link.png', 'img_text' => 'Link Lupa Password'],
                    ['no' => 6, 'text' => 'Masukkan Email Pemulihan', 'desc' => 'Sistem akan mengirimkan 6 digit kode OTP ke email ini.', 'real_img' => 'real_forgot_email_field.png', 'img_text' => 'Email Pemulihan'],
                    ['no' => 7, 'text' => 'Cek Email & Input OTP', 'desc' => 'Buka inbox email Anda, salin kode OTP, dan masukkan di halaman verifikasi.', 'real_img' => 'real_verify_otp_page.png', 'img_text' => 'Halaman Verifikasi OTP'],
                    ['no' => 8, 'text' => 'Set Password Baru', 'desc' => 'Setelah OTP valid, buat password baru minimal 8 karakter.', 'real_img' => 'real_reset_password_page.png', 'img_text' => 'Halaman Reset Password'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-2-dashboard',
        'title' => 'MENU 2: DASHBOARD UTAMA (/admin)',
        'icon' => 'fas fa-chart-line',
        'desc' => 'Dashboard memberikan pandangan 360 derajat terhadap kesehatan sistem, mencakup statistik pengguna, koneksi database, dan ketersediaan API Key AI.',
        'sections' => [
            [
                'id' => 'dash-overview',
                'title' => '2A. Ringkasan Statistik',
                'steps' => [
                    ['no' => 9, 'text' => 'Card Statistik Utama', 'desc' => 'Menampilkan Total User, Database Aktif, dan AI Provider.', 'real_img' => 'real_dashboard.png', 'img_text' => 'Statistik Dashboard'],
                    ['no' => 10, 'text' => 'Sidebar Navigasi', 'desc' => 'Gunakan sidebar di kiri untuk berpindah antar modul administrasi.', 'real_img' => 'real_sidebar.png', 'img_text' => 'Sidebar'],
                    ['no' => 11, 'text' => 'Toggle Dark/Light Mode', 'desc' => 'Sesuaikan kenyamanan mata dengan mengganti tema di pojok kanan atas.', 'real_img' => 'real_dash_darkmode.png', 'img_text' => 'Tombol Tema'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-3-users',
        'title' => 'MENU 3: USER MANAGEMENT (/admin/users)',
        'icon' => 'fas fa-users-cog',
        'desc' => 'Modul paling krusial untuk mengatur siapa yang boleh menggunakan sistem dan data apa saja yang boleh mereka akses melalui AI.',
        'sections' => [
            [
                'id' => 'user-list',
                'title' => '3A. Pengelolaan Daftar User',
                'steps' => [
                    ['no' => 12, 'text' => 'Tabel Data User', 'desc' => 'Daftar seluruh user beserta role dan status adminnya.', 'real_img' => 'real_user_list.png', 'img_text' => 'Tabel User'],
                    ['no' => 13, 'text' => 'Fitur Pencarian & Filter', 'desc' => 'Gunakan form di atas tabel untuk mencari user berdasarkan nama/email atau filter per role.', 'real_img' => 'real_user_filter_form.png', 'img_text' => 'Form Filter'],
                    ['no' => 14, 'text' => 'Tombol Tambah User', 'desc' => 'Klik untuk membuat akun baru secara manual.', 'real_img' => 'real_user_tambah_btn.png', 'img_text' => 'Tombol Tambah'],
                    ['no' => 15, 'text' => 'Import User Massal', 'desc' => 'Upload file Excel untuk mendaftarkan banyak user sekaligus.', 'real_img' => 'real_user_import_modal.png', 'img_text' => 'Modal Import'],
                    ['no' => 16, 'text' => 'Download Template Excel', 'desc' => 'Unduh format yang benar sebelum melakukan import.', 'real_img' => 'real_user_template_btn.png', 'img_text' => 'Tombol Template'],
                ]
            ],
            [
                'id' => 'user-ai-config',
                'title' => '3B. Konfigurasi AI Per User',
                'steps' => [
                    ['no' => 17, 'text' => 'Buka Modal AI Config', 'desc' => 'Klik ikon robot di baris user yang diinginkan.', 'real_img' => 'real_user_ai_btn.png', 'img_text' => 'Tombol AI Config'],
                    ['no' => 18, 'text' => 'Pilih Model AI', 'desc' => 'Tentukan model mana saja (misal: GPT-4, Gemini Pro) yang boleh digunakan user ini.', 'real_img' => 'real_ai_config_modal.png', 'img_text' => 'Pilih Model'],
                    ['no' => 19, 'text' => 'Pilih API Key', 'desc' => 'Tentukan API Key mana yang akan menanggung biaya penggunaan user ini.', 'real_img' => 'real_ai_config_tab_keys.png', 'img_text' => 'Pilih API Key'],
                    ['no' => 20, 'text' => 'Simpan Konfigurasi AI', 'desc' => 'Pastikan setiap model memiliki key yang sesuai sebelum klik Simpan.', 'real_img' => 'real_ai_config_save.png', 'img_text' => 'Simpan AI Config'],
                ]
            ],
            [
                'id' => 'user-rls',
                'title' => '3C. Row Level Security (RLS)',
                'steps' => [
                    ['no' => 21, 'text' => 'Buka Filter Tabel', 'desc' => 'Klik ikon filter untuk membatasi baris data yang bisa dibaca AI untuk user ini.', 'real_img' => 'real_user_rls_btn.png', 'img_text' => 'Tombol RLS'],
                    ['no' => 22, 'text' => 'Pilih Tabel Database', 'desc' => 'Pilih tabel yang ingin dibatasi (misal: tabel penjualan).', 'real_img' => 'real_rls_table_select.png', 'img_text' => 'Pilih Tabel'],
                    ['no' => 23, 'text' => 'Tambah Aturan Filter', 'desc' => 'Contoh: <code>kode_cabang = "B282"</code> agar user hanya bisa melihat data cabangnya.', 'real_img' => 'real_rls_add_rule.png', 'img_text' => 'Tambah Aturan'],
                    ['no' => 24, 'text' => 'Preview Hasil Filter', 'desc' => 'Cek 5 baris contoh data untuk memastikan filter bekerja dengan benar.', 'real_img' => 'real_rls_preview.png', 'img_text' => 'Tombol Preview'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-4-roles',
        'title' => 'MENU 4: ROLE & PERMISSIONS (/admin/roles)',
        'icon' => 'fas fa-user-shield',
        'desc' => 'Mengatur grup akses yang menentukan tabel mana saja yang "diketahui" oleh AI untuk setiap departemen atau role tertentu.',
        'sections' => [
            [
                'id' => 'role-manage',
                'title' => '4A. Pengaturan Akses Tabel',
                'steps' => [
                    ['no' => 25, 'text' => 'Pilih Role di Sidebar', 'desc' => 'Klik nama role di sidebar kiri (misal: Role Accounting) untuk memuat daftar tabelnya.', 'real_img' => 'real_role_list.png', 'img_text' => 'Pilih Role'],
                    ['no' => 26, 'text' => 'Pilih Tabel Database', 'desc' => 'Klik pada baris tabel yang ingin diizinkan. Tabel yang aktif akan berwarna hijau dengan indikator centang.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Daftar Tabel Permissions'],
                    ['no' => 27, 'text' => 'Gunakan Filter & Search', 'desc' => 'Cari tabel spesifik menggunakan form pencarian atau filter per database/schema untuk mempermudah navigasi.', 'real_img' => 'real_role_permissions.png', 'img_text' => 'Filter Tabel Role'],
                    ['no' => 28, 'text' => 'Simpan Hak Akses', 'desc' => 'Klik tombol <strong>"Simpan Akses"</strong> di pojok kanan atas untuk menerapkan perubahan pada semua user dalam role tersebut.', 'real_img' => 'real_role_save_permissions.png', 'img_text' => 'Tombol Simpan'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-5-db',
        'title' => 'MENU 5: DATABASE MANAGEMENT (/admin/databases)',
        'icon' => 'fas fa-database',
        'desc' => 'Menghubungkan aplikasi ke berbagai sumber data (ERP, CRM, dll) agar AI dapat menarik informasi secara real-time.',
        'sections' => [
            [
                'id' => 'db-conn',
                'title' => '5A. Koneksi Database Baru',
                'steps' => [
                    ['no' => 28, 'text' => 'Tambah Database', 'desc' => 'Klik tombol untuk mendaftarkan server database baru.', 'real_img' => 'real_db_add_btn.png', 'img_text' => 'Tambah DB'],
                    ['no' => 29, 'text' => 'Pilih Driver (PGSQL/MySQL)', 'desc' => 'Pilih jenis database yang digunakan.', 'real_img' => 'real_db_list.png', 'img_text' => 'Pilih Driver'],
                    ['no' => 30, 'text' => 'Uji Koneksi (Test All)', 'desc' => 'Pastikan server bisa terhubung ke database target.', 'real_img' => 'real_db_test_all.png', 'img_text' => 'Test All Connections'],
                    ['no' => 31, 'text' => 'Lihat Schema Tabel', 'desc' => 'Klik ikon mata untuk memastikan tabel terbaca oleh sistem.', 'real_img' => 'real_db_schema_btn.png', 'img_text' => 'Lihat Schema'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-6-ai',
        'title' => 'MENU 6: AI INFRASTRUCTURE (/admin/ai-management)',
        'icon' => 'fas fa-brain',
        'desc' => 'Pusat kendali "Otak" chatbot. Mengatur provider, rotasi API Key, dan model AI yang digunakan.',
        'sections' => [
            [
                'id' => 'ai-provider',
                'title' => '6A. Provider & Status',
                'steps' => [
                    ['no' => 32, 'text' => 'Ringkasan AI Management', 'desc' => 'Lihat total provider dan key yang aktif.', 'real_img' => 'real_ai_management.png', 'img_text' => 'AI Overview'],
                    ['no' => 33, 'text' => 'Toggle Provider Aktif', 'desc' => 'Matikan/hidupkan provider secara instan jika ada gangguan.', 'real_img' => 'real_ai_toggle_on.png', 'img_text' => 'Toggle Provider'],
                ]
            ],
            [
                'id' => 'ai-keys',
                'title' => '6B. API Key & Rotasi',
                'steps' => [
                    ['no' => 34, 'text' => 'Tambah API Key Baru', 'desc' => 'Sistem mendukung multi-key per provider untuk rotasi otomatis.', 'real_img' => 'real_ai_add_key_btn.png', 'img_text' => 'Tambah Key'],
                    ['no' => 35, 'text' => 'Health Check API Key', 'desc' => 'Uji apakah key masih valid atau sudah expired/kehabisan saldo.', 'real_img' => 'real_ai_health_btn.png', 'img_text' => 'Tombol Health Check'],
                    ['no' => 36, 'text' => 'Reset Rate Limit', 'desc' => 'Jika key terkena blokir sementara (429), reset statusnya di sini.', 'real_img' => 'real_ai_reset_limit_btn.png', 'img_text' => 'Reset Limit'],
                ]
            ],
            [
                'id' => 'ai-models',
                'title' => '6C. Model Management',
                'steps' => [
                    ['no' => 37, 'text' => 'Tab Model AI', 'desc' => 'Daftar model yang bisa dipilih oleh user.', 'real_img' => 'real_ai_models_tab.png', 'img_text' => 'Tab Models'],
                    ['no' => 38, 'text' => 'Tambah Model Baru', 'desc' => 'Daftarkan identifier model terbaru (misal: GPT-4o).', 'real_img' => 'real_ai_add_model_btn.png', 'img_text' => 'Tambah Model'],
                ]
            ]
        ]
    ]
];
@endphp

<style>
    /* CSS Layout (Tetap Sama) */
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
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.1rem; flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    }
    .step-title { font-size: 1.1rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; }
    .step-desc { color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; }

    .screenshot-wrapper {
        margin-top: 1.25rem;
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid var(--glass-border2);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        position: relative;
        background: #1e293b;
    }
    .screenshot-wrapper.real-shot { border-color: rgba(239, 68, 68, 0.5); border-width: 3px; }
    .mockup-img { width: 100%; max-height: 550px; object-fit: contain; display: block; transition: transform 0.3s ease; cursor: zoom-in; }
    .screenshot-badge {
        position: absolute; bottom: 0; left: 0; right: 0;
        padding: 6px 14px; font-size: 0.75rem; font-weight: 700;
        text-align: center; color: white; background: rgba(239, 68, 68, 0.8);
    }

    .progress-container { width: 100%; height: 5px; background: var(--glass-border); position: fixed; top: 0; left: 0; z-index: 9999; }
    .progress-bar { height: 100%; background: var(--primary); width: 0%; }
    
    .img-lightbox { display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; cursor:zoom-out; }
    .img-lightbox.show { display:flex; }
    .img-lightbox img { max-width:95vw; max-height:95vh; border: 3px solid white; border-radius:8px; }

    @media print {
        .guide-toc, .progress-container, .print-btn { display: none !important; }
        .guide-content { width: 100%; }
        .menu-section { break-inside: avoid; border: none; box-shadow: none; }
    }
</style>

<div class="progress-container">
    <div class="progress-bar" id="progressBar"></div>
</div>

<div class="img-lightbox" id="imgLightbox" onclick="this.classList.remove('show')">
    <img id="lightboxImg" src="" alt="Screenshot">
</div>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h1 style="color: var(--text-main); font-weight: 800; font-size: 2rem; margin:0;">Admin exhaustive Guide</h1>
        <p class="text-muted mb-0">Panduan langkah-demi-langkah dengan screenshot aktual berpetunjuk kotak merah.</p>
    </div>
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print me-1"></i> Cetak Dokumen
    </button>
</div>

<div class="guide-wrap">
    <nav class="guide-toc" id="guideToc">
        <input type="text" id="guideSearch" class="form-control mb-3" placeholder="Cari langkah...">
        @foreach($guideData as $menu)
            <a class="toc-menu-link" href="#{{ $menu['id'] }}">{{ $menu['title'] }}</a>
            <div class="toc-sub">
                @foreach($menu['sections'] as $sec)
                    <a class="toc-link" href="#{{ $sec['id'] }}">{{ $sec['title'] }}</a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="guide-content">
        @foreach($guideData as $menu)
            <section id="{{ $menu['id'] }}" class="menu-section">
                <h2 class="menu-title border-bottom pb-3">
                    <i class="{{ $menu['icon'] }}"></i> {{ $menu['title'] }}
                </h2>
                <p class="text-muted mb-4">{{ $menu['desc'] }}</p>

                @foreach($menu['sections'] as $sec)
                    <div id="{{ $sec['id'] }}" class="subsection">
                        <div class="subsection-title">{{ $sec['title'] }}</div>
                        @foreach($sec['steps'] as $step)
                            <div class="guide-step">
                                <div class="step-number">{{ $step['no'] }}</div>
                                <div class="flex-grow-1">
                                    <div class="step-title">{{ $step['text'] }}</div>
                                    <div class="step-desc">{!! $step['desc'] !!}</div>
                                    <div class="screenshot-wrapper real-shot" onclick="openLightbox('{{ asset('admin_guide/' . $step['real_img']) }}')">
                                        <img src="{{ asset('admin_guide/' . $step['real_img']) }}" class="mockup-img" alt="Step {{ $step['no'] }}" onerror="this.src='https://placehold.co/1280x720/1e293b/ef4444?text=Screenshot+Pending'">
                                        <div class="screenshot-badge">
                                            <i class="fas fa-mouse-pointer me-1"></i> PERHATIKAN KOTAK MERAH — Klik untuk perbesar
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('imgLightbox').classList.add('show');
}
window.onscroll = function() {
    let winScroll = document.body.scrollTop || document.documentElement.scrollTop;
    let height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    let scrolled = (winScroll / height) * 100;
    document.getElementById("progressBar").style.width = scrolled + "%";
};
</script>
@endsection
