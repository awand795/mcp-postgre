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
            border-radius: 12px; padding: 15px; margin: 15px 0;
            width: 100%; height: 300px; position: relative;
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

        <!-- Main Chat Area (flex-1, akan bergeser saat sidebar buka) -->
        <div class="flex flex-col flex-1 min-w-0 h-full bg-black/10" style="z-index: 1; position: relative;">
        <div class="p-4 md:p-5 border-b border-white/10 flex items-center justify-between flex-shrink-0 transition-all duration-300" style="z-index: 100; position: relative;">
            <div class="flex items-center gap-2 md:gap-3 w-full max-w-full">
                <div class="flex items-center" style="z-index: 110; position: relative;">
                    <button id="btn-open-sidebar" type="button" title="Toggle Sidebar" class="p-1.5 md:p-2 -ml-1 text-[#A1A09A] hover:text-white rounded-lg hover:bg-white/10 transition-colors" style="z-index: 120; position: relative; cursor: pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <button id="btn-new-chat-header" type="button" title="Chat Baru" class="hidden p-1.5 md:p-2 text-[#A1A09A] hover:text-white rounded-lg hover:bg-white/10 transition-colors" style="z-index: 120; position: relative; cursor: pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 md:w-6 md:h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
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

            const sidebar = document.getElementById('chat-sidebar');
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
                    if (btnNewChatHeader) btnNewChatHeader.classList.add('hidden');
                } else {
                    // CLOSE sidebar - collapse width
                    sidebar.style.width = '0';
                    sidebar.style.opacity = '0';
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

            if (btnOpenSidebar) {
                btnOpenSidebar.addEventListener('click', function() {
                    toggleSidebar();
                });
            }

            if (btnCloseSidebar) btnCloseSidebar.addEventListener('click', (e) => {
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
                    const response = await fetch('/chatbot/send', {
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

                                        // Info konteks tambahan (nama tabel/label)
                                        let detail = '';
                                        if (tc.name === 'execute_query' && tc.arguments?.label) {
                                            detail = ` · ${tc.arguments.label}`;
                                        }
                                        if (tc.name === 'describe_table' && tc.arguments?.table_name) {
                                            detail = '';  // Sembunyikan nama tabel teknis
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
                                        typingText.textContent = 'Menganalisis data...';
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
                const res = await fetch('/chatbot/sessions');
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
                    clickArea.style.pointerEvents = 'auto';
                    clickArea.style.cursor = isLoading ? 'not-allowed' : 'pointer';
                    clickArea.style.opacity = isLoading ? '0.5' : '1';
                    clickArea.innerHTML = `
                        <svg class="w-4 h-4 flex-shrink-0 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        <span class="text-[11px] md:text-xs truncate select-none">${s.title}</span>
                    `;
                    clickArea.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (!isLoading) {
                            loadSession(s.id);
                        }
                    });

                    // Delete button
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'delete-session btn-clear p-1.5 opacity-0 group-hover:opacity-100 transition-opacity rounded-md hover:bg-red-500/20 hover:text-red-500';
                    deleteBtn.style.pointerEvents = 'auto';
                    deleteBtn.innerHTML = `<svg class="w-3.5 h-3.5 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>`;
                    deleteBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        deleteSession(s.id, e);
                    });

                    item.appendChild(clickArea);
                    item.appendChild(deleteBtn);
                    historyList.appendChild(item);
                });
            } catch (e) {
                historyList.innerHTML = '<div class="p-4 text-center text-red-400 text-xs">Gagal memuat riwayat</div>';
            }
        }

        async function loadSession(id) {
            if (isLoading) {
                return;
            }
            
            // Set loading flag to prevent concurrent operations
            isLoading = true;
            
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
            
            currentSessionId = id;
            window.history.pushState({}, '', '?chat=' + id);

            // Close sidebar on mobile only
            if (window.innerWidth < 768) toggleSidebar(false);

            // Show loading state with spinner
            chatMessages.innerHTML = '<div class="flex flex-col items-center justify-center h-full gap-4"><svg class="animate-spin h-10 w-10 text-[#f53003]" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><p class="text-[#A1A09A] text-sm animate-pulse">Memuat riwayat chat...</p></div>';

            try {
                const res = await fetch('/chatbot/sessions', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                    // Timeout 10 menit untuk history dengan data besar
                    signal: AbortSignal.timeout(600000)
                });

                const contentType = res.headers.get('content-type');
                
                if (!res.ok) {
                    let errorMsg = 'HTTP ' + res.status;
                    
                    // Try to get error details from response
                    if (contentType && contentType.includes('application/json')) {
                        const errorData = await res.json();
                        errorMsg = errorData.message || errorData.error || errorMsg;
                    } else {
                        const text = await res.text();
                        console.error('Server response:', text.substring(0, 500));
                        if (text.includes('Allowed memory size')) {
                            errorMsg = 'Memory limit exceeded. Data terlalu besar.';
                        } else if (text.includes('timeout')) {
                            errorMsg = 'Request timeout. Server terlalu lama meresponse.';
                        }
                    }
                    
                    throw new Error(errorMsg);
                }

                const data = await res.json();

                chatMessages.innerHTML = '';
                conversationHistory = [];

                if (data.history.length === 0) {
                    addWelcomeMessage();
                    await loadSessions();
                    return;
                }

                // Process messages with error handling - Load ALL smart tables
                for (let index = 0; index < data.history.length; index++) {
                    const msg = data.history[index];
                    
                    try {
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
                            // Safely handle tool_results - ALL DATA
                            let toolResultsForMsg = [];
                            try {
                                toolResultsForMsg = Array.isArray(msg.tool_results) ? msg.tool_results : [];
                            } catch (e) {
                                console.warn('Failed to parse tool_results for message', index, e);
                            }

                            // Temporarily set global for markdown renderer
                            const originalGlobal = currentToolResults;
                            currentToolResults = toolResultsForMsg;

                            try {
                                bubble.innerHTML = renderMarkdown(msg.content);
                                bubble.querySelectorAll('pre code').forEach(b => { 
                                    try { hljs.highlightElement(b); } catch (e) {} 
                                });

                                initChartsInBubble(bubble);

                                // INIT ALL SMART TABLES - No lazy load, show all data
                                initSmartTablesInBubble(bubble, toolResultsForMsg);
                            } catch (e) {
                                console.error('Failed to render message', index, e);
                                bubble.textContent = '[Pesan tidak dapat ditampilkan]';
                            }

                            // Restore global
                            currentToolResults = originalGlobal;
                        } else {
                            bubble.textContent = msg.content;
                        }

                        const timeEl = document.createElement('span');
                        timeEl.className = 'text-[10px] text-[#706f6c] ' + (msg.role === 'user' ? 'mr-1' : 'ml-1');
                        timeEl.textContent = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

                        wrap.appendChild(bubble);
                        wrap.appendChild(timeEl);
                        chatMessages.appendChild(wrap);
                        
                    } catch (e) {
                        console.error('Failed to process message', index, e);
                        // Continue to next message instead of failing completely
                    }
                }

                await loadSessions();

                // Wait for all smart tables to render (increased for large data)
                await new Promise(resolve => setTimeout(resolve, 1000));

                chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'instant' });

            } catch (e) {
                console.error('Failed to load session:', e);
                chatMessages.innerHTML = '<div class="p-4 text-center text-red-400">Gagal memuat percakapan. ' + e.message + '</div>';
            } finally {
                // CRITICAL: Always release loading flag, even on error
                setTimeout(() => {
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
                    
                    // Lazy load observer cleanup (removed - now loading all tables immediately)
                }, 100);
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
        const tableObserver = new MutationObserver(mutations => {
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
                // Clean data: remove HTML tags only
                const cleanRows = rows.map(row =>
                    row.map(cell => {
                        if (cell === null || cell === undefined) return '';
                        const temp = document.createElement('div');
                        temp.innerHTML = cell;
                        return temp.textContent || temp.innerText || String(cell);
                    })
                );

                const cleanHeaders = headers.map(h => {
                    const temp = document.createElement('div');
                    temp.innerHTML = h;
                    return temp.textContent || temp.innerText || String(h);
                });

                // Generate filename with timestamp
                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                const filename = `table-export-${timestamp}.xlsx`;

                // Send ALL data to backend for Excel generation
                const response = await fetch('/chatbot/export/excel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        headers: cleanHeaders,
                        rows: cleanRows,
                        filename: filename
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

                alert(`✅ Export berhasil! ${rows.length} baris data telah diunduh.`);

            } catch (error) {
                console.error('[Export Error]', error);
                
                let errorMsg = 'Gagal export tabel.';
                if (error.message.includes('timeout')) {
                    errorMsg = 'Export timeout. Silakan coba lagi.';
                } else if (error.message.includes('memory')) {
                    errorMsg = 'Memory limit. Silakan coba lagi.';
                } else if (error.message.includes('413')) {
                    errorMsg = 'Data terlalu besar. Silakan filter data terlebih dahulu.';
                } else {
                    errorMsg = `❌ ${error.message}`;
                }
                
                alert(errorMsg);
                
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
                const chartTitle = chartConfig.options?.plugins?.title?.text || 'Chart Data';
                
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
                const response = await fetch('/chatbot/export/excel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        headers: headers,
                        rows: rows,
                        filename: filename,
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

            } catch (error) {
                console.error('[Chart Export Error]', error);
                alert('Gagal export grafik: ' + error.message);
            } finally {
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg> Export Excel`;
                }
            }
        }

        function isCurrencyColumn(header) {
            if (!header) return false;
            const h = header.toLowerCase();
            return h.includes('total') || h.includes('amount') || h.includes('dpp') || 
                   h.includes('netto') || h.includes('cogs') || h.includes('gpn') || 
                   h.includes('harga') || h.includes('price') || h.includes('nominal') ||
                   h.includes('sales') || h.includes('laba') || h.includes('profit') ||
                   h.includes('pencapaian');
        }

        function formatCellValue(val, header) {
            if (val === null || val === undefined || val === '') return '';
            if (header && isCurrencyColumn(header)) {
                const num = parseFloat(String(val).replace(/[^0-9.-]/g, ''));
                if (!isNaN(num)) return currencyFormatter.format(num);
            }
            if (typeof val === 'number') return val.toLocaleString('id-ID');
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
                actionsDiv.innerHTML = `<button class="smart-table-export-btn" title="Export ke Excel">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export Excel
                </button>`;
                const exportBtn = actionsDiv.querySelector('.smart-table-export-btn');
                exportBtn.onclick = () => exportTableToExcel(tableId, headers, filtered);
                toolbar.appendChild(actionsDiv);
            }

            const thead = wrap.querySelector('thead');
            if (thead) {
                thead.innerHTML = '<tr>' + headers.map((h, i) => {
                    const cls = sortCol === i ? (sortDir === 'asc' ? 'sort-asc' : 'sort-desc') : '';
                    const icon = sortCol === i ? (sortDir === 'asc' ? '▲' : '▼') : '▲▼';
                    return `<th class="${cls}" data-col="${i}">${h}<span class="sort-icon">${icon}</span></th>`;
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
                        return `<td class="${isLong ? 'wrap' : ''}">${formatCellValue(row[i], h)}</td>`;
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
                actionsDiv.innerHTML = `<button class="smart-table-export-btn" title="Export ke Excel">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export Excel
                </button>`;
                const exportBtn = actionsDiv.querySelector('.smart-table-export-btn');
                exportBtn.onclick = () => exportTableToExcel(tableId, headers, filtered);
                toolbar.appendChild(actionsDiv);
            }

            const thead = wrap.querySelector('thead');
            if (thead) {
                thead.innerHTML = '<tr>' + headers.map((h, i) => {
                    const cls = sortCol === i ? (sortDir === 'asc' ? 'sort-asc' : 'sort-desc') : '';
                    const icon = sortCol === i ? (sortDir === 'asc' ? '▲' : '▼') : '▲▼';
                    return `<th class="${cls}" data-col="${i}">${h}<span class="sort-icon">${icon}</span></th>`;
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
                        return `<td class="${isLong ? 'wrap' : ''}">${formatCellValue(row[i], h)}</td>`;
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
            // Use message-specific tool results if provided, otherwise fall back to global
            const toolResults = messageToolResults !== null ? messageToolResults : currentToolResults;

            bubble.querySelectorAll('.smart-table-wrap:not([data-initialized])').forEach((wrap, idx) => {
                const tableId = wrap.getAttribute('data-table-id') || ('st-' + Math.random().toString(36).substr(2, 9));
                const toolIdx = parseInt(wrap.getAttribute('data-tool-index'));

                let headers = [];
                let allRows = [];
                let toolRes = null;

                try {
                    // CASE A: Static Data (from history/base64)
                    const hb64 = wrap.getAttribute('data-headers-b64');
                    const rb64 = wrap.getAttribute('data-rows-b64');

                    if (hb64 && rb64) {
                        try {
                            headers = JSON.parse(decodeURIComponent(escape(atob(hb64))));
                            allRows = JSON.parse(decodeURIComponent(escape(atob(rb64))));
                        } catch (e) {
                            console.error('Failed to parse base64 data for table', tableId, e);
                            wrap.innerHTML = '<div class="p-4 text-red-400">⚠️ Gagal memuat data tabel</div>';
                            wrap.setAttribute('data-initialized', 'true');
                            return;
                        }
                    }
                    // CASE B: Dynamic Data (from tool result)
                    else if (!isNaN(toolIdx)) {
                        try {
                            toolRes = toolResults[toolIdx];
                        } catch (e) {
                            console.warn('Failed to get tool result at index', toolIdx, e);
                            toolRes = null;
                        }

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

                        if (tableData.rows && Array.isArray(tableData.rows)) {
                            headers = tableData.columns || (tableData.rows[0] && typeof tableData.rows[0] === 'object' ? Object.keys(tableData.rows[0]) : []);
                            allRows = tableData.rows.map(r => Array.isArray(r) ? r : headers.map(h => r[h]));
                            
                            if (allRows.length > 1000) {
                                console.log(`[SmartTable] Initializing table with ${allRows.length} rows`, tableId);
                            }
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

                    if (allRows.length === 0 && !toolRes) {
                        wrap.setAttribute('data-initialized', 'waiting');
                        return;
                    }

                    smartTables[tableId] = {
                        headers, allRows, filteredRows: allRows,
                        page: 0, sortCol: -1, sortDir: 'asc', query: ''
                    };

                    buildSmartTable(tableId);

                    wrap.setAttribute('data-initialized', 'true');
                    
                } catch (e) {
                    console.error('Failed to initialize smart table:', e, {
                        tableId,
                        toolIdx,
                        rowsCount: allRows ? allRows.length : 0
                    });
                    
                    wrap.innerHTML = `<div class="p-4 text-red-400">
                        <p class="font-bold">⚠️ Gagal memuat tabel</p>
                        <p class="text-sm opacity-75">${e.message}</p>
                        <p class="text-xs mt-2 opacity-50">Data terlalu besar atau format tidak valid</p>
                    </div>`;
                    wrap.setAttribute('data-initialized', 'error');
                }
            });
        }

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
                        const chartId = 'chart-' + Math.random().toString(36).substr(2, 9);
                        let encoded;
                        try { encoded = btoa(unescape(encodeURIComponent(code))); } catch(e) { encoded = btoa(code); }
                        return `<div class="chart-container"><canvas id="${chartId}"></canvas></div>
                                <input type="hidden" class="chart-data-provider" data-id="${chartId}" data-b64="${encoded}">`;
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
                        } catch(e) { return '<div class="table-wrap"><span class="opacity-40 animate-pulse text-xs">⏳ Sedang memproses data...</span></div>'; }
                        return `<div class="table-wrap">⚠️ Konfigurasi tabel tidak valid atau data tidak ditemukan (Index #${params ? params.tool_index : '?'})</div>`;
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
                const response = await fetch('/chatbot/send', {
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
        function initChartsInBubble(bubble) {
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
                        // Fallback data lama
                        rawData = provider.value.replace(/&apos;/g, "'");
                    }
                } catch(e) { return; }

                const cleanJson = rawData.replace(/\/\/[^\n]*/g, '').replace(/\/\*[\s\S]*?\*\//g, '').trim();

                // Jika sedang streaming (belum tutup }), jangan parse, tampilkan loading
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
                    // Hapus loader jika ada
                    const loader = container ? container.querySelector('.chart-loading') : null;
                    if (loader) loader.remove();
                    canvas.style.opacity = '1';

                    config.options = config.options || {};
                    config.options.responsive = true;
                    config.options.maintainAspectRatio = false;
                    
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
                                if (value >= 1000 || value <= -1000) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                                return value;
                            };
                        }
                    });

                    // Format tooltips sebagai Rupiah
                    if (!config.options.plugins.tooltip) config.options.plugins.tooltip = {};
                    if (!config.options.plugins.tooltip.callbacks) config.options.plugins.tooltip.callbacks = {};
                    config.options.plugins.tooltip.callbacks.label = function(context) {
                        let label = context.dataset.label || '';
                        if (label) label += ': ';
                        if (context.parsed.y !== null) {
                            label += currencyFormatter.format(context.parsed.y);
                        }
                        return label;
                    };

                    new Chart(canvas, config);
                    canvas.setAttribute('data-chart-initialized', 'true');
                    provider.remove();

                    // Add export toolbar
                    if (container && !container.querySelector('.chart-toolbar')) {
                        const toolbar = document.createElement('div');
                        toolbar.className = 'chart-toolbar';
                        toolbar.innerHTML = `<button class="chart-export-btn" title="Export data grafik ke Excel">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Export Excel
                        </button>`;
                        const exportBtn = toolbar.querySelector('.chart-export-btn');
                        exportBtn.onclick = () => exportChartToExcel(chartId, config);
                        container.insertBefore(toolbar, canvas);
                    }
                } catch (e) {
                    const loader = container ? container.querySelector('.chart-loading') : null;
                    if (loader) loader.remove();
                    console.error('Chart.js init error:', e);
                    if (container) container.innerHTML = '<p style="color:#f87171;font-size:12px;padding:10px">⚠️ Gagal render grafik: ' + e.message + '</p>';
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
