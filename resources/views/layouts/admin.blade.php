<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Dashboard - darkotech AI</title>
    <script>
        (function() {
            const theme = localStorage.getItem('theme');
            const isDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) {
                document.documentElement.classList.add('dark');
                const s = document.createElement('style');
                s.id = 'fouc-fix';
                s.innerHTML = 'html,body{background:#0b1120!important;color:#f1f5f9!important;}';
                document.head.appendChild(s);
            }
        })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ── Design Tokens ── */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            /* Light theme — high-contrast, professional */
            --bg-main:       #f1f3fb;
            --bg-secondary:  #e8ecf8;
            --card-bg:       #ffffff;
            --card-hover:    #fafbff;
            --glass-border:  rgba(99,102,241,0.22);
            --glass-border2: rgba(99,102,241,0.12);
            --text-main:     #0f172a;
            --text-muted:    #475569;
            --text-subtle:   #94a3b8;
            --sidebar-bg:    #ffffff;
            --input-bg:      #ffffff;
            --input-border:  rgba(99,102,241,0.28);
            --shadow-sm:     0 2px 8px rgba(99,102,241,0.10);
            --shadow-md:     0 4px 20px rgba(99,102,241,0.13);
            --shadow-lg:     0 8px 32px rgba(99,102,241,0.16);
            /* Light theme extras */
            --table-head-bg: #ededff;
            --table-head-color: #3730a3;
            --table-row-hover: rgba(99,102,241,0.06);
            --table-border:  rgba(99,102,241,0.13);
            --badge-bg:      #f0f0ff;
            --input-text:    #0f172a;
            --select-bg:     #ffffff;
        }
        html.dark {
            --bg-main:       #0b1120;
            --bg-secondary:  #111827;
            --card-bg:       rgba(17,24,39,0.85);
            --card-hover:    rgba(30,41,59,0.95);
            --glass-border:  rgba(99,102,241,0.2);
            --glass-border2: rgba(255,255,255,0.06);
            --text-main:     #f1f5f9;
            --text-muted:    #94a3b8;
            --text-subtle:   #64748b;
            --sidebar-bg:    rgba(11,17,32,0.98);
            --input-bg:      rgba(15,23,42,0.8);
            --input-border:  rgba(99,102,241,0.3);
            --shadow-sm:     0 2px 8px rgba(0,0,0,0.3);
            --shadow-md:     0 4px 20px rgba(0,0,0,0.4);
            --shadow-lg:     0 8px 32px rgba(0,0,0,0.5);
            --table-head-bg: rgba(99,102,241,0.13);
            --table-head-color: #a5b4fc;
            --table-row-hover: rgba(99,102,241,0.06);
            --table-border:  rgba(255,255,255,0.06);
            --badge-bg:      rgba(99,102,241,0.15);
            --input-text:    #f1f5f9;
            --select-bg:     rgba(15,23,42,0.8);
        }

        *{margin:0;padding:0;box-sizing:border-box;font-family:'Outfit',sans-serif;}

        body {
            background: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            transition: background 0.4s, color 0.3s;
        }
        body {
            background: linear-gradient(145deg, #f0f2fc 0%, #eef0fb 60%, #ebe8ff 100%);
        }
        html.dark body {
            background: linear-gradient(135deg, #0b1120 0%, #0f172a 60%, #111827 100%);
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 255px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--glass-border);
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
            box-shadow: 4px 0 30px rgba(99,102,241,0.07);
        }
        html.dark .sidebar {
            box-shadow: 4px 0 30px rgba(0,0,0,0.4);
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.5rem 0.75rem 2rem;
        }
        .sidebar-brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1rem;
            box-shadow: 0 4px 12px rgba(99,102,241,0.35);
            flex-shrink: 0;
        }
        .sidebar-brand-text {
            font-size: 1rem; font-weight: 700;
            color: var(--text-main);
            line-height: 1.2;
        }
        .sidebar-brand-sub {
            font-size: 0.65rem; color: var(--text-subtle);
            font-weight: 400;
        }
        .nav-section-label {
            font-size: 0.62rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--text-subtle); padding: 0 0.75rem;
            margin: 1.2rem 0 0.5rem;
        }
        .nav-links { list-style: none; flex: 1; }
        .nav-links li { margin-bottom: 2px; }
        .nav-links a {
            display: flex; align-items: center; gap: 10px;
            padding: 0.7rem 0.75rem;
            color: var(--text-muted);
            text-decoration: none; border-radius: 10px;
            font-size: 0.88rem; font-weight: 500;
            transition: all 0.2s;
            position: relative;
        }
        .nav-links a i { width: 18px; text-align: center; font-size: 0.85rem; }
        .nav-links a:hover {
            background: rgba(99,102,241,0.08);
            color: var(--primary);
        }
        .nav-links a.active {
            background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(79,70,229,0.08));
            color: var(--primary);
            font-weight: 600;
            border: 1px solid rgba(99,102,241,0.15);
        }
        .nav-links a.active::before {
            content: '';
            position: absolute; left: 0; top: 20%; bottom: 20%;
            width: 3px; border-radius: 0 3px 3px 0;
            background: var(--primary);
        }
        .nav-divider {
            height: 1px;
            background: var(--glass-border2);
            margin: 1rem 0;
        }
        .logout-btn {
            display: flex; align-items: center; gap: 10px;
            padding: 0.7rem 0.75rem;
            color: #ef4444; text-decoration: none;
            border-radius: 10px; font-size: 0.88rem; font-weight: 500;
            transition: all 0.2s; margin-top: 0.5rem;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.1); }

        /* ── Main Content ── */
        .main-content {
            flex: 1; padding: 1.5rem;
            overflow-y: auto;
            margin-left: 255px;
            transition: margin-left 0.3s;
            width: calc(100% - 255px);
            min-height: 100vh;
        }

        /* ── Top Header Bar ── */
        .top-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.75rem; gap: 1rem; flex-wrap: wrap;
        }
        .top-header-left { display: flex; align-items: center; gap: 12px; }
        .top-header-title h1 {
            font-size: 1.35rem; font-weight: 700; color: var(--text-main);
            line-height: 1.2;
        }
        .top-header-title p { font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }
        .top-header-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .mobile-menu-toggle {
            display: none;
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            padding: 0.55rem 0.8rem;
            border-radius: 10px; cursor: pointer;
            font-size: 1rem; transition: all 0.2s;
            box-shadow: var(--shadow-sm);
        }
        .mobile-menu-toggle:hover { background: rgba(99,102,241,0.1); color: var(--primary); }

        /* ── Theme Toggle ── */
        .theme-switch-wrap {
            display: flex; align-items: center; gap: 7px;
            cursor: pointer; user-select: none;
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 10px; padding: 0.45rem 0.8rem;
            transition: all 0.2s; box-shadow: var(--shadow-sm);
        }
        .theme-switch-wrap:hover { border-color: var(--primary); }
        .theme-switch-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
        .theme-switch-track {
            width: 38px; height: 20px; border-radius: 999px;
            background: #cbd5e1; border: 1px solid rgba(0,0,0,0.08);
            position: relative; transition: background 0.3s; flex-shrink: 0;
        }
        html.dark .theme-switch-track { background: var(--primary); border-color: rgba(99,102,241,0.4); }
        .theme-switch-thumb {
            width: 14px; height: 14px; border-radius: 50%;
            background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.25);
            position: absolute; top: 2px; left: 2px;
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            display: flex; align-items: center; justify-content: center;
            font-size: 7px; color: #f59e0b;
        }
        html.dark .theme-switch-thumb { transform: translateX(18px); color: #818cf8; }

        /* ── User Chip ── */
        .user-chip {
            display: flex; align-items: center; gap: 8px;
            padding: 0.45rem 0.9rem;
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 10px; box-shadow: var(--shadow-sm);
        }
        .user-chip i { color: var(--primary); font-size: 1rem; }
        .user-chip span { font-size: 0.78rem; color: var(--text-main); font-weight: 500; }

        /* ── Glass Card ── */
        .glass-card {
            background: var(--card-bg);
            border: 1.5px solid var(--glass-border2);
            border-radius: 18px; padding: 1.75rem;
            box-shadow: var(--shadow-md);
            transition: background 0.3s, border-color 0.3s;
        }

        /* ── Buttons ── */
        .btn {
            padding: 0.65rem 1.25rem; border-radius: 10px; border: none;
            cursor: pointer; font-weight: 600; transition: all 0.25s;
            text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center;
            gap: 7px; white-space: nowrap; font-size: 0.875rem;
            font-family: 'Outfit', sans-serif;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; box-shadow: 0 4px 12px rgba(99,102,241,0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); filter: brightness(1.08); }
        .btn-success { background: rgba(16,185,129,0.12); color: #10b981; border: 1px solid rgba(16,185,129,0.25); }
        .btn-success:hover { background: rgba(16,185,129,0.22); transform: translateY(-1px); }
        .btn-info { background: rgba(6,182,212,0.12); color: #06b6d4; border: 1px solid rgba(6,182,212,0.25); }
        .btn-info:hover { background: rgba(6,182,212,0.22); transform: translateY(-1px); }
        .btn-secondary { background: rgba(148,163,184,0.1); color: var(--text-muted); border: 1px solid var(--glass-border2); }
        .btn-secondary:hover { background: rgba(148,163,184,0.18); color: var(--text-main); transform: translateY(-1px); }
        .btn-danger, .btn-delete { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
        .btn-danger:hover, .btn-delete:hover { background: rgba(239,68,68,0.2); transform: translateY(-1px); }
        .btn-cancel { background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--glass-border2); }
        .btn-cancel:hover { background: var(--card-hover); color: var(--text-main); transform: translateY(-1px); }
        .btn-edit { background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.2); }
        .btn-edit:hover { background: rgba(245,158,11,0.2); transform: translateY(-1px); }

        /* ── Status Badges ── */
        .status-yes, .status-success { background: rgba(16,185,129,0.12); color: #059669; padding: 0.3rem 0.75rem; border-radius: 8px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; border: 1.5px solid rgba(16,185,129,0.3); }
        .status-no, .status-error, .status-failed { background: rgba(239,68,68,0.1); color: #dc2626; padding: 0.3rem 0.75rem; border-radius: 8px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; border: 1.5px solid rgba(239,68,68,0.3); }
        .status-pending, .status-warning { background: rgba(245,158,11,0.1); color: #d97706; padding: 0.3rem 0.75rem; border-radius: 8px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; border: 1.5px solid rgba(245,158,11,0.3); }
        .role-badge { background: rgba(99,102,241,0.12); color: #4338ca; padding: 0.3rem 0.75rem; border-radius: 8px; font-size: 0.8rem; font-weight: 700; border: 1.5px solid rgba(99,102,241,0.25); }
        html.dark .status-yes, html.dark .status-success { color: #10b981; border-color: rgba(16,185,129,0.2); }
        html.dark .status-no, html.dark .status-error, html.dark .status-failed { color: #ef4444; border-color: rgba(239,68,68,0.2); }
        html.dark .status-pending, html.dark .status-warning { color: #f59e0b; border-color: rgba(245,158,11,0.2); }
        html.dark .role-badge { color: var(--primary-light); border-color: rgba(99,102,241,0.15); background: rgba(99,102,241,0.1); }

        /* ── Filter Form ── */
        .filter-card { margin-bottom: 1.5rem; padding: 1.25rem !important; }
        .filter-form { display: flex; gap: 1.25rem; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label { display: block; margin-bottom: 0.5rem; font-size: 0.78rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
        .filter-group input, .filter-group select, .filter-group textarea {
            width: 100%; background: var(--input-bg);
            border: 1.5px solid var(--input-border);
            padding: 0.7rem 0.9rem; border-radius: 10px;
            color: var(--input-text); transition: all 0.2s;
            font-size: 0.88rem; font-family: 'Outfit', sans-serif;
            appearance: auto; box-shadow: inset 0 1px 3px rgba(99,102,241,0.06);
        }
        .filter-group input::placeholder, .filter-group textarea::placeholder { color: var(--text-subtle); }
        .filter-group input:focus, .filter-group select:focus, .filter-group textarea:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        .filter-actions { display: flex; gap: 0.75rem; }

        /* ── Tables ── */
        .table-responsive { overflow-x: auto; border-radius: 12px; }
        table.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        table.data-table thead tr { background: var(--table-head-bg); }
        table.data-table th { padding: 0.85rem 1rem; text-align: left; font-weight: 700; color: var(--table-head-color); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid var(--glass-border); white-space: nowrap; }
        table.data-table td { padding: 0.85rem 1rem; border-bottom: 1px solid var(--table-border); color: var(--text-main); vertical-align: middle; }
        table.data-table tbody tr:last-child td { border-bottom: none; }
        table.data-table tbody tr:hover { background: var(--table-row-hover); }
        html.dark table.data-table thead tr { background: var(--table-head-bg); }
        html.dark table.data-table tbody tr:hover { background: var(--table-row-hover); }

        /* ── Alert ── */
        .alert { padding: 0.9rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border: 1.5px solid transparent; font-size: 0.875rem; font-weight: 500; }
        .alert-success { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.35); color: #047857; }
        .alert-error, .alert-danger { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.35); color: #b91c1c; }
        .alert-warning { background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.35); color: #b45309; }
        .alert-info { background: rgba(99,102,241,0.1); border-color: rgba(99,102,241,0.35); color: #4338ca; }
        html.dark .alert-success { color: #10b981; border-color: rgba(16,185,129,0.25); }
        html.dark .alert-error, html.dark .alert-danger { color: #ef4444; border-color: rgba(239,68,68,0.25); }
        html.dark .alert-warning { color: #f59e0b; border-color: rgba(245,158,11,0.25); }
        html.dark .alert-info { color: var(--primary-light); border-color: rgba(99,102,241,0.25); }

        /* ── Sidebar Overlay ── */
        .sidebar-overlay {
            display: none; position: fixed;
            inset: 0; background: rgba(0,0,0,0.55);
            z-index: 999; backdrop-filter: blur(2px);
        }
        .sidebar-overlay.active { display: block; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(99,102,241,0.4); }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .mobile-menu-toggle { display: flex; align-items: center; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .glass-card { padding: 1.25rem; border-radius: 14px; }
            .top-header { margin-bottom: 1.25rem; }
            .top-header-title h1 { font-size: 1.1rem; }
            .user-chip span { display: none; }
        }
        @media (max-width: 480px) {
            .sidebar-brand-text { display: none; }
            .theme-switch-label { display: none; }
        }

        /* ── Legacy .header class compat ── */
        .header { display: none !important; }
    </style>
</head>

<body>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><i class="fas fa-robot"></i></div>
            <div class="sidebar-brand-text">
                Darko AI<br>
                <span class="sidebar-brand-sub">Admin Panel</span>
            </div>
        </div>

        <p class="nav-section-label">Menu Utama</p>
        <ul class="nav-links">
            <li><a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-chart-pie"></i><span>Dashboard</span></a></li>
            <li><a href="{{ route('admin.databases') }}" class="{{ request()->routeIs('admin.databases') ? 'active' : '' }}">
                <i class="fas fa-database"></i><span>Management Database</span></a></li>
            <li><a href="{{ route('admin.ai_management') }}" class="{{ request()->routeIs('admin.ai_management') ? 'active' : '' }}">
                <i class="fas fa-brain"></i><span>Management AI</span></a></li>
            <li><a href="{{ route('admin.roles') }}" class="{{ request()->routeIs('admin.roles') ? 'active' : '' }}">
                <i class="fas fa-user-shield"></i><span>Management Role</span></a></li>
            <li><a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active' : '' }}">
                <i class="fas fa-users"></i><span>Management User</span></a></li>
        </ul>

        <div class="nav-divider"></div>
        <p class="nav-section-label">Lainnya</p>
        <ul class="nav-links">
            <li><a href="{{ route('chatbot') }}"><i class="fas fa-comment-dots"></i><span>Kembali ke Chatbot</span></a></li>
        </ul>

        <div class="nav-divider"></div>
        <form action="{{ route('logout') }}" method="POST" id="logout-form">@csrf
            <a href="#" class="logout-btn" onclick="document.getElementById('logout-form').submit();">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </form>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="top-header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <div class="top-header-title">
                    <h1>@yield('page-title', 'Admin Panel')</h1>
                    <p>Darko AI Management System</p>
                </div>
            </div>
            <div class="top-header-right">
                <div class="theme-switch-wrap" onclick="toggleTheme()" title="Toggle Theme">
                    <span class="theme-switch-label">☀</span>
                    <div class="theme-switch-track">
                        <div class="theme-switch-thumb" id="theme-thumb">
                            <i class="fas fa-sun" id="theme-icon"></i>
                        </div>
                    </div>
                    <span class="theme-switch-label">☾</span>
                </div>
                <div class="user-chip">
                    <i class="fas fa-user-circle"></i>
                    <span>{{ auth()->user()->name ?? 'Admin' }}</span>
                </div>
            </div>
        </div>

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @yield('scripts')
    <script>
        function toggleTheme(){
            const dark=document.documentElement.classList.contains('dark');
            if(dark){
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme','light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme','dark');
            }
            updateThemeIcon();
        }
        function updateThemeIcon(){
            const icon=document.getElementById('theme-icon');
            if(!icon) return;
            const dark=document.documentElement.classList.contains('dark');
            icon.className=dark?'fas fa-moon':'fas fa-sun';
        }
        document.addEventListener('DOMContentLoaded', function() {
            // Remove FOUC fix now that styles have loaded
            const foucFix = document.getElementById('fouc-fix');
            if (foucFix) foucFix.remove();
            updateThemeIcon();
        });

        function toggleSidebar(){
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }
        document.querySelectorAll('.nav-links a').forEach(l=>l.addEventListener('click',()=>{
            if(window.innerWidth<=1024) toggleSidebar();
        }));
    </script>
</body>
</html>