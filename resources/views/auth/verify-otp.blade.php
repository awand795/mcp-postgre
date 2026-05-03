<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi OTP - darkotech AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top left, #f3f4f6, #e5e7eb);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }
        html.dark body { background: radial-gradient(circle at top left, #1a1a1a, #000000); }
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            transition: all 0.3s ease;
        }
        html.dark .glass-panel {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.8);
        }
        .input-glass {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: #1f2937;
            transition: all 0.2s;
            text-align: center;
            letter-spacing: 0.5em;
            font-size: 1.5rem;
        }
        html.dark .input-glass {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        .input-glass:focus {
            background: #ffffff;
            border-color: #f53003;
            outline: none;
            box-shadow: 0 0 0 2px rgba(245, 48, 3, 0.2);
        }
        html.dark .input-glass:focus { background: rgba(255, 255, 255, 0.08); }
        .btn-primary {
            background: linear-gradient(135deg, #f53003, #ff4433);
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(245, 48, 3, 0.4);
        }
        .theme-toggle {
            position: absolute; top: 20px; right: 20px;
            background: rgba(255, 255, 255, 0.8); border: 1px solid rgba(0,0,0,0.1);
            padding: 10px; border-radius: 50%; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: all 0.3s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .theme-toggle:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.15); }
        html.dark .theme-toggle { background: rgba(255, 255, 255, 0.1); border-color: rgba(255, 255, 255, 0.2); color: white; }
    </style>
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                updateThemeIcon('light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                updateThemeIcon('dark');
            }
        }
        function updateThemeIcon(theme) {
            const icon = document.getElementById('theme-icon');
            if (theme === 'dark') {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />';
            } else {
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />';
            }
        }
        document.addEventListener('DOMContentLoaded', () => {
            updateThemeIcon(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        });
    </script>
</head>
<body class="p-6 relative">
    <button onclick="toggleTheme()" class="theme-toggle" title="Toggle Theme">
        <svg id="theme-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-800 dark:text-white"></svg>
    </button>

    <div class="w-full max-w-md glass-panel p-10 rounded-3xl relative">
        <div class="flex flex-col items-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Verifikasi OTP</h1>
            <p class="text-gray-500 dark:text-[#A1A09A] text-sm mt-2 text-center">Masukkan 6 digit kode OTP yang telah dikirimkan ke <strong>{{ $email }}</strong></p>
        </div>

        @if (session('status'))
            <div class="mb-4 text-sm font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 p-3 rounded-xl border border-green-200 dark:border-green-800">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('password.verify.post') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-[#A1A09A] uppercase tracking-wider mb-2 ml-1">Kode OTP</label>
                <input type="text" name="otp" required autofocus maxlength="6"
                    class="w-full input-glass rounded-xl px-4 py-3" placeholder="000000">
                @error('otp') <p class="text-red-500 text-[11px] mt-1 ml-1 text-center">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="w-full btn-primary text-white font-bold py-4 rounded-xl shadow-lg mt-2 text-sm tracking-wide">
                VERIFIKASI
            </button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-gray-500 dark:text-[#A1A09A] text-sm">
                Belum menerima email?
                <form action="{{ route('password.email') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <button type="submit" class="text-[#f53003] hover:text-[#ff4433] font-semibold transition-colors bg-transparent border-none cursor-pointer p-0">Kirim Ulang</button>
                </form>
            </p>
        </div>
    </div>
</body>
</html>
