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
        .smart-table-search {
            flex: 1; min-width: 120px; max-width: 220px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12);
            border-radius: 6px; padding: 4px 9px; font-size: 11px; color: #fff;
            outline: none; font-family: 'Outfit', sans-serif;
        }
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
    </style>
</head>

<body class="flex items-center justify-center p-4">

    <div class="flex flex-col w-full max-w-4xl h-[90vh] glass-panel rounded-3xl overflow-hidden">

        <!-- Header -->
        <div class="p-5 border-b border-white/10 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <img src="{{ asset('logo_dmi.png') }}" alt="Darko AI Logo" class="w-10 h-10 object-contain">
                <div>
                    <h1 class="text-white font-semibold text-lg leading-tight">darkotech AI</h1>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs text-[#A1A09A]">Online</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 header-actions">
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
                <button id="btn-clear-chat" title="Hapus riwayat"
                    class="btn-clear flex items-center gap-1.5 px-3 py-2 rounded-xl text-[#A1A09A] text-xs border border-white/10 hover:border-red-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                        <path d="M10 11v6M14 11v6M9 6V4h6v2" />
                    </svg>
                    <span class="btn-text">Hapus Riwayat</span>
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
        <div id="chat-messages" class="flex-1 overflow-y-auto p-6 space-y-5">
            <div class="flex flex-col items-start gap-1.5 max-w-[85%]">
                <div class="chat-bubble-ai p-4 rounded-2xl text-sm shadow-sm markdown-body">
                    <p>Halo! Saya <strong>darkotech AI</strong> 👋</p>
                    <p style="margin-top:6px">Apa yang bisa saya bantu untuk mempermudah urusan Anda hari ini?</p>
                </div>
            </div>
        </div>

        <!-- Typing Indicator (hidden, kept for JS compatibility) -->
        <div id="typing-indicator" class="hidden"></div>
        <div id="typing-inner" class="hidden"></div>
        <span id="typing-text" class="hidden"></span>


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
            <p class="text-[10px] text-center text-[#706f6c] mt-3 uppercase tracking-widest">Powered by darkotech</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <script>
        const messageInput    = document.getElementById('message-input');
        const chatMessages    = document.getElementById('chat-messages');
        const typingIndicator = document.getElementById('typing-indicator');
        const typingText      = document.getElementById('typing-text');
        const btnClear        = document.getElementById('btn-clear-chat');
        const sendBtn         = document.getElementById('send-btn');
        const sendIcon        = document.getElementById('send-icon');
        const loadingIcon     = document.getElementById('loading-icon');

        let conversationHistory = [];
        let currentToolResults  = []; // Untuk menyimpan hasil tool call agar bisa diakses Direct Smart Table
        let isLoading = false;

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
            if (!st) return;
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
            if (!wrap) return;

            const info = wrap.querySelector('.smart-table-info');
            if (info) info.textContent = `📊 ${filtered.length.toLocaleString('id')} baris · ${headers.length} kol`;

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
                tbody.innerHTML = pageRows.length === 0
                    ? `<tr><td colspan="${headers.length}" style="text-align:center;color:#706f6c;padding:16px">Tidak ada data</td></tr>`
                    : pageRows.map(row => '<tr>' + headers.map((h, i) => {
                        const isLong = String(row[i]).length > 40;
                        return `<td class="${isLong ? 'wrap' : ''}">${formatCellValue(row[i], h)}</td>`;
                    }).join('') + '</tr>').join('');
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
        function initSmartTablesInBubble(bubble) {
            bubble.querySelectorAll('.smart-table-wrap:not([data-initialized])').forEach(wrap => {
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
                        headers = JSON.parse(decodeURIComponent(escape(atob(hb64))));
                        allRows = JSON.parse(decodeURIComponent(escape(atob(rb64))));
                    }
                    // CASE B: Dynamic Data (from tool result)
                    else if (!isNaN(toolIdx)) {
                        toolRes = currentToolResults[toolIdx];
                        
                        const hasValidData = (res) => {
                            if (!res) return false;
                            if (res.data && res.data.rows) return true;
                            if (res.rows) return true;
                            return false;
                        };

                        if (!hasValidData(toolRes)) {
                            for (let i = currentToolResults.length - 1; i >= 0; i--) {
                                const r = currentToolResults[i];
                                if (r && r.tool_name === 'execute_query' && hasValidData(r)) {
                                    toolRes = r;
                                    break;
                                }
                            }
                            
                            if (!hasValidData(toolRes)) {
                                for (let i = currentToolResults.length - 1; i >= 0; i--) {
                                    const r = currentToolResults[i];
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
                        page: 0, sortCol: -1, sortDir: 'asc', query: ''
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
        }

        // ── Event listeners ───────────────────────────────────────────────────
        messageInput.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey && !isLoading) { e.preventDefault(); submitMessage(); }
        });
        sendBtn.addEventListener('click', () => { if (!isLoading) submitMessage(); });
        btnClear.addEventListener('click', () => {
            conversationHistory = [];
            chatMessages.innerHTML = '';
            addMessage('Riwayat percakapan telah dihapus. Ada yang bisa saya bantu? 😊', 'ai');
        });

        // ── Submit ────────────────────────────────────────────────────────────
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
            currentToolResults = []; // Reset tool results per turn
            const toolBadges = {};

            try {
                const response = await fetch('{{ route("chatbot.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ message, history: conversationHistory }),
                });

                // Tangani error JSON response (non-stream) dari server
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

                            if (parsed.chunk !== undefined && parsed.chunk !== '') {
                                aiResponseText += parsed.chunk;
                                
                                // Hanya hapus loading card dan render jika sudah ada konten yang cukup
                                if (aiResponseText.trim().length > 0 && bubble._loadInterval) {
                                    clearInterval(bubble._loadInterval);
                                    bubble._loadInterval = null;
                                    renderStreamToBubble(bubble, aiResponseText);
                                } else if (!bubble._loadInterval) {
                                    // Loading card sudah dihapus, lanjut render
                                    renderStreamToBubble(bubble, aiResponseText);
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
                                } else if (tc.status === 'success') {
                                    if (tc.result) {
                                        currentToolResults.push(tc.result);
                                        renderStreamToBubble(bubble, aiResponseText);
                                    }
                                    
                                    const runningBadge = toolArea.querySelector('.tool-call-badge.running');
                                    if (runningBadge) {
                                        runningBadge.classList.replace('running', 'done');
                                        const dot = runningBadge.querySelector('.tool-call-dot');
                                        if (dot) { dot.classList.remove('running'); dot.textContent = '✓'; }
                                    }
                                    typingText.textContent = 'Menganalisis data...';
                                }
                            }

                            if (parsed.history && Array.isArray(parsed.history)) {
                                conversationHistory = parsed.history;
                            }

                            if (parsed.error && parsed.response) {
                                bubble.innerHTML = renderMarkdown(parsed.response);
                            }

                        } catch (e) { /* Abaikan parse error line individual */ }
                    }
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }

                if (toolArea.children.length === 0) toolArea.style.display = 'none';

            } catch (err) {
                console.error('[Agentic] Error:', err);
                bubble.innerHTML = renderMarkdown('Maaf, terjadi kesalahan koneksi ke server. Silakan coba lagi.');
            } finally {
                setLoading(false);
                typingText.textContent = 'AI sedang berpikir...';
                chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
            }
        }

        // ── Data pesan loading bisnis ──────────────────────────────────────────
        const loadingSteps = [
            { icon: '📂', label: 'Membaca data',                        sub: 'Menghubungkan ke sumber data...' },
            { icon: '🔍', label: 'Memproses data',                      sub: 'Memvalidasi kelengkapan informasi...' },
            { icon: '📊', label: 'Menganalisis data',                   sub: 'Menyusun insights yang relevan...' },
            { icon: '📋', label: 'Menyiapkan ringkasan data',           sub: 'Mengumpulkan data...' },
            { icon: '📈', label: 'Mengolah data',                       sub: 'Menghitung dan memverifikasi angka...' },
            { icon: '🔎', label: 'Memverifikasi data',                  sub: 'Memastikan konsistensi informasi...' },
            { icon: '🗂️', label: 'Membaca catatan transaksi',           sub: 'Memeriksa rekam jejak aktivitas...' },
            { icon: '⚙️', label: 'Menampilkan hasil data',              sub: 'Menyiapkan tampilan yang jelas...' },
        ];

        // ── Buat bubble AI ────────────────────────────────────────────────────
        function createStreamBubble() {
            const time = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const wrap = document.createElement('div');
            wrap.className = 'flex flex-col gap-1.5 items-start max-w-[95%]';

            const toolArea = document.createElement('div');
            toolArea.className = 'flex flex-col gap-1 pl-1 mb-1';

            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble-ai p-4 rounded-2xl text-sm shadow-sm markdown-body';

            // Loading card awal
            const s0 = loadingSteps[0];
            bubble.innerHTML = `<div class="ai-loading-card">
                <div class="ai-loading-top">
                    <div class="ai-loading-icon-wrap" id="ai-load-icon">${s0.icon}</div>
                    <div class="ai-loading-text">
                        <div class="ai-loading-label anim" id="ai-load-label">${s0.label}</div>
                        <div class="ai-loading-sub" id="ai-load-sub">${s0.sub}</div>
                    </div>
                </div>
                <div class="ai-loading-bar-wrap"><div class="ai-loading-bar"></div></div>
            </div>`;

            // Rotasi pesan bisnis setiap 2.5 detik
            let stepIdx = 1;
            const loadInterval = setInterval(() => {
                const labelEl = bubble.querySelector('#ai-load-label');
                if (!labelEl) { clearInterval(loadInterval); return; }
                const s = loadingSteps[stepIdx % loadingSteps.length]; stepIdx++;
                bubble.querySelector('#ai-load-icon').textContent = s.icon;
                labelEl.classList.remove('anim'); void labelEl.offsetWidth;
                labelEl.classList.add('anim'); labelEl.textContent = s.label;
                bubble.querySelector('#ai-load-sub').textContent = s.sub;
            }, 2500);
            bubble._loadInterval = loadInterval;

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
                } catch (e) {
                    const loader = container ? container.querySelector('.chart-loading') : null;
                    if (loader) loader.remove();
                    console.error('Chart.js init error:', e);
                    if (container) container.innerHTML = '<p style="color:#f87171;font-size:12px;padding:10px">⚠️ Gagal render grafik: ' + e.message + '</p>';
                }
            });
        }

        // ── Render stream ke bubble ───────────────────────────────────────────
        function renderStreamToBubble(bubble, text) {
            // Jangan render jika text kosong, biarkan loading card tetap tampil
            if (!text || text.trim().length === 0) return;
            
            bubble.innerHTML = renderMarkdown(text);
            bubble.querySelectorAll('pre code').forEach(b => { try { hljs.highlightElement(b); } catch (e) {} });
            initChartsInBubble(bubble);
            initSmartTablesInBubble(bubble);
        }

        // ── Render pesan biasa ────────────────────────────────────────────────
        function addMessage(text, sender) {
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
                initSmartTablesInBubble(bubble);
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

        window.onload = () => messageInput.focus();
    </script>
</body>
</html>
