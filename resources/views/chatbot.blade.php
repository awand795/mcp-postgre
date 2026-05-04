<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Darko AI</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Client-Side PDF Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    
    <!-- Client-Side Excel Generation with Styling -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top left, #f3f4f6, #e5e7eb);
            height: 100vh;
            overflow: hidden;
            color: #1f2937;
            transition: background 0.3s ease;
        }
        html.dark body {
            background: linear-gradient(135deg, #0b1120 0%, #0f172a 60%, #111827 100%);
            color: #f1f5f9;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }
        html.dark .glass-panel {
            background: rgba(15,23,42,0.85);
            border: 1px solid rgba(99,102,241,0.15);
            box-shadow: 0 8px 40px 0 rgba(0,0,0,0.7);
        }
        .chat-bubble-user {
            background: linear-gradient(135deg, #f53003, #ff4433);
            color: white;
            border-bottom-right-radius: 4px;
        }
        .chat-bubble-ai {
            background: #ffffff;
            color: #1f2937;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        html.dark .chat-bubble-ai {
            background: rgba(17,24,39,0.9);
            color: #e2e8f0;
            border: 1px solid rgba(99,102,241,0.15);
            box-shadow: none;
        }
        /* Tool Call Badge */
        .tool-call-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 20px; font-size: 11px;
            font-weight: 500; margin: 2px 0; border: 1px solid; transition: all 0.3s;
        }
        .tool-call-badge.running { background: rgba(245,158,11,0.12); border-color: rgba(245,158,11,0.3); color: #fbbf24; }
        .tool-call-badge.done    { background: rgba(16,185,129,0.1);  border-color: rgba(16,185,129,0.25); color: #34d399; }
        .tool-call-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .tool-call-dot.running { animation: pulse-dot 1s infinite; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.12); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.2); }
        html.dark ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); }
        html.dark ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* Typing dots */
        .typing-indicator span {
            display: inline-block; width: 4px; height: 4px;
            background-color: #A1A09A; border-radius: 50%; margin-right: 2px;
            animation: bounce 1.4s infinite ease-in-out both;
        }
        .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
        .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce { 0%,80%,100%{transform:scale(0)} 40%{transform:scale(1.0)} }

        /* AI Loading Card */
        .ai-loading-card { display:flex; flex-direction:column; gap:10px; min-width:240px; }
        .ai-loading-top { display:flex; align-items:center; gap:10px; }
        .ai-loading-icon-wrap {
            width:32px; height:32px; border-radius:9px; flex-shrink:0;
            background:linear-gradient(135deg,rgba(245,48,3,0.18),rgba(245,48,3,0.04));
            border:1px solid rgba(245,48,3,0.22);
            display:flex; align-items:center; justify-content:center; font-size:15px;
            animation:icon-breathe 2s ease-in-out infinite;
        }
        @keyframes icon-breathe { 0%,100%{box-shadow:0 0 0 0 rgba(245,48,3,0.18)} 50%{box-shadow:0 0 0 6px rgba(245,48,3,0)} }
        .ai-loading-text { flex:1; overflow:hidden; }
        .ai-loading-label { font-size:12px; font-weight:600; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        html.dark .ai-loading-label { color:#fff; }
        .ai-loading-label.anim { animation:txt-in 0.35s ease; }
        @keyframes txt-in { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:translateY(0)} }
        .ai-loading-sub { font-size:10px; color:#706f6c; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .ai-loading-bar-wrap { width:100%; height:2px; background:rgba(0,0,0,0.06); border-radius:2px; overflow:hidden; }
        html.dark .ai-loading-bar-wrap { background:rgba(255,255,255,0.06); }
        .ai-loading-bar {
            height:100%; width:35%;
            background:linear-gradient(90deg,transparent,#f53003,transparent);
            animation:bar-sweep 1.5s ease-in-out infinite;
        }
        @keyframes bar-sweep { 0%{transform:translateX(-150%)} 100%{transform:translateX(450%)} }

        /* Status Bar */
        .status-bar {
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px; background: rgba(255,255,255,0.05);
            z-index: 50; overflow: hidden;
        }
        .status-bar.active {
            background: rgba(245,48,3,0.1);
        }
        .status-bar-progress {
            position: absolute; top: 0; left: 0; bottom: 0;
            width: 100%;
            background: linear-gradient(90deg, #f53003, #ff6b6b, #f53003);
            background-size: 200% 100%;
            animation: progress-pulse 2s ease-in-out infinite;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .status-bar.active .status-bar-progress {
            transform: translateX(0);
        }
        @keyframes progress-pulse {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* SweetAlert2 Toast Custom Styles */
        .swal2-toast {
            background: rgba(0, 0, 0, 0.85) !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5) !important;
            border-radius: 12px !important;
            padding: 12px 16px !important;
        }
        .swal2-toast .swal2-title {
            color: #fff !important;
            font-size: 13px !important;
            font-weight: 500 !important;
        }
        .swal2-toast.swal2-success {
            border-color: rgba(16, 185, 129, 0.3) !important;
        }
        .swal2-toast.swal2-success .swal2-title {
            color: #34d399 !important;
        }
        .swal2-toast.swal2-error {
            border-color: rgba(239, 68, 68, 0.3) !important;
        }
        .swal2-toast.swal2-error .swal2-title {
            color: #f87171 !important;
        }
        .swal2-toast.swal2-info {
            border-color: rgba(59, 130, 246, 0.3) !important;
        }
        .swal2-toast.swal2-info .swal2-title {
            color: #60a5fa !important;
        }
        .swal2-confirm {
            background-color: #f53003 !important;
            font-size: 13px !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
        }


        /* Chat Area */
        #chat-messages {
            overflow-x: hidden;
        }

        /* Markdown styles */
        .markdown-body { line-height: 1.6; }
        .markdown-body p { margin: 6px 0; font-size: 13px; }
        .markdown-body h1,.markdown-body h2 { font-size: 15px; font-weight: 700; color: #1f2937; margin: 16px 0 8px; }
        html.dark .markdown-body h1, html.dark .markdown-body h2 { color: #fff; }
        .markdown-body h3 { font-size: 14px; font-weight: 600; color: #f97316; margin: 14px 0 6px; border-bottom: 1px solid rgba(0,0,0,0.08); padding-bottom: 4px; }
        html.dark .markdown-body h3 { border-bottom-color: rgba(255,255,255,0.08); }
        .markdown-body h4 { font-size: 13px; font-weight: 600; color: #fb923c; margin: 10px 0 4px; }
        .markdown-body ul,.markdown-body ol { padding-left: 18px; margin: 6px 0; }
        .markdown-body li { margin: 3px 0; font-size: 13px; }
        .markdown-body strong { color: #111827; font-weight: 600; }
        html.dark .markdown-body strong { color: #ffffff; }
        .markdown-body em { color: #4b5563; font-style: italic; }
        html.dark .markdown-body em { color: #d4d4d0; }
        .markdown-body code { background: rgba(0,0,0,0.05); padding: 1px 5px; border-radius: 4px; font-family: monospace; font-size: 11px; color: #ea580c; }
        html.dark .markdown-body code { background: rgba(255,255,255,0.1); color: #fb923c; }
        .markdown-body pre { background: #1f2937; padding: 10px; border-radius: 8px; margin: 8px 0; overflow-x: auto; border: 1px solid rgba(0,0,0,0.1); }
        html.dark .markdown-body pre { background: rgba(0,0,0,0.4); border-color: rgba(255,255,255,0.08); }
        .markdown-body pre code { background: none; padding: 0; color: inherit; font-size: 12px; }
        .markdown-body .table-wrap { 
            overflow-x: auto; 
            margin: 12px 0; 
            border-radius: 8px; 
            border: 1px solid rgba(0,0,0,0.1);
            width: 100%;
            max-width: 100%;
        }
        html.dark .markdown-body .table-wrap { border-color: rgba(255,255,255,0.1); }
        .markdown-body table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 500px; }
        .markdown-body table thead tr { background: rgba(245,48,3,0.1); }
        html.dark .markdown-body table thead tr { background: rgba(245,48,3,0.2); }
        .markdown-body table th { padding: 9px 14px; text-align: left; font-weight: 600; color: #111827; white-space: nowrap; border-bottom: 2px solid rgba(245,48,3,0.3); }
        html.dark .markdown-body table th { color: #fff; border-bottom-color: rgba(245,48,3,0.4); }
        .markdown-body table td { padding: 8px 14px; border-bottom: 1px solid rgba(0,0,0,0.05); color: #374151; white-space: nowrap; }
        html.dark .markdown-body table td { border-bottom-color: rgba(255,255,255,0.06); color: #d4d4d0; }
        .markdown-body table tbody tr:hover { background: rgba(0,0,0,0.02); }
        html.dark .markdown-body table tbody tr:hover { background: rgba(255,255,255,0.04); }
        .markdown-body table tbody tr:last-child td { border-bottom: none; }
        .markdown-body blockquote { border-left: 3px solid #f97316; padding-left: 12px; margin: 8px 0; color: #6b7280; font-style: italic; font-size: 12px; }
        html.dark .markdown-body blockquote { color: #A1A09A; }
        .markdown-body hr { border: none; border-top: 1px solid rgba(0,0,0,0.1); margin: 12px 0; }
        html.dark .markdown-body hr { border-top-color: rgba(255,255,255,0.08); }

        /* Chart Container */
        .chart-container {
            background: rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 12px; padding: 0 0 25px 0; margin: 24px 0;
            width: 100%; min-height: 400px; height: auto; position: relative;
            overflow: hidden;
        }
        html.dark .chart-container {
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .chart-container canvas {
            position: relative; z-index: 1;
            max-height: 330px;
            padding: 0 15px;
        }
        .chart-container .chart-toolbar {
            margin-bottom: 5px;
        }
        .chart-container + .markdown-body,
        .chart-container + p {
            margin-top: 15px;
        }
        .chart-toolbar {
            display: flex; justify-content: flex-end; gap: 8px;
            margin-bottom: 10px; padding: 0 15px 10px 15px;
            border-bottom: 1px solid rgba(0,0,0,0.07);
        }
        html.dark .chart-toolbar {
            border-bottom-color: rgba(255,255,255,0.08);
        }
        .chart-export-btn {
            background: rgba(34,197,94,0.15); color: #22c55e;
            border: 1px solid rgba(34,197,94,0.3);
            padding: 6px 12px; border-radius: 6px;
            font-size: 11px; cursor: pointer;
            transition: all 0.2s; font-family: 'Outfit', sans-serif;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .chart-export-btn:hover:not(:disabled) {
            background: rgba(34,197,94,0.25); color: #4ade80; border-color: rgba(34,197,94,0.5);
        }
        .chart-export-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .chart-export-pdf-btn {
            background: rgba(239,68,68,0.15); color: #ef4444;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 6px 12px; border-radius: 6px;
            font-size: 11px; cursor: pointer;
            transition: all 0.2s; font-family: 'Outfit', sans-serif;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .chart-export-pdf-btn:hover:not(:disabled) {
            background: rgba(239,68,68,0.25); color: #f87171; border-color: rgba(239,68,68,0.5);
        }
        .chart-export-pdf-btn:disabled { opacity: 0.3; cursor: not-allowed; }

        /* ── Dashboard ── */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin: 24px 0;
            width: 100%;
        }
        .metric-card {
            background: rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.3s ease;
        }
        html.dark .metric-card {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .metric-card:hover {
            background: rgba(0,0,0,0.05);
            border-color: rgba(245, 48, 3, 0.3);
            transform: translateY(-2px);
        }
        html.dark .metric-card:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .metric-label {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        html.dark .metric-label { color: #A1A09A; }
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }
        html.dark .metric-value { color: #fff; }
        .metric-change {
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .metric-change.up { color: #34d399; }
        .metric-change.down { color: #f87171; }
        .metric-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 4px;
        }

        /* ── Smart Table ── */
        .smart-table-wrap {
            margin: 24px 0; border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.08);
            overflow: hidden; background: rgba(0,0,0,0.02);
            max-width: 100%;
        }
        html.dark .smart-table-wrap {
            border-color: rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }
        .smart-table-toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px; padding: 8px 12px;
            background: rgba(245,48,3,0.06);
            border-bottom: 1px solid rgba(0,0,0,0.06); flex-wrap: wrap;
        }
        html.dark .smart-table-toolbar {
            background: rgba(245,48,3,0.08);
            border-bottom-color: rgba(255,255,255,0.08);
        }
        .smart-table-title {
            padding: 12px 14px 10px;
            font-weight: 600;
            font-size: 13px;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(245,48,3,0.06);
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        html.dark .smart-table-title {
            color: #fff;
            background: rgba(245,48,3,0.12);
            border-bottom-color: rgba(255,255,255,0.06);
        }
        .chart-title {
            padding: 12px 14px 10px;
            font-weight: 600;
            font-size: 13px;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(245,48,3,0.06);
            border-bottom: 1px solid rgba(0,0,0,0.06);
            margin-bottom: 10px;
        }
        html.dark .chart-title {
            color: #fff;
            background: rgba(245,48,3,0.12);
            border-bottom-color: rgba(255,255,255,0.06);
        }
        .smart-table-info { font-size: 11px; color: #6b7280; white-space: nowrap; }
        html.dark .smart-table-info { color: #A1A09A; }
        .smart-table-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .smart-table-search {
            flex: 1; min-width: 120px; max-width: 220px;
            background: rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.1);
            border-radius: 6px; padding: 4px 9px; font-size: 11px; color: #1f2937;
            outline: none; font-family: 'Outfit', sans-serif;
        }
        html.dark .smart-table-search {
            background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.12); color: #fff;
        }
        .smart-table-export-btn {
            background: rgba(34,197,94,0.15); color: #22c55e;
            border: 1px solid rgba(34,197,94,0.3);
            padding: 4px 10px; border-radius: 6px;
            font-size: 11px; cursor: pointer;
            transition: all 0.2s; font-family: 'Outfit', sans-serif;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .smart-table-export-btn:hover:not(:disabled) {
            background: rgba(34,197,94,0.25); color: #4ade80; border-color: rgba(34,197,94,0.5);
        }
        .smart-table-export-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .smart-table-export-pdf-btn {
            background: rgba(239,68,68,0.15); color: #ef4444;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 4px 10px; border-radius: 6px;
            font-size: 11px; cursor: pointer;
            transition: all 0.2s; font-family: 'Outfit', sans-serif;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .smart-table-export-pdf-btn:hover:not(:disabled) {
            background: rgba(239,68,68,0.25); color: #f87171; border-color: rgba(239,68,68,0.5);
        }
        .smart-table-export-pdf-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .smart-table-search::placeholder { color: rgba(0,0,0,0.25); }
        html.dark .smart-table-search::placeholder { color: rgba(255,255,255,0.25); }
        .smart-table-search:focus { border-color: rgba(245,48,3,0.5); }
        .smart-table-scroll { 
            overflow-x: auto; 
            width: 100%;
            -webkit-overflow-scrolling: touch;
        }
        .smart-table-scroll table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 600px; table-layout: auto; }
        .smart-table-scroll thead tr { background: rgba(245,48,3,0.08); }
        html.dark .smart-table-scroll thead tr { background: rgba(245,48,3,0.15); }
        .smart-table-scroll th {
            padding: 8px 13px; text-align: left; font-weight: 600; color: #1f2937;
            white-space: nowrap; border-bottom: 2px solid rgba(245,48,3,0.2);
            cursor: pointer; user-select: none; font-size: 11px;
        }
        html.dark .smart-table-scroll th { color: #fff; border-bottom-color: rgba(245,48,3,0.35); }
        .smart-table-scroll th:hover { background: rgba(245,48,3,0.25); }
        .smart-table-scroll th .sort-icon { margin-left: 4px; opacity: 0.4; font-size: 10px; }
        .smart-table-scroll th.sort-asc .sort-icon,
        .smart-table-scroll th.sort-desc .sort-icon { opacity: 1; color: #f53003; }
        .smart-table-scroll td {
            padding: 7px 13px !important; border-bottom: 1px solid rgba(0,0,0,0.06) !important;
            color: #374151 !important; max-width: 400px !important; font-size: 11px !important;
            overflow: hidden !important; text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        html.dark .smart-table-scroll td {
            border-bottom-color: rgba(255,255,255,0.05) !important;
            color: #d4d4d0 !important;
        }
        .smart-table-scroll td.wrap { white-space: normal !important; line-height: 1.4 !important; min-width: 200px !important; }
        .smart-table-scroll tbody tr:hover { background: rgba(0,0,0,0.03); }
        html.dark .smart-table-scroll tbody tr:hover { background: rgba(255,255,255,0.04); }
        .smart-table-scroll tbody tr:last-child td { border-bottom: none; }
        .smart-table-pagination {
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px; padding: 7px 12px;
            border-top: 1px solid rgba(0,0,0,0.07);
            background: rgba(0,0,0,0.02); flex-wrap: wrap;
        }
        html.dark .smart-table-pagination {
            border-top-color: rgba(255,255,255,0.07);
            background: rgba(0,0,0,0.15);
        }
        .smart-table-page-info { font-size: 11px; color: #6b7280; }
        html.dark .smart-table-page-info { color: #706f6c; }
        .smart-table-btns { display: flex; gap: 4px; flex-wrap: wrap; }
        .st-btn {
            padding: 3px 9px; border-radius: 5px;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(0,0,0,0.04); color: #6b7280;
            font-size: 11px; cursor: pointer; transition: all 0.15s;
            font-family: 'Outfit', sans-serif;
        }
        html.dark .st-btn {
            border-color: rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.04); color: #A1A09A;
        }
        .st-btn:hover:not(:disabled) { background: rgba(245,48,3,0.15); color: #ff4433; border-color: rgba(245,48,3,0.3); }
        .st-btn:disabled { opacity: 0.3; cursor: default; }
        .st-btn.active { background: rgba(245,48,3,0.2); color: #ff4433; border-color: rgba(245,48,3,0.4); font-weight: 600; }

        .btn-clear { transition: all 0.2s; }
        .btn-clear:hover { background: rgba(245,48,3,0.15); color: #ff4433; }

        /* Mobile */
        @media (max-width: 768px) {
            .glass-panel { border-radius: 20px !important; }
            .header-actions .btn-text { display: none; }
            .header-actions a, .header-actions button { padding: 0.5rem !important; min-width: 40px; justify-content: center; }
            .chat-bubble-user, .chat-bubble-ai { max-width: 90% !important; }
            .markdown-body p, .markdown-body li { font-size: 12px; }
            .markdown-body h1, .markdown-body h2 { font-size: 14px; }
            .markdown-body h3 { font-size: 13px; }
            .markdown-body code { font-size: 10px; padding: 1px 4px; }
            .markdown-body pre { padding: 8px; }
            .markdown-body table { font-size: 11px; }
            .markdown-body table th, .markdown-body table td { padding: 6px 10px; }
        }
        @media (max-width: 480px) {
            body { padding: 0 !important; }
            .glass-panel { height: 100vh !important; max-height: 100vh !important; border-radius: 0 !important; border: none !important; }
            .p-5 { padding: 0.75rem !important; }
            .p-6 { padding: 1rem !important; }
            .w-10 { width: 2.25rem !important; height: 2.25rem !important; }
            .header-actions { gap: 0.25rem !important; }
            .chat-bubble-user, .chat-bubble-ai { max-width: 95% !important; }
            .chat-bubble-ai { max-width: 98% !important; }
            .markdown-body p, .markdown-body li { font-size: 11px; }
            .markdown-body h1, .markdown-body h2 { font-size: 13px; }
            .markdown-body h3 { font-size: 12px; }
            .markdown-body code { font-size: 9px; }
            .markdown-body table { font-size: 10px; }
            .markdown-body table th, .markdown-body table td { padding: 5px 8px; }
            #message-input { font-size: 14px !important; padding-left: 12px !important; }
            #send-btn { width: 36px !important; }
            .text-\[10px\] { font-size: 9px !important; }
        }
        .smart-table-btn {
            background: rgba(245, 48, 3, 0.1);
            color: #f53003;
            border: 1px solid rgba(245, 48, 3, 0.2);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            margin: 0 2px;
        }
        .smart-table-btn:hover:not(:disabled) {
            background: #f53003;
            color: white;
        }
        .smart-table-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        /* Sidebar fixes */
        #chat-sidebar {
            background-color: #ffffff !important;
        }
        #chat-sidebar.open {
            background-color: rgba(255, 255, 255, 0.98) !important;
        }
        html.dark #chat-sidebar {
            background-color: #0a0a0a !important;
        }
        html.dark #chat-sidebar.open {
            background-color: rgba(10, 10, 10, 0.98) !important;
        }
        
        #history-list .group {
            cursor: pointer;
            user-select: none;
        }
        #history-list .group:active {
            background-color: rgba(0,0,0,0.1) !important;
        }
        html.dark #history-list .group:active {
            background-color: rgba(255,255,255,0.15) !important;
        }
        
        /* Delete Modal Animations */
        #delete-modal.show {
            display: flex;
        }
        #delete-modal.show .delete-modal-backdrop {
            opacity: 1;
        }
        #delete-modal.show .delete-modal-content {
            transform: scale(1);
            opacity: 1;
        }

        /* ERP Guidance Video Player Styling */
        .erp-video-container {
            margin-top: 12px;
            width: 100%;
        }
        .erp-video-container video {
            max-height: 480px;
            object-fit: contain;
            background: #000;
        }
        .erp-video-container video::-webkit-media-controls-panel {
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
        }

        @media (max-width: 768px) {
            .erp-video-container {
                max-width: 100% !important;
            }
        }

        /* ── Typewriter Cursor ── */
        .typing-cursor {
            display: inline-block;
            width: 2px;
            height: 1em;
            background: #f53003;
            margin-left: 2px;
            vertical-align: text-bottom;
            border-radius: 1px;
            animation: cursor-blink 0.8s steps(1) infinite;
        }
        @keyframes cursor-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
        .typing-cursor.done {
            animation: cursor-fade 0.4s ease forwards;
        }
        @keyframes cursor-fade {
            from { opacity: 1; }
            to   { opacity: 0; width: 0; margin: 0; }
        }
    </style>
</head>

<body class="flex items-center justify-center p-2 md:p-4">

    <!-- Main Container -->
    <div class="flex w-full max-w-6xl h-[95vh] glass-panel rounded-3xl overflow-hidden relative">

        <!-- Sidebar (Pushes content, not overlay) -->
        <aside id="chat-sidebar" class="w-72 bg-white/90 dark:bg-[#0a0a0a]/95 border-r border-gray-200 dark:border-white/10 flex flex-col transition-all duration-300 flex-shrink-0 overflow-hidden" style="width: 0; opacity: 0;">

            <!-- Sidebar Header / Mobile Close -->
            <div class="p-4 border-b border-gray-200 dark:border-white/10 flex items-center justify-between flex-shrink-0" style="min-width: 288px;">
                <button id="btn-new-chat" class="flex-1 flex items-center justify-center gap-2 py-2 bg-gradient-to-r from-[#f53003]/10 dark:from-[#f53003]/20 to-[#ff4433]/10 dark:to-[#ff4433]/20 hover:from-[#f53003]/20 dark:hover:from-[#f53003]/40 hover:to-[#ff4433]/20 dark:hover:to-[#ff4433]/40 text-gray-800 dark:text-white border border-[#f53003]/30 dark:border-[#f53003]/50 rounded-xl text-sm font-medium transition-all shadow-lg shadow-red-500/5 dark:shadow-red-500/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Chat Baru
                </button>
                <button id="btn-close-sidebar" class="ml-3 p-1.5 text-gray-400 dark:text-[#A1A09A] hover:text-gray-700 dark:hover:text-white rounded-lg hover:bg-black/5 dark:hover:bg-white/10 transition-colors flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <!-- History List -->
            <div class="px-4 py-3 pb-1 text-[11px] font-semibold text-gray-400 dark:text-white/40 uppercase tracking-wider flex-shrink-0" style="min-width: 288px;">Riwayat Terakhir</div>
            <div id="history-list" class="flex-1 overflow-y-auto p-2 space-y-1 custom-scrollbar" style="min-width: 288px;">
                <!-- JS populated -->
                <div class="flex items-center justify-center h-full text-[#A1A09A] text-xs opacity-50">Memuat riwayat...</div>
            </div>
        </aside>

        <!-- Overlay (hanya untuk mobile/backdrop click) -->
        <div id="sidebar-overlay" class="absolute inset-0 bg-black/60 z-0 hidden backdrop-blur-sm transition-opacity opacity-0" style="pointer-events: none;"></div>

        <!-- Main Chat Area (flex-1, akan bergeser saat sidebar buka) -->
        <div class="flex flex-col flex-1 min-w-0 h-full">
        <div class="p-4 md:p-5 border-b border-black/10 dark:border-white/10 flex items-center justify-between flex-shrink-0 transition-all duration-300 bg-white/60 dark:bg-black/20">
            <div class="flex items-center gap-2 md:gap-3 w-full max-w-full">
                <!-- Hamburger and New Chat for Header -->
                <div class="flex items-center">
                    <button id="btn-open-sidebar" title="Toggle Sidebar" class="p-1.5 md:p-2 -ml-1 text-gray-500 dark:text-[#A1A09A] hover:text-gray-800 dark:hover:text-white rounded-lg hover:bg-black/5 dark:hover:bg-white/10 transition-colors cursor-pointer select-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <button id="btn-new-chat-header" title="Chat Baru" class="hidden p-1.5 md:p-2 text-gray-500 dark:text-[#A1A09A] hover:text-gray-800 dark:hover:text-white rounded-lg hover:bg-black/5 dark:hover:bg-white/10 transition-colors cursor-pointer select-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>
                
                <img src="{{ asset('logo_dmi.png') }}" alt="Darko AI Logo" class="w-8 h-8 md:w-10 md:h-10 object-contain ml-1">
                <div class="min-w-0">
                <h1 class="text-gray-900 dark:text-white font-semibold text-base md:text-lg leading-tight truncate">darkotech AI</h1>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-[11px] md:text-xs text-[#A1A09A]">Online</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 header-actions flex-shrink-0">
                @if(auth()->user()->is_admin)
                    <a href="{{ route('admin.dashboard') }}" title="Admin Dashboard"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-[#818cf8] text-xs border border-indigo-500/20 bg-indigo-500/10 hover:bg-indigo-500/20 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                        <span class="btn-text">Admin Dashboard</span>
                    </a>
                @endif
                <button id="btn-clear-chat" title="Hapus riwayat obrolan ini"
                    class="btn-clear hidden md:flex items-center gap-1.5 px-3 py-2 rounded-xl text-gray-500 dark:text-[#A1A09A] text-xs border border-black/10 dark:border-white/10 hover:border-red-500/30 hover:bg-black/10 dark:hover:bg-black/20 transition-all focus:ring-1 focus:ring-red-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                        <path d="M10 11v6M14 11v6M9 6V4h6v2" />
                    </svg>
                    <span class="btn-text">Hapus Riwayat</span>
                </button>
                <button id="btn-clear-chat-mobile" title="Hapus riwayat obrolan ini"
                    class="btn-clear md:hidden flex items-center p-2 rounded-xl text-gray-500 dark:text-[#A1A09A] border border-transparent hover:border-red-500/30 hover:bg-black/10 dark:hover:bg-black/20 hover:text-red-500 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><path d="M10 11v6M14 11v6M9 6V4h6v2" />
                    </svg>
                </button>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" title="Keluar"
                        class="btn-clear flex items-center gap-1.5 px-3 py-2 rounded-xl text-gray-500 dark:text-[#A1A09A] text-xs border border-black/10 dark:border-white/10 hover:border-red-500/30 hover:text-red-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        <span class="btn-text">Logout</span>
                    </button>
                </form>
                <!-- Theme Toggle Switch -->
                <button onclick="toggleTheme()" id="theme-toggle-btn" title="Toggle Theme"
                    class="flex items-center gap-2 px-3 py-2 rounded-xl text-gray-500 dark:text-[#A1A09A] text-xs border border-black/10 dark:border-white/10 hover:border-indigo-500/30 hover:text-indigo-500 transition-all">
                    <i class="fas fa-moon" id="theme-icon"></i>
                    <span class="btn-text" id="theme-toggle-label">Dark</span>
                </button>
            </div>
        </div>

        <!-- Chat Area -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-4 md:p-6 space-y-5 custom-scrollbar relative">
            <!-- Status Bar -->
            <div id="status-bar" class="status-bar">
                <div class="status-bar-progress"></div>
            </div>
            
            <!-- Initial content will be cleared if history is loaded, otherwise defaults to welcome message -->
            <div class="flex flex-col items-start gap-1.5 max-w-[90%] md:max-w-[85%]">
                <div class="chat-bubble-ai p-4 rounded-2xl text-sm shadow-sm markdown-body">
                    <p>Halo! Saya <strong>darkotech AI</strong> 👋</p>
                    <p style="margin-top:6px">Apa yang bisa saya bantu untuk mempermudah urusan Anda hari ini?</p>
                </div>
            </div>
        </div>

        <!-- Typing Indicator (now integrated in input area) -->


        <!-- Input -->
        <div class="p-5 border-t border-black/10 dark:border-white/10 flex-shrink-0 bg-white/50 dark:bg-black/20">
            <div class="relative">
                <input type="text" id="message-input" placeholder="Ketik pesan anda di sini..."
                    class="w-full bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 rounded-2xl py-3.5 pl-5 pr-14 text-gray-900 dark:text-white placeholder-black/25 dark:placeholder-white/25 focus:outline-none focus:ring-2 focus:ring-[#f53003]/40 transition-all text-sm"
                    autocomplete="off">
                <button id="send-btn"
                    class="absolute right-2 top-1.5 bottom-1.5 w-10 bg-[#f53003] hover:bg-[#ff4433] disabled:opacity-40 text-white rounded-xl flex items-center justify-center transition-all shadow-lg shadow-red-500/20">
                    <svg id="send-icon" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14" /><path d="m12 5 7 7-7 7" />
                    </svg>
                    <svg id="loading-icon" class="w-4 h-4 hidden animate-spin" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                    </svg>
                </button>
            </div>
            
            <!-- Floating Status Indicator -->
            <div id="typing-indicator" class="hidden absolute -top-8 left-1/2 transform -translate-x-1/2 whitespace-nowrap">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-black/80 backdrop-blur-sm border border-white/10 shadow-lg">
                    <svg class="animate-spin h-4 w-4 text-[#f53003]" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span id="typing-text" class="text-xs text-white font-medium">AI sedang berpikir...</span>
                </div>
            </div>
            
            <p class="text-[10px] text-center text-[#706f6c] mt-3 uppercase tracking-widest leading-relaxed">
                Powered by darkotech<br/>
            </p>
        </div>

        </div> <!-- End Main Content -->
    </div> <!-- End Glass Panel -->

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 z-[100] hidden items-center justify-center">
        <!-- Backdrop -->
        <div class="delete-modal-backdrop absolute inset-0 bg-black/70 backdrop-blur-sm transition-opacity opacity-0"></div>
        
        <!-- Modal Content -->
        <div class="delete-modal-content relative w-full max-w-md mx-4 transform transition-all scale-95 opacity-0">
            <div class="glass-panel bg-black/90 rounded-2xl border border-white/10 shadow-2xl overflow-hidden">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-white/10 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-500/10 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </div>
                    <h3 class="text-white font-semibold text-lg">Hapus Riwayat Chat</h3>
                </div>
                
                <!-- Modal Body -->
                <div class="px-6 py-5">
                    <p class="text-[#A1A09A] text-sm leading-relaxed">
                        Apakah Anda yakin ingin menghapus sesi obrolan ini? 
                        <span class="text-red-400 font-medium">Tindakan ini tidak dapat dibatalkan.</span>
                    </p>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-white/10 flex items-center justify-end gap-3 bg-white/5">
                    <button id="modal-cancel-btn" class="px-4 py-2 rounded-xl text-[#A1A09A] text-sm font-medium border border-white/10 hover:bg-white/5 hover:text-white transition-all">
                        Batal
                    </button>
                    <button id="modal-delete-btn" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium border border-red-500/30 shadow-lg shadow-red-500/20 transition-all flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <script>
        // Theme initialization
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        function toggleTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                updateThemeToggle('light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                updateThemeToggle('dark');
            }
        }

        function updateThemeToggle(theme) {
            const icon = document.getElementById('theme-icon');
            const label = document.getElementById('theme-toggle-label');
            if (icon) {
                if (theme === 'dark') {
                    icon.className = 'fas fa-sun';
                } else {
                    icon.className = 'fas fa-moon';
                }
            }
            if (label) {
                label.textContent = theme === 'dark' ? 'Light' : 'Dark';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            updateThemeToggle(currentTheme);
            
            const messageInput    = document.getElementById('message-input');
            const chatMessages    = document.getElementById('chat-messages');
            const typingIndicator = document.getElementById('typing-indicator');
            const typingText      = document.getElementById('typing-text');
            const btnClear        = document.getElementById('btn-clear-chat');
            const btnClearMobile  = document.getElementById('btn-clear-chat-mobile');
            const sendBtn         = document.getElementById('send-btn');
            const sendIcon        = document.getElementById('send-icon');
            const loadingIcon     = document.getElementById('loading-icon');
            const statusBar       = document.getElementById('status-bar');

            let conversationHistory = [];
            let currentToolResults  = [];
            let isLoading = false;
            let currentSessionId = new URLSearchParams(window.location.search).get('chat') || null;
            let loadSessionAbortController = null; // AbortController for cancelling in-flight load requests
            let loadEarlierAbortController = null; // AbortController for cancelling in-flight load earlier requests

            const sidebar = document.getElementById('chat-sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const btnOpenSidebar = document.getElementById('btn-open-sidebar');
            const btnCloseSidebar = document.getElementById('btn-close-sidebar');
            const btnNewChat = document.getElementById('btn-new-chat');
            const btnNewChatHeader = document.getElementById('btn-new-chat-header');
            const historyList = document.getElementById('history-list');
            
            // Delete modal elements
            const deleteModal = document.getElementById('delete-modal');
            const modalBackdrop = deleteModal.querySelector('.delete-modal-backdrop');
            const modalContent = deleteModal.querySelector('.delete-modal-content');
            const modalCancelBtn = document.getElementById('modal-cancel-btn');
            const modalDeleteBtn = document.getElementById('modal-delete-btn');
            
            let deleteCallback = null;

            let isSidebarOpen = false;

            // Modal functions
            function showDeleteModal(sessionId, callback) {
                deleteCallback = { sessionId, callback };
                deleteModal.classList.add('show');
                deleteModal.classList.remove('hidden');
                setTimeout(() => {
                    modalBackdrop.classList.remove('opacity-0');
                    modalContent.classList.remove('scale-95', 'opacity-0');
                }, 10);
            }

            function hideDeleteModal() {
                modalBackdrop.classList.add('opacity-0');
                modalContent.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    deleteModal.classList.remove('show');
                    deleteModal.classList.add('hidden');
                    deleteCallback = null;
                }, 300);
            }

            function applySidebarState() {
                if (isSidebarOpen) {
                    // OPEN sidebar - show with width
                    sidebar.style.width = '288px';
                    sidebar.style.opacity = '1';
                    sidebarOverlay.style.zIndex = '0';
                    if (btnNewChatHeader) btnNewChatHeader.classList.add('hidden');
                } else {
                    // CLOSE sidebar - collapse width
                    sidebar.style.width = '0';
                    sidebar.style.opacity = '0';
                    sidebarOverlay.style.zIndex = '0';
                    if (btnNewChatHeader) btnNewChatHeader.classList.remove('hidden');
                }
            }

            function toggleSidebar(show) {
                if (typeof show === 'boolean') {
                    isSidebarOpen = show;
                } else {
                    isSidebarOpen = !isSidebarOpen;
                }
                applySidebarState();
            }

            window.addEventListener('resize', () => {
                applySidebarState();
            });

            applySidebarState();

            // Hamburger button click handler
            btnOpenSidebar.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });

            if (btnCloseSidebar) btnCloseSidebar.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar(false);
            });
            if (sidebarOverlay) sidebarOverlay.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar(false);
            });
            if (btnNewChat) btnNewChat.addEventListener('click', () => { startNewChat(); if (window.innerWidth < 768) toggleSidebar(false); });
            if (btnNewChatHeader) btnNewChatHeader.addEventListener('click', () => startNewChat());

            // Modal event listeners
            if (modalCancelBtn) {
                modalCancelBtn.addEventListener('click', hideDeleteModal);
            }
            if (modalDeleteBtn) {
                modalDeleteBtn.addEventListener('click', () => {
                    if (deleteCallback) {
                        hideDeleteModal();
                        deleteCallback.callback(deleteCallback.sessionId);
                    }
                });
            }
            if (modalBackdrop) {
                modalBackdrop.addEventListener('click', hideDeleteModal);
            }
            // Close modal on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && deleteModal.classList.contains('show')) {
                    hideDeleteModal();
                }
            });

            // Clear chat handlers
            const handleClear = () => {
                if (currentSessionId) {
                    deleteSession(currentSessionId, { stopPropagation: () => {} });
                } else {
                    conversationHistory = [];
                    chatMessages.innerHTML = '';
                    addWelcomeMessage();
                }
            };

            if (btnClear) btnClear.addEventListener('click', handleClear);
            if (btnClearMobile) btnClearMobile.addEventListener('click', handleClear);

            // Message input handlers
            messageInput.addEventListener('keydown', e => {
                if (e.key === 'Enter' && !e.shiftKey && !isLoading) { 
                    e.preventDefault(); 
                    submitMessage(); 
                }
            });
            sendBtn.addEventListener('click', () => { 
                if (!isLoading) { 
                    submitMessage(); 
                }
            });
            btnClear.addEventListener('click', () => {
                conversationHistory = [];
                chatMessages.innerHTML = '';
                addMessage('Riwayat percakapan telah dihapus. Ada yang bisa saya bantu? 😊', 'ai');
            });

            // ── Submit ─────────────────────────────────────────────────────────────
            async function submitMessage() {
                const message = messageInput.value.trim();
                if (!message || isLoading) return;

                addMessage(message, 'user');
                messageInput.value = '';
                setLoading(true);
                typingText.textContent = 'AI sedang berpikir...';
                chatMessages.scrollTop = chatMessages.scrollHeight;

                const { bubble, toolArea, wrapper } = createStreamBubble();
                chatMessages.appendChild(wrapper);
                chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });

                let aiResponseText = '';
                const toolBadges = {};

                try {
                        const selectedModelId = document.getElementById('ai-model-select')?.value;
                        const response = await fetch('{{ route("chatbot.send") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ 
                                message, 
                                history: conversationHistory,
                                chat_session_id: currentSessionId,
                                model_id: selectedModelId
                            }),
                    });

                    // Save preference
                    if (selectedModelId) localStorage.setItem('last_ai_model', selectedModelId);

                    // FIX: Tangani error JSON response (non-stream) dari server
                    const contentType = response.headers.get('content-type') || '';
                    if (contentType.includes('application/json')) {
                        const json = await response.json();
                        const errMsg = json.error || 'Terjadi kesalahan pada server.';
                        bubble.innerHTML = renderMarkdown('⚠️ ' + errMsg);
                        setLoading(false);
                        return;
                    }

                    if (!response.ok) throw new Error('HTTP ' + response.status);

                    const reader  = response.body.getReader();
                    const decoder = new TextDecoder('utf-8');
                    let buffer = '';

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;

                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split(/\r?\n/);
                        buffer = lines.pop() || '';

                        for (const line of lines) {
                            const trimmed = line.trim();
                            if (!trimmed || !trimmed.startsWith('data:')) continue;

                            const dataStr = trimmed.slice(5).trim();
                            if (dataStr === '[DONE]') continue;

                            try {
                                const parsed = JSON.parse(dataStr);

                                // ── Streaming text chunk ──────────────────────────
                                if (parsed.chunk !== undefined && parsed.chunk !== '') {
                                    aiResponseText += parsed.chunk;
                                    updateStreamBubbleText(bubble, aiResponseText);
                                }

                                // ── Notifikasi proses (label bisnis) ──────────────
                                if (parsed.tool_call) {
                                    const tc = parsed.tool_call;
                                    const icon  = toolIcons[tc.name] || '🔄';
                                    const label = toolLabels[tc.name] || 'Memproses data';

                                    if (tc.status === 'running') {
                                        const badgeId = `tool-${tc.id}`;
                                        if (!document.getElementById(badgeId)) {
                                            const badge = document.createElement('div');
                                            badge.id = badgeId;
                                            badge.className = 'tool-call-badge running';
                                            badge.dataset.tool = tc.name;

                                            // Info konteks tambahan (label bisnis)
                                            let detail = '';
                                            if (tc.name === 'execute_query' && tc.arguments?.label) {
                                                detail = ` · ${tc.arguments.label}`;
                                            }

                                            if (['describe_table', 'list_tables', 'get_schema_info'].includes(tc.name)) {
                                                detail = '';
                                            }

                                            badge.innerHTML = `
                                                <span class="tool-call-dot running"></span>
                                                <span>${icon} ${label}${detail}</span>
                                            `;
                                            toolArea.appendChild(badge);
                                        }
                                        typingText.textContent = label + '...';
                                    } else if (tc.status === 'done' || tc.status === 'success') {
                                        const badge = document.getElementById(`tool-${tc.id}`);
                                        if (badge) {
                                            badge.classList.remove('running');
                                            badge.classList.add('done');
                                            const dot = badge.querySelector('.tool-call-dot');
                                            if (dot) {
                                                dot.classList.remove('running');
                                                dot.textContent = '✓';
                                            }
                                        }
                                        typingText.textContent = 'Menyusun laporan...';
                                    }

                                    // CRITICAL: Store tool result data for later use (e.g., ERP guidance video)
                                    if (tc.result) {
                                        currentToolResults.push(tc.result);
                                    }
                                }

                                // ── History update ────────────────────────────────
                                if (parsed.history && Array.isArray(parsed.history)) {
                                    conversationHistory = parsed.history;
                                    // Update session ID if provided
                                    if (parsed.chat_session_id) {
                                        currentSessionId = parsed.chat_session_id;
                                        window.history.pushState({}, '', '?chat=' + currentSessionId);
                                    }
                                }

                                // ── Error ─────────────────────────────────────────
                                if (parsed.error && parsed.response) {
                                    bubble.innerHTML = renderMarkdown(parsed.response);
                                }

                            } catch(e) {
                                // Abaikan parse error untuk line individual
                            }
                        }

                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }

                    // --- STREAM SELESAI ---
                    finalizeStreamBubble(bubble, aiResponseText, currentToolResults);

                    if (toolArea.children.length === 0) {
                        toolArea.style.display = 'none';
                    }

                    // Check for ERP guidance video and add video player after streaming completes
                    const videoUrl = extractErpGuidanceVideo(currentToolResults);
                    if (videoUrl) {
                        const videoContainer = renderVideoPlayer(videoUrl);
                        // Insert video after the bubble wrapper
                        const wrapper = bubble.closest('.flex.flex-col.gap-1\\.5');
                        if (wrapper) {
                            const timeEl = wrapper.querySelector('span.text-\\[10px\\]');
                            if (timeEl) {
                                wrapper.insertBefore(videoContainer, timeEl);
                            } else {
                                wrapper.appendChild(videoContainer);
                            }
                        }
                    }

                } catch(err) {
                    console.error('[Agentic] Error:', err);
                    bubble.innerHTML = renderMarkdown('Maaf, terjadi kesalahan koneksi ke server. Silakan coba lagi.');
                } finally {
                    setLoading(false);
                    typingText.textContent = 'AI sedang berpikir...';
                    chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
                }
            }

            function updateStreamBubbleText(bubble, text) {
                // ── Fast Stream Renderer ─────────────────────────────────
                // Strategi baru: Tampilkan teks mentah LANGSUNG tanpa renderMarkdown()
                // agar terasa real-time. Markdown hanya dirender SEKALI di finalizeStreamBubble.
                // Ini menghilangkan bottleneck utama: renderMarkdown() berat tiap chunk.
                if (!text || text.trim() === '') return;

                // Inisialisasi raw-text container jika belum ada
                if (!bubble._streamInited) {
                    bubble._streamInited = true;
                    bubble.innerHTML = '';
                    // Buat container teks mentah dengan style mirip chat
                    const rawDiv = document.createElement('div');
                    rawDiv.id = '_stream_raw';
                    rawDiv.style.cssText = 'white-space: pre-wrap; word-break: break-word; font-family: inherit; font-size: inherit; line-height: 1.7; color: inherit;';
                    bubble.appendChild(rawDiv);
                    // Cursor berkedip
                    const cur = document.createElement('span');
                    cur.className = 'typing-cursor';
                    cur.id = '_stream_cur';
                    bubble.appendChild(cur);
                }

                // Strip blok khusus dari tampilan mentah agar tidak menampilkan raw JSON
                function stripSpecialBlocksRaw(t) {
                    return t
                        .replace(/```smart_table[\s\S]*?(```|$)/gm, '\n📊 [Tabel data sedang disiapkan...]\n')
                        .replace(/```chart[\s\S]*?(```|$)/gm, '\n📈 [Grafik sedang disiapkan...]\n')
                        .replace(/```dashboard[\s\S]*?(```|$)/gm, '\n🗂️ [Dashboard sedang disiapkan...]\n');
                }

                const rawDiv = bubble.querySelector('#_stream_raw');
                if (rawDiv) {
                    rawDiv.textContent = stripSpecialBlocksRaw(text);
                }

                // Auto-scroll saat konten baru tiba (throttled 100ms)
                const now = Date.now();
                if (!bubble._lastScroll || now - bubble._lastScroll > 100) {
                    bubble._lastScroll = now;
                    chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
                }
            }

            function finalizeStreamBubble(bubble, text, toolResultsForRender) {
                // Stop typewriter jika masih berjalan (backward compat)
                if (bubble._twState && bubble._twState.rafId) {
                    cancelAnimationFrame(bubble._twState.rafId);
                    bubble._twState.rafId = null;
                }
                // Hapus cursor berkedip jika ada
                const cur = bubble.querySelector('.typing-cursor');
                if (cur) cur.remove();

                // Bersihkan raw streaming container
                bubble._streamInited = false;

                if (!text || text.trim() === '') {
                    bubble.innerHTML = '';
                } else {
                    bubble.innerHTML = renderMarkdown(text);
                    bubble.querySelectorAll('pre code').forEach(b => {
                        try { hljs.highlightElement(b); } catch(e) {}
                    });
                }

                const activeResults = toolResultsForRender !== undefined ? toolResultsForRender : currentToolResults;
                // Defer inisialisasi chart/table agar DOM siap
                setTimeout(() => {
                    // FIX: paksa konversi tabel markdown biasa → smart table di semua jalur
                    convertMarkdownTablesToSmartTables(bubble);
                    initChartsInBubble(bubble, activeResults);
                    initDashboardsInBubble(bubble);
                    initSmartTablesInBubble(bubble, activeResults);
                    autoInjectSmartTableFromToolResults(bubble, activeResults);
                }, 60);
            }

            // ── Render pesan biasa (legacy stub - panggil addMessage utama) ──────────────────────────────────────
            // FIX: Fungsi ini dihapus karena duplikat dengan addMessage utama di bawah.
            // addMessage utama sudah handle initSmartTables, initCharts, dll.

        async function loadSessions() {
            try {
                const res = await fetch('{{ route("chatbot.sessions") }}');
                const sessions = await res.json();

                historyList.innerHTML = '';
                historyList.style.pointerEvents = 'auto';
                historyList.style.opacity = '1';

                if (sessions.length === 0) {
                    historyList.innerHTML = '<div class="flex flex-col items-center justify-center p-4 text-center opacity-50"><svg class="w-6 h-6 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><span class="text-xs">Belum ada obrolan</span></div>';
                    return;
                }

                sessions.forEach(s => {
                    const isActive = s.id == currentSessionId;
                    const item = document.createElement('div');
                    item.className = `group flex items-center justify-between p-2 rounded-lg cursor-pointer transition-colors ${isActive ? 'bg-black/10 dark:bg-white/10 text-gray-900 dark:text-white font-medium' : 'text-gray-700 dark:text-[#A1A09A] hover:bg-black/5 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white'}`;
                    item.style.pointerEvents = 'auto';

                    // History item click area
                    const clickArea = document.createElement('div');
                    clickArea.className = 'flex items-center gap-2 overflow-hidden flex-1';
                    clickArea.style.pointerEvents = 'none'; // Let clicks pass through to parent
                    clickArea.innerHTML = `
                        <svg class="w-4 h-4 flex-shrink-0 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        <span class="text-[11px] md:text-xs truncate select-none">${s.title}</span>
                    `;

                    // Delete button
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'delete-session btn-clear p-1.5 opacity-0 group-hover:opacity-100 transition-opacity rounded-md hover:bg-red-500/20 hover:text-red-500';
                    deleteBtn.style.pointerEvents = 'auto'; // Must be clickable
                    deleteBtn.innerHTML = `<svg class="w-3.5 h-3.5 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>`;
                    deleteBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        deleteSession(s.id, e);
                    });

                    item.appendChild(clickArea);
                    item.appendChild(deleteBtn);
                    
                    // Attach click listener to the entire item
                    item.addEventListener('click', function(e) {
                        // If delete button was clicked, stopPropagation handles it.
                        // But we check target just in case
                        if (e.target.closest('.delete-session')) return;

                        e.preventDefault();
                        // Allow switching chats even if loading (will cancel previous load)
                        loadSession(s.id);
                    });

                    historyList.appendChild(item);
                });
            } catch (e) {
                historyList.innerHTML = '<div class="p-4 text-center text-red-400 text-xs">Gagal memuat riwayat</div>';
            }
        }

        // Variable to track pagination state
        let sessionPagination = {
            hasMore: false,
            oldestCursor: null,
            isLoadingMore: false
        };

        // Helper function to render a single message
        function renderMessage(msg, prepend = false) {
            const wrap = document.createElement('div');
            wrap.className = ['flex flex-col gap-1.5',
                msg.role === 'user' ? 'items-end ml-auto max-w-[80%]' : 'items-start max-w-[95%]'
            ].join(' ');

            const bubble = document.createElement('div');
            bubble.className = [
                msg.role === 'user' ? 'chat-bubble-user' : 'chat-bubble-ai',
                'p-4 rounded-2xl text-sm shadow-sm markdown-body'
            ].join(' ');

            if (msg.role === 'ai' || msg.role === 'assistant') {
                // Parse tool_results if it's a string (for backward compatibility)
                let toolResultsForMsg = msg.tool_results || [];
                if (typeof toolResultsForMsg === 'string') {
                    try {
                        toolResultsForMsg = JSON.parse(toolResultsForMsg);
                    } catch (e) {
                        console.error('[RenderMessage] Failed to parse tool_results:', e);
                        toolResultsForMsg = [];
                    }
                }

                // CRITICAL: Pre-populate currentToolResults BEFORE rendering
                const originalGlobal = currentToolResults;
                currentToolResults = [];

                // First pass: Add all tool results from DB to currentToolResults
                toolResultsForMsg.forEach((tr, idx) => {
                    currentToolResults[idx] = tr;
                });

                bubble.innerHTML = renderMarkdown(msg.content);
                bubble.querySelectorAll('pre code').forEach(b => { try { hljs.highlightElement(b); } catch (e) {} });

                const toolResultsForInit = [...currentToolResults];
                currentToolResults = originalGlobal;

                // Defer init to next tick so DOM elements (canvas) are ready
                setTimeout(() => {
                    convertMarkdownTablesToSmartTables(bubble);
                    initChartsInBubble(bubble, toolResultsForInit);
                    initDashboardsInBubble(bubble);
                    initSmartTablesInBubble(bubble, toolResultsForInit);
                    autoInjectSmartTableFromToolResults(bubble, toolResultsForInit);
                }, 50);

                // Check for ERP guidance video and add video player below
                const videoUrl = extractErpGuidanceVideo(toolResultsForMsg);
                if (videoUrl) {
                    const videoContainer = renderVideoPlayer(videoUrl);
                    wrap.appendChild(videoContainer);
                }
            } else {
                bubble.textContent = msg.content;
            }

            const timeEl = document.createElement('span');
            timeEl.className = 'text-[10px] text-[#706f6c] ' + (msg.role === 'user' ? 'mr-1' : 'ml-1');
            // FIX: Gunakan timestamp dari DB (created_at) jika tersedia, bukan waktu sekarang
            if (msg.created_at) {
                try {
                    timeEl.textContent = new Date(msg.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                } catch(e) {
                    timeEl.textContent = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                }
            } else {
                timeEl.textContent = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            }

            wrap.appendChild(bubble);
            wrap.appendChild(timeEl);

            if (prepend) {
                // Insert before the first child (or after "Load More" button)
                const firstChild = chatMessages.firstChild;
                if (firstChild) {
                    chatMessages.insertBefore(wrap, firstChild);
                } else {
                    chatMessages.appendChild(wrap);
                }
            } else {
                chatMessages.appendChild(wrap);
            }

            return wrap;
        }

        // Helper: Extract video URL from ERP guidance tool results
        function extractErpGuidanceVideo(toolResults) {
            if (!Array.isArray(toolResults)) return null;

            for (let i = 0; i < toolResults.length; i++) {
                const tr = toolResults[i];

                // Check if this is an ERP guidance tool result
                if (tr.tool_name === 'get_erp_guidance' || tr.tool === 'get_erp_guidance') {
                    const data = tr.data || tr.result || tr;

                    // Check if data has guides array
                    if (data.guides && Array.isArray(data.guides)) {
                        for (let j = 0; j < data.guides.length; j++) {
                            const guide = data.guides[j];
                            if (guide.video && typeof guide.video === 'string' && guide.video.length > 0) {
                                return guide.video;
                            }
                        }
                    }

                    // Check direct video field
                    if (data.video && typeof data.video === 'string' && data.video.length > 0) {
                        return data.video;
                    }
                }

                // Also check nested result/data
                if (tr.result && typeof tr.result === 'object') {
                    const nestedVideo = extractErpGuidanceVideo([{...tr, tool_name: tr.tool_name || 'get_erp_guidance', data: tr.result}]);
                    if (nestedVideo) return nestedVideo;
                }
            }

            return null;
        }

        // Helper: Render ERP guidance video player
        function renderVideoPlayer(videoUrl) {
            const container = document.createElement('div');
            container.className = 'flex flex-col gap-2 items-start max-w-[95%] erp-video-container';

            const videoLabel = document.createElement('div');
            videoLabel.className = 'flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-orange-500/10 to-red-500/10 border border-orange-500/20 rounded-lg';
            videoLabel.innerHTML = `
                <svg class="w-4 h-4 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                </svg>
                <span class="text-xs font-medium text-orange-400">Video Panduan ERP</span>
            `;

            const videoWrap = document.createElement('div');
            videoWrap.className = 'relative w-full max-w-2xl rounded-xl overflow-hidden bg-black shadow-lg border border-white/10';

            const video = document.createElement('video');
            video.className = 'w-full rounded-xl';
            video.controls = true;
            video.preload = 'metadata';
            video.poster = '';
            video.innerHTML = `<source src="${videoUrl}" type="video/mp4">Browser Anda tidak mendukung pemutaran video.`;

            videoWrap.appendChild(video);
            container.appendChild(videoLabel);
            container.appendChild(videoWrap);

            return container;
        }

        // Helper function to show "Load Earlier Messages" button
        function showLoadEarlierButton() {
            // Remove existing button if any
            const existingBtn = chatMessages.querySelector('.load-earlier-btn');
            if (existingBtn) existingBtn.remove();

            const btnWrap = document.createElement('div');
            btnWrap.className = 'load-earlier-btn flex justify-center py-3 my-2';
            
            const btn = document.createElement('button');
            btn.className = 'px-4 py-2 text-xs font-medium text-[#A1A09A] bg-white/5 hover:bg-white/10 border border-white/10 rounded-full transition-all hover:text-white';
            btn.innerHTML = `
                <svg class="w-4 h-4 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M12 5v14M5 12l7-7 7 7"/>
                </svg>
                Muat Pesan Lebih Awal
            `;
            
            btn.addEventListener('click', async function() {
                if (sessionPagination.isLoadingMore) return;
                
                btn.disabled = true;
                btn.innerHTML = '<svg class="animate-spin h-4 w-4 inline mr-1" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memuat...';
                
                await loadEarlierMessages();
                
                btn.disabled = false;
                btn.innerHTML = `
                    <svg class="w-4 h-4 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 5v14M5 12l7-7 7 7"/>
                    </svg>
                    Muat Pesan Lebih Awal
                `;
            });
            
            btnWrap.appendChild(btn);
            
            // Insert at the top of chatMessages
            if (chatMessages.firstChild) {
                chatMessages.insertBefore(btnWrap, chatMessages.firstChild);
            } else {
                chatMessages.appendChild(btnWrap);
            }
        }

        async function loadEarlierMessages() {
            if (sessionPagination.isLoadingMore || !sessionPagination.hasMore || !sessionPagination.oldestCursor) {
                return;
            }

            // Abort any in-flight loadEarlierMessages request
            if (loadEarlierAbortController) {
                loadEarlierAbortController.abort();
                loadEarlierAbortController = null;
            }

            // Create new AbortController for this request
            loadEarlierAbortController = new AbortController();
            const signal = loadEarlierAbortController.signal;

            sessionPagination.isLoadingMore = true;

            try {
                const res = await fetch(`{{ url('/chatbot/sessions') }}/${currentSessionId}?before=${encodeURIComponent(sessionPagination.oldestCursor)}&limit=50`, { signal });

                if (!res.ok) throw new Error('HTTP ' + res.status);

                const data = await res.json();

                // Check if request was aborted during fetch
                if (signal.aborted) {
                    return; // Don't process aborted request results
                }

                // Update pagination state
                sessionPagination.hasMore = data.pagination.has_more;
                sessionPagination.oldestCursor = data.pagination.oldest_cursor;

                // Render older messages (prepend to top)
                const scrollHeightBefore = chatMessages.scrollHeight;

                data.history.forEach(msg => {
                    renderMessage(msg, true);
                });

                // Maintain scroll position after prepending
                const scrollHeightAfter = chatMessages.scrollHeight;
                const heightDiff = scrollHeightAfter - scrollHeightBefore;
                if (heightDiff > 0) {
                    chatMessages.scrollTop += heightDiff;
                }

                // Update or remove "Load Earlier" button
                if (!sessionPagination.hasMore) {
                    const existingBtn = chatMessages.querySelector('.load-earlier-btn');
                    if (existingBtn) existingBtn.remove();
                }

            } catch (e) {
                // Ignore abort errors - they're expected when switching chats
                if (e.name === 'AbortError') {
                    console.log('[LoadEarlierMessages] Request aborted');
                    return;
                }
                console.error('[LoadEarlierMessages] Error:', e);
            } finally {
                sessionPagination.isLoadingMore = false;
            }
        }

        async function loadSession(id) {
            // Abort any in-flight loadSession request
            if (loadSessionAbortController) {
                loadSessionAbortController.abort();
                loadSessionAbortController = null;
            }

            // Create new AbortController for this request
            loadSessionAbortController = new AbortController();
            const signal = loadSessionAbortController.signal;

            // Set loading flag to prevent concurrent operations
            isLoading = true;

            // Skip MutationObserver during history load
            skipObserver = true;

            // Update history list visual state - show loading
            const historyItems = historyList.querySelectorAll('.group');
            historyItems.forEach(item => {
                const clickArea = item.querySelector('div[class*="flex items-center gap-2"]');
                if (clickArea) {
                    clickArea.style.cursor = 'not-allowed';
                    clickArea.style.opacity = '0.5';
                }
            });

            // Reset global tool results for new session
            currentToolResults = [];

            // Reset pagination state for new session
            sessionPagination = {
                hasMore: false,
                oldestCursor: null,
                isLoadingMore: false
            };

            currentSessionId = id;
            window.history.pushState({}, '', '?chat=' + id);

            // Close sidebar on mobile only
            if (window.innerWidth < 768) toggleSidebar(false);

            // Show loading state with spinner
            chatMessages.innerHTML = '<div class="flex flex-col items-center justify-center h-full gap-4"><svg class="animate-spin h-10 w-10 text-[#f53003]" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><p class="text-[#A1A09A] text-sm animate-pulse">Memuat riwayat chat...</p></div>';

            try {
                const res = await fetch(`{{ url('/chatbot/sessions') }}/${id}`, { signal });

                if (!res.ok) throw new Error('HTTP ' + res.status);

                const data = await res.json();

                chatMessages.innerHTML = '';
                conversationHistory = [];

                if (data.history.length === 0) {
                    addWelcomeMessage();
                    await loadSessions();
                    return;
                }

                // Update pagination state
                sessionPagination.hasMore = data.pagination.has_more;
                sessionPagination.oldestCursor = data.pagination.oldest_cursor;

                // FIX: Rebuild conversationHistory dari DB agar AI punya konteks percakapan lama
                // Hanya ambil role user & assistant (bukan tool/system) dan batasi maxHistory terakhir
                const historyForAI = data.history
                    .filter(m => m.role === 'user' || m.role === 'assistant')
                    .slice(-20);
                historyForAI.forEach(m => {
                    conversationHistory.push({ role: m.role, content: m.content || '' });
                });

                // Render messages using the helper function
                data.history.forEach((msg, index) => {
                    renderMessage(msg, false);
                });

                // Show "Load Earlier" button if there are more messages
                if (sessionPagination.hasMore) {
                    showLoadEarlierButton();
                }

                await loadSessions();

                // Check if aborted before finalizing UI
                if (signal.aborted) {
                    return; // Don't finalize UI for aborted request
                }

                // Re-enable MutationObserver after history load is complete
                skipObserver = false;

                chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'instant' });

            } catch (e) {
                // Ignore abort errors - they're expected when switching chats
                if (e.name === 'AbortError') {
                    console.log('[LoadSession] Request aborted (user switched chats)');
                } else {
                    console.error('[LoadSession] Error:', e);
                    chatMessages.innerHTML = '<div class="p-4 text-center text-red-400">Gagal memuat percakapan: ' + e.message + '</div>';
                }
            } finally {
                // CRITICAL: Always release loading flag, even on error or abort
                isLoading = false;

                // Ensure history list is clickable and restore visual state
                if (historyList) {
                    historyList.style.pointerEvents = 'auto';
                    historyList.style.opacity = '1';
                    const historyItems = historyList.querySelectorAll('.group');
                    historyItems.forEach(item => {
                        const clickArea = item.querySelector('div[class*="flex items-center gap-2"]');
                        if (clickArea) {
                            clickArea.style.cursor = 'pointer';
                            clickArea.style.opacity = '1';
                        }
                    });
                }
            }
        }

        // Internal delete function (called after modal confirmation)
        async function performDelete(id) {
            // Show loading state on history list
            historyList.style.opacity = '0.5';
            historyList.style.pointerEvents = 'none';

            try {
                const res = await fetch(`{{ url('/chatbot/sessions') }}/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                if (res.ok) {
                    if (currentSessionId == id) {
                        startNewChat();
                    } else {
                        // Reload sessions and ensure click handlers are re-attached
                        await loadSessions();
                    }
                }
            } catch (e) {
                console.error('Gagal menghapus sesi', e);
                alert('Gagal menghapus sesi. Silakan coba lagi.');
            } finally {
                // Restore pointer events
                historyList.style.opacity = '1';
                historyList.style.pointerEvents = 'auto';
            }
        }

        // Show delete confirmation modal
        function deleteSession(id, event) {
            if (event) event.stopPropagation();
            showDeleteModal(id, performDelete);
        }

        function addWelcomeMessage() {
            chatMessages.innerHTML = `
            <div class="flex flex-col items-start gap-1.5 max-w-[90%] md:max-w-[85%]">
                <div class="chat-bubble-ai p-4 rounded-2xl text-sm shadow-sm markdown-body">
                    <p>Halo! Saya <strong>darkotech AI</strong> 👋</p>
                    <p style="margin-top:6px">Apa yang bisa saya bantu untuk mempermudah urusan Anda hari ini?</p>
                </div>
            </div>`;
        }

        function startNewChat() {
            currentSessionId = null;
            conversationHistory = [];
            window.history.pushState({}, '', window.location.pathname);
            loadSessions();
            addWelcomeMessage();
            if (window.innerWidth < 768) toggleSidebar(false);
        }

        if (btnNewChat) btnNewChat.addEventListener('click', startNewChat);
        if (btnNewChatHeader) btnNewChatHeader.addEventListener('click', startNewChat);

        // ── SmartTable Engine ─────────────────────────────────────────────────
        const smartTables = {};
        const PAGE_SIZE = 50;

        // Setup MutationObserver to watch for new smart tables
        // Skip observer during history load - explicit init is used instead
        let skipObserver = false;
        const tableObserver = new MutationObserver(mutations => {
            if (skipObserver) return;
            
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) {
                        if (node.classList && node.classList.contains('smart-table-wrap')) {
                            const bubble = node.closest('.chat-bubble-ai') || node.closest('[class*="chat-bubble"]');
                            if (bubble) {
                                setTimeout(() => initSmartTablesInBubble(bubble), 10);
                            }
                        }
                        node.querySelectorAll('.smart-table-wrap').forEach(wrap => {
                            const bubble = wrap.closest('.chat-bubble-ai') || wrap.closest('[class*="chat-bubble"]');
                            if (bubble && !wrap.getAttribute('data-initialized')) {
                                setTimeout(() => initSmartTablesInBubble(bubble), 10);
                            }
                        });
                    }
                });
            });
        });

        const chatContainer = document.getElementById('chat-messages');
        if (chatContainer) {
            tableObserver.observe(chatContainer, { childList: true, subtree: true });
        }

        const currencyFormatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });

        // ── Export Table to Excel ─────────────────────────────────────────────
        async function exportTableToExcel(tableId, headers, rows) {
            const exportBtn = document.querySelector(`#${tableId} .smart-table-export-btn`);
            
            // Show prominent loading modal for better UX with large data
            Swal.fire({
                title: 'Menyiapkan Export Excel',
                html: `Sedang memproses <b>${rows.length.toLocaleString('id')}</b> baris data...<br/><small class="text-[#706f6c]">Mohon tunggu sebentar, file akan otomatis terunduh.</small>`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.innerHTML = `<svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
            }

            // Use timeout to allow Swal to render before heavy computation blocks the thread
            await new Promise(resolve => setTimeout(resolve, 300));

            try {
                const stripHtmlTags = (str) => {
                    if (str === null || str === undefined) return '';
                    if (typeof str !== 'string') return String(str);
                    if (!str.includes('<')) return str;
                    return str.replace(/<[^>]*>?/gm, '').trim();
                };
                
                // Clean data: remove HTML tags only AND force string format for large numbers
                const cleanRows = rows.map(row =>
                    row.map(cell => {
                        if (cell === null || cell === undefined) return '';

                        // 1. Handle already numeric values first
                        if (typeof cell === 'number') return cell;
                        
                        // 2. Remove HTML tags with fast regex (instead of slow DOM manipulation)
                        let value = stripHtmlTags(cell);
                        
                        // 3. Smart Cleanup for Currency
                        // If it contains 'Rp', it's definitely a formatted Indonesian currency string.
                        // Support format Indonesia: titik=ribuan, koma=desimal
                        if (value.includes('Rp')) {
                            value = value.replace(/Rp\s?/g, '');
                            // Jika ada koma → koma desimal, titik ribuan: "1.500.000,50"
                            if (value.includes(',')) {
                                value = value.replace(/\./g, '').replace(',', '.');
                            }
                            // Jika lebih dari satu titik → semua titik ribuan: "1.500.000"
                            else if ((value.match(/\./g) || []).length > 1) {
                                value = value.replace(/\./g, '');
                            }
                            // Satu titik diikuti tepat 3 digit → titik ribuan: "517.000"
                            else if (/\.\d{3}$/.test(value)) {
                                value = value.replace(/\./g, '');
                            }
                        }

                        // 4. Final attempt to get a clean number
                        // If it looks like a number now, convert it to float for proper Excel handling
                        const cleanValue = value.replace(/\s/g, ''); 
                        if (/^-?\d+(\.\d+)?$/.test(cleanValue)) {
                            return parseFloat(cleanValue);
                        }

                        return value.trim();
                    })
                );

                const cleanHeaders = headers.map(h => {
                    const rawLabel = stripHtmlTags(h);
                    return toHumanLabel(rawLabel);
                });

                // Generate filename with timestamp
                const tableLabel = smartTables[tableId]?.label || tableId.replace(/_/g, ' ');
                const safeLabel = tableLabel.replace(/[^a-zA-Z0-9\s-]/g, '').trim().replace(/\s+/g, '-').toLowerCase();
                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                const filename = `${safeLabel || 'table-export'}-${timestamp}.xlsx`;

                // Client-side Excel Generation (SheetJS)
                const wsData = [];
                
                // 1. Report Title (A1)
                const reportTitle = (tableLabel || 'MBI DATA REPORT').toUpperCase();
                wsData.push([reportTitle]);
                
                // 2. Metadata (A2, A3)
                wsData.push([`Generated on: ${new Date().toLocaleString('id-ID')}`]);
                wsData.push(['Generated by DarkoTech AI']);
                wsData.push([]); // Empty row
                
                // 3. Headers
                wsData.push(cleanHeaders);
                
                // 4. Data Rows
                wsData.push(...cleanRows);

                // Create Worksheet
                const ws = XLSX.utils.aoa_to_sheet(wsData);

                // Merge Title Cells
                ws['!merges'] = [
                    { s: { r: 0, c: 0 }, e: { r: 0, c: cleanHeaders.length - 1 } },
                    { s: { r: 1, c: 0 }, e: { r: 1, c: cleanHeaders.length - 1 } },
                    { s: { r: 2, c: 0 }, e: { r: 2, c: cleanHeaders.length - 1 } }
                ];

                // --- Apply Premium Styling ---
                
                const range = XLSX.utils.decode_range(ws['!ref']);
                const borderStyle = {
                    top: { style: "thin", color: { rgb: "CCCCCC" } },
                    bottom: { style: "thin", color: { rgb: "CCCCCC" } },
                    left: { style: "thin", color: { rgb: "CCCCCC" } },
                    right: { style: "thin", color: { rgb: "CCCCCC" } }
                };

                for (let R = 4; R <= range.e.r; ++R) {
                    for (let C = 0; C <= range.e.c; ++C) {
                        const cellRef = XLSX.utils.encode_cell({ r: R, c: C });
                        if (!ws[cellRef]) {
                            ws[cellRef] = { t: 's', v: '' }; // Create empty cell to hold border
                        }
                        
                        ws[cellRef].s = ws[cellRef].s || {};
                        ws[cellRef].s.border = borderStyle;
                        ws[cellRef].s.alignment = ws[cellRef].s.alignment || { vertical: "center" };
                        
                        // If it's the header row (R === 4)
                        if (R === 4) {
                            ws[cellRef].s.font = { bold: true, color: { rgb: "FFFFFF" } };
                            ws[cellRef].s.fill = { fgColor: { rgb: "D32F2F" }, patternType: "solid" }; // Premium Red
                            ws[cellRef].s.alignment.horizontal = "center";
                        }
                    }
                }

                // 2. Titles & Metadata
                if (ws['A1']) {
                    ws['A1'].s = {
                        font: { bold: true, sz: 16, color: { rgb: "333333" } },
                        alignment: { horizontal: "center", vertical: "center" }
                    };
                }
                if (ws['A2']) {
                    ws['A2'].s = {
                        font: { italic: true, sz: 10, color: { rgb: "666666" } },
                        alignment: { horizontal: "center", vertical: "center" }
                    };
                }
                if (ws['A3']) {
                    ws['A3'].s = {
                        font: { italic: true, sz: 8, color: { rgb: "999999" } },
                        alignment: { horizontal: "center", vertical: "center" }
                    };
                }

                // 3. Auto-size Columns & Row Heights
                ws['!cols'] = cleanHeaders.map(h => ({ wch: Math.max(15, h.length + 5) }));
                ws['!rows'] = [
                    { hpt: 35 }, // Row 1 (Title)
                    { hpt: 20 }, // Row 2
                    { hpt: 15 }, // Row 3
                    { hpt: 10 }, // Row 4 (Empty)
                    { hpt: 25 }  // Row 5 (Header)
                ];

                // Create Workbook
                const wb = XLSX.utils.book_new();
                const sheetName = tableLabel.replace(/[\[\]\*\\\/\?]/g, '').substring(0, 31);
                XLSX.utils.book_append_sheet(wb, ws, sheetName || 'Data');

                // Download
                XLSX.writeFile(wb, filename);

                // Close loading modal
                Swal.close();

                // Show success toast
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: `✅ Export berhasil! ${rows.length} baris data telah diunduh.`,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });

            } catch (error) {
                console.error('[Export Error]', error);

                let errorMsg = 'Gagal export tabel.';
                if (error.message.includes('timeout')) {
                    errorMsg = '⏱️ Export timeout. Silakan coba lagi.';
                } else if (error.message.includes('memory')) {
                    errorMsg = '💾 Memory limit. Silakan coba lagi.';
                } else if (error.message.includes('413')) {
                    errorMsg = '⚠️ Data terlalu besar. Silakan filter data terlebih dahulu.';
                } else {
                    errorMsg = `❌ ${error.message}`;
                }

                // Show error toast
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: errorMsg,
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });

                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg> Export Excel`;
                }
            } finally {
                if (exportBtn && exportBtn.disabled) {
                    exportBtn.disabled = false;
                    exportBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg> Export Excel`;
                }
            }
        }

        // ── Export Chart to Excel ──────────────────────────────────────────────
        async function exportChartToExcel(chartId, chartConfig) {
            const chartContainer = document.querySelector(`#${chartId}`)?.closest('.chart-container');
            const exportBtn = chartContainer?.querySelector('.chart-export-btn');
            
            Swal.fire({
                title: 'Menyiapkan Export Excel',
                html: 'Sedang memproses data grafik...<br/><small class="text-[#706f6c]">Mohon tunggu sebentar.</small>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.innerHTML = `<svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
            }

            // Small delay for UI update
            await new Promise(resolve => setTimeout(resolve, 300));

            try {
                // Extract chart data EXACTLY as displayed
                const labels = chartConfig.data?.labels || [];
                const datasets = chartConfig.data?.datasets || [];
                const chartType = chartConfig.type || 'bar';
                const chartContainer = document.querySelector(`#${chartId}`)?.closest('.chart-container');
                const chartTitle = chartContainer?.getAttribute('data-title')
                    || chartConfig.data?.datasets?.[0]?.label
                    || chartConfig.options?.plugins?.title?.text
                    || chartConfig.title
                    || 'Grafik Data';

                // Get currencyColumns from container (stored during chart init)
                let currencyColumns = [];
                try {
                    const storedCols = chartContainer?.getAttribute('data-currency-columns');
                    if (storedCols) {
                        currencyColumns = JSON.parse(storedCols);
                    }
                } catch (e) {
                    console.warn('[Chart Export] Failed to parse currencyColumns:', e);
                }

                // Prepare data for Excel - EXACT data from chart
                const rows = [];
                const headers = ['No', 'Label', ...datasets.map((d, i) => {
                    // Use actual dataset label, or generate meaningful name
                    if (d.label) return d.label;
                    const typeLabel = chartType.charAt(0).toUpperCase() + chartType.slice(1);
                    return `${typeLabel} ${i + 1}`;
                })];
                
                // AI-detect currency columns untuk chart export:
                // Cocokan nama dataset dengan currencyColumns (DB name) atau deteksi dari label
                const detectedChartCurrencyCols = datasets.map((d, i) => {
                    const dsLabel = d.label || `${chartType} ${i + 1}`;
                    // Cek apakah dataset ini cocok dengan currencyColumns dari AI
                    if (isColumnCurrencyByAI(dsLabel, currencyColumns)) return dsLabel;
                    // Fallback: deteksi dari nama label
                    if (isLikelyCurrencyLabel(dsLabel)) return dsLabel;
                    return null;
                }).filter(Boolean);

                // Gabungkan dengan currencyColumns yang ada
                const finalCurrencyCols = [...new Set([...currencyColumns, ...detectedChartCurrencyCols])];

                
                // Find max length
                const maxLength = Math.max(
                    labels.length, 
                    ...datasets.map(d => d.data?.length || 0)
                );
                
                // Build rows with EXACT values from chart
                for (let i = 0; i < maxLength; i++) {
                    const row = [
                        i + 1,  // No
                        labels[i] || '-'  // Label
                    ];
                    
                    // Add data for each dataset - use RAW numeric values
                    datasets.forEach(d => {
                        let value = d.data?.[i];
                        if (value === null || value === undefined || value === '') {
                            row.push(0);  // Excel treats 0 as empty for calculations
                        } else {
                            // Ensure numeric value for Excel calculations
                            const numValue = parseFloat(value);
                            row.push(isNaN(numValue) ? value : numValue);
                        }
                    });
                    
                    rows.push(row);
                }

                // Add summary statistics row
                if (rows.length > 0) {
                    rows.push([]); // Empty row
                    
                    const summaryRow = ['Summary', '', ''];
                    datasets.forEach((d, idx) => {
                        const values = d.data || [];
                        const numericValues = values
                            .map(v => parseFloat(v))
                            .filter(v => !isNaN(v));
                        
                        if (numericValues.length > 0) {
                            const sum = numericValues.reduce((a, b) => a + b, 0);
                            const avg = sum / numericValues.length;
                            const min = Math.min(...numericValues);
                            const max = Math.max(...numericValues);
                            
                            // Add summary: Sum | Avg | Min | Max
                            summaryRow.push(`Σ:${sum.toLocaleString('id-ID')} | Avg:${avg.toLocaleString('id-ID', {maximumFractionDigits: 1})} | Min:${min.toLocaleString('id-ID')} | Max:${max.toLocaleString('id-ID')}`);
                        } else {
                            summaryRow.push('No data');
                        }
                    });
                    
                    rows.push(summaryRow);
                }

                // Generate filename with timestamp
                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                const safeTitle = chartTitle.replace(/[^a-zA-Z0-9]/g, '-').substring(0, 20);
                const filename = `chart-${safeTitle || 'export'}-${timestamp}.xlsx`;

                // Client-side Excel Generation (SheetJS)
                const wsData = [];
                
                // 1. Report Title (A1)
                const reportTitle = (chartTitle || 'MBI CHART REPORT').toUpperCase();
                wsData.push([reportTitle]);
                
                // 2. Metadata (A2, A3)
                wsData.push([`Generated on: ${new Date().toLocaleString('id-ID')}`]);
                wsData.push(['Generated by DarkoTech AI']);
                wsData.push([]); // Empty row
                
                // 3. Headers
                wsData.push(headers);
                
                // 4. Data Rows
                wsData.push(...rows);

                // Create Worksheet
                const ws = XLSX.utils.aoa_to_sheet(wsData);

                // Merge Title Cells
                ws['!merges'] = [
                    { s: { r: 0, c: 0 }, e: { r: 0, c: headers.length - 1 } },
                    { s: { r: 1, c: 0 }, e: { r: 1, c: headers.length - 1 } },
                    { s: { r: 2, c: 0 }, e: { r: 2, c: headers.length - 1 } }
                ];

                // --- Apply Premium Styling ---
                
                const range = XLSX.utils.decode_range(ws['!ref']);
                const borderStyle = {
                    top: { style: "thin", color: { rgb: "CCCCCC" } },
                    bottom: { style: "thin", color: { rgb: "CCCCCC" } },
                    left: { style: "thin", color: { rgb: "CCCCCC" } },
                    right: { style: "thin", color: { rgb: "CCCCCC" } }
                };

                for (let R = 4; R <= range.e.r; ++R) {
                    for (let C = 0; C <= range.e.c; ++C) {
                        const cellRef = XLSX.utils.encode_cell({ r: R, c: C });
                        if (!ws[cellRef]) {
                            ws[cellRef] = { t: 's', v: '' }; // Create empty cell to hold border
                        }
                        
                        ws[cellRef].s = ws[cellRef].s || {};
                        ws[cellRef].s.border = borderStyle;
                        ws[cellRef].s.alignment = ws[cellRef].s.alignment || { vertical: "center" };
                        
                        // If it's the header row (R === 4)
                        if (R === 4) {
                            ws[cellRef].s.font = { bold: true, color: { rgb: "FFFFFF" } };
                            ws[cellRef].s.fill = { fgColor: { rgb: "D32F2F" }, patternType: "solid" };
                            ws[cellRef].s.alignment.horizontal = "center";
                        }
                    }
                }

                if (ws['A1']) {
                    ws['A1'].s = { font: { bold: true, sz: 16, color: { rgb: "333333" } }, alignment: { horizontal: "center", vertical: "center" } };
                }
                if (ws['A2']) {
                    ws['A2'].s = { font: { italic: true, sz: 10, color: { rgb: "666666" } }, alignment: { horizontal: "center", vertical: "center" } };
                }
                if (ws['A3']) {
                    ws['A3'].s = { font: { italic: true, sz: 8, color: { rgb: "999999" } }, alignment: { horizontal: "center", vertical: "center" } };
                }

                // 3. Auto-size Columns & Row Heights
                ws['!cols'] = headers.map(h => ({ wch: Math.max(15, h.length + 5) }));
                ws['!rows'] = [
                    { hpt: 35 }, // Row 1 (Title)
                    { hpt: 20 }, // Row 2
                    { hpt: 15 }, // Row 3
                    { hpt: 10 }, // Row 4 (Empty)
                    { hpt: 25 }  // Row 5 (Header)
                ];

                // Create Workbook
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Chart Data');

                // Download
                XLSX.writeFile(wb, filename);
                
                // Close loading modal
                Swal.close();

                // Show success toast
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: `✅ Export grafik berhasil! Data telah diunduh.`,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });

            } catch (error) {
                console.error('[Chart Export Error]', error);
                
                // Show error toast
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: `❌ Gagal export grafik: ${error.message}`,
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
            } finally {
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg> Export Excel`;
                }
            }
        }

        // ── Export Table to PDF ──────────────────────────────────────────────
        async function exportTableToPdf(tableId, headers, rows) {
            const exportBtn = document.querySelector(`#${tableId} .smart-table-export-pdf-btn`);
            
            Swal.fire({
                title: 'Menyiapkan Export PDF',
                html: `Sedang memproses <b>${rows.length.toLocaleString('id')}</b> baris data...<br/><small class="text-[#706f6c]">Mohon tunggu sebentar, browser mungkin sedikit melambat saat menyusun halaman PDF.</small>`,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.innerHTML = `<svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
            }

            // UI update delay
            await new Promise(resolve => setTimeout(resolve, 300));

            try {
                // Determine orientation based on column count
                const orientation = headers.length > 6 ? 'landscape' : 'portrait';
                const doc = new jspdf.jsPDF({ orientation: orientation });
                
                const tableLabel = smartTables[tableId]?.label || tableId.replace(/_/g, ' ');
                const safeLabel = tableLabel.replace(/[^a-zA-Z0-9\s-]/g, '').trim().replace(/\s+/g, '-').toLowerCase();
                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                const filename = `${safeLabel || 'table-export'}-${timestamp}.pdf`;

                const stripHtmlTags = (str) => {
                    if (str === null || str === undefined) return '';
                    if (typeof str !== 'string') return String(str);
                    if (!str.includes('<')) return str;
                    return str.replace(/<[^>]*>?/gm, '').trim();
                };

                const cleanHeaders = headers.map(h => {
                    const rawLabel = stripHtmlTags(h);
                    return typeof toHumanLabel === 'function' ? toHumanLabel(rawLabel) : rawLabel;
                });
                
                // Identify currency columns to format them properly
                const currencyColsIdx = [];
                headers.forEach((h, i) => {
                    if (typeof isCurrencyColumn === 'function' && isCurrencyColumn(h, tableId)) {
                        currencyColsIdx.push(i);
                    }
                });

                const cleanRows = rows.map(row => 
                    row.map((cell, i) => {
                        let val = stripHtmlTags(cell);
                        if (currencyColsIdx.includes(i)) {
                            // Format as currency if it's a valid number
                            const num = parseFloat(String(val).replace(/[^0-9.-]/g, ''));
                            if (!isNaN(num) && typeof currencyFormatter !== 'undefined') {
                                val = currencyFormatter.format(num);
                            }
                        } else if (/^-?\d+(\.\d+)?$/.test(val)) {
                            // General number formatting
                            const h = headers[i] || '';
                            const isNonNumericStyled = /(id|no|telepon|phone|nik|faktur|polis|rangka|mesin|periode|bulan|tahun|nama|alamat|cabang|merek|model|tipe|kode|code|sku|ref)/i.test(h);
                            if (!isNonNumericStyled) {
                                val = parseFloat(val).toLocaleString('id-ID');
                            }
                        }
                        return val;
                    })
                );

                const pageWidth = doc.internal.pageSize.getWidth();

                doc.setFontSize(16);
                doc.setFont("helvetica", "bold");
                doc.setTextColor(51, 51, 51);
                doc.text(tableLabel.toUpperCase(), pageWidth / 2, 15, { align: 'center' });
                
                doc.setFontSize(10);
                doc.setFont("helvetica", "italic");
                doc.setTextColor(100, 100, 100);
                doc.text(`Generated on: ${new Date().toLocaleString('id-ID')}`, pageWidth / 2, 22, { align: 'center' });
                
                doc.setFontSize(8);
                doc.setFont("helvetica", "italic");
                doc.setTextColor(150, 150, 150);
                doc.text('Generated by DarkoTech AI', pageWidth / 2, 26, { align: 'center' });
                
                doc.setFont("helvetica", "normal");

                doc.autoTable({
                    head: [cleanHeaders],
                    body: cleanRows,
                    startY: 28,
                    theme: 'grid',
                    styles: { fontSize: 8, cellPadding: 3 },
                    headStyles: { fillColor: [211, 47, 47], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                    columnStyles: currencyColsIdx.reduce((acc, idx) => {
                        acc[idx] = { halign: 'right', minCellWidth: 22 };
                        return acc;
                    }, {})
                });

                doc.save(filename);
                
                // Close loading modal
                Swal.close();

                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: `✅ Export PDF berhasil! ${rows.length} baris data telah diunduh.`,
                    showConfirmButton: false, timer: 3000, timerProgressBar: true,
                });

            } catch (error) {
                Swal.close();
                console.error('[PDF Export Error]', error);
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'error',
                    title: `❌ Gagal export PDF: ${error.message}`,
                    showConfirmButton: false, timer: 4000, timerProgressBar: true,
                });
            } finally {
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg> Export PDF`;
                }
            }
        }

        // ── Export Chart to PDF ──────────────────────────────────────────────
        async function exportChartToPdf(chartId, chartConfig) {
            const chartContainer = document.querySelector(`#${chartId}`)?.closest('.chart-container');
            const exportBtn = chartContainer?.querySelector('.chart-export-pdf-btn');
            
            Swal.fire({
                title: 'Menyiapkan Export PDF',
                html: 'Sedang menyusun dokumen PDF...<br/><small class="text-[#706f6c]">Mohon tunggu sebentar.</small>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.innerHTML = `<svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
            }

            // UI update delay
            await new Promise(resolve => setTimeout(resolve, 300));

            try {
                const chartContainer = document.querySelector(`#${chartId}`)?.closest('.chart-container');
                const chartTitle = chartContainer?.getAttribute('data-title')
                    || chartConfig.data?.datasets?.[0]?.label
                    || chartConfig.options?.plugins?.title?.text
                    || chartConfig.title
                    || 'Grafik Data';

                // Get currencyColumns from container (stored during chart init)
                let currencyColumns = [];
                try {
                    const storedCols = chartContainer?.getAttribute('data-currency-columns');
                    if (storedCols) {
                        currencyColumns = JSON.parse(storedCols);
                    }
                } catch (e) {
                    console.warn('[Chart PDF Export] Failed to parse currencyColumns:', e);
                }

                const labels = chartConfig.data?.labels || [];
                const datasets = chartConfig.data?.datasets || [];
                const chartType = chartConfig.type || 'bar';

                // AI-detect currency columns untuk chart PDF export
                const detectedPdfCurrencyCols = datasets.map((d, i) => {
                    const dsLabel = d.label || `${chartType} ${i + 1}`;
                    if (isColumnCurrencyByAI(dsLabel, currencyColumns)) return dsLabel;
                    if (isLikelyCurrencyLabel(dsLabel)) return dsLabel;
                    return null;
                }).filter(Boolean);
                const finalPdfCurrencyCols = [...new Set([...currencyColumns, ...detectedPdfCurrencyCols])];

                // Prepare table data from chart
                const rows = [];
                const headers = ['No', 'Label', ...datasets.map((d, i) => d.label || `${chartType} ${i + 1}`)];
                const maxLength = Math.max(labels.length, ...datasets.map(d => d.data?.length || 0));

                for (let i = 0; i < maxLength; i++) {
                    const row = [i + 1, labels[i] || '-'];
                    datasets.forEach(d => {
                        let value = d.data?.[i];
                        row.push(value === null || value === undefined || value === '' ? 0 : (parseFloat(value) || value));
                    });
                    rows.push(row);
                }

                // Ambil chart image dari snapshot yang sudah disimpan saat render
                const chartImageContainer = document.querySelector(`#${chartId}`)?.closest('.chart-container') || document.getElementById(chartId);
                let chartImage = chartImageContainer?.getAttribute('data-chart-image') || null;
                if (!chartImage) {
                    // fallback: coba capture langsung
                    const canvas = document.querySelector(`#${chartId}-canvas`) || document.querySelector(`#${chartId} canvas`);
                    if (canvas) {
                        try { chartImage = canvas.toDataURL('image/png', 0.9); } catch(e) { chartImage = null; }
                    }
                }

                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                const safeTitle = chartTitle.replace(/[^a-zA-Z0-9]/g, '-').substring(0, 20);
                const filename = `chart-${safeTitle || 'export'}-${timestamp}.pdf`;

                // Client-side PDF Generation
                const orientation = headers.length > 6 ? 'landscape' : 'portrait';
                const doc = new jspdf.jsPDF({ orientation: orientation });
                
                const pageWidth = doc.internal.pageSize.getWidth();

                doc.setFontSize(16);
                doc.setFont("helvetica", "bold");
                doc.setTextColor(51, 51, 51);
                doc.text(chartTitle.toUpperCase(), pageWidth / 2, 15, { align: 'center' });
                
                doc.setFontSize(10);
                doc.setFont("helvetica", "italic");
                doc.setTextColor(100, 100, 100);
                doc.text(`Generated on: ${new Date().toLocaleString('id-ID')}`, pageWidth / 2, 22, { align: 'center' });
                
                doc.setFontSize(8);
                doc.setFont("helvetica", "italic");
                doc.setTextColor(150, 150, 150);
                doc.text('Generated by DarkoTech AI', pageWidth / 2, 26, { align: 'center' });
                
                doc.setFont("helvetica", "normal");

                let startY = 32;
                
                // Add chart image if canvas exists
                if (chartImage) {
                    const imgProps = doc.getImageProperties(chartImage);
                    const pdfWidth = doc.internal.pageSize.getWidth() - 28; // 14px padding on each side
                    const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
                    
                    // Cap height to max 120 to avoid taking up the whole page
                    const finalHeight = Math.min(pdfHeight, 120);
                    const finalWidth = (imgProps.width * finalHeight) / imgProps.height;
                    
                    doc.addImage(chartImage, 'PNG', 14, startY, finalWidth, finalHeight);
                    startY += finalHeight + 10;
                }

                // Currency columns indices (shifted by 2 because No and Label)
                const currencyColsIdx = finalPdfCurrencyCols.map(label => headers.indexOf(label)).filter(idx => idx > -1);

                doc.autoTable({
                    head: [headers],
                    body: rows,
                    startY: startY,
                    theme: 'grid',
                    styles: { fontSize: 8, cellPadding: 3 },
                    headStyles: { fillColor: [211, 47, 47], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                    columnStyles: currencyColsIdx.reduce((acc, idx) => {
                        acc[idx] = { halign: 'right', minCellWidth: 22 };
                        return acc;
                    }, {})
                });

                doc.save(filename);
                
                // Close loading modal
                Swal.close();

                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: `✅ Export PDF grafik berhasil!`,
                    showConfirmButton: false, timer: 3000, timerProgressBar: true,
                });

            } catch (error) {
                Swal.close();
                console.error('[Chart PDF Export Error]', error);
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'error',
                    title: `❌ Gagal export PDF grafik: ${error.message}`,
                    showConfirmButton: false, timer: 4000, timerProgressBar: true,
                });
            } finally {
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg> Export PDF`;
                }
            }
        }

        function isCurrencyColumn(header, tableId = null) {
            if (!header) return false;
            const h = header.toLowerCase();
            
            // 0. AI & Backend Selected Priority (DEFACTO SOURCE OF TRUTH)
            if (tableId && smartTables[tableId] && smartTables[tableId].currencyColumns) {
                const aiCols = smartTables[tableId].currencyColumns.map(c => c.toLowerCase());
                if (aiCols.includes(h)) {
                    return true;
                }
                // Jika AI sudah set currencyColumns eksplisit tapi header ini tidak ada di dalamnya,
                // jangan fallback agar tidak false positive pada kolom qty/count/dll.
                if (aiCols.length > 0) return false;
            }

            // 1. Fallback: deteksi otomatis dari nama header
            // Berlaku untuk tabel dari markdown/auto-convert yang tidak punya metadata AI.
            return isLikelyCurrencyLabel(h);
        }

        // Deteksi otomatis apakah label kemungkinan kolom currency
        // Digunakan untuk grafik yang tidak memiliki currencyColumns metadata
        function isLikelyCurrencyLabel(label) {
            if (!label) return false;
            const h = label.toLowerCase();
            return /(sales|amount|harga|netto|dpp|gpn|cogs|hpp|saldo|growth|realisasi|target|pencapaian|omset|revenue|pendapatan|penjualan|laba|profit|cost|biaya|nilai|total|sum|rupiah|rp)/.test(h);
        }

        // Smart match: cocokkan currencyColumns (nama DB) dengan nama header/dataset (nama display)
        // Contoh: 'total_netto' cocok dengan 'Total Netto' atau 'Total Netto (Rp)'
        function isColumnCurrencyByAI(colName, currencyColumns) {
            if (!colName || !currencyColumns || currencyColumns.length === 0) return false;
            const normalize = (s) => s.toLowerCase().replace(/[^a-z0-9]/g, '');
            const normalizedCol = normalize(colName);
            return currencyColumns.some(c => {
                const nc = normalize(c);
                return nc === normalizedCol || normalizedCol.includes(nc) || nc.includes(normalizedCol);
            });
        }

        function toHumanLabel(str) {
            if (!str) return '';
            
            // Custom mappings for technical abbreviations
            const mapping = {
                'gpn': 'Laba Kotor',
                'gpm': 'GPM (%)',
                'cogs': 'HPP',
                'qty': 'Qty',
                'dpp': 'DPP',
                'ttl': 'Total',
                'ssr': 'SSR (Sales Summary)',
                'trm': 'TRM (Target Realisasi)',
                'hpp': 'HPP',
                'pencapaian_amount': 'Pencapaian (%)',
                'pencapaian_qty': 'Pencapaian Qty (%)',
                'total_netto': 'Total Netto',
                'total_dpp': 'Total DPP',
                'periode_tahun': 'Tahun',
                'periode_bulan': 'Bulan'
            };

            const lower = str.toLowerCase();
            if (mapping[lower]) return mapping[lower];

            // General formatting: snake_case to Title Case
            return str
                .split('_')
                .map(word => {
                    const mappedWord = mapping[word.toLowerCase()];
                    if (mappedWord) return mappedWord;
                    return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
                })
                .join(' ');
        }

        function formatCellValue(val, header, tableId = null) {
            if (val === null || val === undefined || val === '') return '';
            
            const isMoney = header && isCurrencyColumn(header, tableId);
            const strVal = String(val).trim();
            const h = header ? header.toLowerCase() : '';
            
            if (isMoney) {
                // Helper: parse angka dari string, support format Indonesia (titik=ribuan, koma=desimal)
                // contoh: "Rp 517.000" → 517000, "Rp 1.500.000" → 1500000, "517000" → 517000
                const parseIndonesianNumber = (s) => {
                    let v = s.toLowerCase().trim();
                    // Hapus prefix Rp dan spasi
                    v = v.replace(/rp\s?/g, '');
                    // Deteksi format Indonesia: titik sebagai pemisah ribuan
                    // Kasus 1: ada koma → koma adalah desimal, titik adalah ribuan
                    // Contoh: "1.500.000,50" atau "517.000,00"
                    if (v.includes(',')) {
                        v = v.replace(/\./g, '').replace(',', '.');
                    }
                    // Kasus 2: ada titik tapi BUKAN desimal (titik ribuan Indonesia)
                    // Deteksi: jika ada LEBIH DARI SATU titik, atau titik diikuti tepat 3 digit di akhir
                    else if ((v.match(/\./g) || []).length > 1) {
                        // Lebih dari satu titik → semua titik adalah ribuan
                        v = v.replace(/\./g, '');
                    } else if (/\.\d{3}$/.test(v)) {
                        // Satu titik, diikuti tepat 3 digit → titik ribuan (contoh: "517.000")
                        v = v.replace(/\./g, '');
                    }
                    // Kasus 3: shorthand k (contoh: "517k")
                    let num = parseFloat(v.replace(/[^0-9.]/g, ''));
                    if (v.endsWith('k')) num *= 1000;
                    return num;
                };

                // Handle ranges like "200000-300000" atau "Rp 517.000 - Rp 520.000"
                if (strVal.includes('-') && !strVal.startsWith('-')) {
                    const parts = strVal.split('-').map(p => p.trim());
                    if (parts.length === 2) {
                        const n1 = parseIndonesianNumber(parts[0]);
                        const n2 = parseIndonesianNumber(parts[1]);
                        if (!isNaN(n1) && !isNaN(n2)) {
                            return `${currencyFormatter.format(n1)} - ${currencyFormatter.format(n2)}`;
                        }
                    }
                }

                // Standard single number parsing
                const num = parseIndonesianNumber(strVal);
                if (!isNaN(num)) {
                    return currencyFormatter.format(num);
                }
            }
            
            // Patterns that should NOT get thousands separators (IDs, Codes, Years, Refs, etc.)
            // Synchronized with backend PDF export regex
            const isNonNumericStyled = /(id|no|telepon|phone|nik|faktur|polis|rangka|mesin|periode|bulan|tahun|nama|alamat|cabang|merek|model|tipe|kode|code|sku|ref)/i.test(h);

            // Format as standard number with thousands separator if it's numeric
            if (typeof val === 'number') {
                if (isNonNumericStyled) return String(val);
                return val.toLocaleString('id-ID');
            }
            
            // Standard number parsing for non-money columns
            const numVal = parseFloat(strVal.replace(/[^0-9.-]/g, ''));
            
            // If string looks like a pure number and is long, format it ONLY if not an ID/Code/Ref
            if (!isNaN(numVal) && /^-?\d+$/.test(strVal) && strVal.length > 3 && !isNonNumericStyled) {
                return numVal.toLocaleString('id-ID');
            }
            
            return val;
        }

        function buildSmartTable(tableId) {
            const st = smartTables[tableId];
            if (!st) {
                return;
            }
            
            const { headers, allRows, sortCol, sortDir, query } = st;

            let filtered = allRows;
            if (query) {
                const terms = query.toLowerCase().trim().split(/\s+/);
                filtered = allRows.filter(row => {
                    return terms.every(term => 
                        row.some(c => String(c ?? '').toLowerCase().includes(term))
                    );
                });
            }
            if (sortCol >= 0) {
                filtered = [...filtered].sort((a, b) => {
                    const va = a[sortCol] ?? '', vb = b[sortCol] ?? '';
                    const na = parseFloat(String(va).replace(/[^0-9.-]/g, '')),
                          nb = parseFloat(String(vb).replace(/[^0-9.-]/g, ''));
                    const cmp = (!isNaN(na) && !isNaN(nb)) ? (na - nb) : String(va).localeCompare(String(vb), 'id');
                    return sortDir === 'asc' ? cmp : -cmp;
                });
            }

            st.filteredRows = filtered;
            const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
            st.page = Math.min(st.page, totalPages - 1);
            const curPage = st.page;
            const pageRows = filtered.slice(curPage * PAGE_SIZE, (curPage + 1) * PAGE_SIZE);

            const wrap = document.getElementById(tableId);
            
            // Fallback: try to find by data-table-id
            if (!wrap) {
                const wrapByData = document.querySelector(`[data-table-id="${tableId}"]`);
                if (wrapByData) {
                    return buildSmartTableByElement(wrapByData, tableId, st, headers, allRows, sortCol, sortDir, query, filtered, pageRows, curPage, totalPages);
                }
            }
            
            if (!wrap) {
                return;
            }

            const info = wrap.querySelector('.smart-table-info');
            if (info) info.textContent = `📊 ${filtered.length.toLocaleString('id')} baris · ${headers.length} kol`;

            const toolbar = wrap.querySelector('.smart-table-toolbar');
            if (toolbar && !toolbar.querySelector('.smart-table-actions')) {
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'smart-table-actions';
                actionsDiv.innerHTML = `<button class="smart-table-export-pdf-btn" title="Export ke PDF">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Export PDF
                </button>
                <button class="smart-table-export-btn" title="Export ke Excel">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export Excel
                </button>`;
                actionsDiv.querySelector('.smart-table-export-pdf-btn').onclick = () => exportTableToPdf(tableId, headers, filtered);
                actionsDiv.querySelector('.smart-table-export-btn').onclick = () => exportTableToExcel(tableId, headers, filtered);
                toolbar.appendChild(actionsDiv);
            }

            const thead = wrap.querySelector('thead');
            if (thead) {
                thead.innerHTML = '<tr>' + headers.map((h, i) => {
                    const cls = sortCol === i ? (sortDir === 'asc' ? 'sort-asc' : 'sort-desc') : '';
                    const icon = sortCol === i ? (sortDir === 'asc' ? '▲' : '▼') : '▲▼';
                    const label = toHumanLabel(h);
                    return `<th class="${cls}" data-col="${i}">${label}<span class="sort-icon">${icon}</span></th>`;
                }).join('') + '</tr>';
                thead.querySelectorAll('th').forEach(th => {
                    th.onclick = () => {
                        const col = parseInt(th.dataset.col);
                        st.sortDir = (st.sortCol === col && st.sortDir === 'asc') ? 'desc' : 'asc';
                        st.sortCol = col;
                        st.page = 0;
                        buildSmartTable(tableId);
                    };
                });
            }

            const tbody = wrap.querySelector('tbody');
            
            if (tbody) {
                if (pageRows.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${headers.length}" style="text-align:center;color:#706f6c;padding:16px">Tidak ada data</td></tr>`;
                } else {
                    tbody.innerHTML = pageRows.map(row => '<tr>' + headers.map((h, i) => {
                        const isLong = String(row[i]).length > 40;
                        return `<td class="${isLong ? 'wrap' : ''}">${formatCellValue(row[i], h, tableId)}</td>`;
                    }).join('') + '</tr>').join('');
                }
            }

            const pag = wrap.querySelector('.smart-table-pagination');
            if (pag) {
                const pageInfo = pag.querySelector('.smart-table-page-info');
                if (pageInfo) pageInfo.textContent = `Hal ${curPage + 1}/${totalPages}`;
                const btns = pag.querySelector('.smart-table-btns');
                if (btns) {
                    btns.innerHTML = `<button class="st-btn" ${curPage === 0 ? 'disabled' : ''} id="${tableId}-prev">‹</button>` +
                                   `<button class="st-btn" ${curPage >= totalPages - 1 ? 'disabled' : ''} id="${tableId}-next">›</button>`;
                    document.getElementById(`${tableId}-prev`).onclick = () => { st.page--; buildSmartTable(tableId); };
                    document.getElementById(`${tableId}-next`).onclick = () => { st.page++; buildSmartTable(tableId); };
                }
            }
        }
        
        function buildSmartTableByElement(wrap, tableId, st, headers, allRows, sortCol, sortDir, query, filtered, pageRows, curPage, totalPages) {
            let titleEl = wrap.querySelector('.smart-table-title');
            if (st.label || wrap.getAttribute('data-title')) {
                const finalLabel = st.label || wrap.getAttribute('data-title');
                if (!titleEl) {
                    titleEl = document.createElement('div');
                    titleEl.className = 'smart-table-title';
                    const toolbar = wrap.querySelector('.smart-table-toolbar');
                    if (toolbar) {
                        wrap.insertBefore(titleEl, toolbar);
                    } else {
                        wrap.prepend(titleEl);
                    }
                }
                titleEl.innerHTML = `<span class="text-orange-500">📋</span> <span>${finalLabel}</span>`;
            }

            const info = wrap.querySelector('.smart-table-info');
            if (info) info.textContent = `📊 ${filtered.length.toLocaleString('id')} baris · ${headers.length} kol`;

            const toolbar = wrap.querySelector('.smart-table-toolbar');
            if (toolbar && !toolbar.querySelector('.smart-table-actions')) {
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'smart-table-actions';
                actionsDiv.innerHTML = `<button class="smart-table-export-pdf-btn" title="Export ke PDF">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Export PDF
                </button>
                <button class="smart-table-export-btn" title="Export ke Excel">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export Excel
                </button>`;
                actionsDiv.querySelector('.smart-table-export-pdf-btn').onclick = () => exportTableToPdf(tableId, headers, filtered);
                actionsDiv.querySelector('.smart-table-export-btn').onclick = () => exportTableToExcel(tableId, headers, filtered);
                toolbar.appendChild(actionsDiv);
            }

            const thead = wrap.querySelector('thead');
            if (thead) {
                thead.innerHTML = '<tr>' + headers.map((h, i) => {
                    const cls = sortCol === i ? (sortDir === 'asc' ? 'sort-asc' : 'sort-desc') : '';
                    const icon = sortCol === i ? (sortDir === 'asc' ? '▲' : '▼') : '▲▼';
                    const label = toHumanLabel(h);
                    return `<th class="${cls}" data-col="${i}">${label}<span class="sort-icon">${icon}</span></th>`;
                }).join('') + '</tr>';
                thead.querySelectorAll('th').forEach(th => {
                    th.onclick = () => {
                        const col = parseInt(th.dataset.col);
                        st.sortDir = (st.sortCol === col && st.sortDir === 'asc') ? 'desc' : 'asc';
                        st.sortCol = col;
                        st.page = 0;
                        buildSmartTable(tableId);
                    };
                });
            }

            const tbody = wrap.querySelector('tbody');
            
            if (tbody) {
                if (pageRows.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${headers.length}" style="text-align:center;color:#706f6c;padding:16px">Tidak ada data</td></tr>`;
                } else {
                    tbody.innerHTML = pageRows.map(row => '<tr>' + headers.map((h, i) => {
                        const isLong = String(row[i]).length > 40;
                        return `<td class="${isLong ? 'wrap' : ''}">${formatCellValue(row[i], h, tableId)}</td>`;
                    }).join('') + '</tr>').join('');
                }
            }

            const pag = wrap.querySelector('.smart-table-pagination');
            if (pag) {
                const pageInfo = pag.querySelector('.smart-table-page-info');
                if (pageInfo) pageInfo.textContent = `Hal ${curPage + 1}/${totalPages}`;
                const btns = pag.querySelector('.smart-table-btns');
                if (btns) {
                    btns.innerHTML = `<button class="st-btn" ${curPage === 0 ? 'disabled' : ''} id="${tableId}-prev">‹</button>` +
                                   `<button class="st-btn" ${curPage >= totalPages - 1 ? 'disabled' : ''} id="${tableId}-next">›</button>`;
                    const prevBtn = document.getElementById(`${tableId}-prev`);
                    const nextBtn = document.getElementById(`${tableId}-next`);
                    if (prevBtn) prevBtn.onclick = () => { st.page--; buildSmartTable(tableId); };
                    if (nextBtn) nextBtn.onclick = () => { st.page++; buildSmartTable(tableId); };
                }
            }

            wrap.setAttribute('data-initialized', 'true');
        }

        // ── AUTO-CONVERT: Mengubah tabel Markdown biasa (HTML table) menjadi smart_table ──
        // IMPROVED: deteksi judul dari heading terdekat, strip HTML di cells, auto-detect currency
        function convertMarkdownTablesToSmartTables(bubble) {
            const rawTables = bubble.querySelectorAll('table:not(.smart-table)');
            rawTables.forEach((table) => {
                if (table.closest('.smart-table-wrap')) return;

                const headers = [];
                table.querySelectorAll('thead th').forEach(th => headers.push(th.textContent.trim()));

                // Fallback: jika tidak ada thead, ambil baris pertama tbody sebagai header
                if (headers.length === 0) {
                    const firstRow = table.querySelector('tbody tr');
                    if (firstRow) {
                        firstRow.querySelectorAll('td').forEach(td => headers.push(td.textContent.trim()));
                        firstRow.remove();
                    }
                }

                const rows = [];
                table.querySelectorAll('tbody tr').forEach(tr => {
                    const row = [];
                    tr.querySelectorAll('td').forEach(td => {
                        // Strip HTML tags, simpan teks bersih
                        row.push(td.textContent.trim());
                    });
                    if (row.length > 0) rows.push(row);
                });

                if (headers.length === 0 || rows.length === 0) return;

                // Auto-detect judul tabel dari heading sebelumnya (h1–h4 atau strong)
                let tableTitle = 'Tabel Data';
                let prev = table.previousElementSibling;
                while (prev) {
                    const tag = prev.tagName?.toLowerCase();
                    if (['h1','h2','h3','h4'].includes(tag)) {
                        tableTitle = prev.textContent.trim();
                        break;
                    }
                    if (tag === 'p' && prev.querySelector('strong')) {
                        tableTitle = prev.textContent.trim();
                        break;
                    }
                    prev = prev.previousElementSibling;
                }

                // Auto-detect kolom currency dari nama header
                const autoCurrencyCols = headers.filter(h => isLikelyCurrencyLabel(h));

                const tableId = 'st-md-' + Math.random().toString(36).substr(2, 9);
                const hb64 = btoa(unescape(encodeURIComponent(JSON.stringify(headers))));
                const rb64 = btoa(unescape(encodeURIComponent(JSON.stringify(rows))));

                const wrapDiv = document.createElement('div');
                wrapDiv.className = 'smart-table-wrap';
                wrapDiv.id = tableId;
                wrapDiv.setAttribute('data-table-id', tableId);
                wrapDiv.setAttribute('data-headers-b64', hb64);
                wrapDiv.setAttribute('data-rows-b64', rb64);
                wrapDiv.setAttribute('data-currency-columns', JSON.stringify(autoCurrencyCols));
                wrapDiv.setAttribute('data-title', tableTitle);

                wrapDiv.innerHTML = `
                    <div class="smart-table-title"><span class="text-orange-500">📋</span> <span>${tableTitle}</span></div>
                    <div class="smart-table-toolbar">
                        <span class="smart-table-info">📊 ${rows.length} baris · ${headers.length} kol</span>
                        <input class="smart-table-search" type="text" placeholder="🔍 Cari di tabel...">
                    </div>
                    <div class="smart-table-scroll">
                        <table class="smart-table"><thead></thead><tbody></tbody></table>
                    </div>
                    <div class="smart-table-pagination">
                        <span class="smart-table-page-info"></span>
                        <div class="smart-table-btns"></div>
                    </div>`;

                table.parentNode.replaceChild(wrapDiv, table);
            });

            // Re-init tables to pick up the new wraps
            initSmartTablesInBubble(bubble);
        }

        // ── AUTO-INJECT: Build smart_table dari tool_results (RAM atau DB) ──
        // FIXED: tool_results dari DB bisa berupa array nested berbeda struktur.
        // Fungsi ini sekarang menormalisasi semua variasi struktur sebelum render.
        function autoInjectSmartTableFromToolResults(bubble, toolResults) {
            if (!toolResults || toolResults.length === 0) return;
            // Hanya inject jika belum ada smart-table-wrap di bubble
            if (bubble.querySelector('.smart-table-wrap')) return;

            // ── Normalizer: ekstrak rows/cols dari semua variasi struktur DB/RAM ──
            // Dari RAM (live): { tool_name, data: { rows, columns, currency_columns, label }, currency_columns, label }
            // Dari DB (history): bisa { tool_name, data: { rows_returned, columns, rows, ... } }
            //                    atau tool_result langsung berisi rows/columns di level atas
            function extractQueryData(tr) {
                if (!tr) return null;
                const toolName = tr.tool_name || tr.tool || '';
                if (!['execute_query', 'describe_table'].includes(toolName)) return null;

                // Coba berbagai lokasi data
                const candidates = [
                    tr.data,
                    tr.data?.data,
                    tr,
                ];

                for (const d of candidates) {
                    if (!d || typeof d !== 'object') continue;

                    if (toolName === 'execute_query') {
                        const rows = d.rows || [];
                        const cols = d.columns || [];
                        if (Array.isArray(rows) && rows.length > 0 && Array.isArray(cols) && cols.length >= 1) {
                            return {
                                type: 'query',
                                rows,
                                cols,
                                currCols: d.currency_columns || tr.currency_columns || [],
                                label: d.label || tr.label || 'Hasil Data',
                            };
                        }
                    } else if (toolName === 'describe_table') {
                        const cols = d.columns || [];
                        if (Array.isArray(cols) && cols.length > 0) {
                            return {
                                type: 'schema',
                                cols,
                                tableName: d.table_name || d.table || tr.label || 'Tabel',
                            };
                        }
                    }
                }
                return null;
            }

            // Ambil execute_query terbaru yang valid (atau describe_table jika tidak ada)
            let extracted = null;
            for (let i = toolResults.length - 1; i >= 0; i--) {
                extracted = extractQueryData(toolResults[i]);
                if (extracted) break;
            }
            if (!extracted) return;

            let stData = null;
            if (extracted.type === 'query') {
                const { rows, cols, currCols, label } = extracted;
                stData = {
                    title: label,
                    headers: cols,
                    rows: rows.map(r => Array.isArray(r) ? r : cols.map(c => r[c] !== undefined ? r[c] : '')),
                    currency_columns: currCols,
                };
            } else if (extracted.type === 'schema') {
                const { cols, tableName } = extracted;
                stData = {
                    title: 'Struktur Kolom: ' + tableName,
                    headers: ['Nama Kolom', 'Tipe Data', 'Keterangan'],
                    rows: cols.map(c => [c.name || c.column_name || '', c.type || c.data_type || '', c.description || c.notes || '']),
                    currency_columns: [],
                };
            }
            if (!stData) return;

            const tableId = 'st-auto-' + Math.random().toString(36).substr(2, 9);
            const hb64 = btoa(unescape(encodeURIComponent(JSON.stringify(stData.headers))));
            const rb64 = btoa(unescape(encodeURIComponent(JSON.stringify(stData.rows))));
            const currCols = stData.currency_columns;

            const wrapDiv = document.createElement('div');
            wrapDiv.className = 'smart-table-wrap';
            wrapDiv.id = tableId;
            wrapDiv.setAttribute('data-table-id', tableId);
            wrapDiv.setAttribute('data-title', stData.title);
            wrapDiv.setAttribute('data-currency-columns', JSON.stringify(currCols));
            wrapDiv.setAttribute('data-headers-b64', hb64);
            wrapDiv.setAttribute('data-rows-b64', rb64);
            wrapDiv.innerHTML = `
                <div class="smart-table-title"><span class="text-orange-500">📋</span> <span>${stData.title}</span></div>
                <div class="smart-table-toolbar">
                    <span class="smart-table-info">📊 ${stData.rows.length} baris · ${stData.headers.length} kol</span>
                    <input class="smart-table-search" type="text" placeholder="🔍 Cari di tabel...">
                </div>
                <div class="smart-table-scroll">
                    <table><thead></thead><tbody></tbody></table>
                </div>
                <div class="smart-table-pagination">
                    <span class="smart-table-page-info"></span>
                    <div class="smart-table-btns"></div>
                </div>`;

            bubble.appendChild(wrapDiv);

            smartTables[tableId] = {
                headers: stData.headers,
                allRows: stData.rows,
                filteredRows: stData.rows,
                sortCol: -1, sortDir: 'asc', page: 0, query: '',
                currencyColumns: currCols,
                label: stData.title
            };

            // Init search on the new wrap
            const searchInput = wrapDiv.querySelector('.smart-table-search');
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    smartTables[tableId].query = searchInput.value;
                    smartTables[tableId].page = 0;
                    buildSmartTable(tableId);
                });
            }

            // CRITICAL FIX: jangan pakai buildSmartTable(tableId) karena bergantung
            // document.getElementById yang bisa gagal saat wrapDiv belum masuk document
            // (terjadi saat load history — wrap belum di-appendChild ke chatMessages).
            // Gunakan buildSmartTableByElement langsung pakai referensi DOM yang sudah ada.
            const totalPages = Math.max(1, Math.ceil(stData.rows.length / PAGE_SIZE));
            const pageRows = stData.rows.slice(0, PAGE_SIZE);
            buildSmartTableByElement(wrapDiv, tableId, smartTables[tableId],
                stData.headers, stData.rows,
                -1, 'asc', '',
                stData.rows, pageRows, 0, totalPages
            );
        }

        function initSmartTablesInBubble(bubble, messageToolResults = null) {
            const toolResults = messageToolResults !== null ? messageToolResults : currentToolResults;

            bubble.querySelectorAll('.smart-table-wrap:not([data-initialized])').forEach((wrap, idx) => {
                const tableId = wrap.getAttribute('data-table-id') || ('st-' + Math.random().toString(36).substr(2, 9));
                const toolIdx = parseInt(wrap.getAttribute('data-tool-index'));

                let headers = [];
                let allRows = [];
                let toolRes = null;
                let tableLabel = wrap.getAttribute('data-title') || null;
                let currencyColumns = [];

                try {
                    const hb64 = wrap.getAttribute('data-headers-b64');
                    const rb64 = wrap.getAttribute('data-rows-b64');

                    if (hb64 && rb64) {
                        headers = JSON.parse(decodeURIComponent(escape(atob(hb64))));
                        allRows = JSON.parse(decodeURIComponent(escape(atob(rb64))));
                        tableLabel = wrap.getAttribute('data-title') || tableId.replace(/_/g, ' ');
                        const currAttr = wrap.getAttribute('data-currency-columns');
                        if (currAttr) {
                            try { currencyColumns = JSON.parse(currAttr); } catch(e) {}
                        }
                    }
                    else if (!isNaN(toolIdx)) {
                        toolRes = toolResults[toolIdx];

                        const hasValidData = (res) => {
                            if (!res) return false;
                            let d = res.data;
                            if (typeof d === 'string') {
                                try { d = JSON.parse(d); } catch(e) {}
                            }
                            if (d && d.rows && Array.isArray(d.rows) && d.rows.length > 0) return true;
                            if (res.rows && Array.isArray(res.rows) && res.rows.length > 0) return true;
                            if (d && d.columns && Array.isArray(d.columns) && d.columns.length > 0) return true;
                            return false;
                        };

                        if (!hasValidData(toolRes)) {
                            const stTitle = wrap.getAttribute('data-title');
                            let matchedTool = null;
                            
                            // 1. Coba cari berdasarkan kecocokan title vs label (Fuzzy/Keyword Scoring)
                            if (stTitle) {
                                const titleWords = stTitle.toLowerCase().replace(/[^a-z0-9\s]/g, ' ').split(/\s+/).filter(w => w.length > 2);
                                let bestMatch = null;
                                let bestScore = 0;

                                for (let i = toolResults.length - 1; i >= 0; i--) {
                                    const r = toolResults[i];
                                    if (r && (r.tool_name === 'execute_query' || r.tool_name === 'describe_table') && hasValidData(r) && !r._usedForTable) {
                                        let rd = r.data;
                                        if (typeof rd === 'string') {
                                            try { rd = JSON.parse(rd); } catch(e) {}
                                        }
                                        if (r.label || rd?.table_name) {
                                            const labelLower = (r.label || rd?.table_name || '').toLowerCase();
                                            const labelWords = labelLower.replace(/[^a-z0-9\s]/g, ' ').split(/\s+/).filter(w => w.length > 2);
                                            
                                            let score = 0;
                                            // Hitung kata yang sama
                                            for (const w of titleWords) {
                                                if (labelWords.includes(w)) score++;
                                            }
                                            
                                            // Bonus besar jika ada substring exact match
                                            if (stTitle.toLowerCase().includes(labelLower) || labelLower.includes(stTitle.toLowerCase())) {
                                                score += 10;
                                            }
                                            
                                            if (score > bestScore) {
                                                bestScore = score;
                                                bestMatch = r;
                                            }
                                        }
                                    }
                                }

                                // Harus ada minimal 1 kata bermakna (length > 2) yang cocok
                                if (bestMatch && bestScore >= 1) {
                                    matchedTool = bestMatch;
                                    bestMatch._usedForTable = true;
                                }
                            }

                            // 2. Fallback: ambil execute_query terakhir yang belum dipakai
                            if (!matchedTool) {
                                for (let i = toolResults.length - 1; i >= 0; i--) {
                                    const r = toolResults[i];
                                    if (r && (r.tool_name === 'execute_query' || r.tool_name === 'describe_table') && hasValidData(r) && !r._usedForTable) {
                                        matchedTool = r;
                                        r._usedForTable = true;
                                        break;
                                    }
                                }
                            }
                            
                            // 3. Fallback terakhir: ambil sembarang tool_result yang punya data valid dan belum dipakai
                            if (!matchedTool) {
                                for (let i = toolResults.length - 1; i >= 0; i--) {
                                    const r = toolResults[i];
                                    if (r && hasValidData(r) && !r._usedForTable) {
                                        matchedTool = r;
                                        r._usedForTable = true;
                                        break;
                                    }
                                }
                            }

                            // 4. Jika masih tidak ada, ambil hasil valid terakhir meskipun sudah dipakai (daripada kosong)
                            if (!matchedTool) {
                                for (let i = toolResults.length - 1; i >= 0; i--) {
                                    const r = toolResults[i];
                                    if (r && (r.tool_name === 'execute_query' || r.tool_name === 'describe_table') && hasValidData(r)) {
                                        matchedTool = r;
                                        break;
                                    }
                                }
                            }

                            toolRes = matchedTool;
                        }

                        if (!toolRes) {
                            wrap.setAttribute('data-initialized', 'waiting');
                            return;
                        }

                        if (toolRes.error) {
                            const thead = wrap.querySelector('thead');
                            const tbody = wrap.querySelector('tbody');
                            if (thead) thead.innerHTML = '<tr><th class="p-4 text-red-500">⚠️ Kesalahan Query</th></tr>';
                            if (tbody) tbody.innerHTML = `<tr><td class="p-4 text-center opacity-60 italic text-red-400">${toolRes.error}</td></tr>`;
                            wrap.setAttribute('data-initialized', 'true');
                            return;
                        }

                        let tableData = toolRes.data || toolRes;
                        if (typeof tableData === 'string') {
                            try { tableData = JSON.parse(tableData); } catch(e) {}
                        }
                        currencyColumns = tableData.currency_columns || [];
                        tableLabel = tableLabel || toolRes.label || tableData.label || null;

                        if (tableData.rows && Array.isArray(tableData.rows)) {
                            headers = tableData.columns || (tableData.rows[0] && typeof tableData.rows[0] === 'object' ? Object.keys(tableData.rows[0]) : []);
                            allRows = tableData.rows.map(r => Array.isArray(r) ? r : headers.map(h => r[h]));
                        } else if (Array.isArray(tableData)) {
                             if (tableData[0] && typeof tableData[0] === 'object') {
                                headers = Object.keys(tableData[0]);
                                allRows = tableData.map(r => headers.map(h => r[h]));
                             } else {
                                headers = ['Info'];
                                allRows = tableData.map(r => [r]);
                             }
                        } else if (tableData.columns && Array.isArray(tableData.columns) && (tableData.table || tableData.table_name)) {
                            headers = ['Nama Kolom', 'Tipe Data', 'Keterangan'];
                            allRows = tableData.columns.map(c => [
                                c.column || c.name || '', 
                                c.type || '', 
                                c.notes || c.description || ''
                            ]);
                            if (!tableLabel) tableLabel = 'Struktur Kolom: ' + (tableData.table || tableData.table_name);
                        }
                    }

                    // If no data, don't initialize yet (wait for data to arrive)
                    if (allRows.length === 0 && !toolRes) {
                        wrap.setAttribute('data-initialized', 'waiting');
                        return;
                    }

                    // Store state for pagination/sorting
                    smartTables[tableId] = {
                        headers, allRows, filteredRows: allRows,
                        page: 0, sortCol: -1, sortDir: 'asc', query: '',
                        currencyColumns: currencyColumns,
                        label: tableLabel || null
                    };

                    wrap.setAttribute('data-initialized', 'true');
                    if (!wrap.id) wrap.id = tableId;

                    // Init Search
                    const searchInput = wrap.querySelector('.smart-table-search');
                    if (searchInput) {
                        searchInput.addEventListener('input', () => {
                            smartTables[tableId].query = searchInput.value;
                            smartTables[tableId].page  = 0;
                            buildSmartTable(tableId);
                        });
                    }

                    buildSmartTable(tableId);

                } catch (e) { console.error('[SmartTable] Init failed:', e, 'tableId:', tableId); }
            });
            
            // Re-check any tables that were waiting for data
            bubble.querySelectorAll('.smart-table-wrap[data-initialized="waiting"]').forEach(wrap => {
                const toolIdx = parseInt(wrap.getAttribute('data-tool-index'));
                if (!isNaN(toolIdx) && currentToolResults[toolIdx]) {
                    wrap.removeAttribute('data-initialized');
                    initSmartTablesInBubble(bubble);
                }
            });
            
            // Auto-detect: if there are smart-table-wrap elements without data-initialized attribute, try to init them
            bubble.querySelectorAll('.smart-table-wrap:not([data-initialized])').forEach(wrap => {
                initSmartTablesInBubble(bubble);
            });
        }

        // ── marked.js setup ───────────────────────────────────────────────────
        marked.use({
            renderer: {
                table(header, body) {
                    let headers = [];
                    let rows    = [];
                    
                    try {
                        // In marked.use renderer, header and body are HTML strings from inner tokens
                        // We need to parse them back or use the fact that they are already HTML
                        // But for SmartTable, we prefer raw data.
                        // For now, let's just render it as a standard table if it's coming through this classic renderer
                        return `<div class="table-wrap"><table><thead>${header}</thead><tbody>${body}</tbody></table></div>`;
                    } catch(e) { console.error('Table parse error', e); }
                    return `<div class="table-wrap"><table><thead>${header}</thead><tbody>${body}</tbody></table></div>`;
                },
                code(code, lang) {
                    const langClean = (lang || '').trim();

                    if (langClean === 'chart') {
                        try {
                            const chartData = JSON.parse(code.trim());
                            let chartIdx = -1;
                            let chartLabel = null;
                            let currencyColumns = [];

                            if (chartData.tool_index !== undefined) {
                                chartIdx = parseInt(chartData.tool_index);
                                // Try to get label and currency_columns from the tool result
                                if (chartIdx >= 0 && currentToolResults[chartIdx]) {
                                    const tr = currentToolResults[chartIdx];
                                    chartLabel = tr.label || tr.data?.label || null;
                                    // Get currency_columns from execute_query result
                                    currencyColumns = tr.currency_columns || tr.data?.currency_columns || [];
                                }
                            } else if (chartData.type) {
                                chartIdx = currentToolResults.length;
                                // Get title from: explicit title > label > first dataset label
                                chartLabel = chartData.title || chartData.label
                                    || chartData.data?.datasets?.[0]?.label
                                    || null;
                                
                                // Try to get currency_columns from related smart_table if exists
                                // Look for smart_table with same tool_index in recent tool results
                                for (let i = currentToolResults.length - 1; i >= 0; i--) {
                                    const tr = currentToolResults[i];
                                    if (tr && tr.currency_columns && tr.currency_columns.length > 0) {
                                        currencyColumns = tr.currency_columns;
                                        break;
                                    }
                                    if (tr && tr.data && tr.data.currency_columns) {
                                        currencyColumns = tr.data.currency_columns;
                                        break;
                                    }
                                }
                                
                                currentToolResults.push({
                                    tool_name: 'chart',
                                    data: { chart_config: chartData },
                                    label: chartLabel,
                                    currency_columns: currencyColumns
                                });
                            }

                            if (chartIdx < 0) {
                                return '<div class="chart-container"><div class="flex items-center justify-center h-full"><span class="opacity-40 text-xs">⚠️ Format grafik tidak valid</span></div></div>';
                            }

                            const chartId = 'chart-' + Math.random().toString(36).substr(2, 9);
                            const titleAttr = chartLabel ? ` data-title="${chartLabel}"` : '';
                            const currencyAttr = currencyColumns.length > 0 ? ` data-currency-columns='${JSON.stringify(currencyColumns)}'` : '';
                            return `<div class="chart-container" id="${chartId}" data-tool-index="${chartIdx}"${titleAttr}${currencyAttr}>
                                ${chartLabel ? `<div class="chart-title"><span class="text-orange-500">📈</span> <span>${chartLabel}</span></div>` : ''}
                                <canvas id="${chartId}-canvas"></canvas>
                            </div>`;
                        } catch(e) {
                            console.error('[Chart Renderer] Parse error:', e);
                            return '<div class="chart-container"><div class="flex items-center justify-center h-full"><span class="opacity-40 text-xs">⚠️ Error memproses grafik</span></div></div>';
                        }
                    }

                    if (langClean === 'smart_table') {
                        try {
                            if (!code.trim().endsWith('}')) {
                                return '<div class="table-wrap"><span class="opacity-40 animate-pulse text-xs">⏳ Sedang memproses data...</span></div>';
                            }
                            const params = JSON.parse(code.trim());
                            const idx = (params.tool_index !== undefined) ? parseInt(params.tool_index) : -1;
                            const titleAttr = params.title ? ` data-title="${params.title.replace(/"/g, '&quot;')}"` : '';

                            if (idx >= 0 && !currentToolResults[idx]) {
                                return `<div class="table-wrap border-dashed border-white/10 flex items-center gap-2 px-4 py-3">
                                    <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
                                    <span class="opacity-40 text-xs">Menunggu data (Tool #${idx})...</span>
                                </div>`;
                            }

                            if ((idx >= 0 && currentToolResults[idx]) || idx === -1) {
                                const tableId = 'st-direct-' + Math.random().toString(36).substr(2, 9);
                                const hb64 = params.headers ? btoa(unescape(encodeURIComponent(JSON.stringify(params.headers)))) : '';
                                const rb64 = params.rows ? btoa(unescape(encodeURIComponent(JSON.stringify(params.rows)))) : '';
                                const hAttr = hb64 ? ` data-headers-b64="${hb64}"` : '';
                                const rAttr = rb64 ? ` data-rows-b64="${rb64}"` : '';
                                const currCols = params.currency_columns || [];
                                const currAttr = currCols.length > 0 ? ` data-currency-columns='${JSON.stringify(currCols)}'` : '';
                                const dataReady = (params.headers && params.headers.length > 0) ? true : false;
                                
                                return `<div class="smart-table-wrap" id="${tableId}" data-table-id="${tableId}" data-tool-index="${idx}"${titleAttr}${hAttr}${rAttr}${currAttr}>
                                    ${params.title ? `<div class="smart-table-title"><span class="text-orange-500">📋</span> <span>${params.title}</span></div>` : ''}
                                    <div class="smart-table-toolbar">
                                        <span class="smart-table-info">📊 ${dataReady ? 'Menginisialisasi tabel...' : 'Memuat...'}</span>
                                        <input class="smart-table-search" type="text" placeholder="🔍 Cari di tabel...">
                                    </div>
                                    <div class="smart-table-scroll">
                                        <table><thead><tr><th class="p-4">⏳ Menginisialisasi...</th></tr></thead><tbody></tbody></table>
                                    </div>
                                    <div class="smart-table-pagination"><span class="smart-table-page-info"></span><div class="smart-table-btns"></div></div>
                                </div>`;
                            }
                        } catch(e) {
                            console.warn('[SmartTable Renderer] JSON parse error, attempting regex fallback:', e);
                            
                            // Fallback regex untuk mengekstrak title jika JSON rusak (misal lupa tutup bracket/comma)
                            let fallbackTitle = '';
                            const titleMatch = code.match(/"title"\s*:\s*"([^"]+)"/i);
                            if (titleMatch) {
                                fallbackTitle = titleMatch[1];
                            }
                            
                             if (fallbackTitle) {
                                const tableId = 'st-direct-fallback-' + Math.random().toString(36).substr(2, 9);
                                const titleAttr = ` data-title="${fallbackTitle.replace(/"/g, '&quot;')}"`;
                                return `<div class="smart-table-wrap" id="${tableId}" data-table-id="${tableId}" data-tool-index="-1"${titleAttr}>
                                    <div class="smart-table-title"><span class="text-orange-500">📋</span> <span>${fallbackTitle}</span></div>
                                    <div class="smart-table-toolbar">
                                        <span class="smart-table-info">📊 Memulihkan data...</span>
                                        <input class="smart-table-search" type="text" placeholder="🔍 Cari di tabel...">
                                    </div>
                                    <div class="smart-table-scroll">
                                        <table><thead><tr><th class="p-4">⏳ Menyinkronkan data...</th></tr></thead><tbody></tbody></table>
                                    </div>
                                    <div class="smart-table-pagination"><span class="smart-table-page-info"></span><div class="smart-table-btns"></div></div>
                                </div>`;
                            }
                            
                            return '<div class="table-wrap"><span class="opacity-40 animate-pulse text-xs">⏳ Sedang memproses data...</span></div>';
                        }
                        return `<div class="table-wrap">⚠️ Konfigurasi tabel tidak valid atau data tidak ditemukan</div>`;
                    }

                    if (langClean === 'dashboard') {
                        try {
                            const dashboardData = JSON.parse(code.trim());
                            const id = 'db-' + Math.random().toString(36).substr(2, 9);
                            return `<div class="dashboard-grid" id="${id}" data-config='${JSON.stringify(dashboardData).replace(/'/g, "&apos;")}'></div>`;
                        } catch(e) {
                            console.error('[Dashboard Renderer] Error:', e);
                            return '<div class="p-4 text-red-400 opacity-60 text-xs italic">⚠️ Gagal memproses dashboard</div>';
                        }
                    }

                    const escaped = code.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    return `<pre><code class="language-${langClean || 'plaintext'}">${escaped}</code></pre>`;
                }
            },
            gfm: true,
            breaks: true,
            pedantic: false
        });

        function renderMarkdown(text) {
            if (!text) return '';
            try {
                return marked.parse(text.replace(/\r\n/g, '\n').replace(/\r/g, '\n'));
            } catch (e) {
                return `<pre style="white-space:pre-wrap;font-size:12px">${text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</pre>`;
            }
        }

        // ── Label notifikasi bisnis ───────────────────────────────────────────
        const toolIcons  = { list_tables:'📊', describe_table:'🔎', execute_query:'📈', get_schema_info:'🗂️' };
        const toolLabels = { list_tables:'Melihat data yang tersedia', describe_table:'Memeriksa informasi data', execute_query:'Membaca data', get_schema_info:'Melihat data' };


        // ── Loading state ─────────────────────────────────────────────────────
        function setLoading(loading) {
            isLoading = loading;
            sendBtn.disabled = loading;
            messageInput.disabled = loading;
            sendIcon.classList.toggle('hidden', loading);
            loadingIcon.classList.toggle('hidden', !loading);
            typingIndicator.classList.toggle('hidden', !loading);
            
            // Update status bar
            if (statusBar) {
                statusBar.classList.toggle('active', loading);
            }
        }

        async function submitMessage() {
            const message = messageInput.value.trim();
            if (!message || isLoading) return;

            // Add user message immediately
            addMessage(message, 'user');
            conversationHistory.push({ role: 'user', content: message });
            
            setLoading(true);
            messageInput.disabled = true;
            messageInput.placeholder = 'AI sedang memproses...';
            sendBtn.disabled = true;
            messageInput.value = '';

            const { bubble, toolArea, wrapper } = createStreamBubble();
            chatMessages.appendChild(wrapper);
            chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });

            let aiResponseText = '';
            currentToolResults = [];
            const toolBadges = {};
            let lastUpdateTime = Date.now();

            try {
                const selectedModelId = document.getElementById('ai-model-select')?.value;
                const response = await fetch('{{ route("chatbot.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        message,
                        history: conversationHistory,
                        chat_session_id: currentSessionId,
                        model_id: selectedModelId
                    }),
                });

                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    const json = await response.json();
                    const errMsg = json.error || 'Terjadi kesalahan pada server.';
                    bubble.innerHTML = renderMarkdown('⚠️ ' + errMsg);
                    setLoading(false);
                    messageInput.disabled = false;
                    messageInput.placeholder = 'Ketik pesan anda di sini...';
                    sendBtn.disabled = false;
                    return;
                }

                if (!response.ok) throw new Error('HTTP ' + response.status);

                const reader  = response.body.getReader();
                const decoder = new TextDecoder('utf-8');
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split(/\r?\n/);
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        const trimmed = line.trim();
                        if (!trimmed || !trimmed.startsWith('data:')) continue;
                        const dataStr = trimmed.slice(5).trim();
                        if (dataStr === '[DONE]') continue;

                        try {
                            const parsed = JSON.parse(dataStr);

                            if (parsed.chat_session_id !== undefined) {
                                currentSessionId = parsed.chat_session_id;
                                window.history.pushState({}, '', '?chat=' + currentSessionId);
                                loadSessions();
                            }

                            if (parsed.status === 'thinking') {
                                // Update loading card segera saat AI mulai berpikir
                                const labelEl = bubble.querySelector('#ai-load-label');
                                const subEl   = bubble.querySelector('#ai-load-sub');
                                if (labelEl && subEl) {
                                    labelEl.textContent = 'AI sedang merancang jawaban';
                                    subEl.textContent = 'Menganalisis permintaan...';
                                }
                            }

                            if (parsed.chunk !== undefined && parsed.chunk !== '') {
                                aiResponseText += parsed.chunk;

                                // Update loading state to show progress
                                // Hapus loading card jika teks sudah mulai banyak atau jika ini adalah chunk pertama
                                if (aiResponseText.trim().length > 0) {
                                    if (bubble._loadInterval) {
                                        clearInterval(bubble._loadInterval);
                                        bubble._loadInterval = null;
                                    }
                                    
                                    // Sembunyikan loading card jika ada teks nyata yang mengalir
                                    const loadingCard = bubble.querySelector('.ai-loading-card');
                                    if (loadingCard && aiResponseText.trim().length > 5) {
                                        loadingCard.style.display = 'none';
                                    }
                                }

                                updateStreamBubbleText(bubble, aiResponseText);

                                // Scroll smoothly as content arrives
                                if (Date.now() - lastUpdateTime > 200) {
                                    chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
                                    lastUpdateTime = Date.now();
                                }
                            }

                            if (parsed.tool_call) {
                                const tc    = parsed.tool_call;
                                const icon  = toolIcons[tc.name]  || '🔄';
                                const label = toolLabels[tc.name] || 'Memproses data';
                                if (tc.status === 'running') {
                                    const badge = document.createElement('div');
                                    badge.className = 'tool-call-badge running';
                                    let detail = '';
                                    if (tc.name === 'execute_query' && tc.arguments?.label) detail = ` · ${tc.arguments.label}`;
                                    badge.innerHTML = `<span class="tool-call-dot running"></span><span>${icon} ${label}${detail}</span>`;
                                    toolArea.appendChild(badge);
                                    toolBadges[tc.name + '_' + Object.keys(toolBadges).length] = badge;
                                    typingText.textContent = label + '...';
                                    // Update loading card mengikuti state tool
                                    const iconEl = bubble.querySelector('#ai-load-icon');
                                    const labelEl = bubble.querySelector('#ai-load-label');
                                    const subEl = bubble.querySelector('#ai-load-sub');
                                    if (iconEl && labelEl && subEl) {
                                        iconEl.textContent = icon;
                                        labelEl.classList.remove('anim'); void labelEl.offsetWidth; labelEl.classList.add('anim');
                                        labelEl.textContent = label + (detail ? detail : '');
                                        subEl.textContent = 'Sedang memproses...';
                                    }
                                } else if (tc.status === 'success') {
                                    if (tc.result) {
                                        currentToolResults.push(tc.result);
                                        updateStreamBubbleText(bubble, aiResponseText);
                                    }

                                    const runningBadge = toolArea.querySelector('.tool-call-badge.running');
                                    if (runningBadge) {
                                        runningBadge.classList.replace('running', 'done');
                                        const dot = runningBadge.querySelector('.tool-call-dot');
                                        if (dot) { dot.classList.remove('running'); dot.textContent = '✓'; }
                                    }
                                    typingText.textContent = 'Menganalisis data...';
                                    // Update loading card ke state analisis
                                    const iconEl = bubble.querySelector('#ai-load-icon');
                                    const labelEl = bubble.querySelector('#ai-load-label');
                                    const subEl = bubble.querySelector('#ai-load-sub');
                                    if (iconEl && labelEl && subEl) {
                                        iconEl.textContent = '📊';
                                        labelEl.classList.remove('anim'); void labelEl.offsetWidth; labelEl.classList.add('anim');
                                        labelEl.textContent = 'Menganalisis data';
                                        subEl.textContent = 'Menyusun hasil...';
                                    }
                                }
                            }

                            if (parsed.history && Array.isArray(parsed.history)) {
                                conversationHistory = parsed.history;
                            }

                            if (parsed.error && parsed.response) {
                                bubble.innerHTML = renderMarkdown(parsed.response);
                            }

                        } catch (e) { /* Ignore individual parse errors */ }
                    }
                }

                // --- STREAM SELESAI ---
                // Inisialisasi smart tables dan charts setelah stream selesai untuk mencegah re-render berulang
                finalizeStreamBubble(bubble, aiResponseText, currentToolResults);

                if (toolArea.children.length === 0 && aiResponseText.trim().length === 0) {
                    bubble.innerHTML = renderMarkdown('Maaf, saya tidak dapat memproses permintaan Anda.');
                }

                if (toolArea.children.length === 0) toolArea.style.display = 'none';

                // Check for ERP guidance video and add video player after streaming completes
                const videoUrl = extractErpGuidanceVideo(currentToolResults);
                if (videoUrl) {
                    const videoContainer = renderVideoPlayer(videoUrl);
                    const timeEl = wrapper.querySelector('span.text-\\[10px\\]');
                    if (timeEl) {
                        wrapper.insertBefore(videoContainer, timeEl);
                    } else {
                        wrapper.appendChild(videoContainer);
                    }
                }

            } catch (err) {
                console.error('[Agentic] Error:', err);
                bubble.innerHTML = renderMarkdown('⚠️ **Maaf, terjadi kesalahan koneksi ke server.**<br/>Silakan coba lagi atau periksa koneksi internet Anda.');
            } finally {
                setLoading(false);
                messageInput.disabled = false;
                messageInput.placeholder = 'Ketik pesan anda di sini...';
                sendBtn.disabled = false;
                messageInput.focus();
                typingText.textContent = 'AI sedang berpikir...';
                chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
            }
        }

        // ── Buat bubble AI ────────────────────────────────────────────────────
        function createStreamBubble() {
            const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const wrap = document.createElement('div');
            wrap.className = 'flex flex-col gap-1.5 items-start max-w-[95%]';

            const toolArea = document.createElement('div');
            toolArea.className = 'flex flex-col gap-1 pl-1 mb-1';

            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble-ai p-4 rounded-2xl text-sm shadow-sm markdown-body';
            bubble.innerHTML = `<div class="ai-loading-card">
                <div class="ai-loading-top">
                    <div class="ai-loading-icon-wrap" id="ai-load-icon">🤔</div>
                    <div class="ai-loading-text">
                        <div class="ai-loading-label anim" id="ai-load-label">AI sedang berpikir</div>
                        <div class="ai-loading-sub" id="ai-load-sub">Menunggu respons...</div>
                    </div>
                </div>
                <div class="ai-loading-bar-wrap"><div class="ai-loading-bar"></div></div>
            </div>`;

            const timeEl = document.createElement('span');
            timeEl.className = 'text-[10px] text-[#706f6c] ml-1';
            timeEl.textContent = time;

            wrap.appendChild(toolArea);
            wrap.appendChild(bubble);
            wrap.appendChild(timeEl);
            return { bubble, toolArea, wrapper: wrap };
        }


        // ── Init Charts ───────────────────────────────────────────────────────
        function initChartsInBubble(bubble, messageToolResults = null) {
            const toolResults = messageToolResults !== null ? messageToolResults : currentToolResults;

            bubble.querySelectorAll('.chart-container[data-tool-index]').forEach((container) => {
                const chartId = container.id;
                const canvas = document.getElementById(`${chartId}-canvas`);
                const toolIdx = parseInt(container.getAttribute('data-tool-index'));

                if (!canvas || canvas.getAttribute('data-chart-initialized')) return;
                if (isNaN(toolIdx) || toolIdx < 0) return;

                if (!toolResults || toolIdx >= toolResults.length) {
                    return;
                }

                const toolRes = toolResults[toolIdx];

                if (!toolRes || !toolRes.data) {
                    return;
                }

                let config = null;
                if (toolRes.data.chart_config) {
                    config = toolRes.data.chart_config;
                } else if (toolRes.data.config) {
                    config = toolRes.data.config;
                } else if (toolRes.data.type) {
                    config = toolRes.data;
                }

                if (!config || !config.type) {
                    container.innerHTML = '<div class="flex items-center justify-center h-full"><span class="opacity-40 text-xs text-red-400">⚠️ Format grafik tidak valid</span></div>';
                    return;
                }

                const currencyColumns = toolRes.currency_columns || (toolRes.data ? toolRes.data.currency_columns : []);
                
                // Also check HTML attribute as fallback
                let containerCurrencyCols = [];
                try {
                    const storedCols = container?.getAttribute('data-currency-columns');
                    if (storedCols) {
                        containerCurrencyCols = JSON.parse(storedCols);
                    }
                } catch (e) {}
                
                // Use container attribute if toolRes doesn't have currencyColumns
                const finalCurrencyCols = currencyColumns.length > 0 ? currencyColumns : containerCurrencyCols;
                
                // Store label on container for export
                const chartLabel = toolRes.label || toolRes.data?.label || null;
                if (chartLabel) container.setAttribute('data-title', chartLabel);
                if (finalCurrencyCols.length > 0) {
                    container.setAttribute('data-currency-columns', JSON.stringify(finalCurrencyCols));
                }
                initChartWithConfig(canvas, config, container, chartId, finalCurrencyCols);
            });

            // LEGACY: Handle old chart format with base64 data (for backward compatibility)
            bubble.querySelectorAll('.chart-data-provider').forEach(provider => {
                const chartId = provider.getAttribute('data-id');
                let canvas    = document.getElementById(chartId);
                if (!canvas || canvas.getAttribute('data-chart-initialized')) return;

                const container = canvas.closest('.chart-container');
                let rawData = '';
                try {
                    const b64 = provider.getAttribute('data-b64') || '';
                    if (b64) {
                        rawData = decodeURIComponent(escape(atob(b64)));
                    } else {
                        rawData = provider.value.replace(/&apos;/g, "'");
                    }
                } catch(e) { return; }

                const cleanJson = rawData.replace(/\/\/[^\n]*/g, '').replace(/\/\*[\s\S]*?\*\//g, '').trim();

                if (!cleanJson.endsWith('}')) {
                    if (container && !container.querySelector('.chart-loading')) {
                        container.insertAdjacentHTML('afterbegin', `<div class="chart-loading absolute inset-0 flex items-center justify-center bg-black/20 backdrop-blur-[2px] rounded-xl z-20">
                            <span class="opacity-60 animate-pulse text-xs flex items-center gap-2 text-white">
                                <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                📊 Menyiapkan grafik...
                            </span>
                        </div>`);
                        canvas.style.opacity = '0.3';
                    }
                    return;
                }

                try {
                    const config = JSON.parse(cleanJson);
                    // Legacy charts don't usually have currency_columns, but we add placeholder
                    initChartWithConfig(canvas, config, container, chartId, []);
                    provider.remove();
                } catch (e) {
                    const loader = container ? container.querySelector('.chart-loading') : null;
                    if (loader) loader.remove();
                    console.error('Chart.js init error:', e);
                    if (container) container.innerHTML = '<p style="color:#f87171;font-size:12px;padding:10px">⚠️ Gagal render grafik: ' + e.message + '</p>';
                }
            });
        }
        
        function initChartWithConfig(canvas, config, container, chartId, currencyColumns = []) {
            if (!config || !canvas) return;

            config.options = config.options || {};
            config.options.responsive = true;
            config.options.maintainAspectRatio = false;

            // Tambahkan padding layout untuk memberi ruang label
            if (!config.options.layout) config.options.layout = {};
            config.options.layout.padding = {
                top: 10,
                bottom: 15,
                left: 5,
                right: 5
            };

            // Pastikan warna tema gelap jika tidak diset AI
            if (!config.options.plugins) config.options.plugins = {};
            if (!config.options.plugins.legend) config.options.plugins.legend = { labels: { color: '#fff', font: { size: 10 } } };

            if (!config.options.scales) config.options.scales = {};
            const scales = config.options.scales;
            ['x', 'y'].forEach(axis => {
                if (!scales[axis]) scales[axis] = {};
                if (!scales[axis].ticks) scales[axis].ticks = { color: '#A1A09A', font: { size: 9 } };
                if (!scales[axis].grid) scales[axis].grid = { color: 'rgba(255,255,255,0.05)' };

                // Format currency di ticks Y — angka penuh tanpa singkatan
                if (axis === 'y') {
                    // Batasi jumlah ticks agar label tidak tumpang tindih
                    if (!scales[axis].ticks.maxTicksLimit) {
                        scales[axis].ticks.maxTicksLimit = 8;
                    }

                    scales[axis].ticks.callback = function(value) {
                        // Check if ANY dataset in this chart is a currency column
                        let isCurrencyChart = false;
                        if (currencyColumns && currencyColumns.length > 0) {
                            isCurrencyChart = true;
                        } else {
                            // Fallback: deteksi otomatis dari label dataset
                            const firstLabel = config.data?.datasets?.[0]?.label;
                            if (firstLabel && isLikelyCurrencyLabel(firstLabel)) {
                                isCurrencyChart = true;
                            }
                        }

                        // Selalu tampilkan angka PENUH — tidak disingkat (tidak ada 5.5M dll)
                        const numVal = typeof value === 'number' ? value : parseFloat(value);
                        if (isNaN(numVal)) return value;

                        if (isCurrencyChart) {
                            return 'Rp\u00a0' + numVal.toLocaleString('id-ID', { maximumFractionDigits: 0 });
                        }
                        return numVal.toLocaleString('id-ID', { maximumFractionDigits: 2 });
                    };
                }
            });

            // Format tooltips sebagai Rupiah
            if (!config.options.plugins.tooltip) config.options.plugins.tooltip = {};
            if (!config.options.plugins.tooltip.callbacks) config.options.plugins.tooltip.callbacks = {};
            config.options.plugins.tooltip.callbacks.label = function(context) {
                let label = context.dataset.label || '';
                
                // Priority Check: If AI explicitly listed this column or it matches general rules
                let isMoney = false;
                if (currencyColumns && (currencyColumns.includes(label) || currencyColumns.includes(label.toLowerCase()))) {
                    isMoney = true;
                } else {
                    isMoney = isCurrencyColumn(label);
                }

                if (label) label += ': ';
                if (context.parsed.y !== null) {
                    label += isMoney ? currencyFormatter.format(context.parsed.y) : context.parsed.y.toLocaleString('id-ID');
                }
                return label;
            };

            // Smooth line chart: tambahkan tension agar bergelombang
            if (config.type === 'line') {
                config.data.datasets = (config.data.datasets || []).map(ds => ({
                    ...ds,
                    tension: ds.tension ?? 0.4,
                    fill: ds.fill ?? false,
                    pointRadius: ds.pointRadius ?? 4,
                    pointHoverRadius: ds.pointHoverRadius ?? 6,
                    borderWidth: ds.borderWidth ?? 2.5,
                }));
            }

            // Simpan snapshot canvas ke data-chart-image setelah animasi selesai
            if (!config.options.animation) config.options.animation = {};
            const prevOnComplete = config.options.animation.onComplete;
            config.options.animation.onComplete = function(ctx) {
                if (prevOnComplete) prevOnComplete.call(this, ctx);
                try {
                    const snapshotCanvas = canvas;
                    if (snapshotCanvas) {
                        const img = snapshotCanvas.toDataURL('image/png', 0.9);
                        container.setAttribute('data-chart-image', img);
                    }
                } catch(e) { /* canvas tainted, skip */ }
            };

            try {
                new Chart(canvas, config);
                canvas.setAttribute('data-chart-initialized', 'true');

                // Store currencyColumns in container for export functions
                if (container && currencyColumns && currencyColumns.length > 0) {
                    container.setAttribute('data-currency-columns', JSON.stringify(currencyColumns));
                }

                // Add export toolbar
                if (container && !container.querySelector('.chart-toolbar')) {
                    const toolbar = document.createElement('div');
                    toolbar.className = 'chart-toolbar';
                    toolbar.innerHTML = `<button class="chart-export-pdf-btn" title="Export data grafik ke PDF">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                        </svg>
                        Export PDF
                    </button>
                    <button class="chart-export-btn" title="Export data grafik ke Excel">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Export Excel
                    </button>`;
                    toolbar.querySelector('.chart-export-pdf-btn').onclick = () => exportChartToPdf(chartId, config);
                    toolbar.querySelector('.chart-export-btn').onclick = () => exportChartToExcel(chartId, config);
                    container.insertBefore(toolbar, canvas);
                }
            } catch (e) {
                console.error('Chart.js init error:', e);
                if (container) container.innerHTML = '<p style="color:#f87171;font-size:12px;padding:10px">⚠️ Gagal render grafik: ' + e.message + '</p>';
            }
        }

        // ── Init Dashboards ───────────────────────────────────────────────────
        function initDashboardsInBubble(bubble) {
            bubble.querySelectorAll('.dashboard-grid:not([data-initialized])').forEach(grid => {
                try {
                    const configStr = grid.getAttribute('data-config');
                    if (!configStr) return;
                    const config = JSON.parse(configStr.replace(/&apos;/g, "'"));
                    
                    grid.innerHTML = (config.metrics || []).map(m => {
                        const isUp = m.change_type === 'up';
                        const changeColor = isUp ? 'text-emerald-400' : 'text-red-400';
                        const changeIcon = isUp ? '▲' : '▼';
                        
                        // Sanitize and format value
                        let formattedVal = m.value;
                        if (m.type === 'currency' && typeof m.value === 'number') {
                            formattedVal = currencyFormatter.format(m.value);
                        } else if (typeof m.value === 'number') {
                            formattedVal = m.value.toLocaleString('id-ID');
                        }

                        return `
                            <div class="metric-card">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="metric-label">${m.label || 'Metric'}</div>
                                        <div class="metric-value">${formattedVal}</div>
                                    </div>
                                    <div class="metric-icon" style="background: ${m.icon_bg || 'rgba(245,48,3,0.1)'}; color: ${m.icon_color || '#f53003'}">
                                        ${m.icon || '📊'}
                                    </div>
                                </div>
                                ${m.change ? `
                                    <div class="metric-change ${m.change_type || ''}">
                                        <span class="${changeColor}">${changeIcon} ${m.change}</span>
                                        <span class="text-[10px] text-[#706f6c] ml-1">${m.change_label || 'vs last month'}</span>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    }).join('');
                    
                    grid.setAttribute('data-initialized', 'true');
                } catch(e) {
                    console.error('[Dashboard Init] Error:', e);
                }
            });
        }

        // ── Render stream ke bubble ───────────────────────────────────────────
        function renderStreamToBubble(bubble, text, messageToolResults = null) {
            // Jangan render jika text kosong, biarkan loading card tetap tampil
            if (!text || text.trim().length === 0) return;

            bubble.innerHTML = renderMarkdown(text);
            bubble.querySelectorAll('pre code').forEach(b => { try { hljs.highlightElement(b); } catch (e) {} });
            initChartsInBubble(bubble);
            initDashboardsInBubble(bubble);
            initSmartTablesInBubble(bubble, messageToolResults);
        }

        // ── Render pesan biasa ────────────────────────────────────────────────
        function addMessage(text, sender, messageToolResults = null) {
            const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const wrap = document.createElement('div');
            wrap.className = ['flex flex-col gap-1.5',
                sender === 'user' ? 'items-end ml-auto max-w-[80%]' : 'items-start max-w-[95%]'
            ].join(' ');

            const bubble = document.createElement('div');
            bubble.className = [
                sender === 'user' ? 'chat-bubble-user' : 'chat-bubble-ai',
                'p-4 rounded-2xl text-sm shadow-sm markdown-body'
            ].join(' ');

            if (sender === 'ai') {
                bubble.innerHTML = renderMarkdown(text);
                bubble.querySelectorAll('pre code').forEach(b => { try { hljs.highlightElement(b); } catch (e) {} });
                // FIX: konversi tabel markdown → smart table di addMessage juga
                convertMarkdownTablesToSmartTables(bubble);
                initChartsInBubble(bubble);
                initDashboardsInBubble(bubble);
                initSmartTablesInBubble(bubble, messageToolResults);
                autoInjectSmartTableFromToolResults(bubble, messageToolResults || []);
            } else {
                bubble.textContent = text;
            }

            const timeEl = document.createElement('span');
            timeEl.className = 'text-[10px] text-[#706f6c] ' + (sender === 'user' ? 'mr-1' : 'ml-1');
            timeEl.textContent = time;

            wrap.appendChild(bubble);
            wrap.appendChild(timeEl);
            chatMessages.appendChild(wrap);
            requestAnimationFrame(() => chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' }));
        }

        window.onload = () => {
            messageInput.focus();
            loadSessions();
            if (currentSessionId) loadSession(currentSessionId);
        };
        });
    </script>
</body>
</html>
