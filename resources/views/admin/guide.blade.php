@extends('layouts.admin')

@section('page-title', 'Panduan Lengkap Administrator (Exhaustive Guide)')

@section('content')
<style>
    .guide-container {
        max-width: 1200px;
        margin: 0 auto;
        font-family: 'Outfit', sans-serif;
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
        font-size: 1.6rem;
        color: var(--primary);
        margin: 0;
    }
    .guide-body {
        padding: 2rem;
    }
    .guide-step {
        display: flex;
        gap: 20px;
        margin-bottom: 3.5rem;
        border-bottom: 1px dashed var(--glass-border2);
        padding-bottom: 2rem;
    }
    .guide-step:last-child {
        margin-bottom: 0;
        border-bottom: none;
        padding-bottom: 0;
    }
    .step-number {
        width: 42px;
        height: 42px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    }
    .step-content {
        flex: 1;
    }
    .step-content h3 {
        font-size: 1.25rem;
        margin-bottom: 1rem;
        color: var(--text-main);
    }
    .step-content p, .step-content li {
        color: var(--text-muted);
        line-height: 1.7;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
    }
    .step-content ul {
        margin-left: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .screenshot-wrapper {
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid rgba(239, 68, 68, 0.3); /* Red tint border for emphasis */
        box-shadow: var(--shadow-md);
        background: var(--bg-secondary);
        position: relative;
        margin-top: 1.5rem;
    }
    .screenshot-wrapper img {
        width: 100%;
        display: block;
        transition: transform 0.3s;
    }
    .screenshot-wrapper:hover img {
        transform: scale(1.02);
    }
    .screenshot-caption {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
        font-weight: 600;
        text-align: center;
        border-top: 1px solid rgba(239, 68, 68, 0.2);
    }
    html.dark .screenshot-caption {
        color: #fca5a5;
    }
    .badge-info {
        background: rgba(99, 102, 241, 0.1);
        color: var(--primary);
        padding: 6px 16px;
        border-radius: 99px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .nav-guide {
        display: flex;
        gap: 12px;
        margin-bottom: 2.5rem;
        flex-wrap: wrap;
    }
    .nav-guide a {
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        padding: 0.8rem 1.5rem;
        border-radius: 12px;
        color: var(--text-muted);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .nav-guide a:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }
    .back-top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--glass-border);
    }
    .highlight-text {
        color: #ef4444; /* Red color matching the screenshot highlights */
        font-weight: 700;
    }
</style>

<div class="guide-container">
    <div class="back-top-bar">
        <a href="{{ route('chatbot') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Chatbot
        </a>
        <div class="badge-info">PANDUAN ADMINISTRATOR LENGKAP v2.0</div>
    </div>

    <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 2rem; text-align: center;">
        Panduan ini menjelaskan <strong>setiap tombol dan fungsi</strong> yang ada di dalam Admin Panel. Perhatikan area yang disorot dengan <span class="highlight-text">kotak merah bercahaya</span> pada setiap gambar.
    </p>

    <div class="nav-guide">
        <a href="#dashboard"><i class="fas fa-chart-pie"></i> 1. Dashboard</a>
        <a href="#database"><i class="fas fa-database"></i> 2. Management Database</a>
        <a href="#ai"><i class="fas fa-brain"></i> 3. Management AI</a>
        <a href="#roles"><i class="fas fa-user-shield"></i> 4. Management Role</a>
        <a href="#users"><i class="fas fa-users"></i> 5. Management User (Termasuk RLS)</a>
    </div>

    <!-- ==========================================
         A. DASHBOARD
         ========================================== -->
    <section class="guide-section" id="dashboard">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-chart-pie"></i> 1. Dashboard Overview</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">1.1</div>
                    <div class="step-content">
                        <h3>Tampilan Utama & Statistik</h3>
                        <p>Halaman ini menampilkan ringkasan jumlah <strong>Total User</strong>, <strong>Role</strong>, <strong>Database Aktif</strong>, dan <strong>Total Tabel</strong> yang terdeteksi oleh sistem.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_dash_clean.png') }}" alt="Dashboard Overview">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">1.2</div>
                    <div class="step-content">
                        <h3>Pengaturan Tema (Light/Dark Mode)</h3>
                        <p>Klik tombol (ikon matahari/bulan) yang disorot <span class="highlight-text">merah</span> di pojok kanan atas untuk mengubah tampilan admin panel dari terang (light) ke gelap (dark), atau sebaliknya.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_dash_theme.png') }}" alt="Theme Toggle">
                            <div class="screenshot-caption">Perhatikan kotak merah pada ikon di pojok kanan atas.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         B. MANAGEMENT DATABASE
         ========================================== -->
    <section class="guide-section" id="database">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-database"></i> 2. Management Database</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">2.1</div>
                    <div class="step-content">
                        <h3>Tombol Aksi Utama (Bagian Atas)</h3>
                        <p>Pada bagian atas halaman Management Database, terdapat tiga tombol utama yang disorot <span class="highlight-text">merah</span>:</p>
                        <ul>
                            <li><strong>Tambah Database:</strong> Membuka formulir untuk mendaftarkan koneksi database baru ke dalam sistem.</li>
                            <li><strong>Test All Connections:</strong> Mengecek kesehatan koneksi ke *semua* database yang terdaftar sekaligus. Sangat berguna untuk memastikan tidak ada server yang down.</li>
                            <li><strong>Clear Cache:</strong> Menghapus cache tabel. Wajib ditekan jika ada perubahan struktur tabel/schema di database asli agar AI dapat membaca struktur terbaru.</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_db_top_actions.png') }}" alt="Database Top Actions">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">2.2</div>
                    <div class="step-content">
                        <h3>Tombol Aksi per Baris Database</h3>
                        <p>Di setiap baris database yang terdaftar, terdapat 4 ikon aksi yang disorot <span class="highlight-text">merah</span> (dari kiri ke kanan):</p>
                        <ul>
                            <li><i class="fas fa-plug text-info"></i> <strong>Test Connection:</strong> Menguji koneksi hanya untuk database spesifik ini.</li>
                            <li><i class="fas fa-sitemap text-primary"></i> <strong>View Schemas:</strong> Melihat daftar schema yang tersedia di dalam database ini.</li>
                            <li><i class="fas fa-edit text-warning"></i> <strong>Edit:</strong> Mengubah pengaturan koneksi database (Host, Port, Username, dll).</li>
                            <li><i class="fas fa-trash text-danger"></i> <strong>Delete:</strong> Menghapus koneksi database dari sistem. (Database default tidak dapat dihapus).</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_db_row_actions.png') }}" alt="Database Row Actions">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">2.3</div>
                    <div class="step-content">
                        <h3>Formulir Tambah/Edit Database</h3>
                        <p>Saat Anda mengklik "Tambah Database" atau ikon "Edit", modal ini akan muncul. Tombol "Simpan" (atau Add Database) disorot <span class="highlight-text">merah</span>.</p>
                        <p><strong>Penjelasan Field Penting:</strong></p>
                        <ul>
                            <li><strong>Nama Koneksi & Kode:</strong> Nama untuk tampilan, dan Kode (harus unik tanpa spasi) untuk identifier sistem.</li>
                            <li><strong>Driver:</strong> Pilih jenis database (PostgreSQL, MySQL, SQL Server, dll).</li>
                            <li><strong>Host, Port, Database, Username, Password:</strong> Kredensial standar untuk mengakses server database.</li>
                            <li><strong>Schema:</strong> Untuk PostgreSQL biasanya diisi `public`, SQL Server `dbo`.</li>
                            <li><strong>Jadikan Default:</strong> Jika dicentang, ini akan menjadi database utama sistem.</li>
                            <li><strong>Aktif:</strong> Jika tidak dicentang, database ini akan diabaikan oleh AI.</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_db_modal_add.png') }}" alt="Database Add Modal">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         C. MANAGEMENT AI
         ========================================== -->
    <section class="guide-section" id="ai">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-brain"></i> 3. Management AI</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">3.1</div>
                    <div class="step-content">
                        <h3>Navigasi Tab (Providers, Keys, Models)</h3>
                        <p>Halaman ini dibagi menjadi 3 tab utama yang disorot <span class="highlight-text">merah</span>. Anda harus mengaturnya secara berurutan: buat Provider dulu, lalu isi API Key, baru daftarkan Modelnya.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_ai_tabs.png') }}" alt="AI Tabs">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">3.2</div>
                    <div class="step-content">
                        <h3>Tab Providers</h3>
                        <p>Klik tombol <strong>"Tambah Provider"</strong> yang disorot <span class="highlight-text">merah</span> untuk menambahkan perusahaan penyedia AI (contoh: OpenAI, Anthropic, Groq). Di tabelnya, Anda juga bisa melakukan Toggle (mematikan sementara) atau Menghapus provider.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_ai_provider_add.png') }}" alt="AI Provider Add">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">3.3</div>
                    <div class="step-content">
                        <h3>Tab API Keys</h3>
                        <p>Klik <strong>"Tambah API Key"</strong> untuk memasukkan kunci rahasia. Di kolom Action (disorot <span class="highlight-text">merah</span>), terdapat tombol:</p>
                        <ul>
                            <li><i class="fas fa-sync"></i> <strong>Reset Limit:</strong> Mengembalikan penghitungan *usage/cost* kunci ini kembali ke nol.</li>
                            <li><i class="fas fa-heartbeat"></i> <strong>Health Check:</strong> Melakukan ping ke server AI untuk memastikan kunci ini valid dan tidak diblokir/habis kuota.</li>
                            <li><i class="fas fa-toggle-on"></i> <strong>Toggle:</strong> Menghidupkan/mematikan kunci.</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_ai_keys_actions.png') }}" alt="AI Keys Actions">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">3.4</div>
                    <div class="step-content">
                        <h3>Tab Models</h3>
                        <p>Klik <strong>"Tambah Model"</strong> (disorot <span class="highlight-text">merah</span>). Di sini Anda mendaftarkan *string identifier* dari model (misal: `gpt-4o`, `llama-3-8b`) dan mengaitkannya dengan Provider yang sudah dibuat.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_ai_models_add.png') }}" alt="AI Models Add">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         D. MANAGEMENT ROLE
         ========================================== -->
    <section class="guide-section" id="roles">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-user-shield"></i> 4. Management Role (Otorisasi)</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">4.1</div>
                    <div class="step-content">
                        <h3>Menambah Role</h3>
                        <p>Klik tombol <strong>"Tambah Role"</strong> (disorot <span class="highlight-text">merah</span>). Role berfungsi sebagai grup. Setiap user nanti akan dimasukkan ke dalam satu Role.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_role_add_btn.png') }}" alt="Role Add Button">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">4.2</div>
                    <div class="step-content">
                        <h3>Tombol Aksi Role</h3>
                        <p>Pada setiap baris Role, perhatikan 3 ikon yang disorot <span class="highlight-text">merah</span>:</p>
                        <ul>
                            <li><i class="fas fa-shield-alt text-primary"></i> <strong>Manage Permissions:</strong> Sangat Krusial. Untuk mengatur tabel apa saja yang boleh diakses oleh Role ini.</li>
                            <li><i class="fas fa-edit text-warning"></i> <strong>Edit:</strong> Mengubah nama dan deskripsi role.</li>
                            <li><i class="fas fa-trash text-danger"></i> <strong>Delete:</strong> Menghapus role.</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_role_row_actions.png') }}" alt="Role Actions">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">4.3</div>
                    <div class="step-content">
                        <h3>Manage Permissions (Cakupan Akses Tabel)</h3>
                        <p>Ketika Anda mengklik ikon perisai (Manage Permissions), modal ini muncul (bagian-bagiannya disorot <span class="highlight-text">merah</span>). Ini adalah inti keamanan data:</p>
                        <ul>
                            <li><strong>Dropdown Pilih Database:</strong> Pilih database mana yang ingin Anda atur hak aksesnya.</li>
                            <li><strong>Daftar Checkbox Tabel:</strong> Centang tabel-tabel yang **diizinkan** untuk dibaca oleh Chatbot bagi user dengan role ini. Jika tidak dicentang, AI tidak akan tahu tabel tersebut ada.</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_role_permissions_modal.png') }}" alt="Role Permissions Modal">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         E. MANAGEMENT USER & RLS
         ========================================== -->
    <section class="guide-section" id="users">
        <div class="guide-card">
            <div class="guide-header">
                <h2><i class="fas fa-users"></i> 5. Management User (Termasuk RLS)</h2>
            </div>
            <div class="guide-body">
                <div class="guide-step">
                    <div class="step-number">5.1</div>
                    <div class="step-content">
                        <h3>Fungsi Utama (Toolbar Atas)</h3>
                        <p>Di bagian atas (disorot <span class="highlight-text">merah</span>), terdapat fungsi untuk mengelola ribuan user:</p>
                        <ul>
                            <li><strong>Pencarian & Filter Role:</strong> Mencari user spesifik.</li>
                            <li><strong>Export & Import Excel:</strong> Untuk memasukkan user massal menggunakan Excel. Download <strong>Template</strong> terlebih dahulu.</li>
                            <li><strong>Tambah User:</strong> Membuka form pendaftaran user manual.</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_user_top_actions.png') }}" alt="User Top Actions">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">5.2</div>
                    <div class="step-content">
                        <h3>Tambah/Edit User & Privilege</h3>
                        <p>Saat menambah/mengedit user, perhatikan 3 checkbox penting yang disorot <span class="highlight-text">merah</span>:</p>
                        <ul>
                            <li><strong>Is Admin:</strong> Memberikan hak masuk ke halaman Admin Panel ini, namun haknya terbatas (hanya bisa melihat data/user/database yang ia buat sendiri).</li>
                            <li><strong>Is Super Admin:</strong> Memberikan akses "Dewa". Bisa melihat dan mengubah seluruh pengaturan sistem dan seluruh data dari admin lain.</li>
                            <li><strong>Analysis Scope Limited:</strong> Membatasi agar AI tidak melakukan query analisis berat lintas tabel, guna menghemat biaya token API.</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_user_add_modal.png') }}" alt="User Add Modal">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">5.3</div>
                    <div class="step-content">
                        <h3>Tombol Aksi Spesifik User</h3>
                        <p>Di tabel user, terdapat hingga 5 tombol ikon aksi (disorot <span class="highlight-text">merah</span>):</p>
                        <ul>
                            <li><i class="fas fa-edit text-warning"></i> <strong>Edit:</strong> Mengubah data dasar user.</li>
                            <li><i class="fas fa-trash text-danger"></i> <strong>Delete:</strong> Menghapus user.</li>
                            <li><i class="fas fa-brain text-info"></i> <strong>AI Config:</strong> Mengatur model dan API key mana yang boleh dipakai user ini.</li>
                            <li><i class="fas fa-filter text-success"></i> <strong>Set Filter (RLS):</strong> Sangat penting! Untuk membatasi baris data mana yang boleh dilihat user.</li>
                            <li><i class="fas fa-key text-primary"></i> <strong>MCP Token:</strong> (Jika ada) Untuk meng-generate token integrasi eksternal.</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_user_row_actions.png') }}" alt="User Row Actions">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">5.4</div>
                    <div class="step-content">
                        <h3>Modal AI Config</h3>
                        <p>Setelah mengklik ikon Otak, Anda akan melihat modal ini. Anda <strong>wajib</strong> mencentang (menyorot <span class="highlight-text">merah</span>) Model dan API Key mana yang dialokasikan untuk user tersebut. Jika kosong, user tidak bisa chatting.</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_user_ai_config_modal.png') }}" alt="User AI Config">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">5.5</div>
                    <div class="step-content">
                        <h3>Set Filter RLS: Pemilihan Tabel</h3>
                        <p>Klik ikon Filter. Di sisi kiri modal, Anda akan melihat daftar semua tabel yang boleh diakses user (berdasarkan Role-nya). Klik pada nama tabel (disorot <span class="highlight-text">merah</span>) untuk mulai membuat aturan filter (Row-Level Security).</p>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_user_rls_table_select.png') }}" alt="User RLS Table Select">
                        </div>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">5.6</div>
                    <div class="step-content">
                        <h3>Set Filter RLS: Rule Builder (Pembuat Aturan)</h3>
                        <p>Ini adalah fitur paling canggih untuk membatasi data per user (misal: Sales A hanya boleh melihat transaksi cabang A). Perhatikan elemen yang disorot <span class="highlight-text">merah</span>:</p>
                        <ul>
                            <li><strong>Kolom:</strong> Pilih nama kolom di database (misal: `id_cabang`).</li>
                            <li><strong>Operator:</strong> Pilih logika (Sama Dengan `=`, Tidak Sama `!=`, `LIKE`, dll).</li>
                            <li><strong>Nilai:</strong> Masukkan nilai filter (misal: `CAB-01`).</li>
                            <li><strong>Logika (AND/OR):</strong> Jika memiliki lebih dari 1 aturan, gunakan ini untuk menghubungkannya.</li>
                            <li><strong>Tombol Tambah Kondisi:</strong> Untuk menambah aturan berlapis.</li>
                            <li><strong>Tombol Preview:</strong> <strong>SANGAT PENTING.</strong> Selalu klik Preview untuk menguji apakah kueri filter berjalan sukses di database dan melihat 5 sampel datanya, sebelum Anda klik Simpan.</li>
                            <li><strong>Tombol Salin (Copy):</strong> Untuk mempercepat pekerjaan, Anda bisa menyalin semua filter dari user lain yang sudah di-setting.</li>
                        </ul>
                        <div class="screenshot-wrapper">
                            <img src="{{ asset('admin_guide/v2_user_rls_rule_builder.png') }}" alt="User RLS Rule Builder">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div style="text-align: center; padding: 2rem 0; margin-top: 2rem; border-top: 1px solid var(--glass-border);">
        <p style="color: var(--text-subtle); font-size: 1.1rem; margin-bottom: 1rem;">Anda telah mencapai akhir dari Panduan Lengkap.</p>
        <a href="#dashboard" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1rem;">
            <i class="fas fa-chevron-up"></i> Kembali ke Paling Atas
        </a>
    </div>
</div>
@endsection
