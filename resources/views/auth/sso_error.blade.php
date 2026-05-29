<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('SSO Error') }}</title>
    <link rel="icon" href="{{ asset('logo_dmi.png') }}" type="image/png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, sans-serif;
            display: flex; align-items: center; justify-content: center;
            height: 100vh;
            background: #0b1120;
            color: #94a3b8;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 32px;
            max-width: 420px;
            text-align: center;
        }
        .icon { font-size: 40px; margin-bottom: 16px; }
        h2 { color: #f1f5f9; font-size: 18px; margin-bottom: 8px; }
        p { font-size: 14px; line-height: 1.6; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">⚠️</div>
    <h2>{{ __('Gagal Login SSO') }}</h2>
    <p>{{ $message }}</p>
</div>
</body>
</html>
