<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Dashboard - Darko AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg-main: #f0f2f8;
            --bg-secondary: #e2e6f0;
            --card-bg: rgba(255, 255, 255, 0.92);
            --glass-border: rgba(99, 102, 241, 0.12);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --sidebar-bg: rgba(255,255,255,0.97);
            --input-bg: rgba(255,255,255,0.9);
        }

        html.dark {
            --bg-main: #0f172a;
            --bg-secondary: #1e293b;
            --card-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --sidebar-bg: rgba(15, 23, 42, 0.98);
            --input-bg: rgba(15, 23, 42, 0.6);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: radial-gradient(135deg, var(--bg-secondary) 0%, var(--bg-main) 60%, var(--bg-secondary) 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            transition: background 0.4s ease, color 0.3s ease;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            backdrop-filter: blur(16px);
            border-right: 1px solid var(--glass-border);
            padding: 1.5rem 1.25rem;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
            box-shadow: 4px 0 24px rgba(99,102,241,0.06);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
        }

        .nav-links {
            list-style: none;
            flex: 1;
        }

        .nav-links li {
            margin-bottom: 0.5rem;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.8rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(99, 102, 241, 0.1);
            color: var(--text-main);
        }

        .nav-links a.active i {
            color: var(--primary);
        }

        .logout-btn {
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.8rem 1rem;
            color: #ef4444;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            width: calc(100% - 260px);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 12px rgba(99,102,241,0.06);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: rgba(99, 102, 241, 0.08);
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            padding: 0.6rem 0.8rem;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s;
        }

        .mobile-menu-toggle:hover {
            background: rgba(99, 102, 241, 0.15);
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        /* Theme Toggle Switch in header */
        .theme-switch-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }
        .theme-switch-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
            white-space: nowrap;
        }
        .theme-switch-track {
            width: 44px;
            height: 24px;
            border-radius: 999px;
            background: #cbd5e1;
            border: 1px solid rgba(0,0,0,0.1);
            position: relative;
            transition: background 0.3s ease, border-color 0.3s;
            flex-shrink: 0;
        }
        html.dark .theme-switch-track {
            background: rgba(99,102,241,0.6);
            border-color: rgba(99,102,241,0.3);
        }
        .theme-switch-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #f59e0b;
        }
        html.dark .theme-switch-thumb {
            transform: translateX(20px);
            color: #6366f1;
        }

        /* Global UI Elements */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(99,102,241,0.06);
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
            filter: brightness(1.1);
        }

        .btn-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            background: rgba(16, 185, 129, 0.3);
            color: #34d399;
            transform: translateY(-2px);
        }

        .btn-info {
            background: rgba(6, 182, 212, 0.2);
            color: #06b6d4;
            border: 1px solid rgba(6, 182, 212, 0.3);
        }

        .btn-info:hover {
            background: rgba(6, 182, 212, 0.3);
            color: #22d3ee;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(148, 163, 184, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(148, 163, 184, 0.2);
            color: #cbd5e1;
            transform: translateY(-2px);
        }

        .btn-danger, .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover, .btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: rgba(100, 100, 100, 0.1);
            color: var(--text-muted);
            border: 1px solid var(--glass-border);
        }

        .btn-cancel:hover {
            background: rgba(100, 100, 100, 0.2);
            color: var(--text-main);
            transform: translateY(-2px);
        }

        .btn-edit {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .btn-edit:hover {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            transform: translateY(-2px);
        }

        /* Status & Badges */
        .status-yes, .status-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-no, .status-error, .status-failed {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pending, .status-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .role-badge {
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Filter Form */
        .filter-card {
            margin-bottom: 2rem;
            padding: 1.5rem !important;
        }

        .filter-form {
            display: flex;
            gap: 1.5rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.6rem;
            font-size: 0.9rem;
            color: #94a3b8;
            font-weight: 600;
        }

        .filter-group input, .filter-group select {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--glass-border);
            padding: 0.8rem 1rem;
            border-radius: 12px;
            color: var(--text-main);
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--input-bg);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .alert-error, .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .header h1 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .glass-card {
                padding: 1.5rem;
                border-radius: 16px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .header h1 {
                font-size: 1.3rem;
            }

            .logo {
                font-size: 1.3rem;
            }

            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }

            .btn .fa-plus,
            .btn .fa-chevron-left,
            .btn .fa-chevron-right {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.1rem;
            }

            .logo span {
                display: none;
            }

            .nav-links a span {
                display: none;
            }

            .nav-links a {
                justify-content: center;
                padding: 0.8rem;
            }

            .sidebar {
                width: 200px;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-robot"></i>
            <span>Darko AI Admin</span>
        </div>
        <ul class="nav-links">
            <li><a href="{{ route('admin.dashboard') }}"
                    class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i
                        class="fas fa-chart-line"></i> <span>Dashboard</span></a></li>
            <li><a href="{{ route('admin.databases') }}" class="{{ request()->routeIs('admin.databases') ? 'active' : '' }}"><i
                        class="fas fa-database"></i> <span>Management Database</span></a></li>
            <li><a href="{{ route('admin.ai_management') }}" class="{{ request()->routeIs('admin.ai_management') ? 'active' : '' }}"><i
                        class="fas fa-robot"></i> <span>Management AI</span></a></li>
            <li><a href="{{ route('admin.roles') }}" class="{{ request()->routeIs('admin.roles') ? 'active' : '' }}"><i
                        class="fas fa-user-shield"></i> <span>Management Role</span></a></li>
            <li><a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active' : '' }}"><i
                        class="fas fa-users"></i> <span>Management User</span></a></li>
            <li><a href="{{ route('chatbot') }}"><i class="fas fa-comment-dots"></i> <span>Kembali ke Chatbot</span></a></li>
        </ul>
        <form action="{{ route('logout') }}" method="POST" id="logout-form">
            @csrf
            <a href="#" class="logout-btn" onclick="document.getElementById('logout-form').submit();"><i
                    class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </form>
    </div>

    <div class="main-content">
        <!-- Header with page title + theme toggle -->
        <div class="header" id="page-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" onclick="toggleSidebar()" style="display:none;">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 style="font-size:1.4rem;font-weight:700;color:var(--text-main);" id="page-title">@yield('page-title', 'Admin Panel')</h1>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;">Darko AI Management System</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:16px;">
                <!-- Theme Toggle Switch -->
                <div class="theme-switch-wrap" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
                    <span class="theme-switch-label" id="theme-label">Light</span>
                    <div class="theme-switch-track">
                        <div class="theme-switch-thumb" id="theme-thumb">
                            <i class="fas fa-sun" id="theme-icon"></i>
                        </div>
                    </div>
                    <span class="theme-switch-label" id="theme-label-dark">Dark</span>
                </div>
                <!-- User info -->
                <div style="display:flex;align-items:center;gap:8px;padding:6px 12px;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.15);border-radius:10px;">
                    <i class="fas fa-user-circle" style="color:var(--primary);font-size:1rem;"></i>
                    <span style="font-size:0.82rem;color:var(--text-main);font-weight:500;">{{ auth()->user()->name ?? 'Admin' }}</span>
                </div>
            </div>
        </div>
        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @yield('scripts')
    <script>
        // Theme initialization
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        function toggleTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                updateThemeToggle('light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                updateThemeToggle('dark');
            }
        }

        function updateThemeToggle(theme) {
            const icon = document.getElementById('theme-icon');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            updateThemeToggle(currentTheme);

            // Show mobile toggle on small screens
            const mobileToggle = document.querySelector('.mobile-menu-toggle');
            function checkMobile() {
                if (mobileToggle) {
                    mobileToggle.style.display = window.innerWidth <= 1024 ? 'flex' : 'none';
                }
            }
            checkMobile();
            window.addEventListener('resize', checkMobile);
        });

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Close sidebar when clicking on a nav link (mobile)
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    toggleSidebar();
                }
            });
        });
    </script>
</body>

</html>