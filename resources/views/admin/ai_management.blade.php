@extends('layouts.admin')

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     AI MANAGEMENT — Full Redesign v2
     Darko AI Admin Panel
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
    --aim-surface:  rgba(15,23,42,0.6);
    --aim-border:   rgba(255,255,255,0.07);
    --aim-border-h: rgba(99,102,241,0.35);
    --aim-text:     #e2e8f0;
    --aim-muted:    #64748b;
    --aim-dim:      #334155;
    --aim-radius:   18px;
    --aim-radius-sm:10px;
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
.aim-alert.success { background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.2); color: #34d399; }
.aim-alert.danger  { background: rgba(239,68,68,.08);  border: 1px solid rgba(239,68,68,.2);  color: #f87171; }

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
}
.aim-topbar-title .t-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: linear-gradient(135deg, var(--aim-indigo), #818cf8);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: white; flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(99,102,241,.35);
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
    border: 1px solid var(--aim-border);
    border-radius: 16px; padding: 16px 18px;
    position: relative; overflow: hidden;
    transition: border-color .2s;
}
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
.aim-stat-val.blue   { color: #818cf8; }
.aim-stat-val.green  { color: #34d399; }
.aim-stat-val.yellow { color: #fbbf24; }
.aim-stat-val.cyan   { color: #22d3ee; }
.aim-stat-sub   { font-size: 0.7rem; color: var(--aim-dim); margin-top: 5px; }
.aim-stat-icon  {
    position: absolute; right: 14px; top: 50%;
    transform: translateY(-50%); font-size: 1.6rem;
    opacity: .06;
}

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
    border: 1px solid var(--aim-border);
    border-radius: var(--aim-radius);
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
    display: flex; flex-direction: column;
}
.pcard:hover { border-color: rgba(99,102,241,.25); box-shadow: 0 8px 32px rgba(0,0,0,.25); }
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
.av-openai     { background: rgba(16,163,127,.15); }
.av-gemini     { background: rgba(66,133,244,.15); }
.av-claude     { background: rgba(217,119,87,.15);  }
.av-mistral    { background: rgba(255,112,0,.15);   }
.av-groq       { background: rgba(245,80,54,.15);   }
.av-openrouter { background: rgba(124,58,237,.15);  }
.av-deepseek   { background: rgba(14,165,233,.15);  }
.av-default    { background: rgba(99,102,241,.15);  }

.pcard-name  { font-size: 0.92rem; font-weight: 600; flex: 1; min-width: 0; display: flex; align-items: center; gap: 6px; }
.pcard-code  {
    font-size: 0.65rem; font-weight: 700; padding: 2px 7px; border-radius: 5px;
    background: rgba(0,0,0,.25); color: var(--aim-muted); white-space: nowrap;
}

/* Pill */
.pill {
    font-size: 0.62rem; font-weight: 700; letter-spacing: .04em;
    padding: 2px 8px; border-radius: 20px; white-space: nowrap;
}
.pill-on    { background: rgba(16,185,129,.12); color: #34d399; }
.pill-off   { background: rgba(100,116,139,.12); color: #64748b; }
.pill-limit { background: rgba(239,68,68,.12);  color: #f87171; }
.pill-warn  { background: rgba(245,158,11,.12); color: #fbbf24; }

/* Toggle switch */
.sw {
    position: relative; width: 34px; height: 19px;
    flex-shrink: 0; border: none; background: none;
    padding: 0; cursor: pointer;
}
.sw-track {
    display: block; width: 34px; height: 19px; border-radius: 10px;
    background: rgba(255,255,255,.1); transition: background .2s;
}
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
.pcard-tab.active { color: #818cf8; border-bottom-color: #818cf8; }
.pcard-tab:hover:not(.active) { color: var(--aim-text); }

/* Card body */
.pcard-body {
    flex: 1; min-height: 72px; max-height: 160px; overflow-y: auto;
    padding: 8px 14px;
    scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.08) transparent;
}
.pcard-body::-webkit-scrollbar { width: 3px; }
.pcard-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 2px; }

/* Key row */
.key-row {
    display: flex; align-items: center; gap: 7px;
    padding: 7px 0;
    border-bottom: 1px solid rgba(255,255,255,.03);
}
.key-row:last-child { border-bottom: none; }
.key-ico {
    width: 26px; height: 26px; border-radius: 7px;
    background: rgba(255,255,255,.04); flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
}
.key-ico svg { width: 12px; height: 12px; stroke: #475569; fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
.key-name { font-size: 0.8rem; font-weight: 500; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.key-when { font-size: 0.67rem; color: var(--aim-dim); white-space: nowrap; }

/* Mini action buttons */
.mb {
    width: 26px; height: 26px; border-radius: 7px;
    border: 1px solid rgba(255,255,255,.06);
    background: none; cursor: pointer; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all .15s;
}
.mb svg { width: 11px; height: 11px; stroke: #475569; fill: none; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.mb:hover { background: rgba(255,255,255,.06); }
.mb.mb-warn:hover  { background: rgba(245,158,11,.12); } .mb.mb-warn:hover svg  { stroke: #fbbf24; }
.mb.mb-del:hover   { background: rgba(239,68,68,.1);  } .mb.mb-del:hover svg   { stroke: #f87171; }
.mb.mb-edit:hover  { background: rgba(99,102,241,.12); } .mb.mb-edit:hover svg  { stroke: #818cf8; }

/* Models panel */
.models-wrap { display: flex; flex-wrap: wrap; gap: 5px; padding: 8px 0; }
.model-chip {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.72rem; padding: 4px 10px; border-radius: 20px;
    border: 1px solid rgba(255,255,255,.07);
    background: rgba(0,0,0,.2); color: #475569;
    cursor: pointer; transition: all .15s;
}
.model-chip .mdot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.model-chip.mc-on { border-color: rgba(99,102,241,.3); background: rgba(99,102,241,.07); color: #818cf8; }
.model-chip:hover { transform: translateY(-1px); }
.mc-del {
    background: none; border: none; color: inherit;
    cursor: pointer; font-size: 0.75rem; line-height: 1;
    padding: 0; opacity: .5; margin-left: 2px;
    transition: opacity .15s;
}
.mc-del:hover { opacity: 1; }

/* Empty hint */
.empty-hint { font-size: 0.75rem; color: var(--aim-dim); text-align: center; padding: 18px 0; font-style: italic; }

/* Card footer */
.pcard-foot {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 14px;
    border-top: 1px solid var(--aim-border);
    background: rgba(0,0,0,.12);
    gap: 6px;
}
.pf-btn {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.74rem; font-weight: 600;
    background: none; border: none; cursor: pointer;
    font-family: inherit; padding: 4px 8px; border-radius: 6px;
    transition: all .15s;
}
.pf-btn-key  { color: #818cf8; } .pf-btn-key:hover  { background: rgba(99,102,241,.1); }
.pf-btn-mod  { color: #22d3ee; } .pf-btn-mod:hover  { background: rgba(6,182,212,.1); }
.pf-btn:disabled { opacity: .3; cursor: not-allowed; }
.pf-last { font-size: 0.67rem; color: var(--aim-dim); margin-left: auto; white-space: nowrap; }

/* Add new provider card */
.pcard-add-new {
    background: none;
    border: 1px dashed rgba(99,102,241,.25);
    border-radius: var(--aim-radius);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 10px; padding: 28px;
    cursor: pointer; color: #475569; font-size: 0.82rem;
    font-family: inherit; transition: all .2s;
    min-height: 160px; width: 100%;
}
.pcard-add-new:hover { border-color: #6366f1; color: #818cf8; background: rgba(99,102,241,.04); }
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
    background: rgba(0,0,0,.75); backdrop-filter: blur(5px);
    z-index: 2000; align-items: center; justify-content: center;
    padding: 1rem;
}
.modal-box {
    background: #111827;
    border: 1px solid rgba(99,102,241,.2);
    border-radius: 20px; padding: 2rem;
    width: 100%; max-width: 450px;
    box-shadow: 0 25px 60px rgba(0,0,0,.6);
    animation: popIn .2s cubic-bezier(.34,1.56,.64,1);
}
@keyframes popIn {
    from { opacity:0; transform:scale(.92); }
    to   { opacity:1; transform:scale(1); }
}
.modal-box h3 { margin: 0 0 .2rem; font-size: 1.05rem; }
.modal-sub    { color: #475569; font-size: 0.82rem; margin-bottom: 1.5rem; }
.form-grp { margin-bottom: 1.1rem; }
.form-grp label { display:block; margin-bottom:.45rem; color:#94a3b8; font-size:0.82rem; font-weight:600; }
.form-grp input, .form-grp select {
    width: 100%;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.1);
    padding: .65rem .9rem; border-radius: 10px;
    color: white; font-family: inherit; font-size: 0.88rem;
    transition: all .15s;
}
.form-grp input:focus, .form-grp select:focus {
    outline: none; border-color: var(--aim-indigo);
    box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}
.form-grp small { color: #334155; font-size: 0.7rem; display:block; margin-top:4px; }
.form-check { display:flex; align-items:center; gap:8px; margin-top:.3rem; }
.form-check input { width:auto; }
.modal-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:1.5rem; }
.btn-modal-cancel {
    padding: 8px 18px; border-radius: 9px; border: 1px solid rgba(255,255,255,.1);
    background: rgba(255,255,255,.04); color: #94a3b8; font-family:inherit;
    font-size: 0.85rem; cursor: pointer; transition: all .15s;
}
.btn-modal-cancel:hover { background: rgba(255,255,255,.08); color:white; }
.btn-modal-save {
    padding: 8px 22px; border-radius: 9px; border: none; cursor: pointer;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: white;
    font-family: inherit; font-size: 0.85rem; font-weight: 600;
    box-shadow: 0 4px 12px rgba(99,102,241,.25); transition: all .15s;
}
.btn-modal-save:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(99,102,241,.35); }

.prov-emoji { font-size: 1.2rem; }

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
</style>

{{-- Flash messages --}}
@if(session('success'))
<div class="aim-alert success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
<div class="aim-alert danger"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
@endif

{{-- ── TOP BAR ────────────────────────────────────────────────── --}}
<div class="aim-topbar">
    <h1 class="aim-topbar-title">
        <span class="t-icon"><i class="fas fa-robot"></i></span>
        AI Management
    </h1>
    <button class="aim-btn-primary" type="button"
            onclick="document.getElementById('providerModal').style.display='flex'">
        <i class="fas fa-plus"></i> Add Provider
    </button>
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
                    onclick="toggleProvider({{ $provider->id }}, this)"
                    title="{{ $provider->is_active ? 'Disable' : 'Enable' }} provider">
                <span class="sw-track"></span>
                <span class="sw-thumb"></span>
            </button>
            @if(!in_array($provider->code, ['openai','gemini','claude','mistral']))
            <form action="{{ route('admin.ai_management.delete_provider', $provider->id) }}" method="POST"
                  style="display:inline"
                  onsubmit="return confirm('Hapus provider \'{{ $provider->name }}\' beserta semua data-nya?')">
                @csrf @method('DELETE')
                <button type="submit" class="mb mb-del" title="Hapus provider">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                </button>
            </form>
            @else
            <span class="pcard-protected" title="Provider bawaan tidak bisa dihapus"><i class="fas fa-lock"></i></span>
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
            @forelse($providerKeys as $key)
            <div class="key-row">
                <div class="key-ico">
                    <svg viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                </div>
                <span class="key-name" title="{{ $key->key_name }}">{{ $key->key_name }}</span>
                @if($key->limit_reached)
                    <span class="pill pill-limit">LIMIT</span>
                @elseif(!$key->is_active)
                    <span class="pill pill-off">OFF</span>
                @else
                    <span class="pill pill-on">Active</span>
                @endif
                <span class="key-when">{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never' }}</span>
                @if($key->limit_reached)
                <form action="{{ route('admin.ai_management.reset_limit', $key->id) }}" method="POST" style="display:inline">
                    @csrf
                    <button type="submit" class="mb mb-warn" title="Reset limit">
                        <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                    </button>
                </form>
                @endif
                <button type="button" class="mb mb-edit" title="Edit key"
                        onclick="openEditKey({{ json_encode(['id'=>$key->id,'key_name'=>$key->key_name,'is_active'=>$key->is_active]) }})">
                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <form action="{{ route('admin.ai_management.delete_key', $key->id) }}" method="POST" style="display:inline"
                      onsubmit="return confirm('Hapus key ini?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="mb mb-del" title="Hapus key">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                    </button>
                </form>
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
                     onclick="toggleModel({{ $model->id }}, this)"
                     title="{{ $model->model_name }}">
                    <span class="mdot"></span>
                    {{ $model->display_name }}
                    <form action="{{ route('admin.ai_management.delete_model', $model->id) }}" method="POST"
                          style="display:inline"
                          onsubmit="event.stopPropagation(); return confirm('Hapus model ini?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="mc-del" title="Hapus">&times;</button>
                    </form>
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
            <button type="button"
                    class="pf-btn pf-btn-mod"
                    onclick="openAddModel({{ $provider->id }}, '{{ addslashes($provider->name) }}')">
                <i class="fas fa-plus" style="font-size:.65rem"></i> Add Model
            </button>
            <span class="pf-last">Last: {{ $lastUsedLabel }}</span>
        </div>
    </div>
    @endforeach

    {{-- Add new provider card --}}
    <button class="pcard-add-new" type="button"
            onclick="document.getElementById('providerModal').style.display='flex'">
        <span class="add-icon"><i class="fas fa-plus"></i></span>
        <span>Tambah Provider Baru</span>
    </button>
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
                <input type="password" name="api_key" id="keyValue" placeholder="Masukkan API Key">
                <small id="keyHint" style="display:none">Kosongkan jika tidak ingin mengubah</small>
            </div>
            <div class="form-check" id="keyActiveGrp" style="display:none">
                <input type="checkbox" name="is_active" id="keyIsActive" value="1">
                <label for="keyIsActive" style="color:#94a3b8;font-size:.82rem">Aktifkan Key</label>
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
                <label>Nama Provider <span style="color:#ef4444">*</span></label>
                <input type="text" name="name" required placeholder="Contoh: Groq" value="{{ old('name') }}">
            </div>
            <div class="form-grp">
                <label>Kode Unik <span style="color:#ef4444">*</span></label>
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


<script>
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

/* ── Toggle provider ────────────────────────────────────── */
function toggleProvider(id, btn) {
    fetch(`/admin/ai-management/providers/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(r => r.json())
    .then(data => {
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
    })
    .catch(() => location.reload());
}

/* ── Toggle model ───────────────────────────────────────── */
function toggleModel(id, chip) {
    fetch(`/admin/ai-management/models/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(r => r.json())
    .then(data => chip.classList.toggle('mc-on', data.is_active))
    .catch(() => location.reload());
}

/* ── Modal helpers ──────────────────────────────────────── */
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
window.addEventListener('click', e => {
    ['keyModal','modelModal','providerModal'].forEach(id => {
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
    document.getElementById('keyValue').value            = '';
    document.getElementById('keyValue').required         = false;
    document.getElementById('keyLabel').textContent      = 'API Key (opsional)';
    document.getElementById('keyHint').style.display     = 'block';
    document.getElementById('keyActiveGrp').style.display = 'flex';
    document.getElementById('keyIsActive').checked       = !!key.is_active;
    m.style.display = 'flex';
}

/* ── Add Model ──────────────────────────────────────────── */
function openAddModel(providerId, providerName) {
    document.getElementById('modelProviderId').value     = providerId;
    document.getElementById('modelModalSub').textContent = providerName;
    document.getElementById('modelModal').style.display  = 'flex';
}
</script>

@endsection
