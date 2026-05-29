<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Halaman Tidak Ditemukan</title>
    <link rel="icon" href="{{ asset('logo_dmi.png') }}" type="image/png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Script to set light/dark theme early -->
    <script>
        (function () {
            const theme = localStorage.getItem('theme');
            const isDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <style>
        :root {
            --bg-color: #f6f8fc;
            --text-color: #1e293b;
            --subtext-color: #64748b;
            --card-bg: rgba(255, 255, 255, 0.7);
            --card-border: rgba(0, 0, 0, 0.08);
            --brand-color: #f53003;
            --brand-hover: #ff4433;
            --btn-secondary-bg: #e2e8f0;
            --btn-secondary-text: #334155;
            --btn-secondary-hover: #cbd5e1;
            --glow-color: rgba(245, 48, 3, 0.15);
            --aurora-1: rgba(245, 48, 3, 0.05);
            --aurora-2: rgba(59, 130, 246, 0.05);
        }

        html.dark {
            --bg-color: #09090b;
            --text-color: #f8fafc;
            --subtext-color: #94a3b8;
            --card-bg: rgba(18, 18, 20, 0.7);
            --card-border: rgba(255, 255, 255, 0.08);
            --btn-secondary-bg: rgba(255, 255, 255, 0.05);
            --btn-secondary-text: #e2e8f0;
            --btn-secondary-hover: rgba(255, 255, 255, 0.1);
            --glow-color: rgba(245, 48, 3, 0.3);
            --aurora-1: rgba(245, 48, 3, 0.1);
            --aurora-2: rgba(59, 130, 246, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Ambient background glow */
        .aurora-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
            overflow: hidden;
            pointer-events: none;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.8;
            mix-blend-mode: screen;
            animation: float 20s infinite alternate;
        }

        .blob-1 {
            width: 500px;
            height: 500px;
            background: var(--aurora-1);
            top: -10%;
            right: 10%;
        }

        .blob-2 {
            width: 600px;
            height: 600px;
            background: var(--aurora-2);
            bottom: -20%;
            left: -10%;
            animation-duration: 25s;
        }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(40px, -60px) scale(1.1); }
            100% { transform: translate(-20px, 20px) scale(0.95); }
        }

        /* Container */
        .error-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 520px;
            padding: 2.5rem;
            text-align: center;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 32px;
            box-shadow: 
                0 20px 40px -15px rgba(0, 0, 0, 0.05),
                0 30px 60px -30px rgba(0, 0, 0, 0.1),
                inset 0 0 0 1px rgba(255, 255, 255, 0.05);
            margin: 1.5rem;
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Logo styling */
        .logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            animation: pulse 4s infinite ease-in-out;
        }

        .logo-img {
            width: 56px;
            height: 56px;
            object-fit: contain;
        }

        /* Huge 404 text */
        .error-code {
            font-size: 8rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.05em;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--brand-color), #ff6b4a, #3b82f6);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shine 6s linear infinite;
        }

        @keyframes shine {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .error-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }

        .error-desc {
            font-size: 0.95rem;
            color: var(--subtext-color);
            line-height: 1.6;
            margin-bottom: 1rem;
            padding: 0 1rem;
        }

        /* Sub footer */
        .footer-text {
            margin-top: 2.5rem;
            font-size: 0.75rem;
            color: var(--subtext-color);
            opacity: 0.6;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
    </style>
</head>
<body>

    <!-- Ambient glowing backgrounds -->
    <div class="aurora-bg">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <!-- Error Card -->
    <div class="error-container">
        
        <!-- Logo DMI -->
        <div class="logo-wrap">
            <img src="{{ asset('logo_dmi.png') }}" alt="Logo DMI" class="logo-img">
        </div>

        <!-- 404 Code -->
        <div class="error-code">404</div>

        <!-- Title & Desc -->
        <h1 class="error-title">{{ __('Halaman Tidak Ditemukan') }}</h1>
        <p class="error-desc">
            {{ __('Sesi Anda mungkin sudah berakhir, atau halaman yang Anda cari telah dipindahkan, dihapus, atau tidak pernah ada.') }}
        </p>

        <div class="footer-text">
            Powered by darkotech
        </div>
    </div>

</body>
</html>
