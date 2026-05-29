<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Lupa Password') }} - darkotech AI</title>
    <link rel="icon" href="{{ asset('logo_dmi.png') }}" type="image/png">
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
        :root { --primary:#f53003; --bg:#f0f2f8; --card:rgba(255,255,255,0.97); --border:rgba(99,102,241,0.12); --text:#1e293b; --muted:#64748b; --input-bg:rgba(255,255,255,0.9); --input-border:rgba(99,102,241,0.2); }
        html.dark { --bg:#0b1120; --card:rgba(17,24,39,0.92); --border:rgba(99,102,241,0.2); --text:#f1f5f9; --muted:#94a3b8; --input-bg:rgba(15,23,42,0.8); --input-border:rgba(99,102,241,0.3); }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Outfit',sans-serif;}
        body{background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;transition:background 0.4s;position:relative;overflow:hidden;}
        html.dark body{background:linear-gradient(135deg,#0b1120 0%,#0f172a 60%,#111827 100%);}
        body::before{content:'';position:fixed;width:600px;height:600px;background:radial-gradient(circle,rgba(245,48,3,0.06),transparent 70%);top:-200px;right:-100px;pointer-events:none;z-index:0;}
        .auth-card{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:2.5rem 2.25rem;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.08),0 4px 20px rgba(99,102,241,0.08);position:relative;z-index:1;transition:background 0.4s,border-color 0.4s;}
        html.dark .auth-card{box-shadow:0 20px 60px rgba(0,0,0,0.4),0 0 0 1px rgba(99,102,241,0.15);}
        .auth-header{text-align:center;margin-bottom:2rem;}
        .brand-logo{width:70px;height:70px;object-fit:contain;margin-bottom:1.25rem;filter:drop-shadow(0 8px 16px rgba(245,48,3,0.15));}
        .auth-header h1{font-size:1.4rem;font-weight:700;color:var(--text);margin-bottom:4px;}
        .auth-header p{font-size:0.85rem;color:var(--muted);line-height:1.5;}
        .form-group{margin-bottom:1.25rem;}
        .form-label{display:block;margin-bottom:0.5rem;font-size:0.73rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted);}
        .input-wrap{position:relative;}
        .input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:0.85rem;}
        .form-input{width:100%;background:var(--input-bg);border:1px solid var(--input-border);border-radius:12px;padding:0.75rem 1rem 0.75rem 2.75rem;color:var(--text);font-size:0.9rem;transition:all 0.2s;font-family:'Outfit',sans-serif;}
        .form-input::placeholder{color:var(--muted);opacity:0.6;}
        .form-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(245,48,3,0.12);}
        .form-error{color:#ef4444;font-size:0.75rem;margin-top:4px;}
        .alert-success{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#10b981;padding:0.75rem 1rem;border-radius:10px;font-size:0.85rem;margin-bottom:1rem;}
        .btn-submit{width:100%;background:linear-gradient(135deg,#f53003,#ff4433);color:#fff;border:none;border-radius:12px;padding:0.9rem;font-size:0.9rem;font-weight:700;letter-spacing:0.04em;cursor:pointer;transition:all 0.25s;margin-top:0.5rem;box-shadow:0 4px 16px rgba(245,48,3,0.3);font-family:'Outfit',sans-serif;}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(245,48,3,0.4);}
        .auth-footer{margin-top:1.5rem;text-align:center;font-size:0.82rem;color:var(--muted);}
        .auth-footer a{color:var(--primary);font-weight:600;text-decoration:none;}
        .auth-footer a:hover{opacity:0.8;}
        .theme-btn{position:fixed;top:20px;right:20px;width:42px;height:42px;border-radius:12px;background:var(--card);border:1px solid var(--border);color:var(--text);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,0.06);transition:all 0.2s;}
        .theme-btn:hover{border-color:var(--primary);color:var(--primary);}

        /* ── Language Switcher ── */
        .lang-switch-dropdown { position: fixed; top: 20px; right: 72px; z-index: 10; }
        .lang-switch-btn { height: 42px; display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 0 0.8rem; color: var(--text); font-size: 0.85rem; font-weight: 600; transition: all 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.06); font-family: inherit; }
        .lang-switch-btn:hover { border-color: var(--primary); color: var(--primary); transform: scale(1.05); }
        .lang-switch-btn i { font-size: 0.85rem; }
        .lang-dropdown-menu { display: none; position: absolute; top: calc(100% + 8px); right: 0; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 6px; min-width: 170px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000; animation: slideDown 0.2s ease-out; }
        .lang-dropdown-menu.active { display: block; }
        .lang-dropdown-menu a { display: flex; align-items: center; gap: 8px; padding: 8px 12px; color: var(--text); text-decoration: none; font-size: 0.8rem; font-weight: 500; border-radius: 8px; transition: all 0.15s; }
        .lang-dropdown-menu a:hover { background: rgba(245,48,3,0.05); color: var(--primary); }
        html.dark .lang-dropdown-menu a:hover { background: rgba(245,48,3,0.1); }
        .lang-dropdown-menu a.active { background: rgba(245,48,3,0.08); color: var(--primary); font-weight: 700; }
        .flag-icon { font-size: 0.95rem; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        @media(max-width:480px){body{padding:0;align-items:flex-start;}.auth-card{border-radius:0;min-height:100vh;padding:2rem 1.5rem;display:flex;flex-direction:column;justify-content:center;}}
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function(){if(localStorage.getItem('theme')==='dark'){document.documentElement.classList.add('dark');}})();
        function toggleTheme(){
            const d=document.documentElement.classList.contains('dark');
            document.documentElement.classList[d?'remove':'add']('dark');
            localStorage.setItem('theme',d?'light':'dark');
            document.getElementById('ti').className=d?'fas fa-moon':'fas fa-sun';
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
                if (!event.target.closest('.lang-switch-dropdown')) {
                    menu.classList.remove('active');
                }
            }
        });

        function startCountdown(seconds) {
            const btn = document.getElementById('btn-submit');
            const btnText = btn.querySelector('.btn-text');
            const btnIcon = btn.querySelector('i');
            
            btn.disabled = true;
            btn.style.opacity = '0.7';
            btn.style.cursor = 'not-allowed';
            
            let remaining = seconds;
            const originalIconClass = btnIcon.className;
            btnIcon.className = 'fas fa-clock animate-pulse';

            const interval = setInterval(() => {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(interval);
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                    btnText.innerText = "{{ __('KIRIM KODE OTP') }}";
                    btnIcon.className = originalIconClass;
                } else {
                    btnText.innerText = `{{ __('COBA LAGI') }} (${remaining}s)`;
                }
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded',()=>{
            document.getElementById('ti').className=document.documentElement.classList.contains('dark')?'fas fa-moon':'fas fa-sun';
            const f = document.getElementById('fouc-fix');
            if (f) f.remove();

            // Deteksi apakah sedang diakses dari dalam iframe
            if (window.self !== window.top) {
                var iframeInput = document.getElementById('is_iframe_input');
                if (iframeInput) {
                    iframeInput.value = '1';
                }
            }

            // Handle Hard Block
            @if(session('hard_block'))
                const btn = document.getElementById('btn-submit');
                if (btn) {
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                    btn.querySelector('.btn-text').innerText = "{{ __('AKSES DIBATASI') }}";
                }
                Swal.fire({
                    icon: 'error',
                    title: "{{ __('Batas Permintaan Terlampaui') }}",
                    html: '<div style="text-align: justify; font-size: 0.95rem; line-height: 1.5;">{{ __('Mohon maaf, kami mendeteksi aktivitas permintaan yang terlalu sering pada akun Anda. Demi keamanan sistem, permintaan OTP telah dibatasi.') }} <br/><br/>{{ __('Jika Anda tidak menerima email, silakan') }} <b>{{ __('hubungi Administrator') }}</b> {{ __('untuk bantuan pemulihan akun secara manual.') }}</div>',
                    confirmButtonColor: '#f53003',
                    confirmButtonText: "{{ __('Saya Mengerti') }}"
                });
            @elseif(session('throttle_seconds'))
                startCountdown({{ session('throttle_seconds') }});
                Swal.fire({
                    icon: 'warning',
                    title: "{{ __('Batas Permintaan Tercapai') }}",
                    text: "{{ __('Silakan tunggu beberapa saat sebelum mengirim ulang kode OTP.') }}",
                    confirmButtonColor: '#f53003'
                });
            @elseif($errors->has('email'))
                Swal.fire({
                    icon: 'error',
                    title: "{{ __('Kesalahan') }}",
                    text: '{{ $errors->first('email') }}',
                    confirmButtonColor: '#f53003'
                });
            @endif

            @if(session('status'))
                Swal.fire({
                    icon: 'success',
                    title: "{{ __('Berhasil') }}",
                    text: '{{ session('status') }}',
                    confirmButtonColor: '#10b981'
                });
            @endif

            // SSO / Iframe URL Parameter Fallback
            try {
                var urlParams = new URLSearchParams(window.location.search);
                var ssoError = urlParams.get('sso_error');
                var ssoSuccess = urlParams.get('sso_success');
                var ssoHardBlock = urlParams.get('sso_hard_block');
                var ssoThrottle = urlParams.get('sso_throttle_seconds');
                
                var cleanUrlNeeded = false;
                
                if (ssoHardBlock) {
                    const btn = document.getElementById('btn-submit');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor = 'not-allowed';
                        btn.querySelector('.btn-text').innerText = "{{ __('AKSES DIBATASI') }}";
                    }
                    Swal.fire({
                        icon: 'error',
                        title: "{{ __('Batas Permintaan Terlampaui') }}",
                        html: '<div style="text-align: justify; font-size: 0.95rem; line-height: 1.5;">{{ __('Mohon maaf, kami mendeteksi aktivitas permintaan yang terlalu sering pada akun Anda. Demi keamanan sistem, permintaan OTP telah dibatasi.') }} <br/><br/>{{ __('Jika Anda tidak menerima email, silakan') }} <b>{{ __('hubungi Administrator') }}</b> {{ __('untuk bantuan pemulihan akun secara manual.') }}</div>',
                        confirmButtonColor: '#f53003',
                        confirmButtonText: "{{ __('Saya Mengerti') }}"
                    });
                    cleanUrlNeeded = true;
                } else if (ssoThrottle) {
                    startCountdown(parseInt(ssoThrottle));
                    Swal.fire({
                        icon: 'warning',
                        title: "{{ __('Batas Permintaan Tercapai') }}",
                        text: "{{ __('Silakan tunggu beberapa saat sebelum mengirim ulang kode OTP.') }}",
                        confirmButtonColor: '#f53003'
                    });
                    cleanUrlNeeded = true;
                } else if (ssoError) {
                    Swal.fire({
                        icon: 'error',
                        title: "{{ __('Kesalahan') }}",
                        text: ssoError,
                        confirmButtonColor: '#f53003'
                    });
                    cleanUrlNeeded = true;
                }
                
                if (ssoSuccess) {
                    Swal.fire({
                        icon: 'success',
                        title: "{{ __('Berhasil') }}",
                        text: ssoSuccess,
                        confirmButtonColor: '#10b981'
                    });
                    cleanUrlNeeded = true;
                }

                if (cleanUrlNeeded) {
                    var cleanUrl = new URL(window.location.href);
                    cleanUrl.searchParams.delete('sso_success');
                    cleanUrl.searchParams.delete('sso_error');
                    cleanUrl.searchParams.delete('sso_hard_block');
                    cleanUrl.searchParams.delete('sso_throttle_seconds');
                    window.history.replaceState({}, document.title, cleanUrl.pathname + cleanUrl.search);
                }
            } catch (e) {}
        });

        function handleSubmit(form) {
            const btn = document.getElementById('btn-submit');
            const btnText = btn.querySelector('.btn-text');
            const btnIcon = btn.querySelector('i');
            
            if (btn.disabled) return false;
            
            btn.disabled = true;
            btnText.innerText = "{{ __('MEMPROSES...') }}";
            btnIcon.className = 'fas fa-spinner fa-spin';
            return true;
        }
    </script>
</head>
<body>
    <button onclick="toggleTheme()" class="theme-btn" title="{{ __('Toggle Theme') }}"><i id="ti" class="fas fa-sun"></i></button>

    <!-- Language Switcher Dropdown -->
    <div class="lang-switch-dropdown">
        <button class="lang-switch-btn" onclick="toggleLangDropdown(event)" title="{{ __('Bahasa') }}">
            <i class="fas fa-globe"></i>
            <span>{{ app()->getLocale() == 'id' ? 'ID' : 'EN' }}</span>
            <i class="fas fa-chevron-down" style="font-size: 0.65rem;"></i>
        </button>
        <div class="lang-dropdown-menu" id="langDropdownMenu">
            <a href="{{ route('lang.switch', array_merge(request()->query(), ['locale' => 'id'])) }}" class="{{ app()->getLocale() == 'id' ? 'active' : '' }}">
                <span class="flag-icon">🇮🇩</span> Bahasa Indonesia
            </a>
            <a href="{{ route('lang.switch', array_merge(request()->query(), ['locale' => 'en'])) }}" class="{{ app()->getLocale() == 'en' ? 'active' : '' }}">
                <span class="flag-icon">🇬🇧</span> English
            </a>
        </div>
    </div>
    <div class="auth-card">
        <div class="auth-header">
            <img src="{{ asset('logo_dmi.png') }}" alt="darkotech AI Logo" class="brand-logo">
            <h1>{{ __('Lupa Password') }}</h1>
            <p>{{ __('Masukkan email Anda untuk menerima kode OTP reset password.') }}</p>
        </div>

        <form action="{{ route('password.email') }}" method="POST" onsubmit="return handleSubmit(this)">
            @csrf
            <input type="hidden" name="is_iframe" id="is_iframe_input" value="0">
            <div class="form-group">
                <label class="form-label">{{ __('Alamat Email') }}</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" value="{{ request()->query('email', old('email')) }}" required {{ (request()->query('is_iframe') === '1' || request()->query('token')) ? '' : 'autofocus' }}
                        class="form-input" placeholder="{{ __('email@contoh.com') }}">
                </div>
            </div>
            <button type="submit" id="btn-submit" class="btn-submit">
                <i class="fas fa-paper-plane" style="margin-right:8px;"></i>
                <span class="btn-text">{{ __('KIRIM KODE OTP') }}</span>
            </button>
        </form>

        <div class="auth-footer">{{ __('Ingat password?') }} <a href="{{ route('login') }}">{{ __('Masuk sekarang') }}</a></div>
    </div>
</body>
</html>
