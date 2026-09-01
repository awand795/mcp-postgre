<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('SSO Error') }} - darkotech AI</title>
    <link rel="icon" href="{{ asset('logo_dmi.png') }}" type="image/png">
    <script>
        (function() {
            const theme = localStorage.getItem('theme');
            const isDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) {
                document.documentElement.classList.add('dark');
                const s = document.createElement('style');
                s.id = 'fouc-fix';
                s.innerHTML = 'html,body{background:#070b14!important;color:#f8fafc!important;}';
                document.head.appendChild(s);
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #f53003;
            --brand-glow: rgba(245, 48, 3, 0.28);
            --bg-page: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.92);
            --card-border: rgba(226, 232, 240, 0.85);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --card-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(226, 232, 240, 0.7);
        }

        html.dark {
            --brand: #f53003;
            --brand-glow: rgba(245, 48, 3, 0.35);
            --bg-page: #060911;
            --card-bg: rgba(15, 23, 42, 0.78);
            --card-border: rgba(255, 255, 255, 0.09);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --card-shadow: 0 30px 70px -15px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(255, 255, 255, 0.06);
        }

        * { margin:0; padding:0; box-sizing:border-box; font-family:'Plus Jakarta Sans', sans-serif; }
        
        body {
            background-color: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            position: relative;
            overflow-x: hidden;
        }

        .ambient-glow-1 {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            filter: blur(100px);
            width: 550px; height: 550px;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.18) 0%, rgba(245, 48, 3, 0) 70%);
            top: -150px; left: 50%;
            transform: translateX(-50%);
        }

        .error-card-container {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 10;
        }

        .error-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 3rem 2.25rem;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(24px);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .error-card::before {
            content: '';
            position: absolute;
            top: 0; left: 10%; right: 10%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #ef4444, #f53003, transparent);
        }

        .icon-circle {
            width: 72px; height: 72px;
            border-radius: 22px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            color: #ef4444;
            font-size: 1.8rem;
        }

        h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 0.65rem;
            font-family: 'Outfit', sans-serif;
        }

        p {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.55;
            margin-bottom: 2rem;
        }

        .btn-retry {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 48px;
            background: linear-gradient(135deg, #ff471a 0%, #f53003 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            border-radius: 14px;
            box-shadow: 0 8px 22px var(--brand-glow);
            transition: all 0.2s ease;
        }
        .btn-retry:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }
    </style>
</head>
<body>
    <div class="ambient-glow-1"></div>
    <div class="error-card-container">
        <div class="error-card">
            <div class="icon-circle">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <h1>{{ __('Gagal Autentikasi SSO') }}</h1>
            <p>{{ $message ?? __('Terjadi kesalahan saat memverifikasi kredensial Single Sign-On Anda.') }}</p>
            <a href="{{ route('login') }}" class="btn-retry">
                <i class="fas fa-arrow-left"></i>
                <span>{{ __('Kembali ke Halaman Login') }}</span>
            </a>
        </div>
    </div>
</body>
</html>
