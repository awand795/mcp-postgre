<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - darkotech AI</title>
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
        :root{--primary:#f53003;--bg:#f0f2f8;--card:rgba(255,255,255,0.97);--border:rgba(99,102,241,0.12);--text:#1e293b;--muted:#64748b;--input-bg:rgba(255,255,255,0.9);--input-border:rgba(99,102,241,0.2);}
        html.dark{--bg:#0b1120;--card:rgba(17,24,39,0.92);--border:rgba(99,102,241,0.2);--text:#f1f5f9;--muted:#94a3b8;--input-bg:rgba(15,23,42,0.8);--input-border:rgba(99,102,241,0.3);}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Outfit',sans-serif;}
        body{background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;transition:background 0.4s;position:relative;}
        html.dark body{background:linear-gradient(135deg,#0b1120 0%,#0f172a 60%,#111827 100%);}
        body::before{content:'';position:fixed;width:600px;height:600px;background:radial-gradient(circle,rgba(245,48,3,0.06),transparent 70%);top:-200px;right:-100px;pointer-events:none;}
        .auth-card{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:2.5rem 2.25rem;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.08);position:relative;z-index:1;transition:background 0.4s,border-color 0.4s;}
        html.dark .auth-card{box-shadow:0 20px 60px rgba(0,0,0,0.4),0 0 0 1px rgba(99,102,241,0.15);}
        .auth-header{text-align:center;margin-bottom:2rem;}
        .brand-logo{width:70px;height:70px;object-fit:contain;margin-bottom:1.25rem;filter:drop-shadow(0 8px 16px rgba(245,48,3,0.15));}
        .auth-header h1{font-size:1.4rem;font-weight:700;color:var(--text);margin-bottom:4px;}
        .auth-header p{font-size:0.85rem;color:var(--muted);}
        .form-group{margin-bottom:1.25rem;}
        .form-label{display:block;margin-bottom:0.5rem;font-size:0.73rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted);}
        .input-wrap{position:relative;}
        .input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:0.85rem;}
        .form-input{width:100%;background:var(--input-bg);border:1px solid var(--input-border);border-radius:12px;padding:0.75rem 1rem 0.75rem 2.75rem;color:var(--text);font-size:0.9rem;transition:all 0.2s;font-family:'Outfit',sans-serif;}
        .form-input::placeholder{color:var(--muted);opacity:0.6;}
        .form-input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(245,48,3,0.12);}
        .form-error{color:#ef4444;font-size:0.75rem;margin-top:4px;}
        
        .toggle-password {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            cursor: pointer;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            z-index: 10;
        }
        .toggle-password:hover { 
            color: var(--primary);
            background: rgba(245,48,3,0.05);
            transform: translateY(-50%) scale(1.1);
        }
        html.dark .toggle-password:hover {
            background: rgba(245,48,3,0.1);
        }

        .btn-submit{width:100%;background:linear-gradient(135deg,#f53003,#ff4433);color:#fff;border:none;border-radius:12px;padding:0.9rem;font-size:0.9rem;font-weight:700;letter-spacing:0.04em;cursor:pointer;transition:all 0.25s;margin-top:0.5rem;box-shadow:0 4px 16px rgba(245,48,3,0.3);font-family:'Outfit',sans-serif;}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(245,48,3,0.4);}
        .theme-btn{position:fixed;top:20px;right:20px;width:42px;height:42px;border-radius:12px;background:var(--card);border:1px solid var(--border);color:var(--text);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,0.06);transition:all 0.2s;}
        .theme-btn:hover{border-color:var(--primary);color:var(--primary);}
        @media(max-width:480px){body{padding:0;align-items:flex-start;}.auth-card{border-radius:0;min-height:100vh;padding:2rem 1.5rem;display:flex;flex-direction:column;justify-content:center;}}
    </style>
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
        
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
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
        });
    </script>
</head>
<body>
    <button onclick="toggleTheme()" class="theme-btn" title="Toggle Theme"><i id="ti" class="fas fa-sun"></i></button>
    <div class="auth-card">
        <div class="auth-header">
            <img src="{{ asset('logo_dmi.png') }}" alt="darkotech AI Logo" class="brand-logo">
            <h1>Buat Password Baru</h1>
            <p>Masukkan password baru untuk akun Anda.</p>
        </div>
        <form action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="is_iframe" id="is_iframe_input" value="0">
            <input type="hidden" name="email" value="{{ $email }}">
            <input type="hidden" name="otp" value="{{ $otp }}">
            <div class="form-group">
                <label class="form-label">Password Baru</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" required {{ (request()->query('is_iframe') === '1' || request()->query('token')) ? '' : 'autofocus' }}
                        class="form-input" placeholder="Min. 8 karakter" style="padding-right: 2.75rem;">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password', 'eye-icon-1')">
                        <i id="eye-icon-1" class="fas fa-eye"></i>
                    </button>
                </div>
                @error('password')
                    <p class="form-error">{{ $message }}</p>
                @else
                    @if(request()->query('sso_error'))
                        <p class="form-error">{{ request()->query('sso_error') }}</p>
                    @endif
                @enderror
            </div>
            <div class="form-group">
                <label class="form-label">Konfirmasi Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                        class="form-input" placeholder="Ulangi password" style="padding-right: 2.75rem;">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password_confirmation', 'eye-icon-2')">
                        <i id="eye-icon-2" class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-save" style="margin-right:8px;"></i>SIMPAN PASSWORD BARU</button>
        </form>
    </div>
</body>
</html>
