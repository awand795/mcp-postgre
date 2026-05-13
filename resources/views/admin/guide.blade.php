@extends('layouts.admin')

@section('page-title', 'Panduan Administrator (Full System Guide)')

@section('content')
@php
$guideData = [
    [
        'id' => 'menu-1-dashboard',
        'title' => '1. DASHBOARD OVERVIEW',
        'icon' => 'fas fa-chart-pie',
        'desc' => 'Dashboard adalah pusat informasi statistik sistem secara real-time.',
        'sections' => [
            [
                'id' => 'dash-main',
                'title' => '1A. Tampilan Utama',
                'steps' => [
                    ['no' => 1, 'text' => 'Statistik Sistem', 'desc' => 'Melihat jumlah user, database, dan AI provider aktif.', 'real_img' => 'real_dashboard.png'],
                    ['no' => 2, 'text' => 'Navigasi Sidebar', 'desc' => 'Gunakan menu kiri untuk berpindah modul sesuai urutan panduan ini.', 'real_img' => 'real_sidebar.png'],
                    ['no' => 3, 'text' => 'Dark Mode Toggle', 'desc' => 'Klik ikon matahari/bulan untuk mengganti tema.', 'real_img' => 'real_dash_darkmode.png'],
                    ['no' => 4, 'text' => 'Contoh Tema Gelap', 'desc' => 'Visualisasi dashboard saat menggunakan mode malam.', 'real_img' => 'real_dashboard_dark.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-2-db',
        'title' => '2. DATABASE MANAGEMENT',
        'icon' => 'fas fa-database',
        'desc' => 'Menghubungkan sistem ke sumber data eksternal (PostgreSQL, MySQL, MariaDB, dll).',
        'sections' => [
            [
                'id' => 'db-list',
                'title' => '2A. Daftar & Koneksi',
                'steps' => [
                    ['no' => 5, 'text' => 'Grid Database', 'desc' => 'Daftar koneksi yang terdaftar beserta status koneksinya.', 'real_img' => 'real_db_list.png'],
                    ['no' => 6, 'text' => 'Tambah Database (Step 1)', 'desc' => 'Masukkan nama alias dan pilih jenis driver database.', 'real_img' => 'real_db_modal_step1.png'],
                    ['no' => 7, 'text' => 'Tambah Database (Step 2)', 'desc' => 'Isikan detail host, port, username, dan password server.', 'real_img' => 'real_db_modal_step2.png'],
                    ['no' => 8, 'text' => 'Tambah Database (Step 3)', 'desc' => 'Pilih schema (untuk PGSQL) dan uji koneksi sebelum simpan.', 'real_img' => 'real_db_modal_step3.png'],
                ]
            ],
            [
                'id' => 'db-actions',
                'title' => '2B. Aksi & Penghapusan',
                'steps' => [
                    ['no' => 9, 'text' => 'Edit Database', 'desc' => 'Mengubah kredensial database yang sudah ada.', 'real_img' => 'real_db_list.png'],
                    ['no' => 10, 'text' => 'Hapus Database', 'desc' => 'Konfirmasi penghapusan koneksi database dari sistem.', 'real_img' => 'real_db_delete_confirm.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-3-ai',
        'title' => '3. AI MANAGEMENT',
        'icon' => 'fas fa-brain',
        'desc' => 'Mengatur infrastruktur AI, API Key, dan Model yang tersedia.',
        'sections' => [
            [
                'id' => 'ai-prov',
                'title' => '3A. Provider & Key',
                'steps' => [
                    ['no' => 11, 'text' => 'Overview AI', 'desc' => 'Statistik penggunaan dan daftar provider AI.', 'real_img' => 'real_ai_management.png'],
                    ['no' => 12, 'text' => 'Tambah Provider Baru', 'desc' => 'Daftarkan penyedia layanan AI (Custom/Built-in).', 'real_img' => 'real_ai_provider_modal.png'],
                    ['no' => 13, 'text' => 'Tambah API Key', 'desc' => 'Masukkan token rahasia dari dashboard provider.', 'real_img' => 'real_ai_key_modal.png'],
                ]
            ],
            [
                'id' => 'ai-health',
                'title' => '3B. Health Check & Model',
                'steps' => [
                    ['no' => 14, 'text' => 'Health Check API', 'desc' => 'Uji validitas key secara berkala agar chatbot tidak error.', 'real_img' => 'real_ai_health_modal.png'],
                    ['no' => 15, 'text' => 'Tambah Model AI', 'desc' => 'Daftarkan ID model terbaru (misal: gpt-4o).', 'real_img' => 'real_ai_model_modal.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-4-roles',
        'title' => '4. ROLE MANAGEMENT',
        'icon' => 'fas fa-user-shield',
        'desc' => 'Mengatur grup akses tabel database bagi pengguna.',
        'sections' => [
            [
                'id' => 'role-manage',
                'title' => '4A. Pembuatan Role',
                'steps' => [
                    ['no' => 16, 'text' => 'Daftar Role', 'desc' => 'List grup akses yang sudah dibuat.', 'real_img' => 'real_role_list.png'],
                    ['no' => 17, 'text' => 'Form Tambah Role', 'desc' => 'Buat nama role baru (misal: Staff Keuangan).', 'real_img' => 'real_role_modal.png'],
                    ['no' => 18, 'text' => 'Hapus Role', 'desc' => 'Konfirmasi sebelum mencabut akses seluruh grup.', 'real_img' => 'real_role_delete_confirm.png'],
                ]
            ]
        ]
    ],
    [
        'id' => 'menu-5-users',
        'title' => '5. USER MANAGEMENT',
        'icon' => 'fas fa-users',
        'desc' => 'Modul paling detail untuk mengatur hak akses individu pengguna.',
        'sections' => [
            [
                'id' => 'user-add',
                'title' => '5A. Pengelolaan User',
                'steps' => [
                    ['no' => 19, 'text' => 'Daftar User', 'desc' => 'Tabel seluruh pengguna terdaftar.', 'real_img' => 'real_user_list.png'],
                    ['no' => 20, 'text' => 'Form Tambah User', 'desc' => 'Isikan Nama, Email, Password, dan pilih Role.', 'real_img' => 'real_user_modal.png'],
                    ['no' => 21, 'text' => 'Konfigurasi AI', 'desc' => 'Pilih model dan API Key mana yang boleh dipakai user ini.', 'real_img' => 'real_user_ai_modal.png'],
                    ['no' => 22, 'text' => 'Row Level Security (RLS)', 'desc' => 'Batasi baris data yang bisa dibaca AI per user.', 'real_img' => 'real_user_rls_modal.png'],
                    ['no' => 23, 'text' => 'Hapus User', 'desc' => 'Cabut total akses user dari sistem.', 'real_img' => 'real_user_delete_confirm.png'],
                ]
            ]
        ]
    ]
];
@endphp

<style>
    /* CSS Layout */
    .guide-wrap { display: flex; gap: 0; align-items: flex-start; width: 100%; }
    .guide-toc {
        width: 280px; min-width: 280px;
        position: sticky; top: 80px;
        max-height: calc(100vh - 100px);
        overflow-y: auto; background: var(--card-bg);
        border: 1px solid var(--glass-border); border-radius: 14px;
        padding: 1.25rem; box-shadow: var(--shadow-sm);
        margin-right: 1.5rem; flex-shrink: 0;
    }
    .toc-menu-link {
        display: block; color: var(--text-main);
        padding: 8px 10px; border-radius: 8px;
        font-size: 0.9rem; font-weight: 700;
        text-decoration: none; margin-top: 10px;
    }
    .toc-link {
        display: block; color: var(--text-muted);
        padding: 5px 12px; font-size: 0.82rem;
        text-decoration: none; border-left: 2px solid var(--glass-border2);
    }
    .toc-link:hover { color: var(--primary); border-left-color: var(--primary); }

    .guide-content { flex: 1; min-width: 0; }
    .menu-section {
        background: var(--card-bg); border: 1px solid var(--glass-border);
        border-radius: 20px; padding: 2.5rem;
        margin-bottom: 3rem; box-shadow: var(--shadow-md);
        scroll-margin-top: 90px;
    }
    .guide-step {
        display: flex; gap: 20px; padding: 2rem 0;
        border-bottom: 1px solid var(--glass-border2);
    }
    .step-number {
        width: 44px; height: 44px; min-width: 44px;
        background: #1e293b; color: #ffffff;
        border: 2px solid var(--primary); border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.2rem;
    }
    html.dark .step-number { background: var(--primary); border-color: #fff; }

    .screenshot-wrapper {
        margin-top: 1.5rem; border-radius: 12px;
        overflow: hidden; border: 3px solid rgba(239, 68, 68, 0.4);
        position: relative; background: #000;
    }
    .mockup-img { width: 100%; max-height: 600px; object-fit: contain; cursor: zoom-in; }
    .screenshot-badge {
        position: absolute; bottom: 0; left: 0; right: 0;
        padding: 8px; font-size: 0.75rem; font-weight: 700;
        text-align: center; color: white; background: rgba(239, 68, 68, 0.8);
    }
    .img-lightbox { display:none; position:fixed; z-index:99999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); align-items:center; justify-content:center; }
    .img-lightbox.show { display:flex; }
    .img-lightbox img { max-width:98vw; max-height:98vh; border: 4px solid white; }
</style>

<div class="img-lightbox" id="imgLightbox" onclick="this.classList.remove('show')">
    <img id="lightboxImg" src="">
</div>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <h1 style="color: var(--text-main); font-weight: 800; font-size: 2.2rem; margin:0;">Exhaustive Admin Guide</h1>
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i> Cetak PDF</button>
</div>

<div class="guide-wrap">
    <nav class="guide-toc">
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
                <h2 style="color: var(--primary); font-weight: 800; margin-bottom: 1rem;">
                    <i class="{{ $menu['icon'] }} me-2"></i>{{ $menu['title'] }}
                </h2>
                <p class="text-muted mb-4">{{ $menu['desc'] }}</p>

                @foreach($menu['sections'] as $sec)
                    <div id="{{ $sec['id'] }}" style="margin-top: 2rem; scroll-margin-top: 100px;">
                        <h4 style="font-weight: 700; border-left: 4px solid var(--primary); padding-left: 1rem;">{{ $sec['title'] }}</h4>
                        @foreach($sec['steps'] as $step)
                            <div class="guide-step">
                                <div class="step-number">{{ $step['no'] }}</div>
                                <div class="flex-grow-1">
                                    <h5 style="font-weight: 700; margin-bottom: 0.5rem;">{{ $step['text'] }}</h5>
                                    <p class="text-muted">{!! $step['desc'] !!}</p>
                                    <div class="screenshot-wrapper" onclick="openLightbox('{{ asset('admin_guide/' . $step['real_img']) }}')">
                                        <img src="{{ asset('admin_guide/' . $step['real_img']) }}" class="mockup-img" onerror="this.src='https://placehold.co/1280x720/1e293b/ef4444?text=Capture+In+Progress'">
                                        <div class="screenshot-badge">LIHAT KOTAK MERAH — KLIK UNTUK PERBESAR</div>
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
</script>
@endsection
