@extends('layouts.admin')
@section('page-title', 'Dashboard Overview')

@section('content')

<div class="stats-grid">
    <div class="stat-card glass-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,rgba(99,102,241,0.2),rgba(79,70,229,0.08));border-color:rgba(99,102,241,0.2);">
            <i class="fas fa-users" style="color:#6366f1;"></i>
        </div>
        <div class="stat-body">
            <span class="stat-label">Total Users</span>
            <h2 class="stat-value">{{ $stats['users_count'] }}</h2>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,rgba(16,185,129,0.2),rgba(16,185,129,0.05));border-color:rgba(16,185,129,0.2);">
            <i class="fas fa-user-shield" style="color:#10b981;"></i>
        </div>
        <div class="stat-body">
            <span class="stat-label">Total Roles</span>
            <h2 class="stat-value">{{ $stats['roles_count'] }}</h2>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,rgba(139,92,246,0.2),rgba(139,92,246,0.05));border-color:rgba(139,92,246,0.2);">
            <i class="fas fa-database" style="color:#8b5cf6;"></i>
        </div>
        <div class="stat-body">
            <span class="stat-label">Total Databases</span>
            <h2 class="stat-value">{{ $stats['databases_count'] }}</h2>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,rgba(245,158,11,0.2),rgba(245,158,11,0.05));border-color:rgba(245,158,11,0.2);">
            <i class="fas fa-table" style="color:#f59e0b;"></i>
        </div>
        <div class="stat-body">
            <span class="stat-label">Total Tables</span>
            <h2 class="stat-value">{{ $stats['tables_count'] }}</h2>
        </div>
    </div>
</div>

<div class="glass-card welcome-card">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:1rem;">
        <div style="width:44px;height:44px;background:linear-gradient(135deg,#6366f1,#4f46e5);border-radius:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(99,102,241,0.35);">
            <i class="fas fa-rocket" style="color:#fff;font-size:1.1rem;"></i>
        </div>
        <div>
            <h3 style="font-size:1.1rem;font-weight:700;color:var(--text-main);">Selamat Datang, Admin! 👋</h3>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-top:2px;">Kelola sistem Darko AI melalui panel administrasi</p>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
        <a href="{{ route('admin.users') }}" class="quick-link">
            <i class="fas fa-users"></i> <span>Kelola Users</span>
        </a>
        <a href="{{ route('admin.roles') }}" class="quick-link">
            <i class="fas fa-user-shield"></i> <span>Kelola Roles</span>
        </a>
        <a href="{{ route('admin.databases') }}" class="quick-link">
            <i class="fas fa-database"></i> <span>Kelola Database</span>
        </a>
        <a href="{{ route('admin.ai_management') }}" class="quick-link">
            <i class="fas fa-brain"></i> <span>Kelola AI</span>
        </a>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
        margin-bottom: 1.5rem;
    }
    .stat-card {
        display: flex; align-items: center; gap: 1rem;
        padding: 1.5rem !important;
        transition: transform 0.25s, box-shadow 0.25s;
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg) !important; }
    .stat-icon {
        width: 52px; height: 52px; flex-shrink: 0;
        border-radius: 14px; border: 1px solid;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem;
    }
    .stat-body { flex: 1; }
    .stat-label { display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 4px; }
    .stat-value { font-size: 2rem; font-weight: 700; color: var(--text-main); line-height: 1; }
    .welcome-card { padding: 1.75rem !important; }
    .quick-link {
        display: flex; align-items: center; gap: 10px;
        padding: 0.75rem 1rem; border-radius: 10px;
        background: rgba(99,102,241,0.06);
        border: 1px solid rgba(99,102,241,0.12);
        color: var(--text-main); text-decoration: none;
        font-size: 0.875rem; font-weight: 500;
        transition: all 0.2s;
    }
    .quick-link:hover {
        background: rgba(99,102,241,0.14);
        border-color: rgba(99,102,241,0.25);
        color: var(--primary);
        transform: translateX(4px);
    }
    .quick-link i { color: var(--primary); width: 16px; text-align: center; }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .stat-value { font-size: 1.5rem; }
    }
</style>
@endsection
