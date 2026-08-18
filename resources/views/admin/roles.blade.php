@extends('layouts.admin')
@section('page-title', __('Management Role & Permissions'))

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     ROLE & PERMISSIONS MANAGEMENT — Modern Enterprise Redesign v3
══════════════════════════════════════════════════════════════ --}}

<style>
/* ── Design System Variables ────────────────────────── */
:root {
    --rm-primary:      #6366f1;
    --rm-primary-dark: #4f46e5;
    --rm-primary-soft: rgba(99,102,241,0.1);
    --rm-emerald:      #10b981;
    --rm-emerald-dark: #059669;
    --rm-emerald-soft: rgba(16,185,129,0.1);
    --rm-rose:         #ef4444;
    --rm-rose-dark:    #dc2626;
    --rm-rose-soft:    rgba(239,68,68,0.1);
    --rm-amber:        #f59e0b;
    --rm-amber-soft:   rgba(245,158,11,0.1);
    --rm-cyan:         #06b6d4;
    --rm-cyan-soft:    rgba(6,182,212,0.1);
    --rm-purple:       #8b5cf6;
    --rm-purple-soft:  rgba(139,92,246,0.1);
    
    --rm-bg-surface:   #ffffff;
    --rm-bg-subtle:    #f8fafc;
    --rm-bg-hover:     #f1f5f9;
    --rm-border:       #e2e8f0;
    --rm-border-focus: #cbd5e1;
    --rm-text-main:    #0f172a;
    --rm-text-muted:   #64748b;
    --rm-text-dim:     #94a3b8;
    --rm-radius-lg:    14px;
    --rm-radius-md:    10px;
    --rm-radius-sm:    6px;
    --rm-font-mono:    'JetBrains Mono', 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

html.dark {
    --rm-bg-surface:   #0f172a;
    --rm-bg-subtle:    #1e293b;
    --rm-bg-hover:     #334155;
    --rm-border:       #334155;
    --rm-border-focus: #475569;
    --rm-text-main:    #f8fafc;
    --rm-text-muted:   #94a3b8;
    --rm-text-dim:     #64748b;
}

/* ── Top Bar / Header ────────────────────────────────── */
.rm-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;
}
.rm-header-left {
    display: flex; align-items: center; gap: 12px;
}
.rm-header-icon {
    width: 42px; height: 42px; border-radius: 12px;
    background: linear-gradient(135deg, var(--rm-primary), var(--rm-purple));
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 1.2rem;
    box-shadow: 0 4px 12px rgba(99,102,241,0.25);
}
.rm-header-title h1 {
    font-size: 1.25rem; font-weight: 700; color: var(--rm-text-main); margin: 0;
    letter-spacing: -0.01em;
}
.rm-header-title p {
    font-size: 0.8rem; color: var(--rm-text-muted); margin: 2px 0 0;
}

/* ── Main 2-Panel Layout ────────────────────────────── */
.rm-layout {
    display: grid;
    grid-template-columns: 310px 1fr;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 1080px) {
    .rm-layout { grid-template-columns: 1fr; }
}

/* ── Left Sidebar (Role Navigator) ──────────────────── */
.rm-sidebar {
    background: var(--rm-bg-surface);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-lg);
    padding: 1rem;
    position: sticky;
    top: 20px;
    display: flex; flex-direction: column; gap: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.rm-sidebar-top {
    display: flex; align-items: center; justify-content: space-between;
}
.rm-sidebar-title {
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--rm-text-muted);
    display: flex; align-items: center; gap: 6px;
}
.rm-role-search-box {
    position: relative; display: flex; align-items: center;
}
.rm-role-search-box input {
    width: 100%;
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    padding: 7px 28px 7px 30px;
    border-radius: var(--rm-radius-md);
    color: var(--rm-text-main);
    font-size: 0.8rem;
    font-family: inherit;
    outline: none; transition: all .2s;
}
.rm-role-search-box input:focus {
    border-color: var(--rm-primary);
    background: var(--rm-bg-surface);
    box-shadow: 0 0 0 3px var(--rm-primary-soft);
}
.rm-role-search-box .ico {
    position: absolute; left: 10px; color: var(--rm-text-dim);
    font-size: 0.75rem; pointer-events: none;
}
.rm-role-search-box .clear-btn {
    position: absolute; right: 8px; color: var(--rm-text-dim);
    font-size: 0.75rem; cursor: pointer; display: none;
}

.rm-role-list {
    display: flex; flex-direction: column; gap: 6px;
    max-height: calc(100vh - 240px);
    overflow-y: auto;
    padding-right: 2px;
    scrollbar-width: thin;
}
.rm-role-list::-webkit-scrollbar { width: 4px; }
.rm-role-list::-webkit-scrollbar-thumb { background: var(--rm-border); border-radius: 4px; }

/* Role Card Navigation Item */
.rm-role-card {
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-md);
    padding: 10px 12px;
    cursor: pointer;
    transition: all .15s ease;
    position: relative;
    overflow: hidden;
    display: flex; flex-direction: column; gap: 6px;
    text-align: left;
}
.rm-role-card:hover {
    background: var(--rm-bg-hover);
    border-color: var(--rm-border-focus);
    transform: translateY(-1px);
}
.rm-role-card.active {
    background: rgba(99,102,241,0.06);
    border-color: var(--rm-primary);
    box-shadow: 0 2px 10px rgba(99,102,241,0.1);
}
.rm-role-card.active::before {
    content: ''; position: absolute;
    top: 0; left: 0; bottom: 0; width: 3px;
    background: var(--rm-primary);
}

.rm-rc-head {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.rm-rc-name {
    font-size: 0.85rem; font-weight: 700; color: var(--rm-text-main);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: flex; align-items: center; gap: 6px;
}
.rm-rc-actions {
    display: flex; align-items: center; gap: 3px; opacity: 0; transition: opacity .15s;
}
.rm-role-card:hover .rm-rc-actions { opacity: 1; }

.rm-rc-btn {
    width: 22px; height: 22px; border-radius: 5px;
    border: 1px solid var(--rm-border);
    background: var(--rm-bg-surface); color: var(--rm-text-muted);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.68rem; cursor: pointer; transition: all .15s;
}
.rm-rc-btn.edit:hover { background: var(--rm-amber); color: white; border-color: var(--rm-amber); }
.rm-rc-btn.clone:hover { background: var(--rm-cyan); color: white; border-color: var(--rm-cyan); }
.rm-rc-btn.del:hover { background: var(--rm-rose); color: white; border-color: var(--rm-rose); }

.rm-rc-desc {
    font-size: 0.72rem; color: var(--rm-text-muted);
    line-height: 1.3;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rm-rc-meta {
    display: flex; align-items: center; justify-content: space-between; gap: 6px;
    font-size: 0.68rem; color: var(--rm-text-muted);
}
.rm-rc-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 1px 6px; border-radius: 4px;
    font-size: 0.65rem; font-weight: 600;
    background: var(--rm-bg-surface); border: 1px solid var(--rm-border);
}
.rm-rc-pill.users { color: var(--rm-primary); }
.rm-rc-pill.tables { color: var(--rm-emerald); }

.rm-rc-creator {
    font-size: 0.65rem; color: var(--rm-text-dim);
    display: flex; align-items: center; gap: 4px;
}

/* ── Right Panel (Workspace Canvas) ──────────────────── */
.rm-main {
    display: flex; flex-direction: column; gap: 1rem;
}

/* Compact Role Control Bar (Header) */
.rm-workspace-header {
    background: var(--rm-bg-surface);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-lg);
    padding: 1rem 1.25rem;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.rm-wh-info {
    display: flex; align-items: center; gap: 12px; min-width: 0;
}
.rm-wh-avatar {
    width: 42px; height: 42px; border-radius: 10px;
    background: var(--rm-primary-soft);
    border: 1px solid rgba(99,102,241,0.2);
    color: var(--rm-primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}
.rm-wh-text h2 {
    font-size: 1.15rem; font-weight: 700; color: var(--rm-text-main); margin: 0;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.rm-wh-text p {
    font-size: 0.78rem; color: var(--rm-text-muted); margin: 2px 0 0;
}
.rm-wh-badges {
    display: flex; align-items: center; gap: 6px; margin-top: 5px; flex-wrap: wrap;
}
.rm-wh-badge {
    font-size: 0.68rem; font-weight: 600; padding: 2px 7px; border-radius: 5px;
    background: var(--rm-bg-subtle); border: 1px solid var(--rm-border);
    color: var(--rm-text-muted); display: inline-flex; align-items: center; gap: 4px;
}
.rm-wh-badge.highlight {
    background: var(--rm-emerald-soft); border-color: rgba(16,185,129,0.25); color: var(--rm-emerald-dark);
}
html.dark .rm-wh-badge.highlight { color: var(--rm-emerald); }

.rm-wh-actions {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}

/* ── Soft Harmonious Action Buttons ──────────────────── */
.rm-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: var(--rm-radius-sm);
    font-size: 0.78rem; font-weight: 600;
    cursor: pointer; transition: all .15s ease; border: 1px solid transparent;
    font-family: inherit; text-decoration: none; user-select: none;
}
.rm-btn-sm {
    padding: 4px 9px; font-size: 0.72rem; border-radius: 5px;
}

/* Primary CTA Button */
.rm-btn-primary {
    background: linear-gradient(135deg, var(--rm-primary), var(--rm-primary-dark));
    color: white !important;
    border: 1px solid var(--rm-primary-dark) !important;
    box-shadow: 0 2px 6px rgba(99,102,241,0.25);
}
.rm-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99,102,241,0.35);
}

/* Soft Color Buttons */
.rm-btn-emerald {
    background: var(--rm-emerald-soft) !important;
    color: var(--rm-emerald-dark) !important;
    border-color: rgba(16,185,129,0.25) !important;
}
.rm-btn-emerald:hover {
    background: var(--rm-emerald) !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(16,185,129,0.25);
}
html.dark .rm-btn-emerald { color: #34d399 !important; }
html.dark .rm-btn-emerald:hover { color: white !important; }

.rm-btn-rose {
    background: var(--rm-rose-soft) !important;
    color: var(--rm-rose-dark) !important;
    border-color: rgba(239,68,68,0.25) !important;
}
.rm-btn-rose:hover {
    background: var(--rm-rose) !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(239,68,68,0.25);
}
html.dark .rm-btn-rose { color: #f87171 !important; }
html.dark .rm-btn-rose:hover { color: white !important; }

.rm-btn-indigo {
    background: var(--rm-primary-soft) !important;
    color: var(--rm-primary-dark) !important;
    border-color: rgba(99,102,241,0.25) !important;
}
.rm-btn-indigo:hover {
    background: var(--rm-primary) !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(99,102,241,0.25);
}
html.dark .rm-btn-indigo { color: #a5b4fc !important; }
html.dark .rm-btn-indigo:hover { color: white !important; }

.rm-btn-purple {
    background: var(--rm-purple-soft) !important;
    color: #7c3aed !important;
    border-color: rgba(139,92,246,0.25) !important;
}
.rm-btn-purple:hover {
    background: var(--rm-purple) !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(139,92,246,0.25);
}
html.dark .rm-btn-purple { color: #c4b5fd !important; }
html.dark .rm-btn-purple:hover { color: white !important; }

.rm-btn-cyan {
    background: var(--rm-cyan-soft) !important;
    color: #0891b2 !important;
    border-color: rgba(6,182,212,0.25) !important;
}
.rm-btn-cyan:hover {
    background: var(--rm-cyan) !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(6,182,212,0.25);
}
html.dark .rm-btn-cyan { color: #67e8f9 !important; }
html.dark .rm-btn-cyan:hover { color: white !important; }

.rm-btn-amber {
    background: var(--rm-amber-soft) !important;
    color: #d97706 !important;
    border-color: rgba(245,158,11,0.25) !important;
}
.rm-btn-amber:hover {
    background: var(--rm-amber) !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(245,158,11,0.25);
}
html.dark .rm-btn-amber { color: #fde68a !important; }
html.dark .rm-btn-amber:hover { color: white !important; }

.rm-btn-subtle {
    background: var(--rm-bg-subtle) !important;
    color: var(--rm-text-muted) !important;
    border-color: var(--rm-border) !important;
}
.rm-btn-subtle:hover {
    background: var(--rm-bg-hover) !important;
    color: var(--rm-text-main) !important;
    border-color: var(--rm-border-focus) !important;
}

/* ── Tabs Navigation ─────────────────────────────────── */
.rm-tabs {
    display: flex; gap: 6px; border-bottom: 1px solid var(--rm-border);
    padding-bottom: 2px;
}
.rm-tab-btn {
    background: none; border: none;
    padding: 7px 14px; font-size: 0.82rem; font-weight: 600;
    color: var(--rm-text-muted); cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px;
    font-family: inherit; margin-bottom: -1px;
}
.rm-tab-btn:hover { color: var(--rm-text-main); }
.rm-tab-btn.active {
    color: var(--rm-primary); border-bottom-color: var(--rm-primary);
}
.rm-tab-badge {
    font-size: 0.65rem; font-weight: 700; padding: 1px 6px; border-radius: 10px;
    background: var(--rm-primary-soft); color: var(--rm-primary);
}

/* ── Unified Single-Row Workspace Toolbar ────────────── */
.rm-toolbar {
    background: var(--rm-bg-surface);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-md);
    padding: 8px 12px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.rm-tb-left {
    display: flex; align-items: center; gap: 8px; flex: 1; min-width: 280px; flex-wrap: wrap;
}
.rm-tb-right {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}

.rm-input-wrap {
    position: relative; display: flex; align-items: center; min-width: 140px;
}
.rm-input-wrap.search { flex: 1.5; min-width: 200px; }
.rm-input-wrap .ico {
    position: absolute; left: 9px; color: var(--rm-text-dim);
    font-size: 0.75rem; pointer-events: none;
}
.rm-input-wrap .clear-btn {
    position: absolute; right: 8px; color: var(--rm-text-dim);
    font-size: 0.72rem; cursor: pointer; background: none; border: none;
    padding: 2px; display: none;
}
.rm-input-wrap input, .rm-input-wrap select {
    width: 100%;
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    padding: 6px 26px 6px 28px;
    border-radius: var(--rm-radius-sm);
    color: var(--rm-text-main);
    font-size: 0.78rem;
    font-family: inherit;
    outline: none; transition: all .15s;
    height: 32px;
}
.rm-input-wrap select { cursor: pointer; padding-right: 12px; }
.rm-input-wrap input:focus, .rm-input-wrap select:focus {
    border-color: var(--rm-primary);
    background: var(--rm-bg-surface);
    box-shadow: 0 0 0 2px var(--rm-primary-soft);
}

/* Status Filter Segmented Control */
.rm-segmented-pills {
    display: inline-flex; align-items: center;
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-sm);
    padding: 2px; gap: 2px;
}
.rm-seg-btn {
    background: none; border: none;
    padding: 4px 9px; font-size: 0.72rem; font-weight: 600;
    color: var(--rm-text-muted); cursor: pointer;
    border-radius: 4px; transition: all .15s; font-family: inherit;
    display: inline-flex; align-items: center; gap: 4px;
}
.rm-seg-btn:hover { color: var(--rm-text-main); }
.rm-seg-btn.active {
    background: var(--rm-bg-surface);
    color: var(--rm-primary);
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.rm-seg-btn.active.allowed { color: var(--rm-emerald); }
.rm-seg-btn.active.not-allowed { color: var(--rm-rose); }

/* ── Structured Permissions Table Tree ───────────────── */
.rm-table-tree {
    display: flex; flex-direction: column; gap: 10px;
}

/* Database Group Container */
.rm-db-section {
    background: var(--rm-bg-surface);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-md);
    overflow: hidden;
    transition: all .15s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}

.rm-db-header-bar {
    padding: 8px 14px;
    background: var(--rm-bg-subtle);
    border-bottom: 1px solid var(--rm-border);
    display: flex; align-items: center; justify-content: space-between;
    cursor: pointer; user-select: none;
    transition: background .15s;
}
.rm-db-header-bar:hover { background: var(--rm-bg-hover); }

.rm-db-header-left {
    display: flex; align-items: center; gap: 8px;
}
.rm-db-header-icon {
    width: 26px; height: 26px; border-radius: 6px;
    background: var(--rm-primary-soft);
    color: var(--rm-primary); display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; border: 1px solid rgba(99,102,241,0.2);
}
.rm-db-title-text {
    font-size: 0.85rem; font-weight: 700; color: var(--rm-text-main);
}
.rm-db-stat-badge {
    font-size: 0.68rem; font-weight: 600; padding: 1px 6px; border-radius: 4px;
    background: var(--rm-bg-surface); border: 1px solid var(--rm-border);
    color: var(--rm-text-muted); font-variant-numeric: tabular-nums;
}
.rm-db-stat-badge.full {
    background: var(--rm-emerald-soft); border-color: rgba(16,185,129,0.25); color: var(--rm-emerald-dark);
}
html.dark .rm-db-stat-badge.full { color: var(--rm-emerald); }

.rm-db-header-right {
    display: flex; align-items: center; gap: 8px;
}
.rm-db-toggle-ico {
    color: var(--rm-text-dim); transition: transform .2s ease;
    font-size: 0.75rem;
}
.rm-db-section.collapsed .rm-db-toggle-ico {
    transform: rotate(-90deg);
}
.rm-db-section.collapsed .rm-db-content-area {
    display: none;
}

.rm-db-content-area {
    padding: 0;
}

/* Structured Table View */
.rm-perm-table {
    width: 100%; border-collapse: collapse; text-align: left;
    font-size: 0.8rem;
}
.rm-perm-table th {
    background: var(--rm-bg-subtle);
    padding: 6px 12px; font-size: 0.7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
    color: var(--rm-text-muted); border-bottom: 1px solid var(--rm-border);
}
.rm-perm-table td {
    padding: 6px 12px; border-bottom: 1px solid var(--rm-border);
    color: var(--rm-text-main); vertical-align: middle;
}
.rm-perm-table tr:last-child td { border-bottom: none; }

/* Schema Subheader Row */
.rm-schema-row td {
    background: rgba(0,0,0,0.015);
    padding: 5px 12px; font-size: 0.72rem; font-weight: 700;
    color: var(--rm-text-muted); text-transform: uppercase; letter-spacing: 0.03em;
    border-top: 1px solid var(--rm-border);
}
html.dark .rm-schema-row td { background: rgba(255,255,255,0.02); }

/* Table Item Row */
.rm-row-item {
    cursor: pointer; transition: background .12s;
}
.rm-row-item:hover {
    background: var(--rm-bg-hover);
}
.rm-row-item.allowed {
    background: rgba(16,185,129,0.04);
}
.rm-row-item.allowed:hover {
    background: rgba(16,185,129,0.08);
}

.rm-tbl-name {
    font-family: var(--rm-font-mono);
    font-size: 0.8rem; font-weight: 600; color: var(--rm-text-main);
    display: flex; align-items: center; gap: 7px;
}
.rm-tbl-name i { color: var(--rm-text-dim); font-size: 0.72rem; }
.rm-row-item.allowed .rm-tbl-name i { color: var(--rm-emerald); }

/* Type Tag */
.rm-tag {
    font-size: 0.62rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;
    display: inline-block; text-transform: uppercase; letter-spacing: 0.03em;
}
.rm-tag.table { background: var(--rm-primary-soft); color: var(--rm-primary); }
.rm-tag.view  { background: var(--rm-cyan-soft); color: var(--rm-cyan); }
.rm-tag.matview { background: var(--rm-amber-soft); color: var(--rm-amber); }

/* Custom Checkbox / Toggle */
.rm-check-box {
    width: 17px; height: 17px; border-radius: 4px;
    border: 1.5px solid var(--rm-border-focus);
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 0.65rem; transition: all .12s;
    background: var(--rm-bg-surface);
}
.rm-row-item.allowed .rm-check-box {
    background: var(--rm-emerald);
    border-color: var(--rm-emerald);
}
.rm-check-box i { opacity: 0; transform: scale(0.5); transition: all .12s; }
.rm-row-item.allowed .rm-check-box i { opacity: 1; transform: scale(1); }

/* Status Toggle Badge */
.rm-status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.7rem; font-weight: 600; padding: 2px 8px; border-radius: 4px;
    background: var(--rm-bg-subtle); color: var(--rm-text-dim);
    border: 1px solid var(--rm-border);
}
.rm-row-item.allowed .rm-status-badge {
    background: var(--rm-emerald-soft); color: var(--rm-emerald-dark);
    border-color: rgba(16,185,129,0.25);
}
html.dark .rm-row-item.allowed .rm-status-badge { color: var(--rm-emerald); }

/* ── Empty State ─────────────────────────────────────── */
.rm-empty-state {
    text-align: center; padding: 2.5rem 1rem;
    background: var(--rm-bg-surface); border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-md);
}
.rm-empty-state i {
    font-size: 2rem; color: var(--rm-text-dim); margin-bottom: 0.75rem;
}
.rm-empty-state h4 {
    font-size: 0.95rem; font-weight: 700; color: var(--rm-text-main); margin: 0 0 4px;
}
.rm-empty-state p {
    font-size: 0.78rem; color: var(--rm-text-muted); margin: 0 0 1rem;
}

/* ── Members Grid (Tab 2) ────────────────────────────── */
.rm-members-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 10px;
}
.rm-user-card {
    background: var(--rm-bg-surface); border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-md); padding: 10px 12px;
    display: flex; align-items: center; gap: 10px;
}
.rm-user-card-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--rm-primary-soft); color: var(--rm-primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.8rem;
}
.rm-user-card-info h5 {
    font-size: 0.82rem; font-weight: 700; color: var(--rm-text-main); margin: 0;
}
.rm-user-card-info span {
    font-size: 0.72rem; color: var(--rm-text-muted);
}

/* ── Floating Sticky Save Bar ────────────────────────── */
.rm-floating-dock {
    position: fixed; bottom: 24px; left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: rgba(15, 23, 42, 0.94);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 30px;
    padding: 7px 12px 7px 18px;
    display: flex; align-items: center; gap: 14px;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35);
    z-index: 1000;
    transition: transform .25s cubic-bezier(0.4, 0, 0.2, 1), opacity .25s ease;
    opacity: 0; pointer-events: none;
}
.rm-floating-dock.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1; pointer-events: auto;
}
.rm-dock-msg {
    font-size: 0.78rem; font-weight: 600; color: #f1f5f9;
    display: flex; align-items: center; gap: 8px;
}
.rm-dock-msg i { color: var(--rm-amber); }
.rm-dock-actions {
    display: flex; align-items: center; gap: 6px;
}
.rm-dock-btn-save {
    background: var(--rm-emerald); color: white; border: 1px solid var(--rm-emerald-dark);
    padding: 5px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600;
    cursor: pointer; display: flex; align-items: center; gap: 6px;
    transition: all .15s; box-shadow: 0 2px 8px rgba(16,185,129,0.3);
}
.rm-dock-btn-save:hover { background: var(--rm-emerald-dark); transform: translateY(-1px); }

/* ── Modals ──────────────────────────────────────────── */
.rm-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
    z-index: 1100; padding: 1rem;
}
.rm-modal-overlay.open { display: flex; }
.rm-modal-box {
    background: var(--rm-bg-surface);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-lg);
    width: 100%; max-width: 480px;
    padding: 1.25rem;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    animation: rmModalIn .2s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes rmModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(-10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.rm-modal-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1rem; padding-bottom: 8px; border-bottom: 1px solid var(--rm-border);
}
.rm-modal-head h3 { font-size: 1rem; font-weight: 700; color: var(--rm-text-main); margin: 0; }
.rm-modal-close { background: none; border: none; color: var(--rm-text-dim); cursor: pointer; font-size: 0.9rem; }
.rm-modal-close:hover { color: var(--rm-rose); }
.rm-form-grp { margin-bottom: 0.85rem; }
.rm-form-grp label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--rm-text-muted); margin-bottom: 4px; }
.rm-form-grp input, .rm-form-grp textarea {
    width: 100%; background: var(--rm-bg-subtle); border: 1px solid var(--rm-border);
    padding: 7px 10px; border-radius: var(--rm-radius-sm); color: var(--rm-text-main);
    font-size: 0.82rem; font-family: inherit; outline: none; transition: all .15s;
}
.rm-form-grp input:focus, .rm-form-grp textarea:focus {
    border-color: var(--rm-primary); background: var(--rm-bg-surface);
    box-shadow: 0 0 0 2px var(--rm-primary-soft);
}
.rm-modal-foot {
    display: flex; align-items: center; justify-content: flex-end; gap: 8px;
    margin-top: 1.25rem; pt: 8px; border-top: 1px solid var(--rm-border);
}
</style>

{{-- ── TOP HEADER ─────────────────────────────────────────────── --}}
<div class="rm-header">
    <div class="rm-header-left">
        <div class="rm-header-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="rm-header-title">
            <h1>{{ __('Management Role & Hak Akses') }}</h1>
            <p>{{ __('Atur wewenang akses database, schema, dan tabel per peran pengguna') }}</p>
        </div>
    </div>
    <div class="rm-header-right">
        <button class="rm-btn rm-btn-primary" type="button" onclick="showRoleModal('create')">
            <i class="fas fa-plus"></i> {{ __('Tambah Role Baru') }}
        </button>
    </div>
</div>

{{-- ── MAIN 2-PANEL LAYOUT ────────────────────────────────────── --}}
<div class="rm-layout">

    {{-- ── LEFT PANEL: Role Navigator ─────────────────────────── --}}
    <div class="rm-sidebar">
        <div class="rm-sidebar-top">
            <div class="rm-sidebar-title">
                <i class="fas fa-layer-group"></i> {{ __('Daftar Role') }} (<span id="roleCountBadge">{{ $roles->count() }}</span>)
            </div>
        </div>

        {{-- Role Search Input --}}
        <div class="rm-role-search-box">
            <i class="fas fa-search ico"></i>
            <input type="text" id="searchRoleInput" placeholder="{{ __('Cari role...') }}" oninput="filterRoleList(this.value)">
            <i class="fas fa-times clear-btn" id="clearRoleSearch" onclick="clearRoleSearchInput()"></i>
        </div>

        {{-- Role Cards List --}}
        <div class="rm-role-list" id="roleList">
            @foreach($roles as $role)
            @php
                $userCount = $role->users_count ?? ($role->users ? $role->users->count() : 0);
                $permCount = $role->permissions ? $role->permissions->count() : 0;
                $totalTbl  = count($allTables);
            @endphp
            <div class="rm-role-card {{ $loop->first ? 'active' : '' }}"
                 id="role-card-{{ $role->id }}"
                 data-role-id="{{ $role->id }}"
                 data-role-name="{{ strtolower($role->name) }}"
                 onclick="selectRole({{ $role->id }}, this)">
                
                <div class="rm-rc-head">
                    <div class="rm-rc-name" title="{{ $role->name }}">
                        <i class="fas fa-user-shield" style="color:var(--rm-primary);font-size:0.8rem"></i>
                        <span>{{ $role->name }}</span>
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

                <div class="rm-rc-meta">
                    <span class="rm-rc-pill users"><i class="fas fa-users"></i> {{ $userCount }} User</span>
                    <span class="rm-rc-pill tables" id="rc-perm-count-{{ $role->id }}"><i class="fas fa-table"></i> {{ $permCount }}/{{ $totalTbl }} Tabel</span>
                </div>

                <div class="rm-rc-creator">
                    <i class="fas fa-user-edit"></i> {{ $role->addedBy->name ?? 'System' }} &bull; {{ $role->created_at ? $role->created_at->format('d M Y') : '-' }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── RIGHT PANEL: Permissions & Workspace Canvas ────────── --}}
    <div class="rm-main">

        {{-- Compact Role Control Bar --}}
        <div class="rm-workspace-header">
            <div class="rm-wh-info">
                <div class="rm-wh-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="rm-wh-text">
                    <h2 id="heroRoleName">{{ $roles[0]->name ?? __('Pilih Role') }}</h2>
                    <p id="heroRoleDesc">{{ $roles[0]->description ?? __('Pilih salah satu role di panel kiri untuk mengatur hak akses.') }}</p>
                    <div class="rm-wh-badges">
                        <span class="rm-wh-badge highlight" id="heroPermChip"><i class="fas fa-check-circle"></i> <strong id="heroPermCount">0</strong> {{ __('Tabel Diizinkan') }}</span>
                        <span class="rm-wh-badge" id="heroUserChip"><i class="fas fa-users"></i> <strong id="heroUserCount">0</strong> {{ __('Pengguna') }}</span>
                        <span class="rm-wh-badge" id="heroDbChip"><i class="fas fa-database"></i> <strong id="heroDbCount">0</strong> {{ __('Database') }}</span>
                        <span class="rm-wh-badge" id="heroCreatorChip"><i class="fas fa-user-edit"></i> <span id="heroCreatedBy">{{ $roles[0]->addedBy->name ?? 'System' }}</span> &bull; <span id="heroCreatedAt">{{ $roles[0]->created_at ? $roles[0]->created_at->format('d M Y') : '-' }}</span></span>
                    </div>
                </div>
            </div>
            <div class="rm-wh-actions">
                <button type="button" class="rm-btn rm-btn-cyan" onclick="duplicateCurrentRole()">
                    <i class="fas fa-copy"></i> {{ __('Duplikat') }}
                </button>
                <button type="button" class="rm-btn rm-btn-amber" onclick="editCurrentRole()">
                    <i class="fas fa-edit"></i> {{ __('Edit Info') }}
                </button>
                <button type="button" class="rm-btn rm-btn-primary" onclick="savePermissions()">
                    <i class="fas fa-save"></i> <span>{{ __('Simpan Akses') }}</span>
                </button>
            </div>
        </div>

        {{-- Tabs Navigation --}}
        <div class="rm-tabs">
            <button type="button" class="rm-tab-btn active" id="tabBtnPermissions" onclick="switchMainTab('permissions')">
                <i class="fas fa-table"></i> {{ __('Hak Akses Tabel & View') }}
            </button>
            <button type="button" class="rm-tab-btn" id="tabBtnMembers" onclick="switchMainTab('members')">
                <i class="fas fa-users"></i> {{ __('Daftar Pengguna') }}
                <span class="rm-tab-badge" id="tabMemberBadge">0</span>
            </button>
        </div>

        {{-- TAB 1: PERMISSIONS CONTENT --}}
        <div id="tabContentPermissions">
            {{-- Unified Single-Row Workspace Toolbar --}}
            <div class="rm-toolbar">
                <div class="rm-tb-left">
                    {{-- Search Table / Schema --}}
                    <div class="rm-input-wrap search">
                        <i class="fas fa-search ico"></i>
                        <input type="text" id="tableSearchInput" placeholder="{{ __('Cari nama tabel atau schema...') }}" oninput="applyFilters()">
                        <button type="button" class="clear-btn" id="clearTableSearch" onclick="clearTableSearchInput()"><i class="fas fa-times"></i></button>
                    </div>

                    {{-- Database Filter Dropdown --}}
                    <div class="rm-input-wrap">
                        <i class="fas fa-database ico"></i>
                        <select id="dbFilterSelect" onchange="handleDbFilterChange(this.value)">
                            <option value="">{{ __('Semua DB') }}</option>
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

                    {{-- Status Filter Segmented Control --}}
                    <div class="rm-segmented-pills">
                        <button type="button" class="rm-seg-btn active" data-status="all" onclick="setStatusFilter('all', this)">
                            {{ __('Semua') }} (<span id="pillCountAll">0</span>)
                        </button>
                        <button type="button" class="rm-seg-btn" data-status="allowed" onclick="setStatusFilter('allowed', this)">
                            <i class="fas fa-check" style="font-size:.65rem"></i> {{ __('Diizinkan') }} (<span id="pillCountAllowed">0</span>)
                        </button>
                        <button type="button" class="rm-seg-btn" data-status="not_allowed" onclick="setStatusFilter('not_allowed', this)">
                            <i class="fas fa-times" style="font-size:.65rem"></i> {{ __('Belum') }} (<span id="pillCountNotAllowed">0</span>)
                        </button>
                    </div>
                </div>

                <div class="rm-tb-right">
                    {{-- Bulk Actions & Accordion Controls --}}
                    <button type="button" class="rm-btn rm-btn-emerald rm-btn-sm" onclick="bulkAction('select')" title="{{ __('Pilih semua tabel yang tampil') }}">
                        <i class="fas fa-check-square"></i> {{ __('Pilih Semua') }}
                    </button>
                    <button type="button" class="rm-btn rm-btn-rose rm-btn-sm" onclick="bulkAction('deselect')" title="{{ __('Hapus semua tabel yang tampil') }}">
                        <i class="fas fa-square"></i> {{ __('Hapus Semua') }}
                    </button>
                    <button type="button" class="rm-btn rm-btn-indigo rm-btn-sm" onclick="toggleAllAccordions(true)" title="{{ __('Buka semua accordion') }}">
                        <i class="fas fa-folder-open"></i> {{ __('Buka') }}
                    </button>
                    <button type="button" class="rm-btn rm-btn-purple rm-btn-sm" onclick="toggleAllAccordions(false)" title="{{ __('Ciutkan semua accordion') }}">
                        <i class="fas fa-folder"></i> {{ __('Tutup') }}
                    </button>
                </div>
            </div>

            {{-- Structured Permissions Table Tree --}}
            <div class="rm-table-tree" id="treeContainer" style="margin-top: 0.75rem;">
                {{-- Rendered dynamically by JS --}}
            </div>

            {{-- Empty State (Search / Filter Not Found) --}}
            <div class="rm-empty-state" id="tableEmptyState" style="display: none; margin-top: 0.75rem;">
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
            <div class="rm-members-grid" id="membersGrid">
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
        <button type="button" class="rm-btn rm-btn-rose-subtle rm-btn-sm" onclick="discardChanges()">
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
                <button type="button" class="rm-btn rm-btn-rose-subtle" onclick="closeRoleModal()"><i class="fas fa-times"></i> {{ __('Batal') }}</button>
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
            <p style="font-size:0.78rem;color:var(--rm-text-muted);margin:0 0 0.85rem;">
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
                <button type="button" class="rm-btn rm-btn-rose-subtle" onclick="closeCloneModal()"><i class="fas fa-times"></i> {{ __('Batal') }}</button>
                <button type="submit" class="rm-btn rm-btn-cyan" id="btnSubmitClone">
                    <i class="fas fa-clone"></i> {{ __('Duplikat & Buat') }}
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    /* ══════════════════════════════════════════════════════════
       DATA INITIALIZATION
    ══════════════════════════════════════════════════════════ */
    const allRoles   = @json($roles);
    const allTables  = @json($allTables);
    const allDbs     = @json($databases);

    let currentRoleId          = {{ $roles->first()->id ?? 'null' }};
    let selectedTables         = new Set();
    let originalSelectedTables = new Set();
    let currentStatusFilter    = 'all'; // 'all' | 'allowed' | 'not_allowed'
    let hasChanges             = false;

    // Group tables by database_code -> schema_name -> [table objects]
    const groupedTables = {};
    allTables.forEach(t => {
        const dbCode   = t.database_code || 'default';
        const dbName   = t.database_name || dbCode;
        const schema   = t.schema_name   || 'public';

        if (!groupedTables[dbCode]) {
            groupedTables[dbCode] = { dbName: dbName, schemas: {} };
        }
        if (!groupedTables[dbCode].schemas[schema]) {
            groupedTables[dbCode].schemas[schema] = [];
        }
        groupedTables[dbCode].schemas[schema].push(t);
    });

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

        // Update Header Details
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
       RENDER PERMISSIONS STRUCTURED TABLE TREE
    ══════════════════════════════════════════════════════════ */
    function renderTree(visibleTables) {
        const container = document.getElementById('treeContainer');
        const emptyState = document.getElementById('tableEmptyState');
        container.innerHTML = '';

        if (visibleTables.length === 0) {
            emptyState.style.display = 'block';
            return;
        }
        emptyState.style.display = 'none';

        // Group the visible tables
        const filteredGrouped = {};
        visibleTables.forEach(t => {
            const dbCode = t.database_code || 'default';
            const dbName = t.database_name || dbCode;
            const schema = t.schema_name   || 'public';

            if (!filteredGrouped[dbCode]) {
                filteredGrouped[dbCode] = { dbName: dbName, schemas: {} };
            }
            if (!filteredGrouped[dbCode].schemas[schema]) {
                filteredGrouped[dbCode].schemas[schema] = [];
            }
            filteredGrouped[dbCode].schemas[schema].push(t);
        });

        Object.entries(filteredGrouped).forEach(([dbCode, dbData]) => {
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
            const pctDb    = totalInDb > 0 ? Math.round((selectedInDb / totalInDb) * 100) : 0;

            const dbSection = document.createElement('div');
            dbSection.className = 'rm-db-section';
            dbSection.id = 'db-card-' + dbCode;

            // Database Header Bar
            dbSection.innerHTML = `
                <div class="rm-db-header-bar" onclick="toggleDbAccordion('${dbCode}')">
                    <div class="rm-db-header-left">
                        <div class="rm-db-header-icon"><i class="fas fa-database"></i></div>
                        <span class="rm-db-title-text">${escHtml(dbData.dbName)}</span>
                        <span class="rm-db-stat-badge ${isFullDb ? 'full' : ''}" id="db-badge-${dbCode}">
                            ${selectedInDb}/${totalInDb} {{ __('Tabel') }} (${pctDb}%)
                        </span>
                    </div>
                    <div class="rm-db-header-right" onclick="event.stopPropagation()">
                        <button type="button" class="rm-btn ${isFullDb ? 'rm-btn-rose' : 'rm-btn-emerald'} rm-btn-sm" onclick="toggleDatabaseAll('${dbCode}', ${!isFullDb})">
                            <i class="fas ${isFullDb ? 'fa-square' : 'fa-check-square'}"></i>
                            ${isFullDb ? '{{ __("Hapus DB") }}' : '{{ __("Pilih Semua DB") }}'}
                        </button>
                        <i class="fas fa-chevron-down rm-db-toggle-ico"></i>
                    </div>
                </div>
                <div class="rm-db-content-area" id="db-body-${dbCode}">
                    <table class="rm-perm-table">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;"></th>
                                <th>{{ __('Nama Tabel / View') }}</th>
                                <th style="width:120px;">{{ __('Schema') }}</th>
                                <th style="width:100px;">{{ __('Tipe') }}</th>
                                <th style="width:110px;text-align:right;">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-${dbCode}"></tbody>
                    </table>
                </div>
            `;

            const tbody = dbSection.querySelector(`#tbody-${dbCode}`);

            // Render Schemas & Table Rows
            Object.entries(dbData.schemas).forEach(([schemaName, tables]) => {
                let selectedInSchema = 0;
                tables.forEach(t => {
                    const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
                    if (selectedTables.has(key)) selectedInSchema++;
                });
                const isFullSchema = (tables.length > 0 && selectedInSchema === tables.length);

                // Schema Subheader Row
                const schemaRow = document.createElement('tr');
                schemaRow.className = 'rm-schema-row';
                schemaRow.innerHTML = `
                    <td colspan="5">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <i class="fas fa-layer-group" style="color:var(--rm-cyan)"></i>
                                <span>SCHEMA: ${escHtml(schemaName)} (${tables.length} tabel)</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:0.68rem;color:var(--rm-text-dim)">${selectedInSchema}/${tables.length} {{ __('Terpilih') }}</span>
                                <button type="button" class="rm-btn ${isFullSchema ? 'rm-btn-rose' : 'rm-btn-emerald'} rm-btn-sm" style="padding:1px 6px;font-size:0.68rem;"
                                        onclick="toggleSchemaAll('${dbCode}', '${schemaName}', ${!isFullSchema})">
                                    ${isFullSchema ? '<i class="fas fa-times"></i> {{ __("Hapus") }}' : '<i class="fas fa-check"></i> {{ __("Pilih") }}'}
                                </button>
                            </div>
                        </div>
                    </td>
                `;
                tbody.appendChild(schemaRow);

                // Table Item Rows
                tables.forEach(t => {
                    const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
                    const isAllowed = selectedTables.has(key);
                    const type = (t.table_type || 'TABLE').toUpperCase();
                    let typeClass = 'table';
                    let typeIco   = 'fa-table';

                    if (type.includes('VIEW') && type.includes('MAT')) {
                        typeClass = 'matview';
                        typeIco   = 'fa-cube';
                    } else if (type.includes('VIEW')) {
                        typeClass = 'view';
                        typeIco   = 'fa-eye';
                    }

                    const row = document.createElement('tr');
                    row.className = `rm-row-item ${isAllowed ? 'allowed' : ''}`;
                    row.id = `row-${dbCode}-${schemaName}-${t.table_name}`;
                    row.onclick = () => toggleTable(key, row);

                    row.innerHTML = `
                        <td style="text-align:center;">
                            <div class="rm-check-box">
                                <i class="fas fa-check"></i>
                            </div>
                        </td>
                        <td>
                            <div class="rm-tbl-name">
                                <i class="fas ${typeIco}"></i>
                                <span>${escHtml(t.table_name)}</span>
                            </div>
                        </td>
                        <td>
                            <span style="font-size:0.75rem;color:var(--rm-text-muted);font-family:var(--rm-font-mono)">${escHtml(t.schema_name)}</span>
                        </td>
                        <td>
                            <span class="rm-tag ${typeClass}">${escHtml(type)}</span>
                        </td>
                        <td style="text-align:right;">
                            <span class="rm-status-badge">
                                <i class="fas ${isAllowed ? 'fa-check-circle' : 'fa-minus-circle'}"></i>
                                <span>${isAllowed ? '{{ __("Diizinkan") }}' : '{{ __("Terkunci") }}'}</span>
                            </span>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            });

            container.appendChild(dbSection);
        });

        updatePillCounts();
    }

    /* ══════════════════════════════════════════════════════════
       TABLE TOGGLE & PERMISSION STATE MANAGEMENT
    ══════════════════════════════════════════════════════════ */
    window.toggleTable = function(key, rowEl) {
        if (selectedTables.has(key)) {
            selectedTables.delete(key);
            if (rowEl) {
                rowEl.classList.remove('allowed');
                const badge = rowEl.querySelector('.rm-status-badge');
                if (badge) badge.innerHTML = '<i class="fas fa-minus-circle"></i> <span>{{ __("Terkunci") }}</span>';
            }
        } else {
            selectedTables.add(key);
            if (rowEl) {
                rowEl.classList.add('allowed');
                const badge = rowEl.querySelector('.rm-status-badge');
                if (badge) badge.innerHTML = '<i class="fas fa-check-circle"></i> <span>{{ __("Diizinkan") }}</span>';
            }
        }

        checkChanges();
        updateCountsAndBadges();
    };

    window.toggleDatabaseAll = function(dbCode, selectAll) {
        const dbData = groupedTables[dbCode];
        if (!dbData) return;

        Object.values(dbData.schemas).forEach(tables => {
            tables.forEach(t => {
                const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
                if (selectAll) selectedTables.add(key);
                else selectedTables.delete(key);
            });
        });

        checkChanges();
        applyFilters();
    };

    window.toggleSchemaAll = function(dbCode, schemaName, selectAll) {
        const tables = groupedTables[dbCode]?.schemas[schemaName];
        if (!tables) return;

        tables.forEach(t => {
            const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
            if (selectAll) selectedTables.add(key);
            else selectedTables.delete(key);
        });

        checkChanges();
        applyFilters();
    };

    window.bulkAction = function(action) {
        const visible = getFilteredTables();
        visible.forEach(t => {
            const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
            if (action === 'select') selectedTables.add(key);
            else if (action === 'deselect') selectedTables.delete(key);
        });

        checkChanges();
        applyFilters();
    };

    function updateCountsAndBadges() {
        const total = allTables.length;
        const selected = selectedTables.size;

        // Header Count
        document.getElementById('heroPermCount').textContent = selected;

        // Sidebar current role pill
        const sidebarPill = document.getElementById('rc-perm-count-' + currentRoleId);
        if (sidebarPill) sidebarPill.innerHTML = `<i class="fas fa-table"></i> ${selected}/${total} Tabel`;

        // Update DB header badges
        Object.entries(groupedTables).forEach(([dbCode, dbData]) => {
            let totalDb = 0;
            let selDb   = 0;
            Object.values(dbData.schemas).forEach(tbls => {
                tbls.forEach(t => {
                    totalDb++;
                    const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
                    if (selectedTables.has(key)) selDb++;
                });
            });
            const badge = document.getElementById('db-badge-' + dbCode);
            if (badge) {
                const isFull = (totalDb > 0 && selDb === totalDb);
                const pct = totalDb > 0 ? Math.round((selDb / totalDb) * 100) : 0;
                badge.className = `rm-db-stat-badge ${isFull ? 'full' : ''}`;
                badge.textContent = `${selDb}/${totalDb} {{ __('Tabel') }} (${pct}%)`;
            }
        });

        updatePillCounts();
    }

    function updatePillCounts() {
        const visible = getFilteredTablesWithoutStatus();
        let allowed = 0;
        let notAllowed = 0;

        visible.forEach(t => {
            const key = `${t.database_code}|${t.schema_name}|${t.table_name}`;
            if (selectedTables.has(key)) allowed++;
            else notAllowed++;
        });

        document.getElementById('pillCountAll').textContent        = visible.length;
        document.getElementById('pillCountAllowed').textContent    = allowed;
        document.getElementById('pillCountNotAllowed').textContent = notAllowed;
    }

    /* ══════════════════════════════════════════════════════════
       FILTERING & SEARCH LOGIC
    ══════════════════════════════════════════════════════════ */
    function getFilteredTablesWithoutStatus() {
        const q       = (document.getElementById('tableSearchInput').value || '').toLowerCase().trim();
        const selDb   = document.getElementById('dbFilterSelect').value;
        const selSch  = document.getElementById('schemaFilterSelect').value;

        return allTables.filter(t => {
            if (selDb && t.database_code !== selDb) return false;
            if (selSch && t.schema_name !== selSch) return false;
            if (q) {
                const matchName   = (t.table_name || '').toLowerCase().includes(q);
                const matchSchema = (t.schema_name || '').toLowerCase().includes(q);
                const matchDb     = (t.database_name || t.database_code || '').toLowerCase().includes(q);
                if (!matchName && !matchSchema && !matchDb) return false;
            }
            return true;
        });
    }

    function getFilteredTables() {
        const list = getFilteredTablesWithoutStatus();
        if (currentStatusFilter === 'allowed') {
            return list.filter(t => selectedTables.has(`${t.database_code}|${t.schema_name}|${t.table_name}`));
        } else if (currentStatusFilter === 'not_allowed') {
            return list.filter(t => !selectedTables.has(`${t.database_code}|${t.schema_name}|${t.table_name}`));
        }
        return list;
    }

    window.applyFilters = function() {
        const q = (document.getElementById('tableSearchInput').value || '').trim();
        document.getElementById('clearTableSearch').style.display = q ? 'block' : 'none';

        const filtered = getFilteredTables();
        renderTree(filtered);
    };

    window.setStatusFilter = function(status, el) {
        currentStatusFilter = status;
        document.querySelectorAll('.rm-seg-btn').forEach(b => b.classList.remove('active'));
        if (el) el.classList.add('active');
        applyFilters();
    };

    window.handleDbFilterChange = function(dbVal) {
        const schemaSelect = document.getElementById('schemaFilterSelect');
        schemaSelect.innerHTML = '<option value="">{{ __("Semua Schema") }}</option>';

        if (dbVal && groupedTables[dbVal]) {
            Object.keys(groupedTables[dbVal].schemas).forEach(s => {
                const opt = document.createElement('option');
                opt.value = s; opt.textContent = s;
                schemaSelect.appendChild(opt);
            });
        }
        applyFilters();
    };

    window.clearTableSearchInput = function() {
        document.getElementById('tableSearchInput').value = '';
        document.getElementById('clearTableSearch').style.display = 'none';
        applyFilters();
    };

    window.resetAllTableFilters = function() {
        document.getElementById('tableSearchInput').value = '';
        document.getElementById('dbFilterSelect').value = '';
        document.getElementById('schemaFilterSelect').value = '';
        document.getElementById('schemaFilterSelect').innerHTML = '<option value="">{{ __("Semua Schema") }}</option>';
        setStatusFilter('all', document.querySelector('.rm-seg-btn[data-status="all"]'));
    };

    window.toggleDbAccordion = function(dbCode) {
        const el = document.getElementById('db-card-' + dbCode);
        if (el) el.classList.toggle('collapsed');
    };

    window.toggleAllAccordions = function(expand) {
        document.querySelectorAll('.rm-db-section').forEach(el => {
            if (expand) el.classList.remove('collapsed');
            else el.classList.add('collapsed');
        });
    };

    /* ══════════════════════════════════════════════════════════
       MEMBERS TAB RENDERING
    ══════════════════════════════════════════════════════════ */
    function renderMembersTab(users) {
        const grid = document.getElementById('membersGrid');
        const emptyState = document.getElementById('membersEmptyState');
        grid.innerHTML = '';

        if (!users || users.length === 0) {
            emptyState.style.display = 'block';
            return;
        }
        emptyState.style.display = 'none';

        users.forEach(u => {
            const card = document.createElement('div');
            card.className = 'rm-user-card';
            const initials = (u.name || 'U').substring(0, 2).toUpperCase();
            card.innerHTML = `
                <div class="rm-user-card-avatar">${escHtml(initials)}</div>
                <div class="rm-user-card-info">
                    <h5>${escHtml(u.name)}</h5>
                    <span>${escHtml(u.email || '-')}</span>
                </div>
            `;
            grid.appendChild(card);
        });
    }

    window.switchMainTab = function(tabName) {
        const tabPermissions = document.getElementById('tabContentPermissions');
        const tabMembers     = document.getElementById('tabContentMembers');
        const btnPermissions = document.getElementById('tabBtnPermissions');
        const btnMembers     = document.getElementById('tabBtnMembers');

        if (tabName === 'permissions') {
            tabPermissions.style.display = 'block';
            tabMembers.style.display = 'none';
            btnPermissions.classList.add('active');
            btnMembers.classList.remove('active');
        } else {
            tabPermissions.style.display = 'none';
            tabMembers.style.display = 'block';
            btnPermissions.classList.remove('active');
            btnMembers.classList.add('active');
        }
    };

    /* ══════════════════════════════════════════════════════════
       SIDEBAR ROLE LIST SEARCH
    ══════════════════════════════════════════════════════════ */
    window.filterRoleList = function(q) {
        q = (q || '').toLowerCase().trim();
        document.getElementById('clearRoleSearch').style.display = q ? 'block' : 'none';

        let visibleCount = 0;
        document.querySelectorAll('.rm-role-card').forEach(card => {
            const name = card.getAttribute('data-role-name') || '';
            if (!q || name.includes(q)) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        document.getElementById('roleCountBadge').textContent = visibleCount;
    };

    window.clearRoleSearchInput = function() {
        document.getElementById('searchRoleInput').value = '';
        filterRoleList('');
    };

    /* ══════════════════════════════════════════════════════════
       UNSAVED CHANGES & SAVE LOGIC (AJAX)
    ══════════════════════════════════════════════════════════ */
    function checkChanges() {
        let changed = false;
        if (selectedTables.size !== originalSelectedTables.size) {
            changed = true;
        } else {
            for (let t of selectedTables) {
                if (!originalSelectedTables.has(t)) {
                    changed = true; break;
                }
            }
        }
        setHasChanges(changed);
    }

    function setHasChanges(state) {
        hasChanges = state;
        const dock = document.getElementById('floatingSaveDock');
        const activeCard = document.getElementById('role-card-' + currentRoleId);

        if (state) {
            dock.classList.add('show');
            if (activeCard) activeCard.classList.add('has-changes');
        } else {
            dock.classList.remove('show');
            if (activeCard) activeCard.classList.remove('has-changes');
        }
    }

    window.discardChanges = function() {
        if (!hasChanges) return;
        selectedTables = new Set(originalSelectedTables);
        setHasChanges(false);
        applyFilters();
        updateCountsAndBadges();
        Swal.fire({
            toast: true, position: 'top-end', icon: 'info',
            title: "{{ __('Perubahan dibatalkan') }}", showConfirmButton: false, timer: 1500
        });
    };

    window.savePermissions = function() {
        if (!currentRoleId) return;

        const permissions = Array.from(selectedTables);

        Swal.fire({
            title: "{{ __('Menyimpan Hak Akses...') }}",
            text: "{{ __('Mohon tunggu sebentar.') }}",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch(`/admin/roles/${currentRoleId}/permissions`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ permissions: permissions })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                originalSelectedTables = new Set(selectedTables);
                setHasChanges(false);

                // Update role in allRoles memory
                const roleObj = allRoles.find(r => r.id == currentRoleId);
                if (roleObj) {
                    roleObj.permissions = permissions.map(p => {
                        const parts = p.split('|');
                        return { database_code: parts[0], schema_name: parts[1], table_name: parts[2] };
                    });
                }

                Swal.fire({
                    icon: 'success',
                    title: "{{ __('Berhasil Disimpan!') }}",
                    text: data.message || "{{ __('Hak akses role berhasil diperbarui.') }}",
                    timer: 1800, showConfirmButton: false
                });
            } else {
                throw new Error(data.message || 'Failed to save');
            }
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: "{{ __('Gagal Menyimpan') }}",
                text: err.message || "{{ __('Terjadi kesalahan saat menyimpan hak akses.') }}"
            });
        });
    };

    /* ══════════════════════════════════════════════════════════
       ROLE MODAL ACTIONS (CREATE, EDIT, CLONE, DELETE)
    ══════════════════════════════════════════════════════════ */
    window.showRoleModal = function(mode, roleData = null) {
        const form   = document.getElementById('roleForm');
        const title  = document.getElementById('roleModalTitle');
        const nameIn = document.getElementById('roleNameInput');
        const descIn = document.getElementById('roleDescInput');
        const methIn = document.getElementById('roleFormMethod');

        if (mode === 'create') {
            title.textContent = "{{ __('Tambah Role Baru') }}";
            form.action = "{{ route('admin.roles.store') }}";
            methIn.value = 'POST';
            nameIn.value = '';
            descIn.value = '';
        } else {
            title.textContent = "{{ __('Edit Info Role') }}";
            form.action = `/admin/roles/${roleData.id}`;
            methIn.value = 'PUT';
            nameIn.value = roleData.name || '';
            descIn.value = roleData.description || '';
        }
        document.getElementById('roleModal').classList.add('open');
        nameIn.focus();
    };

    window.closeRoleModal = function() {
        document.getElementById('roleModal').classList.remove('open');
    };

    window.editCurrentRole = function() {
        const r = allRoles.find(x => x.id == currentRoleId);
        if (r) showRoleModal('edit', r);
    };

    window.duplicateRole = function(roleId) {
        const r = allRoles.find(x => x.id == roleId);
        if (!r) return;

        document.getElementById('cloneSourceRoleId').value = roleId;
        document.getElementById('cloneRoleNameInput').value = `${r.name} (Salinan)`;
        document.getElementById('cloneRoleDescInput').value = r.description || '';
        document.getElementById('cloneModal').classList.add('open');
        document.getElementById('cloneRoleNameInput').focus();
    };

    window.duplicateCurrentRole = function() {
        duplicateRole(currentRoleId);
    };

    window.closeCloneModal = function() {
        document.getElementById('cloneModal').classList.remove('open');
    };

    window.handleCloneSubmit = function(e) {
        e.preventDefault();
        const sourceId = document.getElementById('cloneSourceRoleId').value;
        const name     = document.getElementById('cloneRoleNameInput').value;
        const desc     = document.getElementById('cloneRoleDescInput').value;

        const btn = document.getElementById('btnSubmitClone');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ __("Menyalin...") }}';

        fetch(`/admin/roles/${sourceId}/clone`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name: name, description: desc })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeCloneModal();
                Swal.fire({
                    icon: 'success',
                    title: "{{ __('Role Berhasil Diduplikasi!') }}",
                    text: "{{ __('Halaman akan disegarkan...') }}",
                    timer: 1500, showConfirmButton: false
                }).then(() => window.location.reload());
            } else {
                throw new Error(data.message || 'Duplicate failed');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-clone"></i> {{ __("Duplikat & Buat") }}';
            Swal.fire({ icon: 'error', title: "{{ __('Gagal Menduplikasi') }}", text: err.message });
        });
    };

    window.deleteRole = function(roleId) {
        const role = allRoles.find(r => r.id == roleId);
        if (!role) return;

        Swal.fire({
            title: "{{ __('Hapus Role Ini?') }}",
            html: `<p>{{ __('Apakah Anda yakin ingin menghapus role') }} <strong>${escHtml(role.name)}</strong>?</p><p style="font-size:0.8rem;color:#ef4444;">{{ __('Tindakan ini tidak dapat dibatalkan.') }}</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: "{{ __('Ya, Hapus Role') }}",
            cancelButtonText: "{{ __('Batal') }}"
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/admin/roles/${roleId}`;
                form.innerHTML = `
                    @csrf
                    <input type="hidden" name="_method" value="DELETE">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    };

    /* ══════════════════════════════════════════════════════════
       KEYBOARD SHORTCUTS & UTILITIES
    ══════════════════════════════════════════════════════════ */
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            if (hasChanges) savePermissions();
        }
        if (e.key === 'Escape') {
            closeRoleModal();
            closeCloneModal();
        }
    });

    function escHtml(str) {
        if (!str) return '';
        const p = document.createElement('p');
        p.textContent = str;
        return p.innerHTML;
    }

    // INITIAL LOAD
    if (currentRoleId) {
        loadRolePermissions(currentRoleId);
    }
});
</script>
@endpush

@endsection