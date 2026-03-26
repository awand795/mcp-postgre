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
            padding: 7px 13px; border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #d4d4d0; white-space: nowrap; font-size: 11px;
        }
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
        // Data lengkap disimpan di memory (smartTables[id].allRows).
        // DOM hanya merender PAGE_SIZE baris agar browser ringan meski data ribuan baris.
        const smartTables = {};
        const PAGE_SIZE = 50;

        function parseMarkdownTable(header, body) {
            // Parse headers — handle any HTML inside <th>
            const thM = [...header.matchAll(/<th[^>]*>([\/\s\S]*?)<\/th>/gi)];
            const headers = thM.map(m => m[1].replace(/<[^>]+>/g, '').trim());

            // Parse rows — use a DOM parser for reliability instead of regex
            const tmpDiv = document.createElement('div');
            tmpDiv.innerHTML = '<table><tbody>' + body + '</tbody></table>';
            const rows = [];
            tmpDiv.querySelectorAll('tr').forEach(tr => {
                const cells = [];
                tr.querySelectorAll('td').forEach(td => cells.push(td.textContent.trim()));
                if (cells.length > 0) rows.push(cells);
            });
            return { headers, rows };
        }

        function buildSmartTable(tableId) {
            const st = smartTables[tableId];
            if (!st) return;
            const { headers, allRows, sortCol, sortDir, query } = st;

            // Filter
            let filtered = allRows;
            if (query) {
                const q = query.toLowerCase();
                filtered = allRows.filter(row => row.some(c => String(c).toLowerCase().includes(q)));
            }
            // Sort
            if (sortCol >= 0) {
                filtered = [...filtered].sort((a, b) => {
                    const va = a[sortCol] ?? '', vb = b[sortCol] ?? '';
                    const na = parseFloat(String(va).replace(/[^0-9.-]/g, '')),
                          nb = parseFloat(String(vb).replace(/[^0-9.-]/g, ''));
                    const cmp = (!isNaN(na) && !isNaN(nb))
                        ? (na - nb)
                        : String(va).localeCompare(String(vb), 'id');
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

            // Info bar
            const info = wrap.querySelector('.smart-table-info');
            if (info) {
                const txt = filtered.length < allRows.length
                    ? `${filtered.length.toLocaleString('id')} dari ${allRows.length.toLocaleString('id')} baris`
                    : `${allRows.length.toLocaleString('id')} baris`;
                info.textContent = `📊 ${txt} · ${headers.length} kolom`;
            }

            // Thead (sortable)
            const thead = wrap.querySelector('thead');
            if (thead) {
                thead.innerHTML = '<tr>' + headers.map((h, i) => {
                    const cls  = sortCol === i ? (sortDir === 'asc' ? 'sort-asc' : 'sort-desc') : '';
                    const icon = sortCol === i ? (sortDir === 'asc' ? '▲' : '▼') : '▲▼';
                    return `<th class="${cls}" data-col="${i}">${h}<span class="sort-icon">${icon}</span></th>`;
                }).join('') + '</tr>';
                thead.querySelectorAll('th').forEach(th => {
                    th.addEventListener('click', () => {
                        const col = parseInt(th.dataset.col);
                        st.sortDir = (st.sortCol === col && st.sortDir === 'asc') ? 'desc' : 'asc';
                        st.sortCol = col;
                        st.page = 0;
                        buildSmartTable(tableId);
                    });
                });
            }

            // Tbody
            const tbody = wrap.querySelector('tbody');
            if (tbody) {
                tbody.innerHTML = pageRows.length === 0
                    ? `<tr><td colspan="${headers.length}" style="text-align:center;color:#706f6c;padding:16px">Tidak ada data yang cocok</td></tr>`
                    : pageRows.map(row =>
                        '<tr>' + headers.map((_, i) => `<td>${row[i] ?? ''}</td>`).join('') + '</tr>'
                    ).join('');
            }

            // Pagination
            const pag = wrap.querySelector('.smart-table-pagination');
            if (pag) {
                const pageInfo = pag.querySelector('.smart-table-page-info');
                if (pageInfo) {
                    const from = curPage * PAGE_SIZE + 1;
                    const to   = Math.min((curPage + 1) * PAGE_SIZE, filtered.length);
                    pageInfo.textContent = `Baris ${from}–${to} · Hal ${curPage + 1}/${totalPages} · klik header = sort`;
                }
                const btns = pag.querySelector('.smart-table-btns');
                if (btns) {
                    const nums = [];
                    if (totalPages <= 7) {
                        for (let i = 0; i < totalPages; i++) nums.push(i);
                    } else {
                        nums.push(0);
                        if (curPage > 2) nums.push('...');
                        for (let i = Math.max(1, curPage - 1); i <= Math.min(totalPages - 2, curPage + 1); i++) nums.push(i);
                        if (curPage < totalPages - 3) nums.push('...');
                        nums.push(totalPages - 1);
                    }
                    btns.innerHTML =
                        `<button class="st-btn" data-action="prev" ${curPage === 0 ? 'disabled' : ''}>‹</button>` +
                        nums.map(p => p === '...'
                            ? `<span style="color:#706f6c;font-size:11px;padding:0 3px">…</span>`
                            : `<button class="st-btn ${p === curPage ? 'active' : ''}" data-action="goto" data-page="${p}">${p + 1}</button>`
                        ).join('') +
                        `<button class="st-btn" data-action="next" ${curPage >= totalPages - 1 ? 'disabled' : ''}>›</button>`;
                    btns.querySelectorAll('.st-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const action = btn.dataset.action;
                            if (action === 'prev')      st.page = Math.max(0, st.page - 1);
                            else if (action === 'next') st.page = Math.min(totalPages - 1, st.page + 1);
                            else if (action === 'goto') st.page = parseInt(btn.dataset.page);
                            buildSmartTable(tableId);
                        });
                    });
                }
            }
        }

        function initSmartTablesInBubble(bubble) {
            bubble.querySelectorAll('.smart-table-wrap[data-table-id]:not([data-initialized])').forEach(wrap => {
                const tableId = wrap.getAttribute('data-table-id');
                try {
                    // Decode base64 → JSON (mendukung semua karakter termasuk kutip, unicode, dll)
                    const hb64 = wrap.getAttribute('data-headers-b64') || '';
                    const rb64 = wrap.getAttribute('data-rows-b64') || '';
                    const headers = hb64
                        ? JSON.parse(decodeURIComponent(escape(atob(hb64))))
                        : JSON.parse(wrap.getAttribute('data-headers') || '[]'); // fallback lama
                    const allRows = rb64
                        ? JSON.parse(decodeURIComponent(escape(atob(rb64))))
                        : JSON.parse(wrap.getAttribute('data-rows') || '[]'); // fallback lama

                    smartTables[tableId] = {
                        headers, allRows, filteredRows: allRows,
                        page: 0, sortCol: -1, sortDir: 'asc', query: ''
                    };
                    wrap.setAttribute('data-initialized', '1');
                    const searchInput = wrap.querySelector('.smart-table-search');
                    if (searchInput) {
                        searchInput.addEventListener('input', () => {
                            smartTables[tableId].query = searchInput.value;
                            smartTables[tableId].page  = 0;
                            buildSmartTable(tableId);
                        });
                    }
                    buildSmartTable(tableId);
                } catch (e) { console.error('SmartTable init error', e, 'tableId:', tableId); }
            });
        }

        // ── marked.js setup ───────────────────────────────────────────────────
        const renderer = new marked.Renderer();

        // ── Tabel → SmartTable (Hybrid API) ──────────────────────────────────
        renderer.table = function(arg1, arg2) {
            let headers = [];
            let rows    = [];

            try {
                // Case A: token object (marked v4+)
                if (arg1 && typeof arg1 === 'object' && arg1.type === 'table') {
                    const token = arg1;
                    if (Array.isArray(token.header)) {
                        headers = token.header.map(h => {
                            const raw = (typeof h === 'object' && h !== null) ? (h.text || '') : String(h || '');
                            return raw.replace(/<[^>]+>/g, '').trim();
                        });
                    }
                    if (Array.isArray(token.rows)) {
                        rows = token.rows.map(row => 
                            row.map(cell => {
                                const raw = (typeof cell === 'object' && cell !== null) ? (cell.text || '') : String(cell || '');
                                return raw.replace(/<[^>]+>/g, '').trim();
                            })
                        );
                    }
                } 
                // Case B: (header, body) strings (legacy/marked v2)
                else if (typeof arg1 === 'string') {
                    const parsed = parseMarkdownTable(arg1, arg2 || '');
                    headers = parsed.headers;
                    rows    = parsed.rows;
                }
            } catch(e) { console.error('Table parse error', e); }

            if (headers.length === 0) {
                // Final fallback: standard table if all else fails
                if (typeof arg1 === 'string' && typeof arg2 === 'string') {
                    return `<div class="table-wrap"><table><thead>${arg1}</thead><tbody>${arg2}</tbody></table></div>`;
                }
                return '<div class="table-wrap">⚠️ Gagal render tabel</div>';
            }
            
            const tableId = 'st-' + Math.random().toString(36).substr(2, 9);
            let hEnc, rEnc;
            try {
                hEnc = btoa(unescape(encodeURIComponent(JSON.stringify(headers))));
                rEnc = btoa(unescape(encodeURIComponent(JSON.stringify(rows))));
            } catch(e) { hEnc = btoa(JSON.stringify(headers)); rEnc = btoa(JSON.stringify(rows)); }

            return `<div class="smart-table-wrap" id="${tableId}" data-table-id="${tableId}" data-headers-b64="${hEnc}" data-rows-b64="${rEnc}">
                <div class="smart-table-toolbar">
                    <span class="smart-table-info">📊 Memuat...</span>
                    <input class="smart-table-search" type="text" placeholder="🔍 Cari di tabel...">
                </div>
                <div class="smart-table-scroll">
                    <table>
                        <thead><tr>${headers.map(h => `<th>${h}<span class='sort-icon'>▲▼</span></th>`).join('')}</tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="smart-table-pagination">
                    <span class="smart-table-page-info"></span>
                    <div class="smart-table-btns"></div>
                </div>
            </div>`;
        };

        // Render custom Chart blocks
        // marked v9: renderer.code dipanggil dengan (token) object jika menggunakan Renderer override
        // atau dengan (code, infostring, escaped) jika memakai pendekatan lama.
        // Kita handle keduanya:
        renderer.code = function(token) {
            // marked v9+ passes a token object; older versions pass (code, lang)
            let code, language;
            if (typeof token === 'object' && token !== null && 'text' in token) {
                code = token.text;
                language = token.lang || '';
            } else {
                // Fallback: token = code string, second arg = language
                code = token;
                language = arguments[1] || '';
            }
            if (language === 'chart') {
                const chartId = 'chart-' + Math.random().toString(36).substr(2, 9);
                // Simpan data sebagai base64 untuk menghindari masalah encoding dengan karakter khusus
                let encoded;
                try { encoded = btoa(unescape(encodeURIComponent(code))); } catch(e) { encoded = btoa(code); }
                return `<div class="chart-container"><canvas id="${chartId}"></canvas></div>
                        <input type="hidden" class="chart-data-provider" data-id="${chartId}" data-b64="${encoded}">`;
            }

            // ── DIRECT SMART TABLE RENDERER ──────────────────────────────────
            if (language === 'smart_table') {
                try {
                    // Jika JSON belum lengkap (masih streaming), jangan tampilkan error dulu
                    if (!code.trim().endsWith('}')) {
                        return '<div class="table-wrap"><span class="opacity-40 animate-pulse text-xs">⏳ Sedang memproses data...</span></div>';
                    }

                    const params = JSON.parse(code);
                    const idx = params.tool_index !== undefined ? params.tool_index : -1;
                    const toolRes = (idx >= 0 && currentToolResults[idx]) ? currentToolResults[idx] : null;

                    if (toolRes) {
                        let headers = [];
                        let rows    = [];

                        // Case A: Standard object with rows/columns (execute_query)
                        if (toolRes.rows && Array.isArray(toolRes.rows)) {
                            headers = toolRes.columns || (toolRes.rows[0] ? Object.keys(toolRes.rows[0]) : []);
                            rows    = toolRes.rows.map(r => headers.map(h => r[h]));
                        } 
                        // Case B: Simple array of objects
                        else if (Array.isArray(toolRes) && toolRes[0] && typeof toolRes[0] === 'object') {
                            headers = Object.keys(toolRes[0]);
                            rows    = toolRes.map(r => headers.map(h => r[h]));
                        }
                        // Case C: Array of strings/primitives
                        else if (Array.isArray(toolRes)) {
                            headers = ['Data'];
                            rows    = toolRes.map(v => [v]);
                        }

                        if (rows.length > 0) {
                            const tableId = 'st-direct-' + Math.random().toString(36).substr(2, 9);
                            const hEnc = btoa(unescape(encodeURIComponent(JSON.stringify(headers))));
                            const rEnc = btoa(unescape(encodeURIComponent(JSON.stringify(rows))));

                            return `<div class="smart-table-wrap" id="${tableId}" data-table-id="${tableId}" data-headers-b64="${hEnc}" data-rows-b64="${rEnc}">
                                <div class="smart-table-toolbar">
                                    <span class="smart-table-info">📊 Memuat...</span>
                                    <input class="smart-table-search" type="text" placeholder="🔍 Cari di tabel...">
                                </div>
                                <div class="smart-table-scroll">
                                    <table>
                                        <thead><tr>${headers.map(h => `<th>${h}<span class='sort-icon'>▲▼</span></th>`).join('')}</tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="smart-table-pagination">
                                    <span class="smart-table-page-info"></span>
                                    <div class="smart-table-btns"></div>
                                </div>
                            </div>`;
                        }
                    }
                } catch(e) { 
                    // Selama streaming, JSON parse error adalah wajar
                    return '<div class="table-wrap"><span class="opacity-40 animate-pulse text-xs">⏳ Sedang memproses data...</span></div>';
                }
                return '<div class="table-wrap">⚠️ Data tabel tidak ditemukan atau kosong (Tool #' + (idx || '?') + ')</div>';
            }

            const escaped = code.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            return `<pre><code class="language-${language || 'plaintext'}">${escaped}</code></pre>`;
        };

        marked.use({ renderer, gfm: true, breaks: true, pedantic: false });

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
                                renderStreamToBubble(bubble, aiResponseText);
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
                                    // Simpan hasil untuk Direct Smart Table
                                    if (tc.result) {
                                        currentToolResults.push(tc.result);
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

        // ── Init Charts ───────────────────────────────────────────────────────
        function initChartsInBubble(bubble) {
            bubble.querySelectorAll('.chart-data-provider').forEach(provider => {
                const chartId = provider.getAttribute('data-id');
                const canvas  = document.getElementById(chartId);
                if (!canvas || canvas.getAttribute('data-chart-initialized')) return;

                // Decode dari base64 (encoding aman untuk semua karakter)
                let rawData = '';
                try {
                    const b64 = provider.getAttribute('data-b64') || '';
                    if (b64) {
                        rawData = decodeURIComponent(escape(atob(b64)));
                    } else {
                        // Fallback untuk data lama pakai value attribute
                        rawData = provider.value.replace(/&apos;/g, "'");
                    }
                } catch(decodeErr) {
                    console.error('Chart decode error:', decodeErr);
                    const container = canvas.closest('.chart-container');
                    if (container) container.innerHTML = '<p style="color:#f87171;font-size:12px;padding:10px">⚠️ Gagal decode data grafik.</p>';
                    return;
                }

                // Bersihkan JSON — hapus komentar JS-style sebelum parse
                const cleanJson = rawData
                    .replace(/\/\/[^\n]*/g, '')       // hapus // komentar
                    .replace(/\/\*[\s\S]*?\*\//g, '') // hapus /* */ komentar
                    .trim();

                try {
                    const config = JSON.parse(cleanJson);
                    config.options = config.options || {};
                    config.options.responsive = true;
                    config.options.maintainAspectRatio = false;
                    // Pastikan warna label axis terisi default jika tidak ada
                    if (!config.options.plugins) config.options.plugins = {};
                    if (!config.options.plugins.legend) config.options.plugins.legend = { labels: { color: '#fff' } };
                    new Chart(canvas, config);
                    canvas.setAttribute('data-chart-initialized', 'true');
                    provider.remove();
                } catch (e) {
                    console.error('Chart.js init error:', e, 'JSON:', cleanJson.substring(0, 200));
                    const container = canvas.closest('.chart-container');
                    if (container) container.innerHTML = '<p style="color:#f87171;font-size:12px;padding:10px">⚠️ Gagal render grafik: ' + e.message + '</p>';
                }
            });
        }

        // ── Render stream ke bubble ───────────────────────────────────────────
        function renderStreamToBubble(bubble, text) {
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
