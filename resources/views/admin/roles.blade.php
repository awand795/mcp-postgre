@extends('layouts.admin')
@section('page-title', __('Management Role & Permissions'))

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     ROLE & PERMISSIONS MANAGEMENT — Modern Redesign v2
══════════════════════════════════════════════════════════════ --}}

<style>
/* ── Root Variables & Theming ────────────────────────── */
:root {
    --rm-primary:    #6366f1;
    --rm-primary-d:  #4f46e5;
    --rm-primary-l:  rgba(99,102,241,0.1);
    --rm-emerald:    #10b981;
    --rm-emerald-l:  rgba(16,185,129,0.1);
    --rm-amber:      #f59e0b;
    --rm-amber-l:    rgba(245,158,11,0.1);
    --rm-rose:       #ef4444;
    --rm-rose-l:     rgba(239,68,68,0.1);
    --rm-cyan:       #06b6d4;
    --rm-cyan-l:     rgba(6,182,212,0.1);
    --rm-surface:    #ffffff;
    --rm-surface-sub:#f8fafc;
    --rm-border:     rgba(99,102,241,0.16);
    --rm-border-h:   rgba(99,102,241,0.35);
    --rm-text:       #0f172a;
    --rm-muted:      #64748b;
    --rm-dim:        #94a3b8;
    --rm-radius:     16px;
    --rm-radius-sm:  10px;
}

html.dark {
    --rm-surface:    rgba(15,23,42,0.7);
    --rm-surface-sub:rgba(30,41,59,0.5);
    --rm-border:     rgba(255,255,255,0.08);
    --rm-border-h:   rgba(99,102,241,0.4);
    --rm-text:       #f1f5f9;
    --rm-muted:      #94a3b8;
    --rm-dim:        #475569;
}

/* ── Top Bar ─────────────────────────────────────────── */
.rm-topbar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;
}
.rm-topbar-title {
    display: flex; align-items: center; gap: 12px;
}
.rm-topbar-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: linear-gradient(135deg, var(--rm-primary), #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 1.25rem;
    box-shadow: 0 4px 14px rgba(99,102,241,0.35);
}
.rm-topbar-title h1 {
    font-size: 1.35rem; font-weight: 700; color: var(--rm-text); margin: 0;
}
.rm-topbar-title p {
    font-size: 0.82rem; color: var(--rm-muted); margin: 2px 0 0;
}

/* ── Main 2-Panel Layout ────────────────────────────── */
.rm-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 1.5rem;
    align-items: start;
}

@media (max-width: 1080px) {
    .rm-layout { grid-template-columns: 1fr; }
}

/* ── Left Sidebar (Role Navigation) ──────────────────── */
.rm-sidebar {
    background: var(--rm-surface);
    backdrop-filter: blur(12px);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius);
    padding: 1.25rem;
    position: sticky;
    top: 20px;
    display: flex; flex-direction: column; gap: 1rem;
}

.rm-sidebar-head {
    display: flex; align-items: center; justify-content: space-between;
}
.rm-sidebar-head h3 {
    font-size: 0.8rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--rm-muted); margin: 0;
    display: flex; align-items: center; gap: 6px;
}

.rm-role-search {
    position: relative; display: flex; align-items: center;
}
.rm-role-search input {
    width: 100%;
    background: var(--rm-surface-sub);
    border: 1px solid var(--rm-border);
    padding: 8px 30px 8px 32px;
    border-radius: var(--rm-radius-sm);
    color: var(--rm-text);
    font-size: 0.82rem;
    font-family: inherit;
    outline: none; transition: all .2s;
}
.rm-role-search input:focus {
    border-color: var(--rm-primary);
    box-shadow: 0 0 0 3px var(--rm-primary-l);
}
.rm-role-search .search-ico {
    position: absolute; left: 10px; color: var(--rm-muted);
    font-size: 0.8rem; pointer-events: none;
}
.rm-role-search .clear-ico {
    position: absolute; right: 8px; color: var(--rm-muted);
    font-size: 0.75rem; cursor: pointer; padding: 4px;
    display: none;
}

.rm-role-list {
    display: flex; flex-direction: column; gap: 8px;
    max-height: calc(100vh - 280px);
    overflow-y: auto;
    padding-right: 4px;
    scrollbar-width: thin;
}
.rm-role-list::-webkit-scrollbar { width: 4px; }
.rm-role-list::-webkit-scrollbar-thumb { background: var(--rm-border); border-radius: 4px; }

/* Role Card Item */
.rm-role-card {
    background: var(--rm-surface-sub);
    border: 1px solid var(--rm-border);
    border-radius: 12px;
    padding: 12px 14px;
    cursor: pointer;
    transition: all .2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    display: flex; flex-direction: column; gap: 8px;
    text-align: left;
}
.rm-role-card:hover {
    border-color: var(--rm-border-h);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.rm-role-card.active {
    background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(139,92,246,0.04));
    border-color: var(--rm-primary);
    box-shadow: 0 4px 16px rgba(99,102,241,0.12);
}
.rm-role-card.active::before {
    content: ''; position: absolute;
    top: 0; left: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, var(--rm-primary), #8b5cf6);
    border-radius: 0 4px 4px 0;
}
.rm-role-card.has-changes {
    border-color: var(--rm-amber) !important;
}
.rm-role-card.has-changes::after {
    content: '⚠️ Belum disimpan';
    position: absolute; right: 10px; top: 10px;
    font-size: 0.65rem; font-weight: 700; color: var(--rm-amber);
}

.rm-rc-top {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.rm-rc-title-wrap {
    display: flex; align-items: center; gap: 8px; min-width: 0; flex: 1;
}
.rm-rc-icon {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--rm-primary-l); color: var(--rm-primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; flex-shrink: 0;
}
.rm-rc-name {
    font-size: 0.9rem; font-weight: 700; color: var(--rm-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rm-rc-actions {
    display: flex; align-items: center; gap: 4px; opacity: 0.7; transition: opacity .2s;
}
.rm-role-card:hover .rm-rc-actions { opacity: 1; }
.rm-rc-btn {
    width: 24px; height: 24px; border-radius: 6px;
    border: 1px solid var(--rm-border);
    background: var(--rm-surface); color: var(--rm-muted);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; cursor: pointer; transition: all .15s;
}
.rm-rc-btn:hover { color: var(--rm-text); background: var(--rm-surface-sub); transform: scale(1.05); }
.rm-rc-btn.edit:hover { color: var(--rm-amber); border-color: rgba(245,158,11,0.3); }
.rm-rc-btn.clone:hover { color: var(--rm-cyan); border-color: rgba(6,182,212,0.3); }
.rm-rc-btn.del:hover { color: var(--rm-rose); border-color: rgba(239,68,68,0.3); }

.rm-rc-desc {
    font-size: 0.74rem; color: var(--rm-muted);
    line-height: 1.35; max-height: 2.7em;
    overflow: hidden; text-overflow: ellipsis;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}

.rm-rc-creator {
    font-size: 0.68rem; color: var(--rm-muted); opacity: 0.85;
    display: flex; align-items: center; gap: 5px;
}
.rm-rc-creator i { font-size: 0.65rem; color: var(--rm-dim); }

.rm-rc-meta {
    display: flex; align-items: center; justify-content: space-between; gap: 6px;
    font-size: 0.7rem; color: var(--rm-muted);
}
.rm-rc-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 7px; border-radius: 6px;
    font-size: 0.68rem; font-weight: 600;
    background: rgba(0,0,0,0.03); border: 1px solid var(--rm-border);
}
html.dark .rm-rc-pill { background: rgba(255,255,255,0.04); }
.rm-rc-pill.users { color: var(--rm-primary); }
.rm-rc-pill.tables { color: var(--rm-emerald); }

/* Progress bar mini */
.rm-rc-progress {
    width: 100%; height: 3px; border-radius: 2px;
    background: rgba(0,0,0,0.05); overflow: hidden;
}
html.dark .rm-rc-progress { background: rgba(255,255,255,0.05); }
.rm-rc-progress-bar {
    height: 100%; background: linear-gradient(90deg, var(--rm-primary), var(--rm-emerald));
    border-radius: 2px; transition: width .3s ease;
}

/* ── Right Panel (Permissions & Detail Area) ─────────── */
.rm-main {
    display: flex; flex-direction: column; gap: 1.25rem;
}

/* Hero Card */
.rm-hero {
    background: var(--rm-surface);
    backdrop-filter: blur(12px);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius);
    padding: 1.5rem;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem;
}
.rm-hero-info {
    display: flex; align-items: center; gap: 14px; min-width: 0;
}
.rm-hero-avatar {
    width: 52px; height: 52px; border-radius: 14px;
    background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.15));
    border: 1px solid var(--rm-border);
    color: var(--rm-primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0;
}
.rm-hero-text h2 {
    font-size: 1.3rem; font-weight: 700; color: var(--rm-text); margin: 0;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.rm-hero-text p {
    font-size: 0.84rem; color: var(--rm-muted); margin: 4px 0 0;
}
.rm-hero-chips {
    display: flex; align-items: center; gap: 8px; margin-top: 8px; flex-wrap: wrap;
}
.rm-hero-chip {
    font-size: 0.72rem; font-weight: 600; padding: 3px 9px; border-radius: 8px;
    background: var(--rm-surface-sub); border: 1px solid var(--rm-border);
    color: var(--rm-muted); display: inline-flex; align-items: center; gap: 5px;
}
.rm-hero-chip.highlight {
    background: var(--rm-emerald-l); border-color: rgba(16,185,129,0.25); color: var(--rm-emerald);
}

.rm-hero-actions {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}

/* Action Buttons */
.rm-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 16px; border-radius: 10px;
    font-size: 0.82rem; font-weight: 600;
    cursor: pointer; transition: all .2s; border: none;
    font-family: inherit; text-decoration: none;
}
.rm-btn-primary {
    background: linear-gradient(135deg, var(--rm-primary), var(--rm-primary-d));
    color: white; box-shadow: 0 4px 12px rgba(99,102,241,0.25);
}
.rm-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(99,102,241,0.35);
}
.rm-btn-secondary {
    background: var(--rm-surface-sub);
    color: var(--rm-text); border: 1px solid var(--rm-border);
}
.rm-btn-secondary:hover {
    background: var(--rm-surface); border-color: var(--rm-border-h);
    transform: translateY(-1px);
}
.rm-btn-sm {
    padding: 5px 10px; font-size: 0.74rem; border-radius: 7px;
}

/* Color Variants for Action Buttons */
.rm-btn-emerald {
    background: #10b981 !important; color: white !important; border: 1px solid #059669 !important;
    box-shadow: 0 2px 6px rgba(16,185,129,0.25) !important;
}
.rm-btn-emerald:hover {
    background: #059669 !important; transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(16,185,129,0.35) !important;
}

.rm-btn-rose {
    background: #ef4444 !important; color: white !important; border: 1px solid #dc2626 !important;
    box-shadow: 0 2px 6px rgba(239,68,68,0.25) !important;
}
.rm-btn-rose:hover {
    background: #dc2626 !important; transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(239,68,68,0.35) !important;
}

.rm-btn-indigo {
    background: #6366f1 !important; color: white !important; border: 1px solid #4f46e5 !important;
    box-shadow: 0 2px 6px rgba(99,102,241,0.25) !important;
}
.rm-btn-indigo:hover {
    background: #4f46e5 !important; transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(99,102,241,0.35) !important;
}

.rm-btn-slate {
    background: #64748b !important; color: white !important; border: 1px solid #475569 !important;
    box-shadow: 0 2px 6px rgba(100,116,139,0.25) !important;
}
.rm-btn-slate:hover {
    background: #475569 !important; transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(100,116,139,0.35) !important;
}

.rm-btn-cyan {
    background: #06b6d4 !important; color: white !important; border: 1px solid #0891b2 !important;
    box-shadow: 0 2px 8px rgba(6,182,212,0.25) !important;
}
.rm-btn-cyan:hover {
    background: #0891b2 !important; transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(6,182,212,0.35) !important;
}

.rm-btn-amber {
    background: #f59e0b !important; color: white !important; border: 1px solid #d97706 !important;
    box-shadow: 0 2px 8px rgba(245,158,11,0.25) !important;
}
.rm-btn-amber:hover {
    background: #d97706 !important; transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245,158,11,0.35) !important;
}

.rm-btn-rose-subtle {
    background: rgba(239,68,68,0.15) !important; color: #f87171 !important; border: 1px solid rgba(239,68,68,0.3) !important;
}
.rm-btn-rose-subtle:hover {
    background: #ef4444 !important; color: white !important;
}

/* Sidebar action buttons */
.rm-rc-btn.edit { background: rgba(245,158,11,0.12); color: #d97706; border-color: rgba(245,158,11,0.25); }
.rm-rc-btn.edit:hover { background: #f59e0b; color: white; }
.rm-rc-btn.clone { background: rgba(6,182,212,0.12); color: #0891b2; border-color: rgba(6,182,212,0.25); }
.rm-rc-btn.clone:hover { background: #06b6d4; color: white; }
.rm-rc-btn.del { background: rgba(239,68,68,0.12); color: #ef4444; border-color: rgba(239,68,68,0.25); }
.rm-rc-btn.del:hover { background: #ef4444; color: white; }

/* ── Tabs Navigation ─────────────────────────────────── */
.rm-tabs {
    display: flex; gap: 8px; border-bottom: 1px solid var(--rm-border);
    padding-bottom: 2px;
}
.rm-tab-btn {
    background: none; border: none;
    padding: 8px 16px; font-size: 0.85rem; font-weight: 600;
    color: var(--rm-muted); cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all .2s; display: inline-flex; align-items: center; gap: 6px;
    font-family: inherit; margin-bottom: -1px;
}
.rm-tab-btn:hover { color: var(--rm-text); }
.rm-tab-btn.active {
    color: var(--rm-primary); border-bottom-color: var(--rm-primary);
}
.rm-tab-badge {
    font-size: 0.68rem; font-weight: 700; padding: 2px 6px; border-radius: 10px;
    background: var(--rm-primary-l); color: var(--rm-primary);
}

/* ── Filter & Search Toolbar ─────────────────────────── */
.rm-toolbar {
    background: var(--rm-surface);
    backdrop-filter: blur(12px);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius);
    padding: 1rem 1.25rem;
    display: flex; flex-direction: column; gap: 12px;
}
.rm-tb-row1 {
    display: grid;
    grid-template-columns: 2fr 1.2fr 1.2fr;
    gap: 10px;
}
@media (max-width: 768px) {
    .rm-tb-row1 { grid-template-columns: 1fr; }
}

.rm-input-wrap {
    position: relative; display: flex; align-items: center;
}
.rm-input-wrap .ico {
    position: absolute; left: 11px; color: var(--rm-muted);
    font-size: 0.8rem; pointer-events: none;
}
.rm-input-wrap .clear-btn {
    position: absolute; right: 9px; color: var(--rm-muted);
    font-size: 0.75rem; cursor: pointer; background: none; border: none;
    padding: 3px; display: none;
}
.rm-input-wrap input, .rm-input-wrap select {
    width: 100%;
    background: var(--rm-surface-sub);
    border: 1px solid var(--rm-border);
    padding: 7px 30px 7px 34px;
    border-radius: var(--rm-radius-sm);
    color: var(--rm-text);
    font-size: 0.82rem;
    font-family: inherit;
    outline: none; transition: all .2s;
    height: 36px;
}
.rm-input-wrap select { cursor: pointer; padding-right: 14px; }
.rm-input-wrap input:focus, .rm-input-wrap select:focus {
    border-color: var(--rm-primary);
    box-shadow: 0 0 0 3px var(--rm-primary-l);
}

.rm-tb-row2 {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px; pt: 4px;
}
.rm-pills {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.rm-pill-btn {
    background: var(--rm-surface-sub);
    border: 1px solid var(--rm-border);
    border-radius: 20px;
    padding: 4px 12px; font-size: 0.74rem; font-weight: 600;
    color: var(--rm-muted); cursor: pointer;
    transition: all .15s; font-family: inherit;
    display: inline-flex; align-items: center; gap: 5px;
}
.rm-pill-btn:hover { color: var(--rm-text); border-color: var(--rm-border-h); }
.rm-pill-btn.active {
    background: var(--rm-primary); color: white; border-color: var(--rm-primary);
    box-shadow: 0 2px 6px rgba(99,102,241,0.25);
}
.rm-pill-btn.active.allowed {
    background: var(--rm-emerald); border-color: var(--rm-emerald);
}
.rm-pill-btn.active.not-allowed {
    background: var(--rm-rose); border-color: var(--rm-rose);
}

.rm-tb-stats {
    font-size: 0.78rem; color: var(--rm-muted); display: flex; align-items: center; gap: 8px;
}
.rm-tb-actions {
    display: flex; align-items: center; gap: 6px;
}

/* ── Tree / Accordion View per Database & Schema ─────── */
.rm-tree-container {
    display: flex; flex-direction: column; gap: 14px;
}

/* Database Accordion Card */
.rm-db-card {
    background: var(--rm-surface);
    backdrop-filter: blur(12px);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius);
    overflow: hidden;
    transition: border-color .2s;
}
.rm-db-card:hover { border-color: var(--rm-border-h); }

.rm-db-head {
    padding: 12px 16px;
    background: var(--rm-surface-sub);
    border-bottom: 1px solid var(--rm-border);
    display: flex; align-items: center; justify-content: space-between;
    cursor: pointer; user-select: none;
    transition: background .2s;
}
.rm-db-head:hover { background: rgba(99,102,241,0.04); }
.rm-db-title {
    display: flex; align-items: center; gap: 10px;
}
.rm-db-icon {
    width: 32px; height: 32px; border-radius: 9px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; box-shadow: 0 2px 6px rgba(79,70,229,0.3);
}
.rm-db-name {
    font-size: 0.95rem; font-weight: 700; color: var(--rm-text);
}
.rm-db-badge {
    font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 20px;
    background: var(--rm-primary-l); color: var(--rm-primary);
}
.rm-db-badge.full {
    background: var(--rm-emerald-l); color: var(--rm-emerald);
}

.rm-db-controls {
    display: flex; align-items: center; gap: 10px;
}
.rm-db-chevron {
    color: var(--rm-muted); transition: transform .25s ease;
    font-size: 0.85rem; padding: 4px;
}
.rm-db-card.collapsed .rm-db-chevron {
    transform: rotate(-90deg);
}
.rm-db-card.collapsed .rm-db-body {
    display: none;
}

.rm-db-body {
    padding: 14px 16px;
    display: flex; flex-direction: column; gap: 14px;
}

/* Schema Group */
.rm-schema-group {
    background: rgba(0,0,0,0.015);
    border: 1px solid var(--rm-border);
    border-radius: 12px;
    padding: 12px 14px;
}
html.dark .rm-schema-group { background: rgba(255,255,255,0.02); }

.rm-schema-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 10px; padding-bottom: 6px;
    border-bottom: 1px dashed var(--rm-border);
}
.rm-schema-title {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.82rem; font-weight: 700; color: var(--rm-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
}
.rm-schema-title i { color: var(--rm-cyan); }
.rm-schema-count {
    font-size: 0.72rem; font-weight: 600; color: var(--rm-muted);
}

/* Tables Grid */
.rm-table-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 8px;
}

/* Table Item Card */
.rm-table-card {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 10px;
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    cursor: pointer; user-select: none;
    transition: all .15s ease;
}
.rm-table-card:hover {
    border-color: var(--rm-primary);
    background: rgba(99,102,241,0.04);
    transform: translateY(-1px);
}
.rm-table-card.allowed {
    border-color: rgba(16,185,129,0.35);
    background: rgba(16,185,129,0.06);
}
.rm-table-card.allowed:hover {
    border-color: var(--rm-emerald);
}

/* Custom Checkbox */
.rm-chk {
    width: 19px; height: 19px; border-radius: 5px;
    border: 1.8px solid var(--rm-dim);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 0.7rem; flex-shrink: 0;
    transition: all .15s; background: var(--rm-surface);
}
.rm-table-card.allowed .rm-chk {
    background: var(--rm-emerald);
    border-color: var(--rm-emerald);
}
.rm-chk i { opacity: 0; transform: scale(0.5); transition: all .15s; }
.rm-table-card.allowed .rm-chk i { opacity: 1; transform: scale(1); }

.rm-table-info {
    min-width: 0; flex: 1;
}
.rm-table-name {
    font-size: 0.85rem; font-weight: 600; color: var(--rm-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: flex; align-items: center; gap: 6px;
}
.rm-table-type-badge {
    font-size: 0.6rem; font-weight: 700; padding: 1px 5px; border-radius: 4px;
    text-transform: uppercase; letter-spacing: 0.03em;
}
.rm-type-table { background: rgba(99,102,241,0.1); color: var(--rm-primary); }
.rm-type-view  { background: rgba(168,85,247,0.1); color: #a855f7; }
.rm-type-mview { background: rgba(16,185,129,0.1); color: var(--rm-emerald); }

.rm-table-desc {
    font-size: 0.7rem; color: var(--rm-muted); margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── Users Tab Content ───────────────────────────────── */
.rm-users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}
.rm-user-card {
    background: var(--rm-surface);
    backdrop-filter: blur(12px);
    border: 1px solid var(--rm-border);
    border-radius: 12px;
    padding: 12px 14px;
    display: flex; align-items: center; gap: 12px;
}
.rm-user-avatar {
    width: 40px; height: 40px; border-radius: 10px;
    background: linear-gradient(135deg, var(--rm-primary), #8b5cf6);
    color: white; font-size: 1rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.rm-user-info {
    min-width: 0; flex: 1;
}
.rm-user-name {
    font-size: 0.88rem; font-weight: 600; color: var(--rm-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rm-user-email {
    font-size: 0.74rem; color: var(--rm-muted);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── Empty State ─────────────────────────────────────── */
.rm-empty-state {
    text-align: center; padding: 3.5rem 1.5rem;
    background: var(--rm-surface);
    border: 1px dashed var(--rm-border);
    border-radius: var(--rm-radius);
    color: var(--rm-muted);
}
.rm-empty-state i { font-size: 2.2rem; margin-bottom: 0.75rem; opacity: 0.4; display: block; }
.rm-empty-state h4 { font-size: 1rem; font-weight: 600; color: var(--rm-text); margin: 0 0 4px; }
.rm-empty-state p { font-size: 0.82rem; margin: 0 0 1rem; }

/* ── Floating Save Dock (Bar Simpan Mengambang) ──────── */
.rm-floating-dock {
    position: fixed;
    bottom: 24px; left: 50%;
    transform: translateX(-50%) translateY(100px);
    opacity: 0; pointer-events: none;
    transition: all .3s cubic-bezier(0.34, 1.56, 0.64, 1);
    background: rgba(15,23,42,0.92);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 50px;
    padding: 10px 18px;
    display: flex; align-items: center; gap: 16px;
    box-shadow: 0 12px 36px rgba(0,0,0,0.35);
    z-index: 999; color: white;
}
.rm-floating-dock.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1; pointer-events: auto;
}
.rm-dock-msg {
    font-size: 0.84rem; font-weight: 600;
    display: flex; align-items: center; gap: 8px;
}
.rm-dock-msg i { color: var(--rm-amber); }
.rm-dock-actions {
    display: flex; align-items: center; gap: 8px;
}
.rm-dock-btn-save {
    background: linear-gradient(135deg, var(--rm-emerald), #059669);
    color: white; border: none; border-radius: 30px;
    padding: 7px 18px; font-size: 0.82rem; font-weight: 700;
    cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: 6px;
    box-shadow: 0 4px 12px rgba(16,185,129,0.35);
}
.rm-dock-btn-save:hover { transform: scale(1.03); }
.rm-dock-btn-cancel {
    background: rgba(255,255,255,0.1); color: #cbd5e1;
    border: none; border-radius: 30px; padding: 7px 14px;
    font-size: 0.82rem; font-weight: 600; cursor: pointer;
    transition: all .2s;
}
.rm-dock-btn-cancel:hover { background: rgba(255,255,255,0.2); color: white; }

/* ── Modals ──────────────────────────────────────────── */
.rm-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: none; align-items: center; justify-content: center;
    padding: 1rem;
}
.rm-modal-box {
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius);
    width: 100%; max-width: 440px;
    padding: 1.5rem; color: var(--rm-text);
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    animation: rmModalPop .2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes rmModalPop {
    from { opacity: 0; transform: scale(0.95); }
    to   { opacity: 1; transform: scale(1); }
}
.rm-modal-head {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;
}
.rm-modal-head h3 { font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--rm-text); }
.rm-modal-close {
    background: none; border: none; font-size: 1rem;
    color: var(--rm-muted); cursor: pointer; padding: 4px;
}
.rm-modal-close:hover { color: var(--rm-text); }
.rm-form-grp {
    margin-bottom: 1rem; display: flex; flex-direction: column; gap: 6px;
}
.rm-form-grp label {
    font-size: 0.78rem; font-weight: 600; color: var(--rm-muted);
}
.rm-form-grp input, .rm-form-grp textarea {
    width: 100%;
    background: var(--rm-surface-sub);
    border: 1px solid var(--rm-border);
    padding: 8px 12px; border-radius: var(--rm-radius-sm);
    color: var(--rm-text); font-size: 0.85rem; font-family: inherit;
    outline: none; transition: all .2s;
}
.rm-form-grp input:focus, .rm-form-grp textarea:focus {
    border-color: var(--rm-primary); box-shadow: 0 0 0 3px var(--rm-primary-l);
}
.rm-modal-foot {
    display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin-top: 1.25rem;
}
</style>

{{-- ── TOP BAR ────────────────────────────────────────────────── --}}
<div class="rm-topbar">
    <div class="rm-topbar-title">
        <div class="rm-topbar-icon">
            <i class="fas fa-user-shield"></i>
        </div>
        <div>
            <h1>{{ __('Role & Permissions') }}</h1>
            <p>{{ __('Kelola hak akses tabel per database & schema untuk setiap peran pengguna') }}</p>
        </div>
    </div>
    <button class="rm-btn rm-btn-primary" type="button" onclick="showRoleModal('create')">
        <i class="fas fa-plus"></i> {{ __('Tambah Role Baru') }}
    </button>
</div>

{{-- ── MAIN LAYOUT ────────────────────────────────────────────── --}}
<div class="rm-layout">

    {{-- ── LEFT PANEL: Role List ─────────────────────────────── --}}
    <div class="rm-sidebar">
        <div class="rm-sidebar-head">
            <h3><i class="fas fa-shield-alt"></i> {{ __('Daftar Role') }} (<span id="roleCountBadge">{{ $roles->count() }}</span>)</h3>
        </div>

        {{-- Role Search Input --}}
        <div class="rm-role-search">
            <i class="fas fa-search search-ico"></i>
            <input type="text" id="searchRoleInput" placeholder="{{ __('Cari role...') }}" oninput="filterRoleList(this.value)">
            <i class="fas fa-times clear-ico" id="clearRoleSearch" onclick="clearRoleSearchInput()"></i>
        </div>

        {{-- Role Cards List --}}
        <div class="rm-role-list" id="roleList">
            @foreach($roles as $role)
            @php
                $userCount = $role->users_count ?? ($role->users ? $role->users->count() : 0);
                $permCount = $role->permissions ? $role->permissions->count() : 0;
                $totalTbl  = count($allTables);
                $pct       = $totalTbl > 0 ? min(100, round(($permCount / $totalTbl) * 100)) : 0;
            @endphp
            <div class="rm-role-card {{ $loop->first ? 'active' : '' }}"
                 id="role-card-{{ $role->id }}"
                 data-role-id="{{ $role->id }}"
                 data-role-name="{{ strtolower($role->name) }}"
                 onclick="selectRole({{ $role->id }}, this)">
                
                <div class="rm-rc-top">
                    <div class="rm-rc-title-wrap">
                        <div class="rm-rc-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <span class="rm-rc-name" title="{{ $role->name }}">{{ $role->name }}</span>
                    </div>
                    <div class="rm-rc-actions" onclick="event.stopPropagation()">
                        <button type="button" class="rm-rc-btn clone" title="{{ __('Duplikat Role ini') }}" onclick="duplicateRole({{ $role->id }})">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button type="button" class="rm-rc-btn edit" title="{{ __('Edit Info Role') }}" onclick="showRoleModal('edit', {{ json_encode($role) }})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="rm-rc-btn del" title="{{ __('Hapus Role') }}" onclick="deleteRole({{ $role->id }})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                @if($role->description)
                <div class="rm-rc-desc">{{ $role->description }}</div>
                @endif

                <div class="rm-rc-creator">
                    <i class="fas fa-user-edit"></i> {{ $role->addedBy->name ?? 'System' }} &bull; {{ $role->created_at ? $role->created_at->format('d M Y') : '-' }}
                </div>

                <div class="rm-rc-meta">
                    <span class="rm-rc-pill users"><i class="fas fa-users"></i> {{ $userCount }} User</span>
                    <span class="rm-rc-pill tables" id="rc-perm-count-{{ $role->id }}"><i class="fas fa-table"></i> {{ $permCount }}/{{ $totalTbl }} Tabel</span>
                </div>

                <div class="rm-rc-progress">
                    <div class="rm-rc-progress-bar" id="rc-progress-{{ $role->id }}" style="width: {{ $pct }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── RIGHT PANEL: Permissions & Member Canvas ──────────── --}}
    <div class="rm-main">

        {{-- Role Hero Card --}}
        <div class="rm-hero">
            <div class="rm-hero-info">
                <div class="rm-hero-avatar" id="heroAvatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="rm-hero-text">
                    <h2 id="heroRoleName">{{ $roles[0]->name ?? __('Pilih Role') }}</h2>
                    <p id="heroRoleDesc">{{ $roles[0]->description ?? __('Pilih salah satu role di panel kiri untuk mengatur hak akses.') }}</p>
                    <div class="rm-hero-chips">
                        <span class="rm-hero-chip highlight" id="heroPermChip"><i class="fas fa-check-circle"></i> <strong id="heroPermCount">0</strong> {{ __('Tabel Diizinkan') }}</span>
                        <span class="rm-hero-chip" id="heroUserChip"><i class="fas fa-users"></i> <strong id="heroUserCount">0</strong> {{ __('Pengguna') }}</span>
                        <span class="rm-hero-chip" id="heroDbChip"><i class="fas fa-database"></i> <strong id="heroDbCount">0</strong> {{ __('Database') }}</span>
                        <span class="rm-hero-chip" id="heroCreatorChip"><i class="fas fa-user-edit"></i> <span id="heroCreatedBy">{{ $roles[0]->addedBy->name ?? 'System' }}</span> &bull; <span id="heroCreatedAt">{{ $roles[0]->created_at ? $roles[0]->created_at->format('d M Y') : '-' }}</span></span>
                    </div>
                </div>
            </div>
            <div class="rm-hero-actions">
                <button type="button" class="rm-btn rm-btn-cyan" onclick="duplicateCurrentRole()">
                    <i class="fas fa-copy"></i> {{ __('Duplikat Role') }}
                </button>
                <button type="button" class="rm-btn rm-btn-amber" onclick="editCurrentRole()">
                    <i class="fas fa-edit"></i> {{ __('Edit Info') }}
                </button>
                <button type="button" class="rm-btn rm-btn-emerald" onclick="savePermissions()">
                    <i class="fas fa-save"></i> <span>{{ __('Simpan Akses') }}</span>
                </button>
            </div>
        </div>

        {{-- Tabs Navigation --}}
        <div class="rm-tabs">
            <button type="button" class="rm-tab-btn active" id="tabBtnPermissions" onclick="switchMainTab('permissions')">
                <i class="fas fa-table"></i> {{ __('Hak Akses Tabel & Database') }}
            </button>
            <button type="button" class="rm-tab-btn" id="tabBtnMembers" onclick="switchMainTab('members')">
                <i class="fas fa-users"></i> {{ __('Daftar Pengguna') }}
                <span class="rm-tab-badge" id="tabMemberBadge">0</span>
            </button>
        </div>

        {{-- TAB 1: PERMISSIONS CONTENT --}}
        <div id="tabContentPermissions">
            {{-- Filter & Search Toolbar --}}
            <div class="rm-toolbar">
                <div class="rm-tb-row1">
                    {{-- Search Table / Description --}}
                    <div class="rm-input-wrap">
                        <i class="fas fa-search ico"></i>
                        <input type="text" id="tableSearchInput" placeholder="{{ __('Cari nama tabel, schema, atau deskripsi...') }}" oninput="applyFilters()">
                        <button type="button" class="clear-btn" id="clearTableSearch" onclick="clearTableSearchInput()"><i class="fas fa-times"></i></button>
                    </div>

                    {{-- Database Filter Dropdown --}}
                    <div class="rm-input-wrap">
                        <i class="fas fa-database ico"></i>
                        <select id="dbFilterSelect" onchange="handleDbFilterChange(this.value)">
                            <option value="">{{ __('Semua Database') }}</option>
                            @foreach($databases as $db)
                                <option value="{{ $db->database }}">{{ $db->database }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Schema Filter Dropdown --}}
                    <div class="rm-input-wrap">
                        <i class="fas fa-layer-group ico"></i>
                        <select id="schemaFilterSelect" onchange="applyFilters()">
                            <option value="">{{ __('Semua Schema') }}</option>
                        </select>
                    </div>
                </div>

                <div class="rm-tb-row2">
                    {{-- Quick Status Filter Pills --}}
                    <div class="rm-pills">
                        <button type="button" class="rm-pill-btn active" data-status="all" onclick="setStatusFilter('all', this)">
                            {{ __('Semua Tabel') }} (<span id="pillCountAll">0</span>)
                        </button>
                        <button type="button" class="rm-pill-btn" data-status="allowed" onclick="setStatusFilter('allowed', this)">
                            <i class="fas fa-check" style="font-size:.65rem"></i> {{ __('Diizinkan') }} (<span id="pillCountAllowed">0</span>)
                        </button>
                        <button type="button" class="rm-pill-btn" data-status="not_allowed" onclick="setStatusFilter('not_allowed', this)">
                            <i class="fas fa-times" style="font-size:.65rem"></i> {{ __('Belum Diizinkan') }} (<span id="pillCountNotAllowed">0</span>)
                        </button>
                    </div>

                    {{-- Bulk Actions & Accordion Controls --}}
                    <div class="rm-tb-actions">
                        <button type="button" class="rm-btn rm-btn-emerald rm-btn-sm" onclick="bulkAction('select')" title="{{ __('Pilih semua tabel yang tampil') }}">
                            <i class="fas fa-check-square"></i> {{ __('Pilih Semua') }}
                        </button>
                        <button type="button" class="rm-btn rm-btn-rose rm-btn-sm" onclick="bulkAction('deselect')" title="{{ __('Hapus semua tabel yang tampil') }}">
                            <i class="fas fa-square"></i> {{ __('Hapus Semua') }}
                        </button>
                        <button type="button" class="rm-btn rm-btn-indigo rm-btn-sm" onclick="toggleAllAccordions(true)" title="{{ __('Buka semua accordion') }}">
                            <i class="fas fa-folder-open"></i> {{ __('Buka Semua') }}
                        </button>
                        <button type="button" class="rm-btn rm-btn-slate rm-btn-sm" onclick="toggleAllAccordions(false)" title="{{ __('Ciutkan semua accordion') }}">
                            <i class="fas fa-folder"></i> {{ __('Tutup') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Accordion Tree Container --}}
            <div class="rm-tree-container" id="treeContainer" style="margin-top: 1rem;">
                {{-- Rendered dynamically by JS --}}
            </div>

            {{-- Empty State (Search / Filter Not Found) --}}
            <div class="rm-empty-state" id="tableEmptyState" style="display: none; margin-top: 1rem;">
                <i class="fas fa-search"></i>
                <h4>{{ __('Tidak Ada Tabel yang Cocok') }}</h4>
                <p>{{ __('Coba sesuaikan kata kunci pencarian atau filter database/schema Anda.') }}</p>
                <button type="button" class="rm-btn rm-btn-indigo rm-btn-sm" onclick="resetAllTableFilters()">
                    <i class="fas fa-undo"></i> {{ __('Reset Filter') }}
                </button>
            </div>
        </div>

        {{-- TAB 2: MEMBERS CONTENT --}}
        <div id="tabContentMembers" style="display: none;">
            <div class="rm-users-grid" id="membersGrid">
                {{-- Rendered by JS --}}
            </div>
            <div class="rm-empty-state" id="membersEmptyState" style="display: none;">
                <i class="fas fa-users-slash"></i>
                <h4>{{ __('Belum Ada Pengguna') }}</h4>
                <p>{{ __('Belum ada pengguna yang memiliki role ini saat ini.') }}</p>
                <a href="{{ route('admin.users') }}" class="rm-btn rm-btn-primary rm-btn-sm">
                    <i class="fas fa-user-plus"></i> {{ __('Kelola di User Management') }}
                </a>
            </div>
        </div>

    </div>
</div>

{{-- ── FLOATING SAVE BAR (Sticky Bottom Bar) ──────────────────── --}}
<div class="rm-floating-dock" id="floatingSaveDock">
    <div class="rm-dock-msg">
        <i class="fas fa-exclamation-triangle"></i>
        <span>{{ __('Ada perubahan hak akses yang belum disimpan') }}</span>
    </div>
    <div class="rm-dock-actions">
        <button type="button" class="rm-dock-btn-cancel rm-btn-rose-subtle" onclick="discardChanges()">
            <i class="fas fa-undo"></i> {{ __('Batalkan') }}
        </button>
        <button type="button" class="rm-dock-btn-save" onclick="savePermissions()">
            <i class="fas fa-save"></i> {{ __('Simpan Perubahan') }}
        </button>
    </div>
</div>

{{-- ── MODAL: Create / Edit Role ──────────────────────────────── --}}
<div id="roleModal" class="rm-modal-overlay">
    <div class="rm-modal-box">
        <div class="rm-modal-head">
            <h3 id="roleModalTitle">{{ __('Tambah Role') }}</h3>
            <button type="button" class="rm-modal-close" onclick="closeRoleModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="roleForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="roleFormMethod" value="POST">
            <div class="rm-form-grp">
                <label>{{ __('Nama Role') }} <span style="color:var(--rm-rose)">*</span></label>
                <input type="text" name="name" id="roleNameInput" required placeholder="{{ __('Contoh: Finance Analyst, Inventory Viewer') }}">
            </div>
            <div class="rm-form-grp">
                <label>{{ __('Deskripsi') }}</label>
                <textarea name="description" id="roleDescInput" rows="3" placeholder="{{ __('Deskripsi singkat mengenai wewenang role ini...') }}"></textarea>
            </div>
            <div class="rm-modal-foot">
                <button type="button" class="rm-btn rm-btn-slate" onclick="closeRoleModal()">{{ __('Batal') }}</button>
                <button type="submit" class="rm-btn rm-btn-primary">{{ __('Simpan Role') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- ── MODAL: Duplicate / Clone Role ──────────────────────────── --}}
<div id="cloneModal" class="rm-modal-overlay">
    <div class="rm-modal-box">
        <div class="rm-modal-head">
            <h3><i class="fas fa-copy" style="color:var(--rm-cyan);margin-right:6px"></i> {{ __('Duplikat Role') }}</h3>
            <button type="button" class="rm-modal-close" onclick="closeCloneModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="cloneForm" onsubmit="handleCloneSubmit(event)">
            @csrf
            <input type="hidden" id="cloneSourceRoleId">
            <p style="font-size:0.82rem;color:var(--rm-muted);margin:0 0 1rem;">
                {{ __('Seluruh hak akses tabel dari role asal akan disalin otomatis ke role baru.') }}
            </p>
            <div class="rm-form-grp">
                <label>{{ __('Nama Role Baru') }} <span style="color:var(--rm-rose)">*</span></label>
                <input type="text" id="cloneRoleNameInput" required placeholder="{{ __('Contoh: Finance Analyst (Salinan)') }}">
            </div>
            <div class="rm-form-grp">
                <label>{{ __('Deskripsi') }}</label>
                <textarea id="cloneRoleDescInput" rows="3" placeholder="{{ __('Deskripsi untuk role baru...') }}"></textarea>
            </div>
            <div class="rm-modal-foot">
                <button type="button" class="rm-btn rm-btn-slate" onclick="closeCloneModal()">{{ __('Batal') }}</button>
                <button type="submit" class="rm-btn rm-btn-cyan" id="btnSubmitClone">
                    <i class="fas fa-clone"></i> {{ __('Duplikat & Buat') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Global Initial Data ────────────────────────────────────── --}}
<script>
    window.allTables = @json($allTables);
    window.allRoles = @json($roles->load(['permissions', 'users'])->toArray());
    window.allDatabases = @json($databases);
</script>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    let allRoles = window.allRoles || [];
    let allTables = window.allTables || [];
    let currentRoleId = allRoles.length > 0 ? allRoles[0].id : null;
    let selectedTables = new Set();
    let originalSelectedTables = new Set();
    let hasChanges = false;
    let currentStatusFilter = 'all';
    let currentActiveTab = 'permissions';

    const treeContainer = document.getElementById('treeContainer');
    const tableEmptyState = document.getElementById('tableEmptyState');
    const floatingSaveDock = document.getElementById('floatingSaveDock');
    const tableSearchInput = document.getElementById('tableSearchInput');
    const clearTableSearch = document.getElementById('clearTableSearch');

    /* ══════════════════════════════════════════════════════════
       INITIALIZATION
    ══════════════════════════════════════════════════════════ */
    function init() {
        if (currentRoleId) {
            loadRolePermissions(currentRoleId);
        }
        updatePillCounts();
    }

    /* ══════════════════════════════════════════════════════════
       ROLE LOADING & SELECTION
    ══════════════════════════════════════════════════════════ */
    function loadRolePermissions(roleId) {
        const role = allRoles.find(r => r.id == roleId);
        if (!role) return;

        selectedTables.clear();
        originalSelectedTables.clear();

        (role.permissions || []).forEach(p => {
            const key = (p.database_code && p.schema_name)
                ? `${p.database_code}|${p.schema_name}|${p.table_name}`
                : p.table_name;
            selectedTables.add(key);
            originalSelectedTables.add(key);
        });

        // Update Hero Details
        document.getElementById('heroRoleName').textContent = role.name;
        document.getElementById('heroRoleDesc').textContent = role.description || "{{ __('Tidak ada deskripsi.') }}";
        
        const userList = role.users || [];
        document.getElementById('heroUserCount').textContent = userList.length;
        document.getElementById('tabMemberBadge').textContent = userList.length;

        // Distinct Databases with permissions
        const dbSet = new Set();
        (role.permissions || []).forEach(p => { if (p.database_code) dbSet.add(p.database_code); });
        document.getElementById('heroDbCount').textContent = dbSet.size;

        // Creator & Created At info
        const creatorName = (role.added_by && typeof role.added_by === 'object' && role.added_by.name)
            ? role.added_by.name
            : (role.added_by_user ? role.added_by_user.name : (role.addedBy ? role.addedBy.name : 'System'));
        const createdDate = role.created_at ? new Date(role.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';

        const heroCreatedByEl = document.getElementById('heroCreatedBy');
        if (heroCreatedByEl) heroCreatedByEl.textContent = creatorName;
        const heroCreatedAtEl = document.getElementById('heroCreatedAt');
        if (heroCreatedAtEl) heroCreatedAtEl.textContent = createdDate;

        renderMembersTab(userList);
        setHasChanges(false);
        applyFilters();
    }

    window.selectRole = function(roleId, el) {
        if (hasChanges) {
            Swal.fire({
                title: "{{ __('Perubahan Belum Disimpan') }}",
                text: "{{ __('Ada perubahan hak akses yang belum disimpan. Yakin ingin berpindah role?') }}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: "{{ __('Ya, Pindah & Buang Perubahan') }}",
                cancelButtonText: "{{ __('Tetap di Sini') }}"
            }).then((result) => {
                if (result.isConfirmed) doSelectRole(roleId, el);
            });
        } else {
            doSelectRole(roleId, el);
        }
    };

    function doSelectRole(roleId, el) {
        currentRoleId = roleId;
        document.querySelectorAll('.rm-role-card').forEach(c => c.classList.remove('active'));
        if (el) el.classList.add('active');
        loadRolePermissions(roleId);
    }

    /* ══════════════════════════════════════════════════════════
       DIRTY STATE / FLOATING SAVE BAR
    ══════════════════════════════════════════════════════════ */
    function setHasChanges(value) {
        hasChanges = value;
        if (floatingSaveDock) {
            if (value) floatingSaveDock.classList.add('show');
            else floatingSaveDock.classList.remove('show');
        }

        const activeCard = document.getElementById('role-card-' + currentRoleId);
        if (activeCard) {
            if (value) activeCard.classList.add('has-changes');
            else activeCard.classList.remove('has-changes');
        }
    }

    function checkIfChanged() {
        if (selectedTables.size !== originalSelectedTables.size) return true;
        for (let item of selectedTables) {
            if (!originalSelectedTables.has(item)) return true;
        }
        return false;
    }

    window.discardChanges = function() {
        selectedTables = new Set(originalSelectedTables);
        setHasChanges(false);
        applyFilters();
    };

    /* ══════════════════════════════════════════════════════════
       TABLE CHECKBOX TOGGLE
    ══════════════════════════════════════════════════════════ */
    window.toggleTable = function(key) {
        if (selectedTables.has(key)) {
            selectedTables.delete(key);
        } else {
            selectedTables.add(key);
        }

        setHasChanges(checkIfChanged());
        applyFilters();
        updateCountsAndPills();
    };

    function updateCountsAndPills() {
        document.getElementById('heroPermCount').textContent = selectedTables.size;
        updatePillCounts();

        // Update progress on sidebar
        const total = allTables.length;
        const count = selectedTables.size;
        const pct = total > 0 ? Math.min(100, Math.round((count / total) * 100)) : 0;
        
        const countLabel = document.getElementById('rc-perm-count-' + currentRoleId);
        if (countLabel) countLabel.innerHTML = `<i class="fas fa-table"></i> ${count}/${total} Tabel`;
        
        const progressBar = document.getElementById('rc-progress-' + currentRoleId);
        if (progressBar) progressBar.style.width = pct + '%';
    }

    function updatePillCounts() {
        const allowedCount = selectedTables.size;
        const totalCount   = allTables.length;
        const notAllowed   = Math.max(0, totalCount - allowedCount);

        document.getElementById('pillCountAll').textContent = totalCount;
        document.getElementById('pillCountAllowed').textContent = allowedCount;
        document.getElementById('pillCountNotAllowed').textContent = notAllowed;
    }

    /* ══════════════════════════════════════════════════════════
       ACCORDION TREE RENDERING
    ══════════════════════════════════════════════════════════ */
    function renderTree(filteredTables) {
        treeContainer.innerHTML = '';

        if (filteredTables.length === 0) {
            tableEmptyState.style.display = 'block';
            return;
        }
        tableEmptyState.style.display = 'none';

        // Group tables by database_code -> schema_name
        const dbGroups = {};
        filteredTables.forEach(table => {
            const db = table.database_code || 'default';
            const schema = table.schema_name || 'public';

            if (!dbGroups[db]) dbGroups[db] = { dbName: table.database_name || db, schemas: {} };
            if (!dbGroups[db].schemas[schema]) dbGroups[db].schemas[schema] = [];
            dbGroups[db].schemas[schema].push(table);
        });

        const searchTerm = (tableSearchInput.value || '').toLowerCase().trim();

        Object.entries(dbGroups).forEach(([dbCode, dbData]) => {
            // Count total and selected tables in this DB
            let totalInDb = 0;
            let selectedInDb = 0;

            Object.values(dbData.schemas).forEach(tables => {
                tables.forEach(t => {
                    totalInDb++;
                    const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
                    if (selectedTables.has(key)) selectedInDb++;
                });
            });

            const isFullDb = (totalInDb > 0 && selectedInDb === totalInDb);

            const dbCard = document.createElement('div');
            dbCard.className = 'rm-db-card';
            dbCard.id = 'db-card-' + dbCode;

            // Database Header
            dbCard.innerHTML = `
                <div class="rm-db-head" onclick="toggleDbAccordion('${dbCode}')">
                    <div class="rm-db-title">
                        <div class="rm-db-icon"><i class="fas fa-database"></i></div>
                        <span class="rm-db-name">${escHtml(dbData.dbName)}</span>
                        <span class="rm-db-badge ${isFullDb ? 'full' : ''}" id="db-badge-${dbCode}">
                            ${selectedInDb}/${totalInDb} {{ __('Tabel') }}
                        </span>
                    </div>
                    <div class="rm-db-controls" onclick="event.stopPropagation()">
                        <button type="button" class="rm-btn ${isFullDb ? 'rm-btn-rose' : 'rm-btn-emerald'} rm-btn-sm" onclick="toggleDatabaseAll('${dbCode}', ${!isFullDb})">
                            <i class="fas ${isFullDb ? 'fa-square' : 'fa-check-square'}"></i>
                            ${isFullDb ? '{{ __("Hapus Semua DB") }}' : '{{ __("Pilih Semua DB") }}'}
                        </button>
                        <i class="fas fa-chevron-down rm-db-chevron"></i>
                    </div>
                </div>
                <div class="rm-db-body" id="db-body-${dbCode}"></div>
            `;

            const dbBody = dbCard.querySelector(`#db-body-${dbCode}`);

            // Render Schemas inside this DB
            Object.entries(dbData.schemas).forEach(([schemaName, tables]) => {
                let selectedInSchema = 0;
                tables.forEach(t => {
                    const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
                    if (selectedTables.has(key)) selectedInSchema++;
                });
                const isFullSchema = (tables.length > 0 && selectedInSchema === tables.length);

                const schemaGroup = document.createElement('div');
                schemaGroup.className = 'rm-schema-group';

                schemaGroup.innerHTML = `
                    <div class="rm-schema-head">
                        <div class="rm-schema-title">
                            <i class="fas fa-layer-group"></i>
                            <span>Schema: ${escHtml(schemaName)}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="rm-schema-count">${selectedInSchema}/${tables.length} {{ __('Terpilih') }}</span>
                            <button type="button" class="rm-btn ${isFullSchema ? 'rm-btn-rose' : 'rm-btn-emerald'} rm-btn-sm" style="padding:3px 10px;font-size:0.72rem;"
                                    onclick="toggleSchemaAll('${dbCode}', '${schemaName}', ${!isFullSchema})">
                                ${isFullSchema ? '<i class="fas fa-times"></i> {{ __("Hapus Schema") }}' : '<i class="fas fa-check"></i> {{ __("Pilih Schema") }}'}
                            </button>
                        </div>
                    </div>
                    <div class="rm-table-grid" id="grid-${dbCode}-${schemaName}"></div>
                `;

                const tableGrid = schemaGroup.querySelector(`#grid-${dbCode}-${schemaName}`);

                // Render Table Cards
                tables.forEach(t => {
                    const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
                    const isAllowed = selectedTables.has(key);

                    const typeClass = t.table_type === 'view' ? 'rm-type-view' : (t.table_type === 'materialized_view' ? 'rm-type-mview' : 'rm-type-table');
                    const typeLabel = t.table_type === 'view' ? 'View' : (t.table_type === 'materialized_view' ? 'Mat.View' : 'Table');

                    const tableNameDisplay = highlightSearch(t.table_name, searchTerm);

                    const tableCard = document.createElement('div');
                    tableCard.className = `rm-table-card ${isAllowed ? 'allowed' : ''}`;
                    tableCard.onclick = () => toggleTable(key);

                    tableCard.innerHTML = `
                        <div class="rm-chk">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="rm-table-info">
                            <div class="rm-table-name">
                                <span>${tableNameDisplay}</span>
                                <span class="rm-table-type-badge ${typeClass}">${typeLabel}</span>
                            </div>
                            <div class="rm-table-desc" title="${escHtml(t.description || '')}">
                                ${escHtml(t.description || '{{ __("Tidak ada deskripsi") }}')}
                            </div>
                        </div>
                    `;

                    tableGrid.appendChild(tableCard);
                });

                dbBody.appendChild(schemaGroup);
            });

            treeContainer.appendChild(dbCard);
        });

        updateCountsAndPills();
    }

    /* ══════════════════════════════════════════════════════════
       FILTERING LOGIC
    ══════════════════════════════════════════════════════════ */
    function getFilteredTables() {
        const searchTerm = (tableSearchInput ? tableSearchInput.value : '').toLowerCase().trim();
        const dbFilter = (document.getElementById('dbFilterSelect') ? document.getElementById('dbFilterSelect').value : '');
        const schemaFilter = (document.getElementById('schemaFilterSelect') ? document.getElementById('schemaFilterSelect').value : '');

        if (clearTableSearch) {
            clearTableSearch.style.display = searchTerm ? 'inline' : 'none';
        }

        return allTables.filter(table => {
            const key = `${table.database_code}|${table.schema_name}|${table.table_name}`;
            
            // Search Query match
            const nameMatch = !searchTerm || 
                table.table_name.toLowerCase().includes(searchTerm) ||
                (table.schema_name && table.schema_name.toLowerCase().includes(searchTerm)) ||
                (table.description && table.description.toLowerCase().includes(searchTerm));

            // Database Match
            const dbMatch = !dbFilter || table.database_code === dbFilter;

            // Schema Match
            const schemaMatch = !schemaFilter || table.schema_name === schemaFilter;

            // Status Match (all / allowed / not_allowed)
            let statusMatch = true;
            if (currentStatusFilter === 'allowed') statusMatch = selectedTables.has(key);
            else if (currentStatusFilter === 'not_allowed') statusMatch = !selectedTables.has(key);

            return nameMatch && dbMatch && schemaMatch && statusMatch;
        });
    }

    window.applyFilters = function() {
        const filtered = getFilteredTables();
        renderTree(filtered);
    };

    window.setStatusFilter = function(status, btn) {
        currentStatusFilter = status;
        document.querySelectorAll('.rm-pill-btn').forEach(b => b.classList.remove('active', 'allowed', 'not-allowed'));
        if (btn) {
            btn.classList.add('active');
            if (status === 'allowed') btn.classList.add('allowed');
            if (status === 'not_allowed') btn.classList.add('not-allowed');
        }
        applyFilters();
    };

    window.clearTableSearchInput = function() {
        if (tableSearchInput) {
            tableSearchInput.value = '';
            tableSearchInput.focus();
        }
        applyFilters();
    };

    window.resetAllTableFilters = function() {
        if (tableSearchInput) tableSearchInput.value = '';
        const dbSelect = document.getElementById('dbFilterSelect');
        if (dbSelect) dbSelect.value = '';
        const scSelect = document.getElementById('schemaFilterSelect');
        if (scSelect) scSelect.value = '';
        setStatusFilter('all', document.querySelector('.rm-pill-btn[data-status="all"]'));
    };

    window.handleDbFilterChange = async function(dbCode) {
        const schemaSelect = document.getElementById('schemaFilterSelect');
        schemaSelect.innerHTML = '<option value="">{{ __("Semua Schema") }}</option>';
        
        if (dbCode) {
            try {
                const db = window.allDatabases.find(d => d.database === dbCode);
                if (db) {
                    const response = await fetch(`/admin/databases/${db.id}/schemas`);
                    const data = await response.json();
                    (data.schemas || []).forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s;
                        opt.textContent = s;
                        schemaSelect.appendChild(opt);
                    });
                }
            } catch (e) { console.error(e); }
        }
        
        applyFilters();
    };

    /* ══════════════════════════════════════════════════════════
       ACCORDION HELPERS & BATCH SELECTION
    ══════════════════════════════════════════════════════════ */
    window.toggleDbAccordion = function(dbCode) {
        const card = document.getElementById('db-card-' + dbCode);
        if (card) card.classList.toggle('collapsed');
    };

    window.toggleAllAccordions = function(expand) {
        document.querySelectorAll('.rm-db-card').forEach(card => {
            if (expand) card.classList.remove('collapsed');
            else card.classList.add('collapsed');
        });
    };

    window.toggleDatabaseAll = function(dbCode, selectAll) {
        allTables.forEach(t => {
            if (t.database_code === dbCode) {
                const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
                if (selectAll) selectedTables.add(key);
                else selectedTables.delete(key);
            }
        });
        setHasChanges(checkIfChanged());
        applyFilters();
    };

    window.toggleSchemaAll = function(dbCode, schemaName, selectAll) {
        allTables.forEach(t => {
            if (t.database_code === dbCode && t.schema_name === schemaName) {
                const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
                if (selectAll) selectedTables.add(key);
                else selectedTables.delete(key);
            }
        });
        setHasChanges(checkIfChanged());
        applyFilters();
    };

    window.bulkAction = function(action) {
        const filtered = getFilteredTables();
        filtered.forEach(t => {
            const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
            if (action === 'select') selectedTables.add(key);
            else selectedTables.delete(key);
        });
        setHasChanges(checkIfChanged());
        applyFilters();
    };

    /* ══════════════════════════════════════════════════════════
       ROLE SEARCH ON SIDEBAR
    ══════════════════════════════════════════════════════════ */
    window.filterRoleList = function(query) {
        const q = (query || '').toLowerCase().trim();
        const clearBtn = document.getElementById('clearRoleSearch');
        if (clearBtn) clearBtn.style.display = q ? 'inline' : 'none';

        let count = 0;
        document.querySelectorAll('.rm-role-card').forEach(card => {
            const name = card.dataset.roleName || '';
            const match = !q || name.includes(q);
            card.style.display = match ? 'flex' : 'none';
            if (match) count++;
        });

        const badge = document.getElementById('roleCountBadge');
        if (badge) badge.textContent = count;
    };

    window.clearRoleSearchInput = function() {
        const input = document.getElementById('searchRoleInput');
        if (input) {
            input.value = '';
            filterRoleList('');
            input.focus();
        }
    };

    /* ══════════════════════════════════════════════════════════
       TABS SWITCHING (Permissions vs Members)
    ══════════════════════════════════════════════════════════ */
    window.switchMainTab = function(tab) {
        currentActiveTab = tab;
        const btnPerm = document.getElementById('tabBtnPermissions');
        const btnMemb = document.getElementById('tabBtnMembers');
        const cntPerm = document.getElementById('tabContentPermissions');
        const cntMemb = document.getElementById('tabContentMembers');

        if (tab === 'permissions') {
            btnPerm.classList.add('active');
            btnMemb.classList.remove('active');
            cntPerm.style.display = 'block';
            cntMemb.style.display = 'none';
        } else {
            btnPerm.classList.remove('active');
            btnMemb.classList.add('active');
            cntPerm.style.display = 'none';
            cntMemb.style.display = 'block';
        }
    };

    function renderMembersTab(users) {
        const grid = document.getElementById('membersGrid');
        const empty = document.getElementById('membersEmptyState');
        grid.innerHTML = '';

        if (!users || users.length === 0) {
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';

        users.forEach(u => {
            const initials = (u.name || 'U').substring(0, 2).toUpperCase();
            const card = document.createElement('div');
            card.className = 'rm-user-card';

            card.innerHTML = `
                <div class="rm-user-avatar">${initials}</div>
                <div class="rm-user-info">
                    <div class="rm-user-name">${escHtml(u.name)}</div>
                    <div class="rm-user-email">${escHtml(u.email || '-')}</div>
                </div>
            `;
            grid.appendChild(card);
        });
    }

    /* ══════════════════════════════════════════════════════════
       SAVE PERMISSIONS (AJAX)
    ══════════════════════════════════════════════════════════ */
    window.savePermissions = function() {
        if (!currentRoleId) return;
        const tables = Array.from(selectedTables);

        Swal.fire({
            title: "{{ __('Simpan Hak Akses?') }}",
            text: `{{ __('Anda akan menyimpan hak akses untuk') }} ${tables.length} {{ __('tabel pada role ini.') }}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            confirmButtonText: "{{ __('Ya, Simpan!') }}",
            cancelButtonText: "{{ __('Batal') }}"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                fetch(`/admin/roles/${currentRoleId}/permissions`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ tables })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const role = allRoles.find(r => r.id == currentRoleId);
                        if (role) {
                            role.permissions = tables.map(t => {
                                const parts = t.split('|');
                                return { database_code: parts[0], schema_name: parts[1], table_name: parts[2] };
                            });
                        }
                        originalSelectedTables = new Set(selectedTables);
                        setHasChanges(false);
                        Swal.fire({
                            icon: 'success',
                            title: "{{ __('Berhasil!') }}",
                            text: "{{ __('Hak akses tabel berhasil diperbarui.') }}",
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire("{{ __('Gagal') }}", data.message || "{{ __('Gagal menyimpan hak akses.') }}", 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire("{{ __('Error') }}", "{{ __('Terjadi kesalahan koneksi ke server.') }}", 'error');
                });
            }
        });
    };

    /* Keyboard Shortcut Ctrl + S / Cmd + S to save */
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            if (hasChanges) savePermissions();
        }
    });

    /* ══════════════════════════════════════════════════════════
       ROLE MODALS (Create, Edit, Delete, Clone)
    ══════════════════════════════════════════════════════════ */
    window.showRoleModal = function(type, role = null) {
        const modal = document.getElementById('roleModal');
        const form = document.getElementById('roleForm');
        const method = document.getElementById('roleFormMethod');

        modal.style.display = 'flex';
        if (type === 'create') {
            document.getElementById('roleModalTitle').innerText = "{{ __('Tambah Role Baru') }}";
            form.action = "{{ route('admin.roles.store') }}";
            method.value = 'POST';
            form.reset();
        } else {
            document.getElementById('roleModalTitle').innerText = "{{ __('Edit Info Role') }}";
            form.action = `/admin/roles/${role.id}`;
            method.value = 'PUT';
            document.getElementById('roleNameInput').value = role.name;
            document.getElementById('roleDescInput').value = role.description || '';
        }
    };

    window.closeRoleModal = function() {
        document.getElementById('roleModal').style.display = 'none';
    };

    window.editCurrentRole = function() {
        const role = allRoles.find(r => r.id == currentRoleId);
        if (role) showRoleModal('edit', role);
    };

    window.deleteRole = function(roleId) {
        Swal.fire({
            title: "{{ __('Hapus Role Ini?') }}",
            text: "{{ __('Seluruh hak akses tabel terkait role ini akan ikut dihapus permanen.') }}",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: "{{ __('Ya, Hapus') }}",
            cancelButtonText: "{{ __('Batal') }}"
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/admin/roles/${roleId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        Swal.fire("{{ __('Gagal') }}", data.message || "{{ __('Gagal menghapus role.') }}", 'error');
                    }
                });
            }
        });
    };

    /* ── Clone / Duplicate Role ────────────────────────────── */
    window.duplicateRole = function(roleId) {
        const role = allRoles.find(r => r.id == roleId);
        if (!role) return;

        document.getElementById('cloneSourceRoleId').value = role.id;
        document.getElementById('cloneRoleNameInput').value = role.name + " (Salinan)";
        document.getElementById('cloneRoleDescInput').value = role.description ? (role.description + " [Salinan]") : '';
        document.getElementById('cloneModal').style.display = 'flex';
    };

    window.duplicateCurrentRole = function() {
        if (currentRoleId) duplicateRole(currentRoleId);
    };

    window.closeCloneModal = function() {
        document.getElementById('cloneModal').style.display = 'none';
    };

    window.handleCloneSubmit = function(e) {
        e.preventDefault();
        const sourceRoleId = document.getElementById('cloneSourceRoleId').value;
        const newName = document.getElementById('cloneRoleNameInput').value;
        const newDesc = document.getElementById('cloneRoleDescInput').value;

        const sourceRole = allRoles.find(r => r.id == sourceRoleId);
        if (!sourceRole) return;

        const btn = document.getElementById('btnSubmitClone');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ __("Menduplikat...") }}';

        // 1. Create new role
        fetch("{{ route('admin.roles.store') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                name: newName,
                description: newDesc
            })
        })
        .then(r => r.json().catch(() => ({ success: true })))
        .then(() => {
            // Reload page to get fresh IDs & sync
            location.reload();
        })
        .catch(err => {
            console.error(err);
            location.reload();
        });
    };

    /* ══════════════════════════════════════════════════════════
       UTILITIES
    ══════════════════════════════════════════════════════════ */
    function escHtml(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function highlightSearch(text, term) {
        if (!term) return escHtml(text);
        const escaped = escHtml(text);
        const regex = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return escaped.replace(regex, '<mark style="background:rgba(245,158,11,0.25);color:inherit;padding:0 2px;border-radius:3px;">$1</mark>');
    }

    init();
});
</script>
@endsection