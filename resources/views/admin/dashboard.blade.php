@extends('layouts.admin')

@section('content')
<div class="header">
    <h1>Dashboard Overview</h1>
</div>

<div class="stats-grid">
    <div class="glass-card stat-card">
        <i class="fas fa-users" style="font-size: 2rem; color: var(--primary); margin-bottom: 1rem;"></i>
        <span style="color: #94a3b8; margin-bottom: 0.5rem;">Total Users</span>
        <h2 style="font-size: 2.5rem;">{{ $stats['users_count'] }}</h2>
    </div>
    <div class="glass-card stat-card">
        <i class="fas fa-user-shield" style="font-size: 2rem; color: #10b981; margin-bottom: 1rem;"></i>
        <span style="color: #94a3b8; margin-bottom: 0.5rem;">Total Roles</span>
        <h2 style="font-size: 2.5rem;">{{ $stats['roles_count'] }}</h2>
    </div>
    <div class="glass-card stat-card">
        <i class="fas fa-database" style="font-size: 2rem; color: #f59e0b; margin-bottom: 1rem;"></i>
        <span style="color: #94a3b8; margin-bottom: 0.5rem;">Total Tables</span>
        <h2 style="font-size: 2.5rem;">{{ $stats['tables_count'] }}</h2>
    </div>
</div>

<div class="glass-card">
    <h3>Selamat Datang, Admin!</h3>
    <p style="color: #94a3b8; margin-top: 1rem;">Gunakan sidebar untuk mengelola pengguna, peran, dan hak akses tabel database.</p>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .stat-card {
            padding: 1.5rem;
        }

        .stat-card i {
            font-size: 1.5rem !important;
        }

        .stat-card h2 {
            font-size: 2rem !important;
        }
    }

    @media (max-width: 480px) {
        .stat-card {
            padding: 1.2rem;
        }

        .stat-card i {
            font-size: 1.3rem !important;
        }

        .stat-card h2 {
            font-size: 1.7rem !important;
        }
    }
</style>
@endsection
