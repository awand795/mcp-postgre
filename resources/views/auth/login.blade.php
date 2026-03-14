<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - MCP Chatbot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top left, #1a1a1a, #000000);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.8);
        }
        .input-glass {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.2s;
        }
        .input-glass:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #f53003;
            outline: none;
            box-shadow: 0 0 0 2px rgba(245, 48, 3, 0.2);
        }
        .btn-primary {
            background: linear-gradient(135deg, #f53003, #ff4433);
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(245, 48, 3, 0.4);
        }
        .checkbox-custom {
            accent-color: #f53003;
        }

        /* Mobile Responsive */
        @media (max-width: 480px) {
            body {
                padding: 0 !important;
                align-items: flex-start;
            }

            .glass-panel {
                border-radius: 0 !important;
                min-height: 100vh;
                padding: 1.5rem !important;
            }

            .w-16 {
                width: 3rem !important;
                height: 3rem !important;
            }

            .w-10 {
                width: 2rem !important;
                height: 2rem !important;
            }

            .text-2xl {
                font-size: 1.5rem !important;
            }

            .input-glass {
                padding: 0.75rem !important;
            }

            button[type="submit"] {
                padding: 0.875rem !important;
            }
        }
    </style>
</head>
<body class="p-6">

    <div class="w-full max-w-md glass-panel p-10 rounded-3xl">
        <div class="flex flex-col items-center mb-10">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#f53003] to-[#ff4433] flex items-center justify-center shadow-lg shadow-red-500/20 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white">Selamat Datang</h1>
            <p class="text-[#A1A09A] text-sm mt-1">Masuk untuk mengakses MCP Chatbot</p>
        </div>

        <form action="{{ route('login.post') }}" method="POST" class="space-y-6">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-[#A1A09A] uppercase tracking-wider mb-2 ml-1">Alamat Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full input-glass rounded-xl px-4 py-3.5 text-sm" placeholder="email@contoh.com">
                @error('email') <p class="text-red-500 text-[11px] mt-1 ml-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex justify-between items-center mb-2 ml-1">
                    <label class="block text-xs font-semibold text-[#A1A09A] uppercase tracking-wider">Password</label>
                    <a href="#" class="text-[10px] text-[#A1A09A] hover:text-white transition-colors">Lupa pass?</a>
                </div>
                <input type="password" name="password" required
                       class="w-full input-glass rounded-xl px-4 py-3.5 text-sm" placeholder="••••••••">
                @error('password') <p class="text-red-500 text-[11px] mt-1 ml-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center ml-1">
                <input type="checkbox" name="remember" id="remember" class="checkbox-custom w-4 h-4 rounded border-white/10 bg-white/5">
                <label for="remember" class="ml-2 text-xs text-[#A1A09A] cursor-pointer">Ingat saya</label>
            </div>

            <button type="submit" class="w-full btn-primary text-white font-bold py-4 rounded-xl shadow-lg mt-2 text-sm tracking-wide">
                MASUK SEKARANG
            </button>
        </form>

        <div class="mt-10 text-center">
            <p class="text-[#A1A09A] text-sm">
                Belum punya akun? 
                <a href="{{ route('register') }}" class="text-[#f53003] hover:text-[#ff4433] font-semibold transition-colors">Daftar sekarang</a>
            </p>
        </div>
    </div>

</body>
</html>
