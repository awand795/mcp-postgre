<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Memuat DarkoAI...</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, sans-serif;
            display: flex; align-items: center; justify-content: center;
            height: 100vh;
            background: #0b1120;
            color: #94a3b8;
        }
        .loader {
            text-align: center;
        }
        .spinner {
            width: 40px; height: 40px;
            border: 3px solid #1e293b;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        p { font-size: 14px; }
    </style>
</head>
<body>
<div class="loader">
    <div class="spinner"></div>
    <p>Memuat DarkoAI untuk {{ $user_name }}...</p>
</div>

<script>
    (function () {
        var token = @json($token);
        var redirectTo = @json($redirect_to);

        // 1. Kirim token ke parent window (ERP) via postMessage
        //    ERP bisa mendengarkan event ini untuk keperluan logging/refresh
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'DARKOAI_SSO_TOKEN',
                    token: token,
                    timestamp: Date.now()
                }, '*');
            }
        } catch (e) {
            // Cross-origin postMessage kadang throw di beberapa browser — aman diabaikan
        }

        // 2. Simpan token di sessionStorage iframe ini sendiri
        //    (sessionStorage tidak cross-site, hanya untuk iframe ini)
        try {
            sessionStorage.setItem('_darkoai_bearer', token);
        } catch (e) {}

        // 3. Redirect ke halaman chatbot dengan menyertakan token di query parameter
        //    agar middleware auth.smart bisa melakukan handshake pertama kali.
        window.location.href = redirectTo + (redirectTo.indexOf('?') === -1 ? '?' : '&') + 'token=' + encodeURIComponent(token);
    })();
</script>
</body>
</html>
