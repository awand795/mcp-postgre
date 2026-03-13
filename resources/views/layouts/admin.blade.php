<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DataBot</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            color: white;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-right: 1px solid var(--glass-border);
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
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
            color: #94a3b8;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .nav-links a:hover, .nav-links a.active {
            background: rgba(99, 102, 241, 0.1);
            color: white;
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
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        /* Global UI Elements */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2rem;
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
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

    </style>
    @yield('scripts')
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-robot"></i>
            <span>DataBot Admin</span>
        </div>
        <ul class="nav-links">
            <li><a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') ? 'active' : '' }}"><i class="fas fa-users"></i> Management User</a></li>
            <li><a href="{{ route('admin.roles') }}" class="{{ request()->routeIs('admin.roles') ? 'active' : '' }}"><i class="fas fa-user-shield"></i> Management Role</a></li>
            <li><a href="{{ route('chatbot') }}"><i class="fas fa-comment-dots"></i> Kembali ke Chatbot</a></li>
        </ul>
        <form action="{{ route('logout') }}" method="POST" id="logout-form">
            @csrf
            <a href="#" class="logout-btn" onclick="document.getElementById('logout-form').submit();"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </form>
    </div>

    <div class="main-content">
        @yield('content')
    </div>
</body>
</html>
