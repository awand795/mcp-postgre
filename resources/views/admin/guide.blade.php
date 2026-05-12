@extends('layouts.admin')

@section('page-title', 'Panduan Penggunaan Admin Panel')

@section('content')
<style>
    .guide-container {
        max-width: 1000px;
        margin: 0 auto;
    }
    .guide-section {
        margin-bottom: 4rem;
        scroll-margin-top: 2rem;
    }
    .guide-card {
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        margin-top: 1.5rem;
    }
    .guide-header {
        padding: 2rem;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(79, 70, 229, 0.05));
        border-bottom: 1px solid var(--glass-border2);
    }
    .guide-header h2 {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 1.5rem;
        color: var(--primary);
    }
    .guide-body {
        padding: 2rem;
    }
    .guide-step {
        display: flex;
        gap: 20px;
        margin-bottom: 2.5rem;
    }
    .step-number {
        width: 36px;
        height: 36px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    }
    .step-content {
        flex: 1;
    }
    .step-content h3 {
        font-size: 1.1rem;
        margin-bottom: 0.75rem;
        color: var(--text-main);
    }
    .step-content p {
        color: var(--text-muted);
        line-height: 1.6;
        margin-bottom: 1.25rem;
    }
    .screenshot-wrapper {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--glass-border2);
        box-shadow: var(--shadow-sm);
        background: #000;
        position: relative;
    }
    .screenshot-wrapper img {
        width: 100%;
        display: block;
        transition: transform 0.3s;
    }
    .screenshot-wrapper:hover img {
        transform: scale(1.02);
    }
    .badge-info {
        background: rgba(99, 102, 241, 0.1);
        color: var(--primary);
        padding: 4px 12px;
        border-radius: 99px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .nav-guide {
        display: flex;
        gap: 10px;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    .nav-guide a {
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        padding: 0.6rem 1.2rem;
        border-radius: 10px;
        color: var(--text-muted);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.2s;
    }
    .nav-guide a:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
    }
    .back-top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--glass-border);
    }
</style>

<div class="guide-container">
    <div class="back-top-bar">
        <a href="{{ route('chatbot') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Chatbot
        </a>
        <div class="badge-info">PANDUAN ADMINISTRATOR v1.0</div>
    </div>

    <div class="nav-guide">
        <a href="#dashboard"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="#database"><i class="fas fa-database"></i> Database</a>
        <a href="#ai"><i class="fas fa-brain"></i> AI Management</a>
        <a href="#roles"><i class="fas fa-user-shield"></i> Roles</a>
        <a href="#users"><i class="fas fa-users"></i> Users</a>
        <a href="#rls"><i class="fas fa-filter"></i> RLS Filter</a>
    </div>

    <!-- Dashboard -->
    <section class="guide-section" id="dashboard">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-chart-pie"></i> 1. Dashboard Overview</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Memahami Dashboard</h3>
                        <p>Dashboard memberikan ringkasan statistik sistem, termasuk jumlah User, Role, Database Aktif, dan Total Tabel yang terdeteksi.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/dashboard.png') }}" alt="Dashboard">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Database Management -->
    <section class="guide-section" id="database">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-database"></i> 2. Management Database</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Daftar Database</h3>
                        <p>Di sini Anda dapat melihat semua koneksi database yang terdaftar. Anda dapat melihat status koneksi, driver yang digunakan, dan melakukan aksi cepat.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/db_list.png') }}" alt="Database List">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Menambahkan Database Baru</h3>
                        <p>Klik tombol <strong>"Tambah Database"</strong>. Isi detail koneksi seperti Host, Port, Username, dan Password. Anda dapat menentukan schema default (biasanya 'public' untuk PostgreSQL atau 'dbo' untuk SQL Server).</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/db_add.png') }}" alt="Add Database">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- AI Management -->
    <section class="guide-section" id="ai">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-brain"></i> 3. Management AI</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Konfigurasi AI Global</h3>
                        <p>Halaman ini digunakan untuk mengelola Provider (OpenAI, Anthropic, dll), API Keys, dan Model yang tersedia untuk digunakan oleh chatbot.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/ai_list.png') }}" alt="AI List">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Menambah API Key</h3>
                        <p>Klik <strong>"Tambah API Key"</strong>. Masukkan kunci API dari provider terkait. Kunci ini akan digunakan oleh sistem untuk melakukan request ke AI.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/ai_add_key.png') }}" alt="Add AI Key">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Role Management -->
    <section class="guide-section" id="roles">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-user-shield"></i> 4. Management Role & Scope</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Daftar Role</h3>
                        <p>Role menentukan kelompok akses. Setiap user harus memiliki satu role.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/role_list.png') }}" alt="Role List">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Mengatur Cakupan (Scope) Data</h3>
                        <p>Klik ikon perisai (Manage Permissions) pada role tertentu. Di sini Anda menentukan tabel mana saja yang boleh diakses oleh role tersebut. AI hanya akan bisa membaca tabel yang dicentang di sini.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/role_permissions.png') }}" alt="Role Permissions">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- User Management -->
    <section class="guide-section" id="users">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-users"></i> 5. Management User</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Daftar User & Hak Akses</h3>
                        <p>Kelola pengguna sistem. Anda dapat menetapkan Role, serta status <strong>Admin</strong> atau <strong>Super Admin</strong>.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/user_list.png') }}" alt="User List">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Menambah User & Privilege</h3>
                        <p>Saat menambah user, centang <strong>"Is Admin"</strong> jika ingin memberikan akses ke Admin Panel ini. <strong>"Is Super Admin"</strong> memberikan akses penuh tanpa batasan penambahan data.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/user_add.png') }}" alt="Add User">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Konfigurasi AI Spesifik User</h3>
                        <p>Klik ikon otak (AI Config). Di sini Anda bisa membatasi model mana saja yang boleh digunakan oleh user tersebut dan menggunakan API Key spesifik.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/user_ai_config.png') }}" alt="User AI Config">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- RLS Filter -->
    <section class="guide-section" id="rls">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-filter"></i> 6. Row Level Security (RLS) Filter</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Pilih Tabel untuk Filter</h3>
                        <p>Klik ikon filter pada daftar user. Pilih tabel yang ingin dibatasi datanya (misal: hanya melihat transaksi dari toko tertentu).</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/user_rls_select.png') }}" alt="RLS Select Table">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Mengatur Kondisi Filter</h3>
                        <p>Tambahkan aturan filter. Pilih Kolom, Operator, dan masukkan Nilai. Gunakan <strong>"Preview"</strong> untuk memastikan query benar sebelum menyimpan.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/user_rls.png') }}" alt="RLS Filter Rules">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div style="text-align: center; padding: 2rem 0;">
        <p style="color: var(--text-subtle);">Butuh bantuan lebih lanjut? Hubungi tim teknis DarkoAI.</p>
        <a href="#dashboard" class="btn btn-primary" style="margin-top: 1rem;">
            <i class="fas fa-chevron-up"></i> Kembali ke Atas
        </a>
    </div>
</div>
@endsection
