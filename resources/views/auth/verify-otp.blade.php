<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Verifikasi OTP') }} - darkotech AI</title>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --brand: #f53003;
            --brand-hover: #ff471a;
            --brand-glow: rgba(245, 48, 3, 0.28);
            --bg-page: #f1f5f9;
            --card-bg: rgba(255, 255, 255, 0.92);
            --card-border: rgba(226, 232, 240, 0.85);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --input-bg: #ffffff;
            --input-border: #cbd5e1;
            --input-focus: #f53003;
            --card-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(226, 232, 240, 0.7);
        }

        html.dark {
            --brand: #f53003;
            --brand-hover: #ff471a;
            --brand-glow: rgba(245, 48, 3, 0.35);
            --bg-page: #060911;
            --card-bg: rgba(15, 23, 42, 0.78);
            --card-border: rgba(255, 255, 255, 0.09);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: rgba(8, 14, 28, 0.75);
            --input-border: rgba(255, 255, 255, 0.12);
            --input-focus: #f53003;
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
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* ── Ambient Background Glows & Grid ── */
        .ambient-glow-1, .ambient-glow-2 {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            filter: blur(100px);
            opacity: 0.6;
        }
        .ambient-glow-1 {
            width: 550px;
            height: 550px;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.16) 0%, rgba(245, 48, 3, 0) 70%);
            top: -150px;
            left: 50%;
            transform: translateX(-50%);
        }
        .ambient-glow-2 {
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, rgba(99, 102, 241, 0) 70%);
            bottom: -150px;
            right: 15%;
        }
        html.dark .ambient-glow-1 {
            opacity: 0.75;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.22) 0%, rgba(245, 48, 3, 0) 70%);
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(100, 116, 139, 0.12) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
            z-index: 0;
        }
        html.dark body::before {
            background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        }

        /* ── Top Header Controls ── */
        .top-nav {
            position: fixed;
            top: 1.5rem;
            right: 1.75rem;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 50;
        }
        .nav-pill-btn {
            height: 42px;
            padding: 0 14px;
            border-radius: 12px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-main);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            backdrop-filter: blur(16px);
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            user-select: none;
        }
        .nav-pill-btn:hover {
            border-color: var(--brand);
            color: var(--brand);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px var(--brand-glow);
        }

        /* Language dropdown */
        .lang-dropdown-wrapper { position: relative; }
        .lang-menu-box {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 6px;
            min-width: 175px;
            box-shadow: 0 16px 36px rgba(0,0,0,0.15);
            z-index: 1000;
            backdrop-filter: blur(20px);
            animation: popIn 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .lang-menu-box.active { display: block; }
        .lang-menu-box a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            border-radius: 9px;
            transition: all 0.15s;
        }
        .lang-menu-box a:hover {
            background: rgba(245, 48, 3, 0.08);
            color: var(--brand);
        }
        .lang-menu-box a.active {
            background: rgba(245, 48, 3, 0.12);
            color: var(--brand);
            font-weight: 700;
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.95) translateY(-6px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* ── Centered Card ── */
        .login-card-container {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 10;
            animation: cardAppear 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes cardAppear {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 2.75rem 2.5rem;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(24px);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 10%;
            right: 10%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--brand), #ff6b4a, transparent);
            opacity: 0.8;
        }

        /* ── Card Header ── */
        .card-header {
            text-align: center;
            margin-bottom: 2.25rem;
        }
        .logo-wrapper {
            width: 68px;
            height: 68px;
            margin: 0 auto 1.25rem auto;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(241, 245, 249, 0.8));
            border: 1px solid rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            box-shadow: 0 10px 25px -5px var(--brand-glow), 0 0 0 1px rgba(245, 48, 3, 0.15);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        html.dark .logo-wrapper {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.8));
            border: 1px solid rgba(255,255,255,0.1);
        }
        .logo-wrapper:hover { transform: scale(1.06) rotate(-2deg); }
        .brand-logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(245, 48, 3, 0.25));
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            background: rgba(245, 48, 3, 0.08);
            border: 1px solid rgba(245, 48, 3, 0.2);
            color: var(--brand);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.75rem;
        }
        .brand-badge .badge-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--brand);
            box-shadow: 0 0 8px var(--brand);
        }

        .card-header h1 {
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-main);
            margin-bottom: 6px;
            font-family: 'Outfit', sans-serif;
        }
        .card-header p {
            font-size: 0.86rem;
            color: var(--text-muted);
            line-height: 1.45;
        }

        /* ── Form Elements ── */
        .form-group { margin-bottom: 1.35rem; }
        .form-label {
            display: block;
            margin-bottom: 0.45rem;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            text-align: center;
        }
        .otp-input {
            width: 100%;
            background-color: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 16px;
            padding: 0.9rem 1rem;
            color: var(--text-main);
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 0.35em;
            text-align: center;
            outline: none;
            transition: all 0.2s ease;
            font-family: 'Outfit', monospace;
        }
        .otp-input::placeholder {
            color: var(--text-muted);
            opacity: 0.35;
            letter-spacing: 0.25em;
        }
        .otp-input:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 4px var(--brand-glow);
        }

        .submit-btn {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #ff471a 0%, #f53003 100%);
            border: none;
            border-radius: 14px;
            color: #ffffff;
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 22px var(--brand-glow);
            transition: all 0.25s ease;
            font-family: inherit;
            margin-top: 0.5rem;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(245, 48, 3, 0.42);
            filter: brightness(1.04);
        }

        .resend-box {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.84rem;
            color: var(--text-muted);
        }
        .resend-btn {
            background: none;
            border: none;
            color: var(--brand);
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            font-size: inherit;
            margin-left: 4px;
            transition: all 0.2s;
        }
        .resend-btn:hover {
            color: var(--brand-hover);
            text-decoration: underline;
        }
        .resend-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            text-decoration: none;
        }

        .card-footer {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.78rem;
            color: var(--text-muted);
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.76rem;
            color: #10b981;
            font-weight: 600;
        }
        .status-pill .dot {
            width: 7px; height: 7px; border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 8px #10b981;
        }

        @media (max-width: 480px) {
            body { padding: 1rem; }
            .login-card { padding: 2.25rem 1.5rem; border-radius: 22px; }
            .top-nav { top: 1rem; right: 1rem; }
            .otp-input { font-size: 1.3rem; letter-spacing: 0.25em; }
        }
    </style>
    <script>
        function toggleTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            document.documentElement.classList[isDark ? 'remove' : 'add']('dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
            const icon = document.getElementById('theme-icon');
            if (icon) icon.className = isDark ? 'fas fa-moon' : 'fas fa-sun';
            const f = document.getElementById('fouc-fix');
            if (f) f.remove();
        }

        function toggleLangDropdown(event) {
            event.stopPropagation();
            const menu = document.getElementById('langDropdownMenu');
            if (menu) menu.classList.toggle('active');
        }

        window.addEventListener('click', function(event) {
            const menu = document.getElementById('langDropdownMenu');
            if (menu && menu.classList.contains('active')) {
                if (!event.target.closest('.lang-dropdown-wrapper')) {
                    menu.classList.remove('active');
                }
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const isDark = document.documentElement.classList.contains('dark');
            const icon = document.getElementById('theme-icon');
            if (icon) icon.className = isDark ? 'fas fa-moon' : 'fas fa-sun';
            const f = document.getElementById('fouc-fix');
            if (f) f.remove();

            if (window.self !== window.top) {
                const iframeInput = document.getElementById('is_iframe_input');
                if (iframeInput) iframeInput.value = '1';
                const resendIframe = document.getElementById('resend_is_iframe_input');
                if (resendIframe) resendIframe.value = '1';
            }

            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: "{{ __('Kesalahan') }}",
                    text: "{{ session('error') }}",
                    confirmButtonColor: '#f53003'
                });
            @endif

            @if(session('status'))
                Swal.fire({
                    icon: 'success',
                    title: "{{ __('Berhasil') }}",
                    text: "{{ session('status') }}",
                    confirmButtonColor: '#10b981'
                });
            @endif

            // SSO parameter fallback
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const ssoError = urlParams.get('sso_error');
                const ssoSuccess = urlParams.get('sso_success');
                let cleanNeeded = false;

                if (ssoError) {
                    Swal.fire({
                        icon: 'error',
                        title: "{{ __('Kesalahan') }}",
                        text: ssoError,
                        confirmButtonColor: '#f53003'
                    });
                    cleanNeeded = true;
                }
                if (ssoSuccess) {
                    Swal.fire({
                        icon: 'success',
                        title: "{{ __('Berhasil') }}",
                        text: ssoSuccess,
                        confirmButtonColor: '#10b981'
                    });
                    cleanNeeded = true;
                }

                if (cleanNeeded) {
                    const cleanUrl = new URL(window.location.href);
                    cleanUrl.searchParams.delete('sso_success');
                    cleanUrl.searchParams.delete('sso_error');
                    window.history.replaceState({}, document.title, cleanUrl.pathname + cleanUrl.search);
                }
            } catch (e) {}
        });

        function handleResend(form) {
            const btn = document.getElementById('resend-btn');
            if (btn.disabled) return false;
            btn.disabled = true;
            btn.innerText = "{{ __('Mengirim...') }}";
            return true;
        }
    </script>
</head>
<body>

    <!-- Ambient Glow Spots -->
    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <!-- Top Action Bar (Language Switcher + Theme Toggle) -->
    <div class="top-nav">
        <div class="lang-dropdown-wrapper">
            <button type="button" class="nav-pill-btn" onclick="toggleLangDropdown(event)" title="{{ __('Pilih Bahasa') }}">
                <i class="fas fa-globe"></i>
                <span>{{ app()->getLocale() == 'id' ? 'ID' : 'EN' }}</span>
                <i class="fas fa-chevron-down" style="font-size: 0.65rem; opacity: 0.7;"></i>
            </button>
            <div class="lang-menu-box" id="langDropdownMenu">
                <a href="{{ route('lang.switch', array_merge(request()->query(), ['locale' => 'id'])) }}" class="{{ app()->getLocale() == 'id' ? 'active' : '' }}">
                    <span>🇮🇩</span> Bahasa Indonesia
                </a>
                <a href="{{ route('lang.switch', array_merge(request()->query(), ['locale' => 'en'])) }}" class="{{ app()->getLocale() == 'en' ? 'active' : '' }}">
                    <span>🇬🇧</span> English
                </a>
            </div>
        </div>

        <button type="button" class="nav-pill-btn" onclick="toggleTheme()" title="{{ __('Ganti Mode Tema') }}">
            <i id="theme-icon" class="fas fa-moon"></i>
        </button>
    </div>

    <!-- Centered Card -->
    <div class="login-card-container">
        <div class="login-card">
            
            <!-- Card Header -->
            <div class="card-header">
                <div class="logo-wrapper">
                    <img src="{{ asset('logo_dmi.png') }}" alt="darkotech AI Logo" class="brand-logo-img">
                </div>
                <div>
                    <div class="brand-badge">
                        <span class="badge-dot"></span>
                        <span>{{ __('Verifikasi Keamanan') }}</span>
                    </div>
                </div>
                <h1>{{ __('Verifikasi OTP') }}</h1>
                <p>{{ __('Masukkan 6 digit kode yang telah dikirim ke') }}<br><strong style="color: var(--text-main);">{{ $email }}</strong></p>
            </div>

            <!-- Form -->
            <form action="{{ route('password.verify.post', request()->query()) }}" method="POST">
                @csrf
                <input type="hidden" name="is_iframe" id="is_iframe_input" value="0">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="form-group">
                    <label class="form-label">{{ __('KODE VERIFIKASI') }}</label>
                    <input type="text" name="otp" required 
                           {{ (request()->query('is_iframe') === '1' || request()->query('token')) ? '' : 'autofocus' }} 
                           maxlength="6" 
                           class="otp-input" 
                           placeholder="000000" 
                           autocomplete="one-time-code">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-shield-check" style="font-size: 0.9rem;"></i>
                    <span>{{ __('VERIFIKASI SEKARANG') }}</span>
                </button>
            </form>

            <div class="resend-box">
                {{ __('Belum menerima email?') }}
                <form action="{{ route('password.email', request()->query()) }}" method="POST" style="display:inline;" onsubmit="return handleResend(this)">
                    @csrf
                    <input type="hidden" name="is_iframe" id="resend_is_iframe_input" value="0">
                    <input type="hidden" name="email" value="{{ $email }}">
                    <button type="submit" id="resend-btn" class="resend-btn">{{ __('Kirim Ulang') }}</button>
                </form>
            </div>

            <!-- Card Footer -->
            <div class="card-footer">
                <div class="status-pill">
                    <span class="dot"></span>
                    <span>{{ __('Sistem Operasional') }}</span>
                </div>
                <span>&copy; {{ date('Y') }} darkotech AI</span>
            </div>

        </div>
    </div>

</body>
</html>