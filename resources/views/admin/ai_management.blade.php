@extends('layouts.admin')

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     AI MANAGEMENT — Full Redesign v2 + Health Check
══════════════════════════════════════════════════════════════ --}}

<style>
/* ══════════════════════════════════════════
   VARIABLES & RESET
══════════════════════════════════════════ */
:root {
    --aim-indigo:   #6366f1;
    --aim-indigo-d: #4f46e5;
    --aim-cyan:     #06b6d4;
    --aim-green:    #10b981;
    --aim-yellow:   #f59e0b;
    --aim-red:      #ef4444;
    --aim-surface:  #ffffff;
    --aim-border:   rgba(99,102,241,0.18);
    --aim-border-h: rgba(99,102,241,0.35);
    --aim-text:     #0f172a;
    --aim-muted:    #475569;
    --aim-dim:      #94a3b8;
    --aim-radius:   18px;
    --aim-radius-sm:10px;
}

html.dark {
    --aim-surface:  rgba(15,23,42,0.6);
    --aim-border:   rgba(255,255,255,0.07);
    --aim-border-h: rgba(99,102,241,0.35);
    --aim-text:     #e2e8f0;
    --aim-muted:    #94a3b8;
    --aim-dim:      #334155;
}

/* ══════════════════════════════════════════
   ALERT
══════════════════════════════════════════ */
.aim-alert {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; border-radius: 12px;
    font-size: 0.88rem; margin-bottom: 1.25rem;
    animation: slideDown .25s ease;
}
.aim-alert.success { background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.2); color: #10b981; }
html.dark .aim-alert.success { color: #34d399; }
.aim-alert.danger  { background: rgba(239,68,68,.08);  border: 1px solid rgba(239,68,68,.2);  color: #ef4444; }
html.dark .aim-alert.danger { color: #f87171; }

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ══════════════════════════════════════════
   TOP BAR
══════════════════════════════════════════ */
.aim-topbar {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 1.5rem; flex-wrap: wrap;
}
.aim-topbar-title {
    flex: 1; margin: 0;
    font-size: 1.35rem; font-weight: 600;
    display: flex; align-items: center; gap: 10px;
    color: var(--aim-text);
}
.aim-topbar-title .t-logo {
    width: 44px; height: 44px;
    background: linear-gradient(135deg, var(--aim-indigo), #8b5cf6);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; color: white;
    box-shadow: 0 4px 15px rgba(99,102,241,0.35);
    flex-shrink: 0;
}

/* Primary button */
.aim-btn-primary {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: 10px; border: none; cursor: pointer;
    font-family: inherit; font-size: 0.85rem; font-weight: 600;
    background: linear-gradient(135deg, var(--aim-indigo), var(--aim-indigo-d));
    color: white; box-shadow: 0 4px 12px rgba(99,102,241,.3);
    transition: all .2s;
}
.aim-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(99,102,241,.4);
}

/* ══════════════════════════════════════════
   STATS ROW
══════════════════════════════════════════ */
.aim-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px; margin-bottom: 1.5rem;
}
.aim-stat {
    background: var(--aim-surface);
    backdrop-filter: blur(10px);
    border: 1px solid var(--aim-border);
    border-radius: 16px; padding: 16px 18px;
    position: relative; overflow: hidden;
    transition: border-color .2s, transform .2s;
    color: var(--aim-text);
}
.aim-stat:hover { border-color: var(--aim-border-h); transform: translateY(-2px); }
.aim-stat::before {
    content: ''; position: absolute;
    top: 0; left: 0; right: 0; height: 2px;
    border-radius: 16px 16px 0 0;
}
.aim-stat.s-blue::before   { background: linear-gradient(90deg, #6366f1, #818cf8); }
.aim-stat.s-green::before  { background: linear-gradient(90deg, #10b981, #34d399); }
.aim-stat.s-yellow::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.aim-stat.s-cyan::before   { background: linear-gradient(90deg, #06b6d4, #22d3ee); }
.aim-stat:hover { border-color: var(--aim-border-h); }
.aim-stat-label { font-size: 0.72rem; color: var(--aim-muted); margin-bottom: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
.aim-stat-val   { font-size: 1.8rem; font-weight: 700; line-height: 1; }
.aim-stat-val.blue   { color: var(--aim-indigo); }
.aim-stat-val.green  { color: #059669; }
html.dark .aim-stat-val.green { color: #34d399; }
.aim-stat-val.yellow { color: #b45309; }
html.dark .aim-stat-val.yellow { color: #fbbf24; }
.aim-stat-val.cyan   { color: #0369a1; }
html.dark .aim-stat-val.cyan { color: #22d3ee; }
.aim-stat-sub   { font-size: 0.7rem; color: var(--aim-dim); margin-top: 5px; }
.aim-stat-icon  {
    position: absolute; right: 14px; top: 50%;
    transform: translateY(-50%); font-size: 1.6rem;
    opacity: .1;
}
html.dark .aim-stat-icon { opacity: .06; }

/* ══════════════════════════════════════════
   SECTION HEADING
══════════════════════════════════════════ */
.aim-section-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 12px;
}
.aim-section-head h2 {
    font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
    color: var(--aim-muted);
}

/* ══════════════════════════════════════════
   PROVIDER GRID
══════════════════════════════════════════ */
.aim-provider-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(370px, 1fr));
    gap: 12px;
}

/* ══════════════════════════════════════════
   PROVIDER CARD
══════════════════════════════════════════ */
.pcard {
    background: var(--aim-surface);
    backdrop-filter: blur(10px);
    border: 1px solid var(--aim-border);
    border-radius: var(--aim-radius);
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
    display: flex; flex-direction: column;
    color: var(--aim-text);
}
.pcard:hover { border-color: rgba(99,102,241,.4); box-shadow: 0 8px 32px rgba(0,0,0,.1); }
html.dark .pcard:hover { border-color: rgba(99,102,241,.25); box-shadow: 0 8px 32px rgba(0,0,0,.25); }
.pcard.pcard--off { opacity: .5; }

/* Head */
.pcard-head {
    display: flex; align-items: center; gap: 9px;
    padding: 13px 15px;
    border-bottom: 1px solid var(--aim-border);
}
.provider-avatar {
    width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; flex-shrink: 0;
}
.av-openai     { background: rgba(16,163,127,.1); }
.av-gemini     { background: rgba(66,133,244,.1); }
.av-claude     { background: rgba(217,119,87,.1);  }
.av-mistral    { background: rgba(255,112,0,.1);   }
.av-groq       { background: rgba(245,80,54,.1);   }
.av-openrouter { background: rgba(124,58,237,.1);  }
.av-deepseek   { background: rgba(14,165,233,.1);  }
.av-default    { background: rgba(99,102,241,.1);  }

.pcard-name  { font-size: 0.92rem; font-weight: 600; flex: 1; min-width: 0; display: flex; align-items: center; gap: 6px; }
.pcard-code  {
    font-size: 0.65rem; font-weight: 700; padding: 2px 7px; border-radius: 5px;
    background: rgba(99,102,241,0.1); color: #4338ca; white-space: nowrap;
}
html.dark .pcard-code { background: rgba(0,0,0,.25); color: var(--aim-muted); }

/* Pill */
.pill {
    font-size: 0.62rem; font-weight: 700; letter-spacing: .04em;
    padding: 2px 8px; border-radius: 20px; white-space: nowrap;
}
.pill-on     { background: rgba(16,185,129,.1); color: #059669; }
html.dark .pill-on { color: #34d399; }
.pill-off    { background: rgba(107,114,128,.1); color: #6b7280; }
.pill-limit  {
    background: rgba(239,68,68,.1);  color: #dc2626;
    border: 1px solid rgba(239,68,68,.2);
    animation: limitPulse 2s ease-in-out infinite;
    cursor: help;
}
html.dark .pill-limit { color: #f87171; border-color: rgba(239,68,68,.3); }
.pill-warn   { background: rgba(245,158,11,.1); color: #d97706; }
html.dark .pill-warn { color: #fbbf24; }
@keyframes limitPulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
    50%     { box-shadow: 0 0 0 4px rgba(239,68,68,.18); }
}

/* Toggle switch */
.sw {
    position: relative; width: 34px; height: 19px;
    flex-shrink: 0; border: none; background: none;
    padding: 0; cursor: pointer;
}
.sw-track {
    display: block; width: 34px; height: 19px; border-radius: 10px;
    background: rgba(0,0,0,.1); transition: background .2s;
}
html.dark .sw-track { background: rgba(255,255,255,.1); }
.sw.on .sw-track { background: var(--aim-indigo); }
.sw-thumb {
    position: absolute; top: 2.5px; left: 2.5px;
    width: 14px; height: 14px; border-radius: 50%;
    background: white; transition: transform .2s;
    box-shadow: 0 1px 4px rgba(0,0,0,.3);
}
.sw.on .sw-thumb { transform: translateX(15px); }

/* Internal tabs */
.pcard-tabs {
    display: flex; border-bottom: 1px solid var(--aim-border);
}
.pcard-tab {
    flex: 1; padding: 8px 0; text-align: center;
    font-size: 0.74rem; font-weight: 600;
    color: var(--aim-muted); cursor: pointer;
    background: none; border: none;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    font-family: inherit; transition: all .15s;
}
.pcard-tab.active { color: var(--aim-indigo); border-bottom-color: var(--aim-indigo); }
.pcard-tab:hover:not(.active) { color: var(--aim-text); }

/* Card body */
.pcard-body {
    flex: 1; min-height: 72px; max-height: 160px; overflow-y: auto;
    padding: 8px 14px;
    scrollbar-width: thin; scrollbar-color: var(--aim-border) transparent;
}
.pcard-body::-webkit-scrollbar { width: 3px; }
.pcard-body::-webkit-scrollbar-thumb { background: var(--aim-border); border-radius: 2px; }

/* Key row */
.key-row {
    display: flex; flex-direction: column; gap: 6px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(0,0,0,.03);
}
html.dark .key-row { border-bottom-color: rgba(255,255,255,.03); }
.key-row:last-child { border-bottom: none; }
.key-top-row { display: flex; align-items: flex-start; gap: 7px; width: 100%; padding-bottom: 2px; }
.key-bottom-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; padding-left: 33px; }

.key-ico {
    width: 26px; height: 26px; border-radius: 7px;
    background: rgba(0,0,0,.04); flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    margin-top: -2px;
}
html.dark .key-ico { background: rgba(255,255,255,.04); }
.key-ico svg { width: 12px; height: 12px; stroke: var(--aim-muted); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
.key-name { 
    font-size: 0.85rem; font-weight: 600; flex: 1; min-width: 0; 
    color: var(--aim-text); 
    word-break: break-all;
    white-space: normal;
    line-height: 1.4;
}
.key-when { font-size: 0.67rem; color: var(--aim-dim); white-space: nowrap; margin-left: auto; padding-top: 2px; }
.key-added-by { font-size: 0.65rem; color: var(--aim-dim); background: rgba(0,0,0,.03); padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; }
html.dark .key-added-by { background: rgba(255,255,255,.04); }

/* Health check dot — kecil di sebelah key-name */
.health-dot {
    width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
    background: var(--aim-dim);
    transition: background .3s;
}
.health-dot.hd-ok      { background: #10b981; box-shadow: 0 0 5px rgba(16,185,129,.5); }
.health-dot.hd-warn    { background: #f59e0b; box-shadow: 0 0 5px rgba(245,158,11,.5); }
.health-dot.hd-bad     { background: #ef4444; box-shadow: 0 0 5px rgba(239,68,68,.5); }
.health-dot.hd-spin    { background: #6366f1; animation: hdPulse .8s infinite; }
@keyframes hdPulse { 0%,100% { opacity:.4; } 50% { opacity:1; } }

/* Mini action buttons */
.mb {
    width: 26px; height: 26px; border-radius: 7px;
    border: 1px solid var(--aim-border);
    background: none; cursor: pointer; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all .15s;
}
.mb svg { width: 11px; height: 11px; stroke: var(--aim-muted); fill: none; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.mb:hover { background: rgba(0,0,0,.05); }
html.dark .mb:hover { background: rgba(255,255,255,.06); }
.mb.mb-warn:hover  { background: rgba(245,158,11,.1); } .mb.mb-warn:hover svg  { stroke: #d97706; }
html.dark .mb.mb-warn:hover svg { stroke: #fbbf24; }
.mb.mb-del           { border-color: rgba(239,68,68,0.25); }
.mb.mb-del svg       { stroke: #ef4444; stroke-width: 2.2; }
.mb.mb-del:hover   { background: rgba(239,68,68,.1);  } .mb.mb-del:hover svg   { stroke: #dc2626; }
html.dark .mb.mb-del { border-color: rgba(239,68,68,0.2); }
html.dark .mb.mb-del svg { stroke: #f87171; }
html.dark .mb.mb-del:hover svg { stroke: #ef4444; }
.mb.mb-edit:hover  { background: rgba(99,102,241,.1); } .mb.mb-edit:hover svg  { stroke: var(--aim-indigo); }
.mb.mb-hc:hover    { background: rgba(6,182,212,.1);  } .mb.mb-hc:hover svg    { stroke: #0891b2; }
html.dark .mb.mb-hc:hover svg { stroke: #22d3ee; }

/* Models panel */
.models-wrap { display: flex; flex-wrap: wrap; gap: 5px; padding: 8px 0; }
.model-chip {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.72rem; padding: 4px 10px; border-radius: 20px;
    border: 1px solid var(--aim-border);
    background: rgba(0,0,0,.03); color: var(--aim-muted);
    cursor: pointer; transition: all .15s;
}
html.dark .model-chip { background: rgba(0,0,0,.2); }
.model-chip .mdot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.model-chip.mc-on { border-color: rgba(99,102,241,.4); background: rgba(99,102,241,.07); color: var(--aim-indigo); }
.model-chip:hover { transform: translateY(-1px); border-color: var(--aim-indigo); }
.mc-del {
    background: none; border: none; color: #ef4444;
    cursor: pointer; font-size: 0.8rem; line-height: 1;
    padding: 0; opacity: 1; margin-left: 4px;
    font-weight: 900;
    transition: transform .15s;
}
.mc-del:hover { transform: scale(1.2); color: #dc2626; }
html.dark .mc-del { color: #f87171; }
.mc-del:hover { opacity: 1; }

/* Empty hint */
.empty-hint { font-size: 0.75rem; color: var(--aim-dim); text-align: center; padding: 18px 0; font-style: italic; }

/* Card footer */
.pcard-foot {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 14px;
    border-top: 1px solid var(--aim-border);
    background: rgba(0,0,0,.03);
    gap: 6px;
}
html.dark .pcard-foot { background: rgba(0,0,0,.12); }
.pf-btn {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.74rem; font-weight: 600;
    background: none; border: none; cursor: pointer;
    font-family: inherit; padding: 4px 8px; border-radius: 6px;
    transition: all .15s;
}
.pf-btn-key  { color: var(--aim-indigo); } .pf-btn-key:hover  { background: rgba(99,102,241,.1); }
.pf-btn-mod  { color: #0891b2; } .pf-btn-mod:hover  { background: rgba(6,182,212,.1); }
html.dark .pf-btn-mod { color: #22d3ee; }
.pf-btn:disabled { opacity: .3; cursor: not-allowed; }
.pf-last { font-size: 0.67rem; color: var(--aim-dim); margin-left: auto; white-space: nowrap; }

/* Add new provider card */
.pcard-add-new {
    background: none;
    border: 1px dashed var(--aim-border);
    border-radius: var(--aim-radius);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 10px; padding: 28px;
    cursor: pointer; color: var(--aim-muted); font-size: 0.82rem;
    font-family: inherit; transition: all .2s;
    min-height: 160px; width: 100%;
}
.pcard-add-new:hover { border-color: var(--aim-indigo); color: var(--aim-indigo); background: rgba(99,102,241,.04); }
.pcard-add-new .add-icon {
    width: 44px; height: 44px; border-radius: 12px;
    border: 1px dashed currentColor;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; transition: transform .2s;
}
.pcard-add-new:hover .add-icon { transform: scale(1.1) rotate(90deg); }

/* Protected label */
.pcard-protected { font-size: 0.63rem; color: var(--aim-dim); }

/* ══════════════════════════════════════════
   MODALS
══════════════════════════════════════════ */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.5); backdrop-filter: blur(5px);
    z-index: 2000; align-items: center; justify-content: center;
    padding: 1rem;
}
html.dark .modal-overlay { background: rgba(0,0,0,.75); }
.modal-box {
    background: #ffffff;
    border: 1.5px solid rgba(99,102,241,0.18);
    border-radius: 20px; padding: 2rem;
    width: 100%; max-width: 450px;
    box-shadow: 0 25px 60px rgba(99,102,241,0.12);
    animation: popIn .2s cubic-bezier(.34,1.56,.64,1);
    color: var(--aim-text);
}
html.dark .modal-box {
    background: #111827;
    border-color: rgba(99,102,241,.2);
    box-shadow: 0 25px 60px rgba(0,0,0,.6);
}
.modal-box.modal-hc { max-width: 520px; }
@keyframes popIn {
    from { opacity:0; transform:scale(.92); }
    to   { opacity:1; transform:scale(1); }
}
.modal-box h3 { margin: 0 0 .2rem; font-size: 1.05rem; }
.modal-sub    { color: var(--aim-muted); font-size: 0.82rem; margin-bottom: 1.5rem; }
.form-grp { margin-bottom: 1.1rem; }
.form-grp label { display:block; margin-bottom:.45rem; color:var(--aim-muted); font-size:0.82rem; font-weight:600; }
.form-grp input, .form-grp select {
    width: 100%;
    background: #f8f9ff;
    border: 1.5px solid rgba(99,102,241,0.25);
    padding: .65rem .9rem; border-radius: 10px;
    color: var(--aim-text); font-family: inherit; font-size: 0.88rem;
    transition: all .15s; box-shadow: inset 0 1px 3px rgba(99,102,241,0.06);
}
html.dark .form-grp input, html.dark .form-grp select {
    background: #1e293b;
    border-color: rgba(255,255,255,.1);
}
.form-grp select option, #hcModelSelect option {
    background-color: #ffffff;
    color: #1f2937;
}
html.dark .form-grp select option, html.dark #hcModelSelect option {
    background-color: #111827;
    color: white;
}
.form-grp input:focus, .form-grp select:focus {
    outline: none; border-color: var(--aim-indigo);
    box-shadow: 0 0 0 3px rgba(99,102,241,.1);
}
.form-grp small { color: var(--aim-dim); font-size: 0.7rem; display:block; margin-top:4px; }
.form-check { display:flex; align-items:center; gap:8px; margin-top:.3rem; }
.form-check input { width:auto; }
.modal-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:1.5rem; }
.btn-modal-cancel {
    background: #fff1f2; color: #e11d48; border: 1px solid #fda4af;
    padding: 8px 18px; border-radius: 9px;
    font-weight: 600; cursor: pointer; transition: all 0.2s;
    font-family: inherit; font-size: 0.85rem;
}
.btn-modal-cancel:hover { background: #ffe4e6; color: #be123c; border-color: #f43f5e; transform: translateY(-1px); }
html.dark .btn-modal-cancel {
    background: rgba(225, 29, 72, 0.1); color: #fb7185; border-color: rgba(225, 29, 72, 0.2);
}
html.dark .btn-modal-cancel:hover { background: rgba(225, 29, 72, 0.2); color: #fda4af; border-color: rgba(225, 29, 72, 0.3); }
.btn-modal-save {
    padding: 8px 22px; border-radius: 9px; border: none; cursor: pointer;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: white;
    font-family: inherit; font-size: 0.85rem; font-weight: 600;
    box-shadow: 0 4px 12px rgba(99,102,241,.25); transition: all .15s;
}
.btn-modal-save:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(99,102,241,.35); }

.prov-emoji { font-size: 1.2rem; }

/* ══════════════════════════════════════════
   HEALTH CHECK MODAL STYLES
══════════════════════════════════════════ */
.hc-status-banner {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; border-radius: 12px;
    margin-bottom: 1.2rem; font-size: 0.9rem; font-weight: 600;
}
.hc-status-banner.hc-ok     { background: rgba(16,185,129,.1);  border: 1px solid rgba(16,185,129,.2); color: #059669; }
html.dark .hc-status-banner.hc-ok { color: #34d399; }
.hc-status-banner.hc-warn   { background: rgba(245,158,11,.1);  border: 1px solid rgba(245,158,11,.2); color: #d97706; }
html.dark .hc-status-banner.hc-warn { color: #fbbf24; }
.hc-status-banner.hc-bad    { background: rgba(239,68,68,.1);   border: 1px solid rgba(239,68,68,.2);  color: #dc2626; }
html.dark .hc-status-banner.hc-bad { color: #f87171; }
.hc-status-banner.hc-loading{ background: rgba(99,102,241,.07); border: 1px solid rgba(99,102,241,.2);  color: var(--aim-indigo); }

.hc-meta { display: flex; gap: 16px; margin-bottom: 1rem; flex-wrap: wrap; }
.hc-meta-item { display: flex; flex-direction: column; gap: 2px; }
.hc-meta-label { font-size: 0.65rem; color: var(--aim-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.hc-meta-value { font-size: 0.88rem; font-weight: 600; color: var(--aim-text); }

.hc-info-table {
    width: 100%; border-collapse: collapse; font-size: 0.8rem;
    margin-top: 0.5rem;
}
.hc-info-table tr { border-bottom: 1px solid var(--aim-border); }
.hc-info-table tr:last-child { border-bottom: none; }
.hc-info-table td { padding: 6px 4px; vertical-align: top; }
.hc-info-table td:first-child {
    color: var(--aim-muted); font-weight: 600; white-space: nowrap;
    padding-right: 12px; width: 45%;
}
.hc-info-table td:last-child { color: var(--aim-text); word-break: break-all; }

.hc-error-box {
    background: rgba(239,68,68,.05); border: 1px solid rgba(239,68,68,.15);
    border-radius: 8px; padding: 10px 12px; margin-top: 10px;
    font-size: 0.78rem; color: #dc2626; line-height: 1.5;
}
html.dark .hc-error-box { color: #f87171; }

.hc-note {
    font-size: 0.72rem; color: var(--aim-dim); text-align: center;
    margin-top: 12px; font-style: italic;
}

.hc-auto-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.7rem; padding: 3px 8px; border-radius: 20px;
    margin-top: 8px;
}
.hc-auto-badge.reset  { background: rgba(16,185,129,.1); color: #059669; border: 1px solid rgba(16,185,129,.2); }
html.dark .hc-auto-badge.reset { color: #34d399; }
.hc-auto-badge.flagged{ background: rgba(239,68,68,.1);  color: #dc2626; border: 1px solid rgba(239,68,68,.2); }
html.dark .hc-auto-badge.flagged { color: #f87171; }

/* Spinner */
.hc-spinner {
    width: 18px; height: 18px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid rgba(99,102,241,.2);
    border-top-color: var(--aim-indigo);
    animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ══════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════ */
@media (max-width: 768px) {
    .aim-stats { grid-template-columns: repeat(2,1fr); }
    .aim-provider-grid { grid-template-columns: 1fr; }
    .aim-topbar { flex-wrap: wrap; }
}
@media (max-width: 480px) {
    .aim-stats { grid-template-columns: 1fr 1fr; }
    .aim-stat-val { font-size: 1.4rem; }
}

/* ══════════════════════════════════════════
   USAGE BADGE per key
══════════════════════════════════════════ */
.key-usage {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 0.63rem; font-weight: 600;
    color: var(--aim-muted);
    background: rgba(0,0,0,.03);
    border: 1px solid var(--aim-border);
    padding: 2px 6px; border-radius: 6px;
    white-space: nowrap; flex-shrink: 0;
    cursor: default;
}
html.dark .key-usage { background: rgba(255,255,255,.04); }
.key-usage .ku-icon { opacity: .5; font-size: .6rem; }
.key-usage.ku-used { color: var(--aim-indigo); border-color: rgba(99,102,241,.2); background: rgba(99,102,241,.06); }

/* Token badge */
.key-tokens {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 0.63rem; font-weight: 600;
    color: var(--aim-muted);
    background: rgba(0,0,0,.03);
    border: 1px solid var(--aim-border);
    padding: 2px 6px; border-radius: 6px;
    white-space: nowrap; flex-shrink: 0;
    cursor: default;
}
html.dark .key-tokens { background: rgba(255,255,255,.04); }
.key-tokens .kt-icon { opacity: .45; font-size: .6rem; }
.key-tokens.kt-has { color: #0891b2; border-color: rgba(6,182,212,.2); background: rgba(6,182,212,.06); }
html.dark .key-tokens.kt-has { color: #22d3ee; }

/* Status badge per key (seragam dengan usage & token badge) */
.key-status {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 0.63rem; font-weight: 700; letter-spacing: .03em;
    padding: 2px 7px; border-radius: 6px;
    white-space: nowrap; flex-shrink: 0;
}
.key-status.ks-ok    { color: #059669; border: 1px solid rgba(16,185,129,.2); background: rgba(16,185,129,.05); }
html.dark .key-status.ks-ok { color: #34d399; }
.key-status.ks-off   { color: #6b7280; border: 1px solid rgba(107,114,128,.2);  background: rgba(107,114,128,.05); }
.key-status.ks-limit {
    color: #dc2626; border: 1px solid rgba(239,68,68,.2); background: rgba(239,68,68,.05);
    animation: limitPulse 2s ease-in-out infinite;
    cursor: help;
}
html.dark .key-status.ks-limit { color: #f87171; }

/* ══════════════════════════════════════════
   LIMIT ALERT BAR inside key panel
══════════════════════════════════════════ */
.limit-alert-bar {
    display: flex; align-items: center; gap: 8px;
    background: rgba(239,68,68,.05);
    border: 1px solid rgba(239,68,68,.15);
    border-radius: 8px; padding: 7px 10px;
    font-size: 0.75rem; color: #dc2626;
    margin-bottom: 6px;
    animation: limitBarSlide .3s ease;
}
html.dark .limit-alert-bar { color: #f87171; }
@keyframes limitBarSlide {
    from { opacity:0; transform: translateY(-4px); }
    to   { opacity:1; transform: translateY(0); }
}
</style>

{{-- ── TOP BAR ────────────────────────────────────────────────── --}}
<div class="aim-topbar">
    <h1 class="aim-topbar-title">
        <div class="t-logo">
            <i class="fas fa-robot"></i>
        </div>
        AI Management
    </h1>
    @if(auth()->user()->is_super_admin)
    <button class="aim-btn-primary" type="button"
            onclick="document.getElementById('providerModal').style.display='flex'">
        <i class="fas fa-plus"></i> Add Provider
    </button>
    @endif
</div>

{{-- ── STATS ──────────────────────────────────────────────────── --}}
@php
    $totalProviders  = $providers->count();
    $activeProviders = $providers->where('is_active', true)->count();
    $totalKeys       = $providers->flatMap->apiKeys->count();
    $activeKeys      = $providers->flatMap->apiKeys->where('is_active', true)->where('limit_reached', false)->count();
    $limitKeys       = $providers->flatMap->apiKeys->where('limit_reached', true)->count();
    $totalModels     = $providers->flatMap->models->where('is_active', true)->count();
@endphp
<div class="aim-stats">
    <div class="aim-stat s-blue">
        <div class="aim-stat-label">Providers</div>
        <div class="aim-stat-val blue">{{ $totalProviders }}</div>
        <div class="aim-stat-sub">{{ $activeProviders }} aktif</div>
        <span class="aim-stat-icon"><i class="fas fa-server"></i></span>
    </div>
    <div class="aim-stat s-green">
        <div class="aim-stat-label">API Keys</div>
        <div class="aim-stat-val green">{{ $totalKeys }}</div>
        <div class="aim-stat-sub">{{ $activeKeys }} aktif</div>
        <span class="aim-stat-icon"><i class="fas fa-key"></i></span>
    </div>
    <div class="aim-stat s-yellow">
        <div class="aim-stat-label">Rate Limited</div>
        <div class="aim-stat-val yellow">{{ $limitKeys }}</div>
        <div class="aim-stat-sub">{{ $limitKeys > 0 ? 'Perlu reset' : 'Semua aman' }}</div>
        <span class="aim-stat-icon"><i class="fas fa-exclamation-triangle"></i></span>
    </div>
    <div class="aim-stat s-cyan">
        <div class="aim-stat-label">Active Models</div>
        <div class="aim-stat-val cyan">{{ $totalModels }}</div>
        <div class="aim-stat-sub">Semua provider</div>
        <span class="aim-stat-icon"><i class="fas fa-brain"></i></span>
    </div>
</div>

{{-- ── PROVIDER GRID ──────────────────────────────────────────── --}}
<div class="aim-section-head">
    <h2>Providers ({{ $totalProviders }})</h2>
</div>

<div class="aim-provider-grid">
    @foreach($providers as $provider)
    @php
        $providerKeys  = $provider->apiKeys;
        $hasLimit      = $providerKeys->where('limit_reached', true)->count() > 0;
        $lastUsed      = $providerKeys->whereNotNull('last_used_at')->sortByDesc('last_used_at')->first();
        $lastUsedLabel = $lastUsed?->last_used_at ? $lastUsed->last_used_at->diffForHumans() : 'Never';

        $avatarEmoji = match($provider->code) {
            'openai'      => '🟢',
            'gemini'      => '🔵',
            'claude'      => '🟠',
            'mistral'     => '🔥',
            'groq'        => '⚡',
            'openrouter'  => '🌐',
            'deepseek'    => '🌊',
            default       => '🤖',
        };
        $avClass = 'av-' . (in_array($provider->code, ['openai','gemini','claude','mistral','groq','openrouter','deepseek']) ? $provider->code : 'default');
    @endphp
    <div class="pcard {{ !$provider->is_active ? 'pcard--off' : '' }}" id="pcard-{{ $provider->id }}">

        {{-- Head --}}
        <div class="pcard-head">
            <div class="provider-avatar {{ $avClass }}">
                <span class="prov-emoji">{{ $avatarEmoji }}</span>
            </div>
            <span class="pcard-name">
                {{ $provider->name }}
                @if($hasLimit && $provider->is_active)
                    <span class="pill pill-limit" style="font-size:.55rem">LIMIT</span>
                @endif
            </span>
            <span class="pcard-code">{{ strtoupper($provider->code) }}</span>
            @if($provider->is_active)
                <span class="pill pill-on" id="pill-{{ $provider->id }}">Active</span>
            @else
                <span class="pill pill-off" id="pill-{{ $provider->id }}">Off</span>
            @endif
            <button class="sw {{ $provider->is_active ? 'on' : '' }}"
                    @if(auth()->user()->is_super_admin)
                    onclick="toggleProvider({{ $provider->id }}, this)"
                    @else
                    disabled
                    @endif
                    title="{{ $provider->is_active ? 'Disable' : 'Enable' }} provider">
                <span class="sw-track"></span>
                <span class="sw-thumb"></span>
            </button>
            @if(auth()->user()->is_super_admin)
                @if(!in_array($provider->code, ['openai','gemini','claude','mistral']))
                <form action="{{ route('admin.ai_management.delete_provider', $provider->id) }}" method="POST"
                      style="display:inline"
                      onsubmit="confirmDelete(event, 'Hapus Provider?', 'Seluruh API Key dan Model di bawah provider ini akan ikut terhapus.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="mb mb-del" title="Hapus provider">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                    </button>
                </form>
                @else
                <span class="pcard-protected" title="Provider bawaan tidak bisa dihapus"><i class="fas fa-lock"></i></span>
                @endif
            @endif
        </div>

        {{-- Internal Tabs --}}
        <div class="pcard-tabs">
            <button class="pcard-tab active" type="button"
                    onclick="switchTab(this,'keys-{{ $provider->id }}','{{ $provider->id }}')">
                🔑 Keys ({{ $providerKeys->count() }})
            </button>
            <button class="pcard-tab" type="button"
                    onclick="switchTab(this,'models-{{ $provider->id }}','{{ $provider->id }}')">
                🧠 Models ({{ $provider->models->count() }})
            </button>
        </div>

        {{-- Keys panel --}}
        <div class="pcard-body" id="keys-{{ $provider->id }}">
            @php $limitCount = $providerKeys->where('limit_reached', true)->count(); @endphp
            @if($limitCount > 0)
            <div class="limit-alert-bar" id="limitbar-{{ $provider->id }}">
                <span style="font-size:.85rem">⚠️</span>
                <span><strong>{{ $limitCount }} key</strong> kena rate limit saat dipakai user — klik reset untuk aktifkan kembali</span>
            </div>
            @else
            <div class="limit-alert-bar" id="limitbar-{{ $provider->id }}" style="display:none"></div>
            @endif
            @forelse($providerKeys as $key)
            <div class="key-row" id="krow-{{ $key->id }}">
                <div class="key-top-row">
                    <div class="key-ico">
                        <svg viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    </div>

                    {{-- Health dot (status terakhir dicek) --}}
                    <span class="health-dot" id="hdot-{{ $key->id }}"
                          title="Belum dicek — klik ikon health check untuk cek kesehatan key ini"></span>

                    <span class="key-name" title="{{ $key->key_name }}">{{ $key->key_name }}</span>

                    <span class="key-when" id="kwhen-{{ $key->id }}">
                        <i class="far fa-clock"></i> {{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never used' }}
                    </span>

                    <div style="display:flex;gap:4px; margin-left: 8px;">
                        <button type="button" class="mb mb-edit" title="Edit key"
                                onclick="openEditKey({{ json_encode(['id'=>$key->id,'key_name'=>$key->key_name,'is_active'=>$key->is_active,'api_key'=>$key->api_key]) }})">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        
                        {{-- Health Check Button --}}
                        <button type="button" class="mb mb-hc" title="Health Check — ping ke provider"
                                onclick="runHealthCheck({{ $key->id }}, '{{ addslashes($key->key_name) }}', '{{ addslashes($provider->name) }}', {{ json_encode($provider->models->where('is_active', true)->pluck('model_name')->values()) }})">  
                            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </button>

                        @if($key->limit_reached)
                        <form action="{{ route('admin.ai_management.reset_limit', $key->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Reset status limit?');">
                            @csrf
                            <button type="submit" class="mb mb-warn" title="Reset limit flag">
                                <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                            </button>
                        </form>
                        @endif

                        <form action="{{ route('admin.ai_management.delete_key', $key->id) }}" method="POST" style="margin:0"
                              onsubmit="confirmDelete(event, 'Hapus API Key?', 'Key yang dihapus tidak bisa dikembalikan.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="mb mb-del" title="Hapus">
                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="key-bottom-row">
                    @if($key->limit_reached)
                        <span class="key-status ks-limit kpill" id="kpill-{{ $key->id }}" title="Key ini kena rate limit saat dipakai user — klik Reset untuk aktifkan kembali">
                            ⚠ LIMIT
                        </span>
                    @elseif(!$key->is_active)
                        <span class="key-status ks-off kpill" id="kpill-{{ $key->id }}">● OFF</span>
                    @else
                        <span class="key-status ks-ok kpill" id="kpill-{{ $key->id }}">● OK</span>
                    @endif

                    {{-- Usage count badge --}}
                    <span class="key-usage {{ $key->usage_count > 0 ? 'ku-used' : '' }}"
                          id="kusage-{{ $key->id }}"
                          title="Dipakai {{ $key->usage_count }} kali">  
                        <span class="ku-icon">↗</span>{{ $key->usage_count }}×
                    </span>

                    {{-- Token count badge --}}
                    @php
                        $tc = $key->token_count ?? 0;
                        if ($tc >= 1000000)      $tcLabel = number_format($tc/1000000, 1) . 'M';
                        elseif ($tc >= 1000)     $tcLabel = number_format($tc/1000, 1) . 'K';
                        else                    $tcLabel = (string)$tc;
                    @endphp
                    <span class="key-tokens {{ $tc > 0 ? 'kt-has' : '' }}"
                          id="ktoken-{{ $key->id }}"
                          title="Total token dipakai: {{ number_format($tc) }} tokens">
                        <span class="kt-icon">◈</span>{{ $tcLabel }}
                    </span>
                    
                    <span class="key-added-by" title="Ditambahkan Oleh: {{ $key->addedBy->name ?? 'System' }} pada {{ $key->created_at->format('d M Y') }}">
                        <i class="fas fa-user-plus"></i> {{ Str::limit($key->addedBy->name ?? 'System', 15) }} &bull; {{ $key->created_at->format('d M Y') }}
                    </span>
                </div>
            </div>
            @empty
            <p class="empty-hint">Belum ada API Key — klik "Add Key" di bawah</p>
            @endforelse
        </div>

        {{-- Models panel --}}
        <div class="pcard-body" id="models-{{ $provider->id }}" style="display:none">
            <div class="models-wrap">
                @forelse($provider->models as $model)
                <div class="model-chip {{ $model->is_active ? 'mc-on' : '' }}"
                     @if(auth()->user()->is_super_admin)
                     onclick="toggleModel({{ $model->id }}, this)"
                     @endif
                     title="{{ $model->model_name }}">
                    <span class="mdot"></span>
                    {{ $model->display_name }}
                    @if(auth()->user()->is_super_admin)
                    <form action="{{ route('admin.ai_management.delete_model', $model->id) }}" method="POST"
                          style="display:inline"
                          onsubmit="event.stopPropagation(); confirmDelete(event, 'Hapus Model AI?', 'Model ini akan dihapus dari daftar pilihan user.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="mc-del" title="Hapus">&times;</button>
                    </form>
                    @endif
                </div>
                @empty
                <p class="empty-hint">Belum ada model — klik "Add Model" di bawah</p>
                @endforelse
            </div>
        </div>

        {{-- Footer --}}
        <div class="pcard-foot">
            <button type="button"
                    class="pf-btn pf-btn-key"
                    {{ !$provider->is_active ? 'disabled' : '' }}
                    onclick="openAddKey({{ $provider->id }}, '{{ addslashes($provider->name) }}')">
                <i class="fas fa-plus" style="font-size:.65rem"></i> Add Key
            </button>
            @if(auth()->user()->is_super_admin)
            <button type="button"
                    class="pf-btn pf-btn-mod"
                    onclick="openAddModel({{ $provider->id }}, '{{ addslashes($provider->name) }}')">
                <i class="fas fa-plus" style="font-size:.65rem"></i> Add Model
            </button>
            @endif
            <span class="pf-last">Last: {{ $lastUsedLabel }}</span>
        </div>
    </div>
    @endforeach

    {{-- Add new provider card --}}
    @if(auth()->user()->is_super_admin)
    <button class="pcard-add-new" type="button"
            onclick="document.getElementById('providerModal').style.display='flex'">
        <span class="add-icon"><i class="fas fa-plus"></i></span>
        <span>Tambah Provider Baru</span>
    </button>
    @endif
</div>


{{-- ══════════════════════════════════════
     MODALS
══════════════════════════════════════ --}}

{{-- Add / Edit API Key --}}
<div id="keyModal" class="modal-overlay">
    <div class="modal-box">
        <h3 id="keyModalTitle">Add API Key</h3>
        <p class="modal-sub" id="keyModalSub"></p>
        <form id="keyForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="keyFormMethod" value="POST">
            <input type="hidden" name="provider_id" id="keyProviderId">
            <div class="form-grp">
                <label>Nama Key (alias)</label>
                <input type="text" name="key_name" id="keyName" required placeholder="Contoh: Key Utama Production">
            </div>
            <div class="form-grp">
                <label id="keyLabel">API Key</label>
                <div style="position:relative;display:flex;align-items:center;">
                    <input type="password" name="api_key" id="keyValue" placeholder="Masukkan API Key" style="padding-right:35px;width:100%;">
                    <button type="button" onclick="toggleApiKeyVisibility()" style="position:absolute;right:10px;background:none;border:none;cursor:pointer;color:var(--aim-muted);">
                        <i class="fas fa-eye" id="keyEyeIcon"></i>
                    </button>
                </div>
                <small id="keyHint" style="display:none">Kosongkan jika tidak ingin mengubah</small>
            </div>
            <div class="form-check" id="keyActiveGrp" style="display:none">
                <input type="checkbox" name="is_active" id="keyIsActive" value="1">
                <label for="keyIsActive" style="color:var(--aim-muted);font-size:.82rem">Aktifkan Key</label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('keyModal')">Batal</button>
                <button type="submit" class="btn-modal-save">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Model --}}
<div id="modelModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Add Model AI</h3>
        <p class="modal-sub" id="modelModalSub"></p>
        <form action="{{ route('admin.ai_management.store_model') }}" method="POST">
            @csrf
            <input type="hidden" name="provider_id" id="modelProviderId">
            <div class="form-grp">
                <label>ID Model (system name)</label>
                <input type="text" name="model_name" required placeholder="Contoh: gpt-4o">
                <small>ID teknis yang dikirim ke API</small>
            </div>
            <div class="form-grp">
                <label>Display Name</label>
                <input type="text" name="display_name" required placeholder="Contoh: GPT-4o">
                <small>Nama yang tampil di antarmuka user</small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('modelModal')">Batal</button>
                <button type="submit" class="btn-modal-save">Simpan Model</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Provider --}}
<div id="providerModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Add Provider AI Baru</h3>
        <p class="modal-sub">
            Mendukung semua provider OpenAI-compatible: Groq, OpenRouter, DeepSeek, LM Studio, dll.
        </p>
        <form action="{{ route('admin.ai_management.store_provider') }}" method="POST">
            @csrf
            <div class="form-grp">
                <label>Nama Provider <span style="color:var(--aim-red)">*</span></label>
                <input type="text" name="name" required placeholder="Contoh: Groq" value="{{ old('name') }}">
            </div>
            <div class="form-grp">
                <label>Kode Unik <span style="color:var(--aim-red)">*</span></label>
                <input type="text" name="code" required placeholder="Contoh: groq"
                       pattern="[a-z0-9_]+" title="Huruf kecil, angka, underscore"
                       value="{{ old('code') }}">
                <small>Huruf kecil, angka, underscore — identifier internal sistem</small>
            </div>
            <div class="form-grp">
                <label>Base URL API</label>
                <input type="url" name="base_url" placeholder="Contoh: https://api.groq.com/openai/v1"
                       value="{{ old('base_url') }}">
                <small>Wajib untuk provider custom. Kosongkan jika built-in.</small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('providerModal')">Batal</button>
                <button type="submit" class="btn-modal-save"><i class="fas fa-plus"></i> Tambah Provider</button>
            </div>
        </form>
    </div>
</div>

{{-- ══ HEALTH CHECK MODAL ═══════════════════════════════════════════════════ --}}
<div id="hcModal" class="modal-overlay">
    <div class="modal-box modal-hc">
        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:.3rem;">
            <div style="width:30px;height:30px;border-radius:8px;background:rgba(6,182,212,.12);display:flex;align-items:center;justify-content:center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22d3ee" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <h3 style="margin:0;font-size:1rem;">API Key Health Check</h3>
        </div>
        <p class="modal-sub" id="hcModalSub" style="margin-bottom:.75rem;"></p>

        {{-- Model Selector --}}
        <div id="hcModelSelector" style="margin-bottom:1rem;">
            <label style="display:block;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--aim-muted);margin-bottom:6px;">
                Model untuk diuji
            </label>
            <div style="display:flex;gap:8px;">
                <select id="hcModelSelect"
                        style="flex:1;background:var(--aim-surface);border:1px solid var(--aim-border);padding:7px 10px;border-radius:9px;color:var(--aim-text);font-family:inherit;font-size:0.82rem;">
                    {{-- diisi JS saat buka modal --}}
                </select>
                <button type="button" id="hcRunBtn"
                        onclick="executeHealthCheck()"
                        style="padding:7px 16px;border-radius:9px;border:none;cursor:pointer;
                               background:linear-gradient(135deg,#06b6d4,#0891b2);color:white;
                               font-family:inherit;font-size:0.82rem;font-weight:600;
                               white-space:nowrap;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Cek Sekarang
                </button>
            </div>
            <div style="margin-top:5px;display:flex;align-items:center;gap:6px;">
                <input type="checkbox" id="hcCustomModelToggle" onchange="toggleCustomModel(this)">
                <label for="hcCustomModelToggle" style="font-size:0.72rem;color:var(--aim-muted);cursor:pointer;">Ketik model manual</label>
            </div>
            <input type="text" id="hcCustomModelInput" placeholder="Contoh: gemini-2.0-flash-exp"
                   style="display:none;margin-top:6px;width:100%;background:var(--aim-surface);
                          border:1px solid var(--aim-border);padding:7px 10px;border-radius:9px;
                          color:var(--aim-text);font-family:inherit;font-size:0.82rem;">
        </div>

        {{-- Status Banner --}}
        <div id="hcBanner" class="hc-status-banner" style="display:none;">
            <div class="hc-spinner" id="hcSpinner" style="display:none;"></div>
            <span id="hcBannerText"></span>
        </div>

        {{-- Meta row: HTTP status & latency --}}
        <div class="hc-meta" id="hcMeta" style="display:none;">
            <div class="hc-meta-item">
                <span class="hc-meta-label">HTTP Status</span>
                <span class="hc-meta-value" id="hcHttpStatus">—</span>
            </div>
            <div class="hc-meta-item">
                <span class="hc-meta-label">Latency</span>
                <span class="hc-meta-value" id="hcLatency">—</span>
            </div>
            <div class="hc-meta-item" id="hcAutoFlagWrap" style="display:none;">
                <span class="hc-meta-label">Auto Action</span>
                <span class="hc-meta-value" id="hcAutoFlag">—</span>
            </div>
        </div>

        {{-- Info table --}}
        <div id="hcInfoWrap" style="display:none;">
            <div style="font-size:0.7rem;color:var(--aim-muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">
                Info dari Provider
            </div>
            <table class="hc-info-table" id="hcInfoTable"></table>
        </div>

        {{-- Error detail --}}
        <div id="hcErrorBox" class="hc-error-box" style="display:none;"></div>

        {{-- Note --}}
        <p class="hc-note" id="hcNote"></p>

        <div class="modal-actions" style="margin-top:1.2rem;">
            <button type="button" class="btn-modal-cancel" onclick="closeModal('hcModal')">Tutup</button>
            <button type="button" class="btn-modal-save" id="hcRetryBtn" style="display:none;"
                    onclick="executeHealthCheck()">
                <i class="fas fa-sync-alt" style="font-size:.75rem"></i> Cek Ulang
            </button>
        </div>
    </div>
</div>


<script>
/* ── Global Delete Confirmation ───────────────────────────── */
function confirmDelete(e, title, text) {
    e.preventDefault();
    const form = e.currentTarget;
    const isDark = document.documentElement.classList.contains('dark');
    
    Swal.fire({
        title: title || 'Hapus data?',
        text: text || 'Tindakan ini tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        background: isDark ? '#1e293b' : '#ffffff',
        color: isDark ? '#f1f5f9' : '#1e293b',
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
}

/* ── Tab switching ───────────────────────────────────────── */
function switchTab(btn, panelId, provId) {
    const card = document.getElementById('pcard-' + provId);
    card.querySelectorAll('.pcard-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    ['keys-' + provId, 'models-' + provId].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    const target = document.getElementById(panelId);
    if (target) target.style.display = 'block';
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

/* ── Toggle provider ────────────────────────────────────── */
function toggleProvider(id, btn) {
    fetch(`/admin/ai-management/providers/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(async r => {
        const text = await r.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            Swal.fire({
                icon: 'error',
                title: 'Response Non-JSON (' + r.status + ')',
                html: '<div style="text-align:left;max-height:200px;overflow:auto;font-family:monospace;font-size:0.75rem;">' + 
                      escapeHtml(text.substring(0, 1000)) + '</div>'
            });
            return;
        }

        if (r.ok) {
            const on = data.is_active;
            const card = document.getElementById('pcard-' + id);
            btn.classList.toggle('on', on);
            card.classList.toggle('pcard--off', !on);

            const pill = document.getElementById('pill-' + id);
            if (pill) {
                pill.className = 'pill ' + (on ? 'pill-on' : 'pill-off');
                pill.textContent = on ? 'Active' : 'Off';
            }

            const addKeyBtn = card.querySelector('.pf-btn-key');
            if (addKeyBtn) addKeyBtn.disabled = !on;
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error ' + r.status,
                text: data.error || data.message || 'Gagal mengubah status provider.'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Fetch Error',
            text: err.message || err.toString()
        });
    });
}

/* ── Toggle model ───────────────────────────────────────── */
function toggleModel(id, chip) {
    fetch(`/admin/ai-management/models/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(async r => {
        const text = await r.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            Swal.fire({
                icon: 'error',
                title: 'Response Non-JSON (' + r.status + ')',
                html: '<div style="text-align:left;max-height:200px;overflow:auto;font-family:monospace;font-size:0.75rem;">' + 
                      escapeHtml(text.substring(0, 1000)) + '</div>'
            });
            return;
        }

        if (r.ok) {
            chip.classList.toggle('mc-on', data.is_active);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error ' + r.status,
                text: data.error || data.message || 'Gagal mengubah status model.'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Fetch Error',
            text: err.message || err.toString()
        });
    });
}

/* ── Modal helpers ──────────────────────────────────────── */
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
window.addEventListener('click', e => {
    ['keyModal','modelModal','providerModal','hcModal'].forEach(id => {
        if (e.target === document.getElementById(id)) closeModal(id);
    });
});

/* ── Add API Key ────────────────────────────────────────── */
function openAddKey(providerId, providerName) {
    const m = document.getElementById('keyModal');
    document.getElementById('keyModalTitle').textContent = 'Add API Key';
    document.getElementById('keyModalSub').textContent   = providerName;
    document.getElementById('keyProviderId').value       = providerId;
    document.getElementById('keyFormMethod').value       = 'POST';
    document.getElementById('keyForm').action            = '{{ route("admin.ai_management.store_key") }}';
    document.getElementById('keyName').value             = '';
    document.getElementById('keyValue').value            = '';
    document.getElementById('keyValue').required         = true;
    document.getElementById('keyLabel').textContent      = 'API Key';
    document.getElementById('keyHint').style.display     = 'none';
    document.getElementById('keyActiveGrp').style.display = 'none';
    
    document.getElementById('keyValue').type = 'text';
    document.getElementById('keyEyeIcon').className = 'fas fa-eye-slash';
    
    m.style.display = 'flex';
}

/* ── Edit API Key ───────────────────────────────────────── */
function openEditKey(key) {
    const m = document.getElementById('keyModal');
    document.getElementById('keyModalTitle').textContent = 'Edit API Key';
    document.getElementById('keyModalSub').textContent   = 'ID: ' + key.id;
    document.getElementById('keyProviderId').value       = '';
    document.getElementById('keyFormMethod').value       = 'PUT';
    document.getElementById('keyForm').action            = '/admin/ai-management/keys/' + key.id;
    document.getElementById('keyName').value             = key.key_name;
    document.getElementById('keyValue').value            = key.api_key || '';
    document.getElementById('keyValue').required         = false;
    document.getElementById('keyLabel').textContent      = 'API Key (opsional)';
    document.getElementById('keyHint').style.display     = 'block';
    document.getElementById('keyActiveGrp').style.display = 'flex';
    document.getElementById('keyIsActive').checked       = !!key.is_active;

    document.getElementById('keyValue').type = 'text';
    document.getElementById('keyEyeIcon').className = 'fas fa-eye-slash';

    m.style.display = 'flex';
}

function toggleApiKeyVisibility() {
    const input = document.getElementById('keyValue');
    const icon = document.getElementById('keyEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

/* ── Add Model ──────────────────────────────────────────── */
function openAddModel(providerId, providerName) {
    document.getElementById('modelProviderId').value     = providerId;
    document.getElementById('modelModalSub').textContent = providerName;
    document.getElementById('modelModal').style.display  = 'flex';
}

/* ══════════════════════════════════════════════════════════
   HEALTH CHECK LOGIC
══════════════════════════════════════════════════════════ */
let _lastHcKeyId   = null;
let _lastHcKeyName = null;
let _lastHcProv    = null;
let _lastHcModels  = [];

function runHealthCheck(keyId, keyName, providerName, providerModels) {
    _lastHcKeyId   = keyId;
    _lastHcKeyName = keyName;
    _lastHcProv    = providerName;
    _lastHcModels  = providerModels || [];

    /* Isi dropdown model */
    const sel = document.getElementById('hcModelSelect');
    sel.innerHTML = '';

    // Option auto-detect (biarkan server yang pilih)
    const autoOpt = document.createElement('option');
    autoOpt.value = '';
    autoOpt.textContent = '🔄 Auto-detect (pilih model terbaik otomatis)';
    sel.appendChild(autoOpt);

    // Option dari model aktif provider
    if (_lastHcModels.length > 0) {
        const sep = document.createElement('option');
        sep.disabled = true;
        sep.textContent = '── Model dari database ──';
        sel.appendChild(sep);

        _lastHcModels.forEach(function(m) {
            const opt = document.createElement('option');
            opt.value = m;
            opt.textContent = m;
            sel.appendChild(opt);
        });
    }

    // Reset custom model input
    document.getElementById('hcCustomModelToggle').checked = false;
    document.getElementById('hcCustomModelInput').style.display = 'none';
    document.getElementById('hcCustomModelInput').value = '';

    /* Reset result area */
    document.getElementById('hcModalSub').textContent   = providerName + ' — ' + keyName;
    document.getElementById('hcBanner').style.display   = 'none';
    document.getElementById('hcMeta').style.display     = 'none';
    document.getElementById('hcInfoWrap').style.display = 'none';
    document.getElementById('hcErrorBox').style.display = 'none';
    document.getElementById('hcNote').textContent       = '';
    document.getElementById('hcRetryBtn').style.display = 'none';
    document.getElementById('hcAutoFlagWrap').style.display = 'none';

    document.getElementById('hcModal').style.display = 'flex';
}

function toggleCustomModel(checkbox) {
    const input = document.getElementById('hcCustomModelInput');
    const sel   = document.getElementById('hcModelSelect');
    if (checkbox.checked) {
        input.style.display = 'block';
        sel.disabled = true;
        input.focus();
    } else {
        input.style.display = 'none';
        sel.disabled = false;
        input.value = '';
    }
}

function executeHealthCheck() {
    const keyId = _lastHcKeyId;
    if (!keyId) return;

    /* Tentukan model yang akan dipakai */
    const useCustom = document.getElementById('hcCustomModelToggle').checked;
    let selectedModel = '';
    if (useCustom) {
        selectedModel = document.getElementById('hcCustomModelInput').value.trim();
    } else {
        selectedModel = document.getElementById('hcModelSelect').value;
    }

    /* Tampilkan loading */
    const banner = document.getElementById('hcBanner');
    banner.className = 'hc-status-banner hc-loading';
    banner.style.display = 'flex';
    document.getElementById('hcSpinner').style.display  = 'block';
    document.getElementById('hcBannerText').textContent = 'Menghubungi provider' + (selectedModel ? ' dengan model ' + selectedModel : '') + '...';
    document.getElementById('hcMeta').style.display     = 'none';
    document.getElementById('hcInfoWrap').style.display = 'none';
    document.getElementById('hcErrorBox').style.display = 'none';
    document.getElementById('hcNote').textContent       = '';
    document.getElementById('hcRetryBtn').style.display = 'none';
    document.getElementById('hcAutoFlagWrap').style.display = 'none';
    document.getElementById('hcRunBtn').disabled        = true;

    /* Set health dot ke spinning */
    const dot = document.getElementById('hdot-' + keyId);
    if (dot) { dot.className = 'health-dot hd-spin'; dot.title = 'Sedang mengecek...'; }

    /* Build form body */
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    if (selectedModel) formData.append('model', selectedModel);

    fetch(`/admin/ai-management/keys/${keyId}/health-check`, {
        method:  'POST',
        headers: { 'Accept': 'application/json' },
        body:    formData,
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('hcRunBtn').disabled = false;
        renderHealthResult(keyId, data);
    })
    .catch(err  => {
        document.getElementById('hcRunBtn').disabled = false;
        renderHealthResult(keyId, {
            status:     'error',
            message:    '❌ Gagal terhubung ke server: ' + err.message,
            latency_ms: null,
            info:       {},
        });
    });
}

function retryLastHealthCheck() {
    if (_lastHcKeyId) executeHealthCheck();
}

function renderHealthResult(keyId, data) {
    const banner    = document.getElementById('hcBanner');
    const spinner   = document.getElementById('hcSpinner');
    const bannerTxt = document.getElementById('hcBannerText');
    const meta      = document.getElementById('hcMeta');
    const infoWrap  = document.getElementById('hcInfoWrap');
    const infoTable = document.getElementById('hcInfoTable');
    const errorBox  = document.getElementById('hcErrorBox');
    const noteEl    = document.getElementById('hcNote');
    const retryBtn  = document.getElementById('hcRetryBtn');
    const dot       = document.getElementById('hdot-' + keyId);

    /* -- Determine banner class & dot class -- */
    let bannerClass = 'hc-status-banner ';
    let dotClass    = 'health-dot ';

    switch (data.status) {
        case 'ok':
            bannerClass += 'hc-ok';  dotClass += 'hd-ok';  break;
        case 'warning':
        case 'server_error':
            bannerClass += 'hc-warn'; dotClass += 'hd-warn'; break;
        case 'rate_limited':
        case 'invalid':
        case 'forbidden':
        case 'error':
        default:
            bannerClass += 'hc-bad'; dotClass += 'hd-bad';  break;
    }

    banner.className = bannerClass;
    spinner.style.display = 'none';
    bannerTxt.textContent = data.message || 'Selesai';

    if (dot) {
        dot.className = dotClass;
        dot.title = data.message || '';
    }

    /* -- Meta row -- */
    if (data.http_status || data.latency_ms !== null) {
        meta.style.display = 'flex';
        document.getElementById('hcHttpStatus').textContent = data.http_status ? data.http_status : '—';
        document.getElementById('hcLatency').textContent    = data.latency_ms  ? data.latency_ms + ' ms' : '—';
    }

    /* -- Auto action badge -- */
    if (data.auto_reset || data.auto_flagged) {
        const autoWrap  = document.getElementById('hcAutoFlagWrap');
        const autoFlag  = document.getElementById('hcAutoFlag');
        autoWrap.style.display = 'flex';
        if (data.auto_reset) {
        autoFlag.innerHTML = '<span class="hc-auto-badge reset">✅ Limit flag otomatis di-reset</span>';
        const pill = document.getElementById('kpill-' + keyId);
        if (pill) { pill.className = 'key-status ks-ok kpill'; pill.innerHTML = '● OK'; pill.title = ''; }
        } else {
            autoFlag.innerHTML = '<span class="hc-auto-badge flagged">⚠️ Key otomatis ditandai LIMIT</span>';
        const pill = document.getElementById('kpill-' + keyId);
        if (pill) { pill.className = 'key-status ks-limit kpill'; pill.innerHTML = '⚠ LIMIT'; pill.title = 'Key ini kena rate limit saat dipakai user — klik Reset untuk aktifkan kembali'; }
        }
    }

    /* -- Info table -- */
    const info = data.info || {};
    const infoEntries = Object.entries(info);
    if (infoEntries.length > 0) {
        infoWrap.style.display = 'block';
        infoTable.innerHTML = infoEntries.map(([k, v]) =>
            `<tr><td>${escHtml(k)}</td><td>${escHtml(String(v))}</td></tr>`
        ).join('');
    }

    /* -- Error detail -- */
    if (data.error_detail) {
        errorBox.style.display = 'block';
        errorBox.textContent = 'Detail error: ' + data.error_detail;
    }

    /* -- Note -- */
    const notes = {
        'ok':           'Key valid. Info rate limit di atas diambil langsung dari header response provider.',
        'rate_limited': 'Key sedang terkena rate limit atau quota habis. Tunggu reset atau isi ulang kredit.',
        'invalid':      'Pastikan API Key sudah benar dan tidak expired.',
        'forbidden':    'Periksa billing dan permission akun di dashboard provider.',
        'server_error': 'Provider sedang bermasalah. Coba beberapa saat lagi.',
        'error':        'Koneksi ke provider gagal. Periksa koneksi internet dan Base URL.',
    };
    noteEl.textContent = notes[data.status] || '';

    retryBtn.style.display = 'inline-flex';
    meta.style.display = 'flex';
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ══════════════════════════════════════════════════════════
   LIVE STATUS POLLING — cek tiap 30 detik apakah ada key baru kena limit
══════════════════════════════════════════════════════════ */
const _providerIdMap = @json($providers->pluck('id', 'id'));

function pollKeyStatus() {
    fetch('{{ route("admin.ai_management.poll_status") }}', {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(r => r.json())
    .then(data => {
        const keys = data.keys || {};
        const limitPerProv = data.limit_per_provider || {};

        Object.entries(keys).forEach(([keyId, key]) => {
            const pill = document.getElementById('kpill-' + keyId);
            const dot  = document.getElementById('hdot-' + keyId);
            const when = document.getElementById('kwhen-' + keyId);

            if (!pill) return;

            /* Update status badge */
            if (pill) {
                if (key.limit_reached) {
                    if (!pill.classList.contains('ks-limit')) {
                        pill.className = 'key-status ks-limit kpill';
                        pill.innerHTML = '⚠ LIMIT';
                        pill.title = 'Key ini kena rate limit saat dipakai user — klik Reset untuk aktifkan kembali';
                        pill.style.outline = '2px solid #ef4444';
                        setTimeout(() => { pill.style.outline = ''; }, 2000);
                    }
                } else if (!key.is_active) {
                    pill.className = 'key-status ks-off kpill';
                    pill.innerHTML = '● OFF';
                    pill.title = '';
                } else {
                    pill.className = 'key-status ks-ok kpill';
                    pill.innerHTML = '● OK';
                    pill.title = '';
                }
            }

            /* Update last-used time */
            if (when && key.last_used_at) {
                when.textContent = key.last_used_at;
            }

            /* Update usage count badge */
            const usageBadge = document.getElementById('kusage-' + keyId);
            if (usageBadge && key.usage_count !== undefined) {
                usageBadge.innerHTML = '<span class="ku-icon">↗</span>' + key.usage_count + '×';
                if (key.usage_count > 0) usageBadge.classList.add('ku-used');
                usageBadge.title = 'Dipakai ' + key.usage_count + ' kali';
            }

            /* Update token count badge */
            const tokenBadge = document.getElementById('ktoken-' + keyId);
            if (tokenBadge && key.token_count !== undefined) {
                const tc = key.token_count || 0;
                let tcLabel;
                if (tc >= 1000000)     tcLabel = (tc / 1000000).toFixed(1) + 'M';
                else if (tc >= 1000)   tcLabel = (tc / 1000).toFixed(1) + 'K';
                else                   tcLabel = String(tc);
                tokenBadge.innerHTML = '<span class="kt-icon">◈</span>' + tcLabel;
                if (tc > 0) tokenBadge.classList.add('kt-has');
                tokenBadge.title = 'Total token dipakai: ' + tc.toLocaleString('id-ID') + ' tokens';
            }

            /* Update health dot warna jika key baru kena limit */
            if (dot && key.limit_reached && !dot.classList.contains('hd-spin')) {
                dot.className = 'health-dot hd-bad';
                dot.title = 'Key kena rate limit';
            }
        });

        /* Update limit-alert-bar per provider */
        Object.entries(limitPerProv).forEach(([provId, count]) => {
            const bar = document.getElementById('limitbar-' + provId);
            if (bar) {
                bar.style.display = 'flex';
                bar.innerHTML = '<span style="font-size:.85rem">⚠️</span>' +
                    '<span><strong>' + count + ' key</strong> kena rate limit saat dipakai user — klik reset untuk aktifkan kembali</span>';
            }
        });

        /* Sembunyikan bar untuk provider yang sudah tidak ada limitnya */
        document.querySelectorAll('.limit-alert-bar').forEach(bar => {
            const provId = bar.id.replace('limitbar-', '');
            if (!limitPerProv[provId]) {
                bar.style.display = 'none';
            }
        });

        /* Update stat box "Rate Limited" di header */
        const statLimitEl = document.querySelector('.aim-stat.s-yellow .aim-stat-val');
        if (statLimitEl) {
            statLimitEl.textContent = data.total_limit ?? '?';
        }
        const statSubEl = document.querySelector('.aim-stat.s-yellow .aim-stat-sub');
        if (statSubEl) {
            statSubEl.textContent = (data.total_limit > 0) ? 'Perlu reset' : 'Semua aman';
        }
    })
    .catch(err => console.warn('[AI Management Poll] Error:', err));
}

/* Jalankan polling tiap 30 detik */
setInterval(pollKeyStatus, 30000);
/* Sekali langsung saat load untuk pastikan sync */
pollKeyStatus();
</script>

@endsection
