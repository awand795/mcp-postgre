@extends('layouts.admin')

@section('page-title', 'Panduan Administrator (Full System Guide)')

@section('content')
@php
$guideData = [
    [
        'id' => 'menu-0-auth',
        'title' => '0. AUTENTIKASI & KEAMANAN',
        'icon' => 'fas fa-shield-alt',
        'desc' => 'Proses masuk ke sistem dan pemulihan akun untuk menjaga keamanan data.',
        'sections' => [
            [
                'id' => 'auth-login',
                'title' => '0A. Login ke Panel',
                'steps' => [
                    ['no' => 1, 'text' => 'Halaman Login', 'desc' => 'Masukkan email dan password terdaftar.', 'real_img' => 'real_login_page.png'],
                    ['no' => 2, 'text' => 'Lupa Password', 'desc' => 'Klik link "Lupa Password" untuk memulai pemulihan via email.', 'real_img' => 'real_login_forgot_link.png'],
                    ['no' => 3, 'text' => 'Verifikasi OTP', 'desc' => 'Masukkan 6 digit kode yang dikirim ke inbox email Anda.', 'real_img' => 'real_verify_otp_page.png'],
                    ['no' => 4, 'text' => 'Setel Password Baru', 'desc' => 'Buat password baru yang kuat (minimal 8 karakter).', 'real_img' => 'real_reset_password_page.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-1-chatbot',
        'title' => '1. darkotech AI (CHATBOT)',
        'icon' => 'fas fa-robot',
        'desc' => 'Antarmuka utama chatbot untuk berinteraksi dengan data melalui kecerdasan buatan.',
        'sections' => [
            [
                'id' => 'chat-interface',
                'title' => '1A. Antarmuka Chat',
                'steps' => [
                    ['no' => 5, 'text' => 'Halaman Utama Chat', 'desc' => 'Area percakapan utama dengan AI.', 'real_img' => 'real_chatbot_page.png'],
                    ['no' => 6, 'text' => 'Sidebar Riwayat', 'desc' => 'Melihat daftar percakapan sebelumnya dan membuat chat baru.', 'real_img' => 'real_chatbot_sidebar.png'],
                    ['no' => 7, 'text' => 'Aksi Riwayat (Delete)', 'desc' => 'Klik ikon sampah di sidebar untuk menghapus sesi tertentu.', 'real_img' => 'real_chatbot_delete_confirm.png'],
                    ['no' => 8, 'text' => 'Export Data (PDF/Excel)', 'desc' => 'AI dapat menghasilkan tabel yang bisa diunduh langsung.', 'real_img' => 'real_chatbot_export.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-2-dashboard',
        'title' => '2. DASHBOARD ADMIN',
        'icon' => 'fas fa-chart-pie',
        'desc' => 'Pusat statistik sistem dan pengaturan preferensi visual administrator.',
        'sections' => [
            [
                'id' => 'dash-main',
                'title' => '2A. Statistik & Tema',
                'steps' => [
                    ['no' => 9, 'text' => 'Overview Statistik', 'desc' => 'Melihat jumlah user, database, dan AI provider aktif.', 'real_img' => 'real_dashboard.png'],
                    ['no' => 10, 'text' => 'Navigasi Sidebar Admin', 'desc' => 'Akses cepat ke seluruh modul manajemen.', 'real_img' => 'real_sidebar.png'],
                    ['no' => 11, 'text' => 'Mode Gelap (Dark Mode)', 'desc' => 'Tampilan Dashboard saat tema gelap diaktifkan.', 'real_img' => 'real_dashboard_dark.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-3-db',
        'title' => '3. DATABASE MANAGEMENT',
        'icon' => 'fas fa-database',
        'desc' => 'Menghubungkan aplikasi ke berbagai sumber data organisasi.',
        'sections' => [
            [
                'id' => 'db-wizard',
                'title' => '3A. Wizard Tambah Database',
                'steps' => [
                    ['no' => 12, 'text' => 'Langkah 1: Identitas', 'desc' => 'Isikan nama alias dan pilih jenis database.', 'real_img' => 'real_db_modal_step1.png'],
                    ['no' => 13, 'text' => 'Langkah 2: Kredensial', 'desc' => 'Isikan Host, Port, Nama DB, Username, dan Password.', 'real_img' => 'real_db_modal_step2.png'],
                    ['no' => 14, 'text' => 'Langkah 3: Finalisasi', 'desc' => 'Pilih schema (untuk PGSQL) dan uji koneksi.', 'real_img' => 'real_db_modal_step3.png'],
                ]
            ],
            [
                'id' => 'db-ops',
                'title' => '3B. Operasi Database',
                'steps' => [
                    ['no' => 15, 'text' => 'Uji Semua Koneksi', 'desc' => 'Gunakan tombol "Test All" untuk cek kesehatan semua DB.', 'real_img' => 'real_db_test_all.png'],
                    ['no' => 16, 'text' => 'Konfirmasi Hapus DB', 'desc' => 'Kotak merah menunjukkan tombol konfirmasi penghapusan.', 'real_img' => 'real_db_delete_confirm.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-4-ai',
        'title' => '4. AI INFRASTRUCTURE',
        'icon' => 'fas fa-brain',
        'desc' => 'Manajemen "Otak" sistem meliputi provider, API key, dan model.',
        'sections' => [
            [
                'id' => 'ai-config',
                'title' => '4A. Provider & Key',
                'steps' => [
                    ['no' => 17, 'text' => 'Tambah Provider', 'desc' => 'Daftarkan provider baru (misal: Groq, Anthropic).', 'real_img' => 'real_ai_provider_modal.png'],
                    ['no' => 18, 'text' => 'Tambah API Key', 'desc' => 'Masukkan API Key untuk rotasi otomatis.', 'real_img' => 'real_ai_key_modal.png'],
                    ['no' => 19, 'text' => 'Modal Health Check', 'desc' => 'Uji apakah key masih valid atau sudah limit.', 'real_img' => 'real_ai_health_modal.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-5-roles',
        'title' => '5. ROLE MANAGEMENT',
        'icon' => 'fas fa-user-shield',
        'desc' => 'Mengatur grup hak akses tabel untuk keamanan data.',
        'sections' => [
            [
                'id' => 'role-ops',
                'title' => '5A. Kelola Role',
                'steps' => [
                    ['no' => 20, 'text' => 'Tambah Role Baru', 'desc' => 'Buat nama role dan deskripsinya.', 'real_img' => 'real_role_modal.png'],
                    ['no' => 21, 'text' => 'Konfirmasi Hapus Role', 'desc' => 'Tampilan dialog saat akan menghapus grup akses.', 'real_img' => 'real_role_delete_confirm.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-6-users',
        'title' => '6. USER MANAGEMENT',
        'icon' => 'fas fa-users-cog',
        'desc' => 'Pengaturan mendalam per pengguna, dari akses AI hingga filter data.',
        'sections' => [
            [
                'id' => 'user-base',
                'title' => '6A. Manajemen Akun',
                'steps' => [
                    ['no' => 22, 'text' => 'Modal Tambah User', 'desc' => 'Isikan detail akun dan tetapkan role-nya.', 'real_img' => 'real_user_modal.png'],
                    ['no' => 23, 'text' => 'Konfigurasi AI User', 'desc' => 'Tentukan model apa yang boleh digunakan individu.', 'real_img' => 'real_user_ai_modal.png'],
                    ['no' => 24, 'text' => 'Visual Rule Builder (RLS)', 'desc' => 'Membatasi baris data spesifik yang bisa dilihat AI.', 'real_img' => 'real_user_rls_modal.png'],
                    ['no' => 25, 'text' => 'Konfirmasi Hapus User', 'desc' => 'Peringatan terakhir sebelum menghapus akses user.', 'real_img' => 'real_user_delete_confirm.png'],
                ]
            ]
        ]
    ]
];
@endphp

<style>
    /* Layout */
    .guide-wrap { display: flex; gap: 0; align-items: flex-start; width: 100%; }
    .guide-toc {
        width: 300px; min-width: 300px;
        position: sticky; top: 80px;
        max-height: calc(100vh - 100px);
        overflow-y: auto; background: var(--card-bg);
        border: 1px solid var(--glass-border); border-radius: 14px;
        padding: 1.5rem; box-shadow: var(--shadow-sm);
        margin-right: 1.5rem; flex-shrink: 0;
    }
    .toc-menu-link {
        display: block; color: var(--text-main);
        padding: 10px; border-radius: 8px;
        font-size: 0.95rem; font-weight: 800;
        text-decoration: none; margin-top: 15px;
        background: rgba(99,102,241,0.05);
    }
    .toc-link {
        display: block; color: var(--text-muted);
        padding: 6px 15px; font-size: 0.85rem; font-weight: 600;
        text-decoration: none; border-left: 2px solid var(--glass-border2);
        transition: all 0.2s;
    }
    .toc-link:hover { color: var(--primary); border-left-color: var(--primary); background: rgba(99,102,241,0.03); }

    .guide-content { flex: 1; min-width: 0; }
    .menu-section {
        background: var(--card-bg); border: 1px solid var(--glass-border);
        border-radius: 20px; padding: 2.5rem;
        margin-bottom: 3.5rem; box-shadow: var(--shadow-md);
        scroll-margin-top: 90px;
    }
    .guide-step {
        display: flex; gap: 20px; padding: 2.5rem 0;
        border-bottom: 1px solid var(--glass-border2);
    }
    .step-number {
        width: 48px; height: 48px; min-width: 48px;
        background: #0f172a; color: #ffffff;
        border: 2px solid var(--primary); border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.3rem;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }
    html.dark .step-number { background: var(--primary); border-color: #fff; }

    .screenshot-wrapper {
        margin-top: 1.5rem; border-radius: 14px;
        overflow: hidden; border: 4px solid #ef4444;
        position: relative; background: #000;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .mockup-img { width: 100%; max-height: 650px; object-fit: contain; cursor: zoom-in; }
    .screenshot-badge {
        position: absolute; bottom: 0; left: 0; right: 0;
        padding: 10px; font-size: 0.8rem; font-weight: 800;
        text-align: center; color: white; background: rgba(239, 68, 68, 0.9);
        letter-spacing: 0.5px;
    }
    .img-lightbox { display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.96); align-items:center; justify-content:center; cursor: zoom-out; }
    .img-lightbox.show { display:flex; }
    .img-lightbox img { max-width:98vw; max-height:98vh; border: 4px solid white; border-radius: 8px; }

    .print-btn {
        position: fixed; bottom: 30px; right: 30px; z-index: 1000;
        padding: 12px 25px; border-radius: 50px; font-weight: 800;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
    }
</style>

<div class="img-lightbox" id="imgLightbox" onclick="this.classList.remove('show')">
    <img id="lightboxImg" src="">
</div>

<div class="mb-5 d-flex justify-content-between align-items-center">
    <div>
        <h1 style="color: var(--text-main); font-weight: 900; font-size: 2.5rem; margin:0; letter-spacing:-1px;">Exhaustive System Guide</h1>
        <p class="text-muted mb-0">Dokumentasi operasional lengkap dengan instruksi visual kotak merah.</p>
    </div>
    <button onclick="window.print()" class="btn btn-primary print-btn">
        <i class="fas fa-print me-2"></i> Cetak Dokumen PDF
    </button>
</div>

<div class="guide-wrap">
    <nav class="guide-toc">
        <p class="text-xs fw-bold text-muted text-uppercase mb-3">Navigasi Modul</p>
        @foreach($guideData as $menu)
            <a class="toc-menu-link" href="#{{ $menu['id'] }}">{{ $menu['title'] }}</a>
            @foreach($menu['sections'] as $sec)
                <a class="toc-link" href="#{{ $sec['id'] }}">{{ $sec['title'] }}</a>
            @endforeach
        @endforeach
    </nav>

    <div class="guide-content">
        @foreach($guideData as $menu)
            <section id="{{ $menu['id'] }}" class="menu-section">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="p-3 rounded-xl bg-primary text-white shadow-sm">
                        <i class="{{ $menu['icon'] }} fa-2x"></i>
                    </div>
                    <h2 style="font-weight: 900; margin:0;">{{ $menu['title'] }}</h2>
                </div>
                <p class="text-muted fs-5 mb-4">{{ $menu['desc'] }}</p>

                @foreach($menu['sections'] as $sec)
                    <div id="{{ $sec['id'] }}" style="margin-top: 3rem; scroll-margin-top: 100px;">
                        <h4 style="font-weight: 800; border-left: 5px solid var(--primary); padding-left: 1.25rem; color: var(--text-main);">
                            {{ $sec['title'] }}
                        </h4>
                        @foreach($sec['steps'] as $step)
                            <div class="guide-step">
                                <div class="step-number">{{ $step['no'] }}</div>
                                <div class="flex-grow-1">
                                    <h5 style="font-weight: 700; color: var(--text-main); margin-bottom: 0.75rem;">{{ $step['text'] }}</h5>
                                    <p class="text-muted" style="line-height: 1.7;">{!! $step['desc'] !!}</p>
                                    <div class="screenshot-wrapper" onclick="openLightbox('{{ asset('admin_guide/' . $step['real_img']) }}')">
                                        <img src="{{ asset('admin_guide/' . $step['real_img']) }}" class="mockup-img" onerror="this.src='https://placehold.co/1280x720/1e293b/ef4444?text=Capture+Sedang+Proses'">
                                        <div class="screenshot-badge">IKUTI PETUNJUK KOTAK MERAH — KLIK UNTUK PERBESAR</div>
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
    const lb = document.getElementById('imgLightbox');
    document.getElementById('lightboxImg').src = src;
    lb.classList.add('show');
}
</script>
@endsection
