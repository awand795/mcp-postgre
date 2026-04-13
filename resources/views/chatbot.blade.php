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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top left, #1a1a1a, #000000);
            height: 100vh;
            overflow: hidden;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.8);
        }
        .chat-bubble-user {
            background: linear-gradient(135deg, #f53003, #ff4433);
            color: white;
            border-bottom-right-radius: 4px;
        }
        .chat-bubble-ai {
            background: rgba(255, 255, 255, 0.05);
            color: #eeeeec;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom-left-radius: 4px;
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
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

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
        .ai-loading-label { font-size:12px; font-weight:600; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .ai-loading-label.anim { animation:txt-in 0.35s ease; }
        @keyframes txt-in { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:translateY(0)} }
        .ai-loading-sub { font-size:10px; color:#706f6c; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .ai-loading-bar-wrap { width:100%; height:2px; background:rgba(255,255,255,0.06); border-radius:2px; overflow:hidden; }
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


        /* Markdown styles */
        .markdown-body { line-height: 1.6; }
        .markdown-body p { margin: 6px 0; font-size: 13px; }
        .markdown-body h1,.markdown-body h2 { font-size: 15px; font-weight: 700; color: #fff; margin: 16px 0 8px; }
        .markdown-body h3 { font-size: 14px; font-weight: 600; color: #f97316; margin: 14px 0 6px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 4px; }
        .markdown-body h4 { font-size: 13px; font-weight: 600; color: #fb923c; margin: 10px 0 4px; }
        .markdown-body ul,.markdown-body ol { padding-left: 18px; margin: 6px 0; }
        .markdown-body li { margin: 3px 0; font-size: 13px; }
        .markdown-body strong { color: #ffffff; font-weight: 600; }
        .markdown-body em { color: #d4d4d0; font-style: italic; }
        .markdown-body code { background: rgba(255,255,255,0.1); padding: 1px 5px; border-radius: 4px; font-family: monospace; font-size: 11px; color: #fb923c; }
        .markdown-body pre { background: rgba(0,0,0,0.4); padding: 10px; border-radius: 8px; margin: 8px 0; overflow-x: auto; border: 1px solid rgba(255,255,255,0.08); }
        .markdown-body pre code { background: none; padding: 0; color: inherit; font-size: 12px; }
        .markdown-body .table-wrap { overflow-x: auto; margin: 12px 0; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); }
        .markdown-body table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 400px; }
        .markdown-body table thead tr { background: rgba(245,48,3,0.2); }
        .markdown-body table th { padding: 9px 14px; text-align: left; font-weight: 600; color: #fff; white-space: nowrap; border-bottom: 2px solid rgba(245,48,3,0.4); }
        .markdown-body table td { padding: 8px 14px; border-bottom: 1px solid rgba(255,255,255,0.06); color: #d4d4d0; white-space: nowrap; }
        .markdown-body table tbody tr:hover { background: rgba(255,255,255,0.04); }
        .markdown-body table tbody tr:last-child td { border-bottom: none; }
        .markdown-body blockquote { border-left: 3px solid #f97316; padding-left: 12px; margin: 8px 0; color: #A1A09A; font-style: italic; font-size: 12px; }
        .markdown-body hr { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 12px 0; }

        /* Chart Container */
        .chart-container {
            background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; padding: 15px 15px 25px 15px; margin: 20px 0;
            width: 100%; height: 400px; position: relative;
        }
        .chart-container canvas {
            position: relative; z-index: 1;
            max-height: 330px;
        }
        .chart-container .chart-toolbar {
            margin-bottom: 5px;
        }
        .chart-container + .markdown-body,
        .chart-container + p,
        .chart-container + div {
            margin-top: 10px;
        }
        .chart-toolbar {
            display: flex; justify-content: flex-end; gap: 8px;
            margin-bottom: 10px; padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
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
            margin: 20px 0;
            width: 100%;
        }
        .metric-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .metric-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(245, 48, 3, 0.3);
            transform: translateY(-2px);
        }
        .metric-label {
            font-size: 11px;
            font-weight: 600;
            color: #A1A09A;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
        }
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
            margin: 12px 0; border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden; background: rgba(0,0,0,0.2);
        }
        .smart-table-toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px; padding: 8px 12px;
            background: rgba(245,48,3,0.08);
            border-bottom: 1px solid rgba(255,255,255,0.08); flex-wrap: wrap;
        }
        .smart-table-info { font-size: 11px; color: #A1A09A; white-space: nowrap; }
        .smart-table-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .smart-table-search {
            flex: 1; min-width: 120px; max-width: 220px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12);
            border-radius: 6px; padding: 4px 9px; font-size: 11px; color: #fff;
            outline: none; font-family: 'Outfit', sans-serif;
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
        .smart-table-search::placeholder { color: rgba(255,255,255,0.25); }
        .smart-table-search:focus { border-color: rgba(245,48,3,0.5); }
        .smart-table-scroll { overflow-x: auto; }
        .smart-table-scroll table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 400px; }
        .smart-table-scroll thead tr { background: rgba(245,48,3,0.15); }
        .smart-table-scroll th {
            padding: 8px 13px; text-align: left; font-weight: 600; color: #fff;
            white-space: nowrap; border-bottom: 2px solid rgba(245,48,3,0.35);
            cursor: pointer; user-select: none; font-size: 11px;
        }
        .smart-table-scroll th:hover { background: rgba(245,48,3,0.25); }
        .smart-table-scroll th .sort-icon { margin-left: 4px; opacity: 0.4; font-size: 10px; }
        .smart-table-scroll th.sort-asc .sort-icon,
        .smart-table-scroll th.sort-desc .sort-icon { opacity: 1; color: #f53003; }
        .smart-table-scroll td {
            padding: 7px 13px !important; border-bottom: 1px solid rgba(255,255,255,0.05) !important;
            color: #d4d4d0 !important; max-width: 300px !important; font-size: 11px !important;
            overflow: hidden !important; text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        .smart-table-scroll td.wrap { white-space: normal !important; line-height: 1.4 !important; min-width: 200px !important; }
        .smart-table-scroll tbody tr:hover { background: rgba(255,255,255,0.04); }
        .smart-table-scroll tbody tr:last-child td { border-bottom: none; }
        .smart-table-pagination {
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px; padding: 7px 12px;
            border-top: 1px solid rgba(255,255,255,0.07);
            background: rgba(0,0,0,0.15); flex-wrap: wrap;
        }
        .smart-table-page-info { font-size: 11px; color: #706f6c; }
        .smart-table-btns { display: flex; gap: 4px; flex-wrap: wrap; }
        .st-btn {
            padding: 3px 9px; border-radius: 5px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.04); color: #A1A09A;
            font-size: 11px; cursor: pointer; transition: all 0.15s;
            font-family: 'Outfit', sans-serif;
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
            background-color: #0a0a0a !important;
        }
        #chat-sidebar.open {
            background-color: rgba(10, 10, 10, 0.98) !important;
        }
        
        #history-list .group {
            cursor: pointer;
            user-select: none;
        }
        #history-list .group:active {
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
    </style>
</head>

<body class="flex items-center justify-center p-2 md:p-4">

    <!-- Main Container -->
    <div class="flex w-full max-w-6xl h-[95vh] glass-panel rounded-3xl overflow-hidden relative">

        <!-- Sidebar (Pushes content, not overlay) -->
        <aside id="chat-sidebar" class="w-72 bg-[#0a0a0a]/95 border-r border-white/10 flex flex-col transition-all duration-300 flex-shrink-0 overflow-hidden" style="width: 0; opacity: 0;">

            <!-- Sidebar Header / Mobile Close -->
            <div class="p-4 border-b border-white/10 flex items-center justify-between flex-shrink-0" style="min-width: 288px;">
                <button id="btn-new-chat" class="flex-1 flex items-center justify-center gap-2 py-2 bg-gradient-to-r from-[#f53003]/20 to-[#ff4433]/20 hover:from-[#f53003]/40 hover:to-[#ff4433]/40 text-white border border-[#f53003]/50 rounded-xl text-sm font-medium transition-all shadow-lg shadow-red-500/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Chat Baru
                </button>
                <button id="btn-close-sidebar" class="ml-3 p-1.5 text-[#A1A09A] hover:text-white rounded-lg hover:bg-white/10 transition-colors flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <!-- History List -->
            <div class="px-4 py-3 pb-1 text-[11px] font-semibold text-white/40 uppercase tracking-wider flex-shrink-0" style="min-width: 288px;">Riwayat Terakhir</div>
            <div id="history-list" class="flex-1 overflow-y-auto p-2 space-y-1 custom-scrollbar" style="min-width: 288px;">
                <!-- JS populated -->
                <div class="flex items-center justify-center h-full text-[#A1A09A] text-xs opacity-50">Memuat riwayat...</div>
            </div>
        </aside>

        <!-- Overlay (hanya untuk mobile/backdrop click) -->
        <div id="sidebar-overlay" class="absolute inset-0 bg-black/60 z-0 hidden backdrop-blur-sm transition-opacity opacity-0" style="pointer-events: none;"></div>

        <!-- Main Chat Area (flex-1, akan bergeser saat sidebar buka) -->
        <div class="flex flex-col flex-1 min-w-0 h-full bg-black/10">
        <div class="p-4 md:p-5 border-b border-white/10 flex items-center justify-between flex-shrink-0 transition-all duration-300">
            <div class="flex items-center gap-2 md:gap-3 w-full max-w-full">
                <!-- Hamburger and New Chat for Header -->
                <div class="flex items-center">
                    <button id="btn-open-sidebar" title="Toggle Sidebar" class="p-1.5 md:p-2 -ml-1 text-[#A1A09A] hover:text-white rounded-lg hover:bg-white/10 transition-colors cursor-pointer select-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <button id="btn-new-chat-header" title="Chat Baru" class="hidden p-1.5 md:p-2 text-[#A1A09A] hover:text-white rounded-lg hover:bg-white/10 transition-colors cursor-pointer select-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>
                
                <img src="{{ asset('logo_dmi.png') }}" alt="Darko AI Logo" class="w-8 h-8 md:w-10 md:h-10 object-contain ml-1">
                <div class="min-w-0">
                    <h1 class="text-white font-semibold text-base md:text-lg leading-tight truncate">darkotech AI</h1>
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
                    class="btn-clear hidden md:flex items-center gap-1.5 px-3 py-2 rounded-xl text-[#A1A09A] text-xs border border-white/10 hover:border-red-500/30 hover:bg-black/20 transition-all focus:ring-1 focus:ring-red-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                        <path d="M10 11v6M14 11v6M9 6V4h6v2" />
                    </svg>
                    <span class="btn-text">Hapus Riwayat</span>
                </button>
                <button id="btn-clear-chat-mobile" title="Hapus riwayat obrolan ini"
                    class="btn-clear md:hidden flex items-center p-2 rounded-xl text-[#A1A09A] border border-transparent hover:border-red-500/30 hover:bg-black/20 hover:text-red-500 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><path d="M10 11v6M14 11v6M9 6V4h6v2" />
                    </svg>
                </button>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" title="Keluar"
                        class="btn-clear flex items-center gap-1.5 px-3 py-2 rounded-xl text-[#A1A09A] text-xs border border-white/10 hover:border-red-500/30 hover:text-red-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        <span class="btn-text">Logout</span>
                    </button>
                </form>
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
        <div class="p-5 bg-black/20 border-t border-white/10 flex-shrink-0">
            <div class="relative">
                <input type="text" id="message-input" placeholder="Ketik pesan anda di sini..."
                    class="w-full bg-white/5 border border-white/10 rounded-2xl py-3.5 pl-5 pr-14 text-white placeholder-white/25 focus:outline-none focus:ring-2 focus:ring-[#f53003]/40 transition-all text-sm"
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
        document.addEventListener('DOMContentLoaded', function() {
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
                    const response = await fetch('{{ route("chatbot.send") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ 
                            message, 
                            history: conversationHistory,
                            chat_session_id: currentSessionId
                        }),
                    });

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
                                    renderStreamToBubble(bubble, aiResponseText);
                                }

                                // ── Notifikasi proses (label bisnis) ──────────────
                                if (parsed.tool_call) {
                                    const tc = parsed.tool_call;
                                    const icon  = toolIcons[tc.name] || '🔄';
                                    const label = toolLabels[tc.name] || 'Memproses data';

                                    if (tc.status === 'running') {
                                        const badge = document.createElement('div');
                                        badge.className = 'tool-call-badge running';
                                        badge.dataset.tool = tc.name;

                                        // Info konteks tambahan (label bisnis)
                                        let detail = '';
                                        if (tc.name === 'execute_query' && tc.arguments?.label) {
                                            // Gunakan label bisnis jika ada, jika tidak, biarkan kosong (jangan tampilkan SQL)
                                            detail = ` · ${tc.arguments.label}`;
                                        }
                                        
                                        // Hilangkan detail nama tabel teknis untuk tool schema
                                        if (['describe_table', 'list_tables', 'get_schema_info'].includes(tc.name)) {
                                            detail = '';
                                        }

                                        badge.innerHTML = `
                                            <span class="tool-call-dot running"></span>
                                            <span>${icon} ${label}${detail}</span>
                                        `;
                                        toolArea.appendChild(badge);
                                        toolBadges[tc.name + '_' + Object.keys(toolBadges).length] = badge;
                                        typingText.textContent = label + '...';
                                    } else if (tc.status === 'done' || tc.status === 'success') {
                                        const runningBadge = toolArea.querySelector('.tool-call-badge.running');
                                        if (runningBadge) {
                                            runningBadge.classList.remove('running');
                                            runningBadge.classList.add('done');
                                            const dot = runningBadge.querySelector('.tool-call-dot');
                                            if (dot) { dot.classList.remove('running'); }
                                            const dotEl = runningBadge.querySelector('.tool-call-dot');
                                            if (dotEl) dotEl.textContent = '✓';
                                        }
                                        typingText.textContent = 'Menyusun laporan...';
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

                    if (toolArea.children.length === 0) {
                        toolArea.style.display = 'none';
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

            // ── Buat bubble AI ────────────────────────────────────────────────────
            function createStreamBubble() {
                const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

                const wrap = document.createElement('div');
                wrap.className = 'flex flex-col gap-1.5 items-start max-w-[95%]';

                const toolArea = document.createElement('div');
                toolArea.className = 'flex flex-col gap-1 pl-1 mb-1';

                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble-ai p-4 rounded-2xl text-sm shadow-sm markdown-body';
                bubble.innerHTML = '<span class="opacity-40 animate-pulse text-xs">⏳ Sedang memproses...</span>';

                const timeEl = document.createElement('span');
                timeEl.className = 'text-[10px] text-[#706f6c] ml-1';
                timeEl.textContent = time;

                wrap.appendChild(toolArea);
                wrap.appendChild(bubble);
                wrap.appendChild(timeEl);

                return { bubble, toolArea, wrapper: wrap };
            }

            function renderStreamToBubble(bubble, text) {
                bubble.innerHTML = renderMarkdown(text);
                bubble.querySelectorAll('pre code').forEach(b => {
                    try { hljs.highlightElement(b); } catch(e) {}
                });
                initChartsInBubble(bubble);
                initSmartTablesInBubble(bubble);
            }

            // ── Render pesan biasa ────────────────────────────────────────────────
            function addMessage(text, sender) {
                const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                const wrap = document.createElement('div');
                wrap.className = [
                    'flex flex-col gap-1.5',
                    sender === 'user' ? 'items-end ml-auto max-w-[80%]' : 'items-start max-w-[95%]'
                ].join(' ');

                const bubble = document.createElement('div');
                bubble.className = [
                    sender === 'user' ? 'chat-bubble-user' : 'chat-bubble-ai',
                    'p-4 rounded-2xl text-sm shadow-sm markdown-body'
                ].join(' ');

                if (sender === 'ai') {
                    bubble.innerHTML = renderMarkdown(text);
                    bubble.querySelectorAll('pre code').forEach(b => { try { hljs.highlightElement(b); } catch(e) {} });
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
                    item.className = `group flex items-center justify-between p-2 rounded-lg cursor-pointer transition-colors ${isActive ? 'bg-white/10 text-white' : 'text-[#A1A09A] hover:bg-white/5 hover:text-white'}`;
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
                    initChartsInBubble(bubble, toolResultsForInit);
                    initDashboardsInBubble(bubble);
                    initSmartTablesInBubble(bubble, toolResultsForInit);
                }, 50);
            } else {
                bubble.textContent = msg.content;
            }

            const timeEl = document.createElement('span');
            timeEl.className = 'text-[10px] text-[#706f6c] ' + (msg.role === 'user' ? 'mr-1' : 'ml-1');
            timeEl.textContent = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

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
            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.innerHTML = `<svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Exporting ${rows.length} rows...`;
            }

            try {
                // Clean data: remove HTML tags only AND force string format for large numbers
                const cleanRows = rows.map(row =>
                    row.map(cell => {
                        if (cell === null || cell === undefined) return '';

                        // 1. Handle already numeric values first
                        if (typeof cell === 'number') return cell;
                        
                        // 2. Remove HTML tags
                        const temp = document.createElement('div');
                        temp.innerHTML = cell;
                        let value = temp.textContent || temp.innerText || String(cell);
                        
                        // 3. Smart Cleanup for Currency
                        // If it contains 'Rp', it's definitely a formatted Indonesian currency string.
                        // We remove 'Rp', thousand separators (.), and handle decimal comma (,) if present.
                        if (value.includes('Rp')) {
                            value = value.replace(/Rp\s?/g, '');
                            // If it has multiple dots, they are thousand separators (e.g., 1.000.000)
                            if ((value.match(/\./g) || []).length > 1) {
                                value = value.replace(/\./g, '');
                            } 
                            // If it has one dot followed by 3 digits at the end, it's likely a thousand separator
                            else if (/\.\d{3}$/.test(value)) {
                                value = value.replace(/\./g, '');
                            }
                            // Convert decimal comma to dot for parseFloat
                            value = value.replace(',', '.');
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
                    const temp = document.createElement('div');
                    temp.innerHTML = h;
                    const rawLabel = temp.textContent || temp.innerText || String(h);
                    return toHumanLabel(rawLabel);
                });

                // Generate filename with timestamp
                const tableLabel = smartTables[tableId]?.label || tableId.replace(/_/g, ' ');
                const safeLabel = tableLabel.replace(/[^a-zA-Z0-9\s-]/g, '').trim().replace(/\s+/g, '-').toLowerCase();
                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                const filename = `${safeLabel || 'table-export'}-${timestamp}.xlsx`;

                // Send ALL data to backend for Excel generation
                const response = await fetch('{{ route("chatbot.export.excel") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        headers: cleanHeaders,
                        rows: cleanRows,
                        filename: filename,
                        title: tableLabel
                    }),
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || errorData.error || 'Export failed');
                }

                // Download the file
                const blob = await response.blob();
                
                if (!blob || blob.size === 0) {
                    throw new Error('File kosong atau tidak valid');
                }
                
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

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
            const exportBtn = document.querySelector(`#${chartId}`).closest('.chart-container').querySelector('.chart-export-btn');
            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.innerHTML = `<svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Exporting...`;
            }

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
                
                // Prepare data for Excel - EXACT data from chart
                const rows = [];
                const headers = ['No', 'Label', ...datasets.map((d, i) => {
                    // Use actual dataset label, or generate meaningful name
                    if (d.label) return d.label;
                    const typeLabel = chartType.charAt(0).toUpperCase() + chartType.slice(1);
                    return `${typeLabel} ${i + 1}`;
                })];
                
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

                // Send to backend for Excel generation
                const response = await fetch('{{ route("chatbot.export.excel") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        headers: headers,
                        rows: rows,
                        filename: filename,
                        title: chartTitle,
                        chartInfo: {
                            type: chartType,
                            title: chartTitle,
                            datasetCount: datasets.length,
                            dataPoints: maxLength
                        }
                    }),
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Export response error:', errorText);
                    throw new Error('Export failed: ' + response.status);
                }

                // Download the file
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

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
            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.innerHTML = `<svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Exporting...`;
            }

            try {
                const cleanHeaders = headers.map(h => {
                    const temp = document.createElement('div');
                    temp.innerHTML = h;
                    return temp.textContent || temp.innerText || String(h);
                });

                const cleanRows = rows.map(row =>
                    row.map(cell => {
                        if (cell === null || cell === undefined) return '';
                        const temp = document.createElement('div');
                        temp.innerHTML = cell;
                        return (temp.textContent || temp.innerText || String(cell)).trim();
                    })
                );

                const tableLabel = smartTables[tableId]?.label || tableId.replace(/_/g, ' ');
                const safeLabel = tableLabel.replace(/[^a-zA-Z0-9\s-]/g, '').trim().replace(/\s+/g, '-').toLowerCase();
                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                const filename = `${safeLabel || 'table-export'}-${timestamp}.pdf`;

                const response = await fetch('{{ route("chatbot.export.pdf") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        headers: cleanHeaders,
                        rows: cleanRows,
                        filename: filename,
                        title: tableLabel
                    }),
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || errorData.error || 'Export failed');
                }

                const blob = await response.blob();
                if (!blob || blob.size === 0) throw new Error('File kosong atau tidak valid');

                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: `✅ Export PDF berhasil! ${rows.length} baris data telah diunduh.`,
                    showConfirmButton: false, timer: 3000, timerProgressBar: true,
                });

            } catch (error) {
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
            const exportBtn = document.querySelector(`#${chartId}`).closest('.chart-container').querySelector('.chart-export-pdf-btn');
            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.innerHTML = `<svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Exporting...`;
            }

            try {
                const chartContainer = document.querySelector(`#${chartId}`)?.closest('.chart-container');
                const chartTitle = chartContainer?.getAttribute('data-title')
                    || chartConfig.data?.datasets?.[0]?.label
                    || chartConfig.options?.plugins?.title?.text
                    || chartConfig.title
                    || 'Grafik Data';
                const labels = chartConfig.data?.labels || [];
                const datasets = chartConfig.data?.datasets || [];
                const chartType = chartConfig.type || 'bar';

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

                // Convert chart to image for PDF
                const canvas = document.querySelector(`#${chartId}-canvas`) || document.querySelector(`#${chartId} canvas`);
                let chartImage = null;
                if (canvas) {
                    try {
                        chartImage = canvas.toDataURL('image/png', 0.7);
                    } catch (e) {
                        console.warn('[PDF Chart] Canvas tainted or error, skipping image');
                        chartImage = null;
                    }
                }

                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                const safeTitle = chartTitle.replace(/[^a-zA-Z0-9]/g, '-').substring(0, 20);
                const filename = `chart-${safeTitle || 'export'}-${timestamp}.pdf`;

                const response = await fetch('{{ route("chatbot.export.pdf") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        headers: headers,
                        rows: rows,
                        filename: filename,
                        title: chartTitle,
                        chartImage: chartImage
                    }),
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => null);
                    throw new Error(errorData?.message || errorData?.error || `Server error (${response.status})`);
                }

                const blob = await response.blob();
                if (!blob || blob.size === 0) throw new Error('File kosong atau tidak valid');

                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: `✅ Export PDF grafik berhasil!`,
                    showConfirmButton: false, timer: 3000, timerProgressBar: true,
                });

            } catch (error) {
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
            }

            // 1. Comprehensive list of money markers in both Indonesian and English
            const moneyMarkers = [
                'netto', 'dpp', 'harga', 'price', 'laba', 'profit', 'nominal', 'biaya', 'fee', 'ongkir',
                'amount', 'sales', 'payment', 'revenue', 'cogs', 'hpp', 'tax', 'diskon', 'discount',
                'budget', 'total_netto', 'total_dpp', 'gpn'
            ];
            
            const isMoney = moneyMarkers.some(p => h.includes(p));
            
            if (isMoney) {
                // Exclusion check for quantities/counts even with money markers
                const excludes = ['qty', 'jumlah', 'count', 'unit', 'terjual', 'stok', 'stock', 'persen', 'percent'];
                if (excludes.some(e => h.includes(e))) {
                    // Exception: specific money totals
                    if (h === 'total_netto' || h === 'total_dpp') return true;
                    return false;
                }
                return true;
            }

            // If it's a "total" column and not a count, it's likely money in this business context
            if (h.includes('total') && !h.includes('qty') && !h.includes('terjual') && !h.includes('jumlah') && !h.includes('count') && !h.includes('unit')) {
                return true;
            }

            return false;
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
            const strVal = String(val);
            const h = header ? header.toLowerCase() : '';
            
            // Clean number for parsing
            const num = parseFloat(strVal.replace(/[^0-9.-]/g, ''));
            
            if (isMoney && !isNaN(num)) {
                return currencyFormatter.format(num);
            }
            
            // Patterns that should NOT get thousands separators (IDs, Codes, Years, Refs, etc.)
            // Synchronized with backend PDF export regex
            const isNonNumericStyled = /(id|no|telepon|phone|nik|faktur|polis|rangka|mesin|periode|bulan|tahun|nama|alamat|cabang|merek|model|tipe|kode|code|sku|ref)/i.test(h);

            // Format as standard number with thousands separator if it's numeric
            if (typeof val === 'number') {
                if (isNonNumericStyled) return String(val);
                return val.toLocaleString('id-ID');
            }
            
            // If string looks like a pure number and is long, format it ONLY if not an ID/Code/Ref
            if (!isNaN(num) && /^-?\d+$/.test(strVal) && strVal.length > 3 && !isNonNumericStyled) {
                return num.toLocaleString('id-ID');
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
                const q = query.toLowerCase();
                filtered = allRows.filter(row => row.some(c => String(c).toLowerCase().includes(q)));
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
        function initSmartTablesInBubble(bubble, messageToolResults = null) {
            const toolResults = messageToolResults !== null ? messageToolResults : currentToolResults;

            bubble.querySelectorAll('.smart-table-wrap:not([data-initialized])').forEach((wrap, idx) => {
                const tableId = wrap.getAttribute('data-table-id') || ('st-' + Math.random().toString(36).substr(2, 9));
                const toolIdx = parseInt(wrap.getAttribute('data-tool-index'));

                let headers = [];
                let allRows = [];
                let toolRes = null;
                let tableLabel = null;

                try {
                    const hb64 = wrap.getAttribute('data-headers-b64');
                    const rb64 = wrap.getAttribute('data-rows-b64');

                    if (hb64 && rb64) {
                        headers = JSON.parse(decodeURIComponent(escape(atob(hb64))));
                        allRows = JSON.parse(decodeURIComponent(escape(atob(rb64))));
                        tableLabel = wrap.getAttribute('data-title') || tableId.replace(/_/g, ' ');
                    }
                    else if (!isNaN(toolIdx)) {
                        toolRes = toolResults[toolIdx];

                        const hasValidData = (res) => {
                            if (!res) return false;
                            if (res.data && res.data.rows && Array.isArray(res.data.rows) && res.data.rows.length > 0) return true;
                            if (res.rows && Array.isArray(res.rows) && res.rows.length > 0) return true;
                            return false;
                        };

                        if (!hasValidData(toolRes)) {
                            for (let i = toolResults.length - 1; i >= 0; i--) {
                                const r = toolResults[i];
                                if (r && r.tool_name === 'execute_query' && hasValidData(r)) {
                                    toolRes = r;
                                    break;
                                }
                            }

                            if (!hasValidData(toolRes)) {
                                for (let i = toolResults.length - 1; i >= 0; i--) {
                                    const r = toolResults[i];
                                    if (r && hasValidData(r)) {
                                        toolRes = r;
                                        break;
                                    }
                                }
                            }
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

                        const tableData = toolRes.data || toolRes;
                        currencyColumns = tableData.currency_columns || [];
                        tableLabel = toolRes.label || tableData.label || null;

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
                        currencyColumns: currencyColumns || [],
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

                            if (chartData.tool_index !== undefined) {
                                chartIdx = parseInt(chartData.tool_index);
                                // Try to get label from the tool result
                                if (chartIdx >= 0 && currentToolResults[chartIdx]) {
                                    const tr = currentToolResults[chartIdx];
                                    chartLabel = tr.label || tr.data?.label || null;
                                }
                            } else if (chartData.type) {
                                chartIdx = currentToolResults.length;
                                // Get title from: explicit title > label > first dataset label
                                chartLabel = chartData.title || chartData.label
                                    || chartData.data?.datasets?.[0]?.label
                                    || null;
                                currentToolResults.push({
                                    tool_name: 'chart',
                                    data: { chart_config: chartData },
                                    label: chartLabel
                                });
                            }

                            if (chartIdx < 0) {
                                return '<div class="chart-container"><div class="flex items-center justify-center h-full"><span class="opacity-40 text-xs">⚠️ Format grafik tidak valid</span></div></div>';
                            }

                            const chartId = 'chart-' + Math.random().toString(36).substr(2, 9);
                            const titleAttr = chartLabel ? ` data-title="${chartLabel}"` : '';
                            return `<div class="chart-container" id="${chartId}" data-tool-index="${chartIdx}"${titleAttr}>
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

                            if (idx >= 0 && !currentToolResults[idx]) {
                                return `<div class="table-wrap border-dashed border-white/10 flex items-center gap-2 px-4 py-3">
                                    <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
                                    <span class="opacity-40 text-xs">Menunggu data (Tool #${idx})...</span>
                                </div>`;
                            }

                            if (idx >= 0 && currentToolResults[idx]) {
                                const tableId = 'st-direct-' + Math.random().toString(36).substr(2, 9);
                                return `<div class="smart-table-wrap" id="${tableId}" data-table-id="${tableId}" data-tool-index="${idx}">
                                    <div class="smart-table-toolbar">
                                        <span class="smart-table-info">📊 Memuat...</span>
                                        <input class="smart-table-search" type="text" placeholder="🔍 Cari di tabel...">
                                    </div>
                                    <div class="smart-table-scroll">
                                        <table><thead><tr><th class="p-4">⏳ Menginisialisasi...</th></tr></thead><tbody></tbody></table>
                                    </div>
                                    <div class="smart-table-pagination"><span class="smart-table-page-info"></span><div class="smart-table-btns"></div></div>
                                </div>`;
                            }
                        } catch(e) {
                            console.error('[SmartTable Renderer] Error:', e);
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
                const response = await fetch('{{ route("chatbot.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        message,
                        history: conversationHistory,
                        chat_session_id: currentSessionId
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

                            if (parsed.chunk !== undefined && parsed.chunk !== '') {
                                aiResponseText += parsed.chunk;

                                // Update loading state to show progress
                                if (bubble._loadInterval && aiResponseText.trim().length > 50) {
                                    clearInterval(bubble._loadInterval);
                                    bubble._loadInterval = null;
                                }

                                if (!bubble._loadInterval) {
                                    renderStreamToBubble(bubble, aiResponseText, currentToolResults);
                                }

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
                                        renderStreamToBubble(bubble, aiResponseText, currentToolResults);
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

                if (toolArea.children.length === 0 && aiResponseText.trim().length === 0) {
                    bubble.innerHTML = renderMarkdown('Maaf, saya tidak dapat memproses permintaan Anda.');
                }

                if (toolArea.children.length === 0) toolArea.style.display = 'none';

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
                // Store label on container for export
                const chartLabel = toolRes.label || toolRes.data?.label || null;
                if (chartLabel) container.setAttribute('data-title', chartLabel);
                initChartWithConfig(canvas, config, container, chartId, currencyColumns);
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

                // Format currency di ticks Y jika datanya besar
                if (axis === 'y') {
                    const oldCallback = scales[axis].ticks.callback;
                    scales[axis].ticks.callback = function(value) {
                        if (oldCallback) return oldCallback.call(this, value);
                        
                        // Check if ANY dataset in this chart is a currency column
                        let isCurrencyChart = false;
                        if (currencyColumns && currencyColumns.length > 0) {
                            isCurrencyChart = true;
                        } else {
                            const firstLabel = config.data?.datasets?.[0]?.label;
                            if (firstLabel && isCurrencyColumn(firstLabel)) {
                                isCurrencyChart = true;
                            }
                        }

                        if (isCurrencyChart) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                        return value.toLocaleString('id-ID');
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

            try {
                new Chart(canvas, config);
                canvas.setAttribute('data-chart-initialized', 'true');

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
                initChartsInBubble(bubble);
                initDashboardsInBubble(bubble);
                initSmartTablesInBubble(bubble, messageToolResults);
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
