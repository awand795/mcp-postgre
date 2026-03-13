<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PostgreSQL MCP Chatbot</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">

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
        @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1.0); } }

        /* ── Markdown styles ── */
        .markdown-body { line-height: 1.6; }

        .markdown-body p { margin: 6px 0; font-size: 13px; }

        .markdown-body h1,.markdown-body h2 { font-size: 15px; font-weight: 700; color: #fff; margin: 16px 0 8px; }
        .markdown-body h3 { font-size: 14px; font-weight: 600; color: #f97316; margin: 14px 0 6px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 4px; }
        .markdown-body h4 { font-size: 13px; font-weight: 600; color: #fb923c; margin: 10px 0 4px; }

        .markdown-body ul, .markdown-body ol { padding-left: 18px; margin: 6px 0; }
        .markdown-body li { margin: 3px 0; font-size: 13px; }

        .markdown-body strong { color: #ffffff; font-weight: 600; }
        .markdown-body em { color: #d4d4d0; font-style: italic; }

        .markdown-body code { background: rgba(255,255,255,0.1); padding: 1px 5px; border-radius: 4px; font-family: monospace; font-size: 11px; color: #fb923c; }
        .markdown-body pre { background: rgba(0,0,0,0.4); padding: 10px; border-radius: 8px; margin: 8px 0; overflow-x: auto; border: 1px solid rgba(255,255,255,0.08); }
        .markdown-body pre code { background: none; padding: 0; color: inherit; font-size: 12px; }

        /* ── TABLE — fix utama ── */
        .markdown-body .table-wrap { overflow-x: auto; margin: 12px 0; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); }
        .markdown-body table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 400px; }
        .markdown-body table thead tr { background: rgba(245,48,3,0.2); }
        .markdown-body table th {
            padding: 9px 14px; text-align: left; font-weight: 600;
            color: #fff; white-space: nowrap;
            border-bottom: 2px solid rgba(245,48,3,0.4);
        }
        .markdown-body table td {
            padding: 8px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            color: #d4d4d0; white-space: nowrap;
        }
        .markdown-body table tbody tr:hover { background: rgba(255,255,255,0.04); }
        .markdown-body table tbody tr:last-child td { border-bottom: none; }

        .markdown-body blockquote { border-left: 3px solid #f97316; padding-left: 12px; margin: 8px 0; color: #A1A09A; font-style: italic; font-size: 12px; }
        .markdown-body hr { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin: 12px 0; }

        .btn-clear { transition: all 0.2s; }
        .btn-clear:hover { background: rgba(245,48,3,0.15); color: #ff4433; }
    </style>
</head>
<body class="flex items-center justify-center p-4">

<div class="flex flex-col w-full max-w-4xl h-[90vh] glass-panel rounded-3xl overflow-hidden">

    <!-- Header -->
    <div class="p-5 border-b border-white/10 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#f53003] to-[#ff4433] flex items-center justify-center shadow-lg shadow-red-500/20 flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                </svg>
            </div>
            <div>
                <h1 class="text-white font-semibold text-lg leading-tight">MCP Server</h1>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-xs text-[#A1A09A]">MCP PostgreSQL Online</span>
                </div>
            </div>
        </div>
        <button id="btn-clear-chat" title="Hapus riwayat"
            class="btn-clear flex items-center gap-1.5 px-3 py-2 rounded-xl text-[#A1A09A] text-xs border border-white/10 hover:border-red-500/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6M9 6V4h6v2"/></svg>
            Hapus Riwayat
        </button>
    </div>

    <!-- Chat Area -->
    <div id="chat-messages" class="flex-1 overflow-y-auto p-6 space-y-5">
        <div class="flex flex-col items-start gap-1.5 max-w-[85%]">
            <div class="chat-bubble-ai p-4 rounded-2xl text-sm shadow-sm markdown-body">
                <p>Halo! Saya <strong>MCP Server</strong> 👋</p>
                <p style="margin-top:6px">Saya bisa diajak ngobrol santai <em>dan</em> membantu analisis data bisnis dari database kamu.</p>
                <p style="margin-top:6px">Coba tanya:</p>
                <ul style="margin-top:4px">
                    <li>💬 "Halo, apa kabar?"</li>
                    <li>📊 "Tampilkan produk terlaris"</li>
                    <li>📍 "Produk terlaris di Jawa Barat"</li>
                    <li>📈 "Analisis RFM pelanggan"</li>
                    <li>💰 "Revenue per bulan"</li>
                </ul>
            </div>
            <span class="text-[10px] text-[#706f6c] ml-1">{{ now()->format('H:i') }}</span>
        </div>
    </div>

    <!-- Typing Indicator -->
    <div id="typing-indicator" class="px-6 pb-2 hidden flex-shrink-0">
        <div class="typing-indicator inline-flex items-center gap-1.5 px-3 py-2 rounded-full bg-white/5 border border-white/10">
            <span></span><span></span><span></span>
            <span id="typing-text" class="text-[11px] text-[#A1A09A] ml-1">AI sedang berpikir...</span>
        </div>
    </div>

    <!-- Input -->
    <div class="p-5 bg-black/20 border-t border-white/10 flex-shrink-0">
        <div class="relative">
            <input
                type="text"
                id="message-input"
                placeholder="Tanya apa saja atau minta analisis data..."
                class="w-full bg-white/5 border border-white/10 rounded-2xl py-3.5 pl-5 pr-14 text-white placeholder-white/25 focus:outline-none focus:ring-2 focus:ring-[#f53003]/40 transition-all text-sm"
                autocomplete="off"
            >
            <button id="send-btn"
                class="absolute right-2 top-1.5 bottom-1.5 w-10 bg-[#f53003] hover:bg-[#ff4433] disabled:opacity-40 text-white rounded-xl flex items-center justify-center transition-all shadow-lg shadow-red-500/20">
                <svg id="send-icon" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                <svg id="loading-icon" class="w-4 h-4 hidden animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
            </button>
        </div>
        <p class="text-[10px] text-center text-[#706f6c] mt-3 uppercase tracking-widest">Powered by Awanda</p>
    </div>
</div>

<!-- marked.js VERSI SPESIFIK yang stabil dengan GFM tables -->
<script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<script>
    const chatForm        = document.getElementById('chat-form');
    const messageInput    = document.getElementById('message-input');
    const chatMessages    = document.getElementById('chat-messages');
    const typingIndicator = document.getElementById('typing-indicator');
    const typingText      = document.getElementById('typing-text');
    const btnClear        = document.getElementById('btn-clear-chat');
    const sendBtn         = document.getElementById('send-btn');
    const sendIcon        = document.getElementById('send-icon');
    const loadingIcon     = document.getElementById('loading-icon');

    let conversationHistory = [];
    let isLoading = false;

    // ── Konfigurasi marked.js v9 (API baru, bukan setOptions) ────────────────
    const renderer = new marked.Renderer();

    // Wrap table dengan div scroll agar tidak overflow
    renderer.table = function(header, body) {
        return `<div class="table-wrap"><table><thead>${header}</thead><tbody>${body}</tbody></table></div>`;
    };

    marked.use({
        renderer,
        gfm: true,
        breaks: true,
        pedantic: false,
    });

    // ── Fungsi render markdown yang aman ─────────────────────────────────────
    function renderMarkdown(text) {
        if (!text) return '';
        try {
            // Normalisasi newline agar tabel terbaca dengan benar
            const normalized = text
                .replace(/\r\n/g, '\n')
                .replace(/\r/g, '\n');
            return marked.parse(normalized);
        } catch(e) {
            console.error('Markdown parse error:', e);
            // Fallback: tampilkan sebagai teks biasa dengan newline
            return '<pre style="white-space:pre-wrap;font-size:12px">' + 
                   text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + 
                   '</pre>';
        }
    }

    // ── Set loading state ─────────────────────────────────────────────────────
    function setLoading(loading) {
        isLoading = loading;
        sendBtn.disabled = loading;
        messageInput.disabled = loading;
        sendIcon.classList.toggle('hidden', loading);
        loadingIcon.classList.toggle('hidden', !loading);
        typingIndicator.classList.toggle('hidden', !loading);
    }

    // ── Submit handler ────────────────────────────────────────────────────────
    messageInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !isLoading) {
            e.preventDefault();
            submitMessage();
        }
    });

    sendBtn.addEventListener('click', () => {
        if (!isLoading) submitMessage();
    });

    async function submitMessage() {
        const message = messageInput.value.trim();
        if (!message || isLoading) return;

        addMessage(message, 'user');
        messageInput.value = '';
        setLoading(true);

        const thinkingStates = [
            "AI sedang berpikir...",
            "Mengambil data dari database...",
            "Menganalisis hasil...",
            "Menyusun laporan..."
        ];
        let stateIdx = 0;
        const stateInterval = setInterval(() => {
            if (stateIdx < thinkingStates.length - 1) {
                stateIdx++;
                typingText.textContent = thinkingStates[stateIdx];
            }
        }, 4000);

        chatMessages.scrollTop = chatMessages.scrollHeight;

        try {
            const response = await fetch('{{ route("chatbot.send") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ message, history: conversationHistory })
            });

            const data = await response.json();

            clearInterval(stateInterval);
            setLoading(false);
            typingText.textContent = "AI sedang berpikir...";

            addMessage(data.response, 'ai');

            if (data.history && Array.isArray(data.history)) {
                conversationHistory = data.history;
            }

        } catch (error) {
            clearInterval(stateInterval);
            setLoading(false);
            typingText.textContent = "AI sedang berpikir...";
            console.error('Error:', error);
            addMessage('Maaf, terjadi kesalahan koneksi ke server. Coba lagi.', 'ai');
        }
    }

    // ── Hapus riwayat ─────────────────────────────────────────────────────────
    btnClear.addEventListener('click', () => {
        conversationHistory = [];
        chatMessages.innerHTML = '';
        addMessage('Riwayat percakapan telah dihapus. Ada yang bisa saya bantu? 😊', 'ai');
    });

    // ── Render pesan ──────────────────────────────────────────────────────────
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
            // Highlight code blocks
            bubble.querySelectorAll('pre code').forEach(b => {
                try { hljs.highlightElement(b); } catch(e) {}
            });
        } else {
            bubble.textContent = text;
        }

        const timeEl = document.createElement('span');
        timeEl.className = 'text-[10px] text-[#706f6c] ' + (sender === 'user' ? 'mr-1' : 'ml-1');
        timeEl.textContent = time;

        wrap.appendChild(bubble);
        wrap.appendChild(timeEl);
        chatMessages.appendChild(wrap);

        // Scroll ke bawah smooth
        requestAnimationFrame(() => {
            chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
        });
    }

    window.onload = () => messageInput.focus();
</script>
</body>
</html>
