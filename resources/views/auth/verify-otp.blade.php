<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi OTP - darkotech AI</title>
    <link rel="icon" href="{{ asset('logo_dmi.png') }}" type="image/png">
    <script>
        (function () {
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
        :root {
            --primary: #f53003;
            --bg: #f0f2f8;
            --card: rgba(255, 255, 255, 0.97);
            --border: rgba(99, 102, 241, 0.12);
            --text: #1e293b;
            --muted: #64748b;
            --input-bg: rgba(255, 255, 255, 0.9);
            --input-border: rgba(99, 102, 241, 0.2);
        }

        html.dark {
            --bg: #0b1120;
            --card: rgba(17, 24, 39, 0.92);
            --border: rgba(99, 102, 241, 0.2);
            --text: #f1f5f9;
            --muted: #94a3b8;
            --input-bg: rgba(15, 23, 42, 0.8);
            --input-border: rgba(99, 102, 241, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            transition: background 0.4s;
            position: relative;
        }

        html.dark body {
            background: linear-gradient(135deg, #0b1120 0%, #0f172a 60%, #111827 100%);
        }

        body::before {
            content: '';
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.06), transparent 70%);
            top: -200px;
            right: -100px;
            pointer-events: none;
        }

        .auth-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2.5rem 2.25rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 1;
            transition: background 0.4s, border-color 0.4s;
        }

        html.dark .auth-card {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(99, 102, 241, 0.15);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo {
            width: 70px;
            height: 70px;
            object-fit: contain;
            margin-bottom: 1.25rem;
            filter: drop-shadow(0 8px 16px rgba(245, 48, 3, 0.15));
        }

        .auth-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }

        .auth-header p {
            font-size: 0.85rem;
            color: var(--muted);
            line-height: 1.5;
        }

        .auth-header strong {
            color: var(--text);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 0.85rem;
        }

        .form-input {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            color: var(--text);
            font-size: 0.9rem;
            transition: all 0.2s;
            font-family: 'Outfit', sans-serif;
            letter-spacing: 0.15em;
        }

        .form-input::placeholder {
            color: var(--muted);
            opacity: 0.6;
            letter-spacing: 0.1em;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(245, 48, 3, 0.12);
        }

        .form-error {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 4px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #10b981;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #f53003, #ff4433);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 0.9rem;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            cursor: pointer;
            transition: all 0.25s;
            margin-top: 0.5rem;
            box-shadow: 0 4px 16px rgba(245, 48, 3, 0.3);
            font-family: 'Outfit', sans-serif;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(245, 48, 3, 0.4);
        }

        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.82rem;
            color: var(--muted);
        }

        .auth-footer a,
        .resend-btn {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            background: none;
            border: none;
            font-family: 'Outfit', sans-serif;
            font-size: inherit;
        }

        .resend-btn:hover {
            opacity: 0.8;
        }

        .theme-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.2s;
        }

        .theme-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        @media(max-width:480px) {
            body {
                padding: 0;
                align-items: flex-start;
            }

            .auth-card {
                border-radius: 0;
                min-height: 100vh;
                padding: 2rem 1.5rem;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () { if (localStorage.getItem('theme') === 'dark') { document.documentElement.classList.add('dark'); } })();
        function toggleTheme() { 
            const d = document.documentElement.classList.contains('dark'); 
            document.documentElement.classList[d ? 'remove' : 'add']('dark'); 
            localStorage.setItem('theme', d ? 'light' : 'dark'); 
            document.getElementById('ti').className = d ? 'fas fa-moon' : 'fas fa-sun'; 
            const f = document.getElementById('fouc-fix');
            if (f) f.remove();
        }

        function startResendCountdown(seconds) {
            const btn = document.getElementById('resend-btn');
            if (!btn) return;
            
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
            
            let remaining = seconds;
            const originalText = 'Kirim Ulang';

            const interval = setInterval(() => {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(interval);
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                    btn.innerText = originalText;
                } else {
                    btn.innerText = `Kirim Ulang (${remaining}s)`;
                }
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', () => { 
            document.getElementById('ti').className = document.documentElement.classList.contains('dark') ? 'fas fa-moon' : 'fas fa-sun'; 
            const f = document.getElementById('fouc-fix');
            if (f) f.remove();

            // Deteksi apakah sedang diakses dari dalam iframe
            if (window.self !== window.top) {
                var iframeInput = document.getElementById('is_iframe_input');
                if (iframeInput) {
                    iframeInput.value = '1';
                }
                var resendIframeInput = document.getElementById('resend_is_iframe_input');
                if (resendIframeInput) {
                    resendIframeInput.value = '1';
                }
            }

            @if(session('hard_block'))
                const resendBtn = document.getElementById('resend-btn');
                if (resendBtn) {
                    resendBtn.disabled = true;
                    resendBtn.style.opacity = '0.5';
                    resendBtn.style.cursor = 'not-allowed';
                    resendBtn.innerText = 'AKSES DIBATASI';
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Batas Permintaan Terlampaui',
                    html: '<div style="text-align: justify; font-size: 0.95rem; line-height: 1.5;">Mohon maaf, kami mendeteksi aktivitas permintaan yang terlalu sering pada akun Anda. Demi keamanan sistem, permintaan OTP telah dibatasi. <br/><br/>Jika Anda tidak menerima email, silakan <b>hubungi Administrator</b> untuk bantuan pemulihan akun secara manual.</div>',
                    confirmButtonColor: '#f53003',
                    confirmButtonText: 'Saya Mengerti'
                });
            @elseif(session('throttle_seconds'))
                startResendCountdown({{ session('throttle_seconds') }});
                Swal.fire({
                    icon: 'warning',
                    title: 'Batas Permintaan Tercapai',
                    text: 'Silakan tunggu beberapa saat sebelum mengirim ulang kode OTP.',
                    confirmButtonColor: '#f53003'
                });
            @elseif($errors->any())
                Swal.fire({
                    icon: 'error',
                    title: 'Terjadi Kesalahan',
                    text: '{{ $errors->first() }}',
                    confirmButtonColor: '#f53003'
                });
            @endif

            @if(session('status'))
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
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
                    const resendBtn = document.getElementById('resend-btn');
                    if (resendBtn) {
                        resendBtn.disabled = true;
                        resendBtn.style.opacity = '0.5';
                        resendBtn.style.cursor = 'not-allowed';
                        resendBtn.innerText = 'AKSES DIBATASI';
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Batas Permintaan Terlampaui',
                        html: '<div style="text-align: justify; font-size: 0.95rem; line-height: 1.5;">Mohon maaf, kami mendeteksi aktivitas permintaan yang terlalu sering pada akun Anda. Demi keamanan sistem, permintaan OTP telah dibatasi. <br/><br/>Jika Anda tidak menerima email, silakan <b>hubungi Administrator</b> untuk bantuan pemulihan akun secara manual.</div>',
                        confirmButtonColor: '#f53003',
                        confirmButtonText: 'Saya Mengerti'
                    });
                    cleanUrlNeeded = true;
                } else if (ssoThrottle) {
                    startResendCountdown(parseInt(ssoThrottle));
                    Swal.fire({
                        icon: 'warning',
                        title: 'Batas Permintaan Tercapai',
                        text: 'Silakan tunggu beberapa saat sebelum mengirim ulang kode OTP.',
                        confirmButtonColor: '#f53003'
                    });
                    cleanUrlNeeded = true;
                } else if (ssoError) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Terjadi Kesalahan',
                        text: ssoError,
                        confirmButtonColor: '#f53003'
                    });
                    cleanUrlNeeded = true;
                }
                
                if (ssoSuccess) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
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

        function handleResend(form) {
            const btn = document.getElementById('resend-btn');
            if (btn.disabled) return false;
            btn.disabled = true;
            btn.innerText = 'Mengirim...';
            return true;
        }
    </script>
</head>

<body>
    <button onclick="toggleTheme()" class="theme-btn" title="Toggle Theme"><i id="ti" class="fas fa-sun"></i></button>
    <div class="auth-card">
        <div class="auth-header">
            <img src="{{ asset('logo_dmi.png') }}" alt="darkotech AI Logo" class="brand-logo">
            <h1>Verifikasi OTP</h1>
            <p>Masukkan 6 digit kode yang dikirim ke<br><strong>{{ $email }}</strong></p>
        </div>
        
        <form action="{{ route('password.verify.post') }}" method="POST">
            @csrf
            <input type="hidden" name="is_iframe" id="is_iframe_input" value="0">
            <input type="hidden" name="email" value="{{ $email }}">
            <div class="form-group">
                <label class="form-label">Kode OTP</label>
                <div class="input-wrap">
                    <i class="fas fa-hashtag"></i>
                    <input type="text" name="otp" required {{ (request()->query('is_iframe') === '1' || request()->query('token')) ? '' : 'autofocus' }} maxlength="6" class="form-input"
                        placeholder="000000" style="text-align:center;">
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-check-circle"
                    style="margin-right:8px;"></i>VERIFIKASI SEKARANG</button>
        </form>
        <div class="auth-footer">
            Belum menerima email?
            <form action="{{ route('password.email') }}" method="POST" style="display:inline;" onsubmit="return handleResend(this)">
                @csrf
                <input type="hidden" name="is_iframe" id="resend_is_iframe_input" value="0">
                <input type="hidden" name="email" value="{{ $email }}">
                <button type="submit" id="resend-btn" class="resend-btn">Kirim Ulang</button>
            </form>
        </div>
    </div>
</body>
</html>