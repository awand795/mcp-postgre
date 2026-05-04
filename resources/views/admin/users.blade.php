@extends('layouts.admin')

@section('content')
<div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; gap:1rem; flex-wrap:wrap;">
    <div class="header-left">
        <h1 class="page-title" style="font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; margin:0; color:var(--text-main);">
            <span class="title-icon" style="width:44px; height:44px; background:linear-gradient(135deg, var(--primary), #8b5cf6); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:white; flex-shrink:0; box-shadow:0 4px 15px rgba(99,102,241,0.35);"><i class="fas fa-users"></i></span>
            Management User
        </h1>
        <p class="page-subtitle" style="color:var(--text-muted); margin:0.3rem 0 0 3.5rem; font-size:0.9rem;">Kelola pengguna, role, dan hak akses sistem</p>
    </div>
    <div class="header-actions" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
        <button class="btn btn-success" onclick="downloadTemplate()" style="background:#10b981; color:white; border:none; padding:0.6rem 1rem; border-radius:10px; cursor:pointer;"><i class="fas fa-download"></i> <span>Template</span></button>
        <button class="btn btn-info" onclick="showModal('import')" style="background:#0ea5e9; color:white; border:none; padding:0.6rem 1rem; border-radius:10px; cursor:pointer;"><i class="fas fa-file-import"></i> <span>Import</span></button>
        <button class="btn btn-secondary" onclick="exportUsers()" style="background:var(--input-bg); color:var(--text-main); border:1px solid var(--glass-border); padding:0.6rem 1rem; border-radius:10px; cursor:pointer;"><i class="fas fa-file-export"></i> <span>Export</span></button>
        <button class="btn btn-primary" onclick="showModal('create')" style="background:var(--primary); color:white; border:none; padding:0.6rem 1rem; border-radius:10px; cursor:pointer;"><i class="fas fa-plus"></i> <span>Tambah User</span></button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <strong>Gagal!</strong>
        <ul style="margin-top: 5px; margin-left: 20px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Filter & Search -->
<div class="glass-card filter-card">
    <form action="{{ route('admin.users') }}" method="GET" class="filter-form">
        <div class="filter-group">
            <label><i class="fas fa-search"></i> Cari User</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nama atau Email...">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-user-tag"></i> Filter Role</label>
            <select name="role_filter">
                <option value="">Semua Role</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ request('role_filter') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('admin.users') }}" class="btn btn-cancel"><i class="fas fa-sync"></i> Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="glass-card table-card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Admin?</th>
                    <th>AI Models</th>
                    <th>API Keys</th>
                    <th>Cakupan</th>
                    <th class="th-sticky">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span class="role-badge">{{ $user->roleModel->name ?? 'No Role' }}</span>
                    </td>
                    <td>
                        @if($user->is_admin)
                            <span class="status-yes"><i class="fas fa-check-circle"></i> Yes</span>
                        @else
                            <span class="status-no"><i class="fas fa-times-circle"></i> No</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $enabledModels = $user->aiModels->where('pivot.is_enabled', true)->values();
                            $visibleModels = $enabledModels->take(2);
                            $extraModels   = $enabledModels->slice(2);
                        @endphp
                        @if($enabledModels->isEmpty())
                            <span class="ai-none">&mdash;</span>
                        @else
                            <div class="ai-pill-group">
                                @foreach($visibleModels as $m)
                                    <span class="ai-pill ai-pill-model" title="{{ $m->display_name }}">{{ Str::limit($m->display_name, 18) }}</span>
                                @endforeach
                                @if($extraModels->count())
                                    <span class="ai-pill ai-pill-more"
                                          data-tooltip="{{ $extraModels->pluck('display_name')->implode('&#10;') }}">
                                        +{{ $extraModels->count() }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>
                        @php
                            $enabledKeys = $user->aiKeys->where('pivot.is_enabled', true)->values();
                            $visibleKeys = $enabledKeys->take(2);
                            $extraKeys   = $enabledKeys->slice(2);
                        @endphp
                        @if($enabledKeys->isEmpty())
                            <span class="ai-none">&mdash;</span>
                        @else
                            <div class="ai-pill-group">
                                @foreach($visibleKeys as $k)
                                    <span class="ai-pill ai-pill-key" title="{{ $k->key_name }}">{{ Str::limit($k->key_name, 18) }}</span>
                                @endforeach
                                @if($extraKeys->count())
                                    <span class="ai-pill ai-pill-more"
                                          data-tooltip="{{ $extraKeys->pluck('key_name')->implode('&#10;') }}">
                                        +{{ $extraKeys->count() }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($user->analysis_scope_limited)
                            <span class="scope-badge scope-limited"><i class="fas fa-database"></i> DB &amp; ERP</span>
                        @else
                            <span class="scope-badge scope-free"><i class="fas fa-globe"></i> Bebas</span>
                        @endif
                    </td>
                    <td class="td-sticky">
                        <div class="action-buttons">
                            <button class="btn btn-info" onclick="showAiConfig({{ json_encode($user) }})" title="AI Config">
                                <i class="fas fa-robot"></i>
                            </button>
                            <button class="btn btn-edit" onclick="showModal('edit', {{ json_encode($user) }})"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('admin.users.delete', $user->id) }}" method="POST" onsubmit="return confirm('Hapus user ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>Tidak ada user yang ditemukan</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
@if($users->hasPages())
<div class="pagination-container">
    <p class="pagination-info">
        Menampilkan {{ $users->firstItem() }} - {{ $users->lastItem() }} dari {{ $users->total() }} user
    </p>
    <nav class="pagination-nav">
        {{ $users->links() }}
    </nav>
</div>
@endif

<!-- User Modal -->
<div id="userModal" class="modal-overlay">
    <div class="glass-card modal-content">
        <h3 id="modalTitle">Tambah User</h3>
        <form id="userForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="name" id="userName" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="userEmail" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="userPassword">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="userRole" required>
                    <option value="">Pilih Role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" style="color: black;">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Max Tokens</label>
                <input type="number" name="max_tokens" id="userMaxTokens" value="32768" required>
            </div>
            <div class="form-group checkbox-group">
                <input type="checkbox" name="is_admin" id="userIsAdmin" value="1">
                <label for="userIsAdmin">Jadikan Admin</label>
            </div>
            <div class="form-group checkbox-group" style="margin-top: 0.5rem;">
                <input type="checkbox" name="analysis_scope_limited" id="userScopeLimited" value="1" checked>
                <label for="userScopeLimited">Cakupan Analisis (Database &amp; ERP)</label>
            </div>
            <p style="color: #64748b; font-size: 0.78rem; margin: -0.75rem 0 1rem 0; padding-left: 0.25rem;">
                <i class="fas fa-info-circle" style="color:#6366f1"></i>
                &#10003; Dicentang = AI hanya analisis database &amp; ERP &nbsp;|&nbsp; &#9744; Tidak dicentang = AI bebas menjawab umum
            </p>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="hideModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     AI CONFIGURATION MODAL — REDESIGNED
     ═══════════════════════════════════════════════════════════ -->
<div id="aiConfigModal" class="modal-overlay">
    <div class="aic-modal glass-card">

        <!-- Header -->
        <div class="aic-header">
            <div class="aic-header-left">
                <div class="aic-avatar" id="aiConfigAvatar">U</div>
                <div>
                    <p class="aic-label">Konfigurasi Akses AI untuk</p>
                    <h3 class="aic-username" id="aiConfigUserName">—</h3>
                </div>
            </div>
            <button class="aic-close-btn" onclick="hideAiConfig()" title="Tutup">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Stats bar -->
        <div class="aic-stats-bar">
            <div class="aic-stat">
                <span class="aic-stat-num" id="statModels">0</span>
                <span class="aic-stat-lbl">Model Dipilih</span>
            </div>
            <div class="aic-stat-divider"></div>
            <div class="aic-stat">
                <span class="aic-stat-num aic-stat-key" id="statKeys">0</span>
                <span class="aic-stat-lbl">API Key Dipilih</span>
            </div>
            <div class="aic-stat-divider"></div>
            <div class="aic-stat">
                <span class="aic-stat-num aic-stat-prov" id="statProviders">0</span>
                <span class="aic-stat-lbl">Provider Aktif</span>
            </div>
        </div>

        <!-- Tab Nav -->
        <div class="aic-tabs">
            <button class="aic-tab active" id="tabBtnModels" onclick="switchAicTab('models')">
                <i class="fas fa-brain"></i>
                <span>AI Models</span>
                <span class="aic-badge" id="badgeModels">0</span>
            </button>
            <button class="aic-tab" id="tabBtnKeys" onclick="switchAicTab('keys')">
                <i class="fas fa-key"></i>
                <span>API Keys</span>
                <span class="aic-badge aic-badge-key" id="badgeKeys">0</span>
            </button>
        </div>

        <form id="aiConfigForm">
            @csrf

            <!-- ── Models Panel ── -->
            <div class="aic-panel" id="aicPanelModels">
                <div class="aic-search-wrap">
                    <i class="fas fa-search aic-search-icon"></i>
                    <input class="aic-search-input" type="text" id="searchModels"
                           placeholder="Cari model AI..." oninput="filterAicItems('models')">
                    <button type="button" class="aic-select-all-btn" onclick="selectAllVisible('models', true)">
                        <i class="fas fa-check-double"></i> Pilih Semua
                    </button>
                    <button type="button" class="aic-clear-btn" onclick="selectAllVisible('models', false)">
                        <i class="fas fa-times"></i> Hapus
                    </button>
                </div>
                <div class="aic-scroll-area" id="aiModelsGrouped"></div>
            </div>

            <!-- ── Keys Panel ── -->
            <div class="aic-panel" id="aicPanelKeys" style="display:none;">
                <div class="aic-search-wrap">
                    <i class="fas fa-search aic-search-icon"></i>
                    <input class="aic-search-input" type="text" id="searchKeys"
                           placeholder="Cari API key..." oninput="filterAicItems('keys')">
                    <button type="button" class="aic-select-all-btn" onclick="selectAllVisible('keys', true)">
                        <i class="fas fa-check-double"></i> Pilih Semua
                    </button>
                    <button type="button" class="aic-clear-btn" onclick="selectAllVisible('keys', false)">
                        <i class="fas fa-times"></i> Hapus
                    </button>
                </div>
                <div class="aic-scroll-area" id="aiKeysGrouped"></div>
            </div>

            <!-- Footer -->
            <div class="aic-footer">
                <p class="aic-footer-hint">
                    <i class="fas fa-info-circle"></i>
                    Klik item untuk toggle akses. Perubahan disimpan saat klik Simpan.
                </p>
                <div class="aic-footer-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideAiConfig()">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSaveAiConfig">
                        <i class="fas fa-save"></i> Simpan Akses AI
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="modal-overlay">
    <div class="glass-card modal-content">
        <h3>Import User</h3>
        <form action="{{ route('admin.users.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>File Excel</label>
                <input type="file" name="file" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="hideImportModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* ── Existing styles (unchanged) ─────────────────────────────────── */
    .filter-group select option { background: var(--card-bg); color: var(--text-main); }
    .action-buttons { display: flex; gap: 8px; }
    .btn-edit, .btn-delete, .btn-info { padding: 8px 12px; }

    .ai-pill-group { display: flex; flex-wrap: wrap; gap: 4px; max-width: 200px; }
    .ai-pill {
        display: inline-flex; align-items: center;
        padding: 3px 9px; border-radius: 20px;
        font-size: 0.72rem; font-weight: 600;
        white-space: nowrap; max-width: 140px;
        overflow: hidden; text-overflow: ellipsis; cursor: default;
    }
    .ai-pill-model { background: rgba(99,102,241,0.15); color: #6366f1; border: 1px solid rgba(99,102,241,0.3); }
    .ai-pill-key   { background: rgba(16,185,129,0.13); color: #10b981; border: 1px solid rgba(16,185,129,0.25); }
    html.dark .ai-pill-model { color: #a5b4fc; }
    html.dark .ai-pill-key { color: #6ee7b7; }
    .ai-pill-more  { background: rgba(99,102,241,0.06); color: var(--text-muted); border: 1px solid var(--glass-border2); cursor: pointer; position: relative; }
    .ai-pill-more:hover::after {
        content: attr(data-tooltip);
        position: absolute; top: calc(100% + 6px); left: 0;
        background: var(--card-bg); color: var(--text-main);
        border: 1px solid var(--glass-border2); border-radius: 8px;
        padding: 8px 12px; font-size: 0.75rem; font-weight: 500;
        white-space: pre-wrap; word-break: break-word;
        max-width: 260px; z-index: 999;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15); line-height: 1.6;
    }
    .ai-none { color: var(--text-muted); font-size: 0.85rem; }
    .scope-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
    .scope-limited { background: rgba(99,102,241,0.15); color: #818cf8; border: 1px solid rgba(99,102,241,0.25); }
    .scope-free    { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.25); }
    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .table-card { padding: 0; overflow: visible; margin-top: 1rem; }
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; min-width: 900px; border-collapse: collapse; color: var(--text-main); }
    th { padding: 1rem 1.25rem; text-align: left; color: var(--text-muted); background: var(--bg-main); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid var(--glass-border2); }
    td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--glass-border); font-size: 0.92rem; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(99,102,241,0.02); }
    .th-sticky { position: sticky; right: 0; z-index: 3; background: var(--bg-main); box-shadow: -3px 0 8px rgba(0,0,0,0.05); }
    .td-sticky { position: sticky; right: 0; z-index: 2; background: var(--card-bg); box-shadow: -3px 0 8px rgba(0,0,0,0.05); transition: background 0.2s; }
    tr:hover .td-sticky { background: rgba(99,102,241,0.05); }
    html.dark .th-sticky, html.dark .td-sticky { box-shadow: -3px 0 8px rgba(0,0,0,0.3); }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(6px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; }
    .modal-content { width: 100%; max-width: 500px; max-height: 95vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); background: var(--card-bg); }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.6rem; color: var(--text-muted); font-weight: 600; font-size: 0.9rem; }
    .form-group input, .form-group select { width: 100%; background: var(--input-bg); border: 1px solid var(--input-border); padding: 0.8rem 1rem; border-radius: 12px; color: var(--text-main); font-family: 'Outfit', sans-serif; transition: all 0.3s; }
    .form-group input:focus, .form-group select:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    .checkbox-group { display: flex; align-items: center; gap: 12px; background: var(--input-bg); padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid var(--input-border); }
    .checkbox-group input { width: 18px !important; height: 18px !important; cursor: pointer; accent-color: var(--primary); }
    .checkbox-group label { margin-bottom: 0; cursor: pointer; color: var(--text-main); }
    .pagination-container { margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
    .pagination-info { color: var(--text-muted); font-size: 0.9rem; }

    /* ═══════════════════════════════════════════════════════════
       AI CONFIG MODAL — STYLES
       ═══════════════════════════════════════════════════════════ */

    /* Modal wrapper: fixed height, flex column, no overflow */
    .aic-modal {
        width: 100%;
        max-width: 780px;
        height: 90vh;           /* fixed tinggi — kunci utama agar scroll bisa kerja */
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        padding: 0;
        overflow: hidden;       /* modal sendiri tidak scroll — child yang scroll */
        border-radius: 20px;
        box-shadow: 0 32px 64px rgba(0,0,0,0.6);
    }

    /* Header — tidak scroll */
    .aic-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.25rem 1.75rem 1rem;
        border-bottom: 1px solid var(--glass-border2);
        flex-shrink: 0;
    }
    .aic-header-left { display: flex; align-items: center; gap: 1rem; }
    .aic-avatar {
        width: 42px; height: 42px; border-radius: 12px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; font-weight: 800; color: white; flex-shrink: 0;
    }
    .aic-label { margin: 0; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
    .aic-username { margin: 0.1rem 0 0; font-size: 1.05rem; font-weight: 700; color: var(--text-main); }
    .aic-close-btn {
        width: 34px; height: 34px; border-radius: 10px;
        background: var(--input-bg); border: 1px solid var(--input-border);
        color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem; transition: all 0.2s; flex-shrink: 0;
    }
    .aic-close-btn:hover { background: rgba(239,68,68,0.15); color: #f87171; border-color: rgba(239,68,68,0.3); }

    /* Stats bar — tidak scroll */
    .aic-stats-bar {
        display: flex; align-items: center;
        padding: 0.75rem 1.75rem;
        background: var(--bg-main);
        border-bottom: 1px solid var(--glass-border2);
        flex-shrink: 0;
    }
    .aic-stat { display: flex; align-items: center; gap: 0.6rem; flex: 1; }
    .aic-stat-num {
        font-size: 1.4rem; font-weight: 800; line-height: 1;
        background: linear-gradient(135deg, #818cf8, #a5b4fc);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .aic-stat-num.aic-stat-key { background: linear-gradient(135deg, #10b981, #34d399); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .aic-stat-num.aic-stat-prov { background: linear-gradient(135deg, #f59e0b, #fbbf24); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .aic-stat-lbl { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; line-height: 1.2; }
    .aic-stat-divider { width: 1px; height: 28px; background: var(--glass-border2); margin: 0 1.25rem; flex-shrink: 0; }

    /* Tabs — tidak scroll */
    .aic-tabs {
        display: flex;
        padding: 0 1.75rem;
        border-bottom: 1px solid var(--glass-border2);
        flex-shrink: 0;
    }
    .aic-tab {
        display: flex; align-items: center; gap: 0.6rem;
        padding: 0.85rem 1.25rem;
        background: transparent; border: none;
        color: var(--text-muted); cursor: pointer; font-size: 0.88rem; font-weight: 600;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px; transition: all 0.2s;
    }
    .aic-tab:hover { color: var(--text-main); }
    .aic-tab.active { color: #818cf8; border-bottom-color: #6366f1; }
    .aic-tab.active.key-tab { color: #34d399; border-bottom-color: #10b981; }
    .aic-badge {
        min-width: 20px; height: 20px; border-radius: 10px;
        background: rgba(99,102,241,0.2); color: #818cf8;
        font-size: 0.7rem; font-weight: 800;
        display: inline-flex; align-items: center; justify-content: center;
        padding: 0 5px;
    }
    .aic-badge.aic-badge-key { background: rgba(16,185,129,0.2); color: #34d399; }

    /* form harus ikut flex chain agar panel bisa flex: 1 */
    #aiConfigForm {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;          /* KUNCI: tanpa ini flex: 1 tidak bisa shrink di Firefox/Chrome */
        overflow: hidden;
    }

    /* Search bar — tidak scroll, flex-shrink: 0 */
    .aic-search-wrap {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.85rem 1.75rem 0.65rem;
        flex-shrink: 0;
    }
    .aic-search-icon { color: var(--text-muted); font-size: 0.85rem; flex-shrink: 0; }
    .aic-search-input {
        flex: 1;
        background: var(--input-bg);
        border: 1px solid var(--input-border);
        padding: 0.5rem 0.85rem; border-radius: 10px;
        color: var(--text-main); font-size: 0.85rem;
        transition: all 0.2s; outline: none;
        width: auto !important; min-width: 0; font-family: 'Outfit', sans-serif;
    }
    .aic-search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    .aic-select-all-btn, .aic-clear-btn {
        padding: 0.45rem 0.75rem; border-radius: 8px; border: none;
        font-size: 0.73rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        white-space: nowrap; display: flex; align-items: center; gap: 4px; flex-shrink: 0;
    }
    .aic-select-all-btn { background: rgba(99,102,241,0.15); color: #818cf8; }
    .aic-select-all-btn:hover { background: rgba(99,102,241,0.28); }
    .aic-clear-btn { background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--input-border); }
    .aic-clear-btn:hover { background: rgba(239,68,68,0.15); color: #f87171; border-color: rgba(239,68,68,0.3); }

    /* Panel — flex column, mengisi sisa ruang */
    .aic-panel {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;          /* KUNCI scroll */
    }

    /* Scroll area — ini yang actual scroll */
    .aic-scroll-area {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem 1.75rem 1rem;
        min-height: 0;          /* KUNCI scroll di semua browser */
        scrollbar-width: thin;
        scrollbar-color: var(--glass-border2) transparent;
    }
    .aic-scroll-area::-webkit-scrollbar { width: 5px; }
    .aic-scroll-area::-webkit-scrollbar-track { background: transparent; }
    .aic-scroll-area::-webkit-scrollbar-thumb { background: var(--glass-border2); border-radius: 3px; }

    /* Provider group */
    .aic-provider-block { margin-bottom: 1.25rem; }
    .aic-provider-header {
        display: flex; align-items: center; gap: 0.6rem;
        margin-bottom: 0.6rem; padding: 0 0.25rem;
    }
    .aic-provider-icon {
        width: 24px; height: 24px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.65rem; font-weight: 700; color: white; flex-shrink: 0;
    }
    .aic-provider-name {
        font-size: 0.72rem; font-weight: 800; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: 0.1em; flex: 1;
    }
    .aic-provider-count { font-size: 0.68rem; color: var(--text-muted); font-weight: 600; }
    .aic-provider-toggle {
        font-size: 0.68rem; font-weight: 700; cursor: pointer;
        color: #6366f1; padding: 2px 8px; border-radius: 5px;
        background: rgba(99,102,241,0.1); transition: all 0.2s;
        border: none; white-space: nowrap;
    }
    .aic-provider-toggle:hover { background: rgba(99,102,241,0.2); }

    /* Item grid */
    .aic-items-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
        gap: 0.45rem;
    }
    .aic-item {
        display: flex; align-items: center; gap: 0.6rem;
        padding: 0.6rem 0.8rem; border-radius: 10px;
        cursor: pointer; transition: all 0.15s;
        border: 1px solid var(--glass-border2);
        background: var(--input-bg);
        user-select: none;
    }
    .aic-item:hover { background: var(--card-bg); border-color: var(--primary); }
    .aic-item.aic-item-checked { background: rgba(99,102,241,0.1); border-color: rgba(99,102,241,0.35); }
    .aic-item.aic-item-checked.aic-item-key { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.35); }
    .aic-item input[type="checkbox"] { width: 14px !important; height: 14px !important; margin: 0; flex-shrink: 0; cursor: pointer; accent-color: #6366f1; }
    .aic-item.aic-item-key input[type="checkbox"] { accent-color: #10b981; }
    .aic-item-name { font-size: 0.81rem; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: color 0.15s; }
    .aic-item-checked .aic-item-name { color: #c7d2fe; font-weight: 600; }
    .aic-item-checked.aic-item-key .aic-item-name { color: #6ee7b7; }
    .aic-item-check-icon { margin-left: auto; font-size: 0.68rem; color: #6366f1; opacity: 0; transition: opacity 0.15s; flex-shrink: 0; }
    .aic-item-checked .aic-item-check-icon { opacity: 1; }
    .aic-item-checked.aic-item-key .aic-item-check-icon { color: #10b981; }
    .aic-item-hidden { display: none !important; }
    .aic-block-hidden { display: none !important; }

    /* Empty state */
    .aic-empty { text-align: center; padding: 2.5rem 1rem; color: #475569; }
    .aic-empty i { font-size: 2rem; margin-bottom: 0.75rem; display: block; }
    .aic-empty p { font-size: 0.85rem; margin: 0; }

    /* Footer — tidak scroll, flex-shrink: 0 */
    .aic-footer {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.9rem 1.75rem 1.1rem;
        border-top: 1px solid var(--glass-border2);
        flex-shrink: 0; gap: 1rem; flex-wrap: wrap;
    }
    .aic-footer-hint { margin: 0; font-size: 0.73rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; }
    .aic-footer-actions { display: flex; gap: 0.75rem; }

    /* Provider colors */
    .aic-prov-openai     { background: #10a37f; }
    .aic-prov-gemini     { background: #4285f4; }
    .aic-prov-claude     { background: #d97757; }
    .aic-prov-mistral    { background: #ff7000; }
    .aic-prov-groq       { background: #f55036; }
    .aic-prov-openrouter { background: #7c3aed; }
    .aic-prov-deepseek   { background: #0ea5e9; }
    .aic-prov-default    { background: #6366f1; }

    @media (max-width: 600px) {
        .aic-modal { max-width: 100%; border-radius: 16px 16px 0 0; height: 96vh; max-height: 96vh; }
        .aic-items-grid { grid-template-columns: 1fr 1fr; }
        .aic-search-wrap { flex-wrap: wrap; }
        .aic-stats-bar { padding: 0.65rem 1.25rem; }
        .aic-stat-divider { margin: 0 0.75rem; }
    }
</style>

<script>
    let currentEditingUserId = null;
    let _aicActiveTab = 'models';

    function providerColorClass(name) {
        const map = { openai:'openai', gemini:'gemini', claude:'claude', mistral:'mistral', groq:'groq', openrouter:'openrouter', deepseek:'deepseek' };
        const key = name.toLowerCase().replace(/[^a-z]/g,'');
        return 'aic-prov-' + (map[key] || 'default');
    }

    function renderAicGroup(container, groupedData, checkedIds, nameField, inputName, isKey) {
        container.innerHTML = '';
        const providers = Object.keys(groupedData);
        if (providers.length === 0) {
            container.innerHTML = `<div class="aic-empty"><i class="fas ${isKey ? 'fa-key' : 'fa-brain'}"></i><p>Tidak ada item tersedia.</p></div>`;
            return;
        }
        providers.forEach(providerName => {
            const items = groupedData[providerName];
            const block = document.createElement('div');
            block.className = 'aic-provider-block';
            block.dataset.provider = providerName.toLowerCase();

            const colorClass = providerColorClass(providerName);
            const checkedCount = items.filter(i => checkedIds.includes(i.id)).length;

            block.innerHTML = `
                <div class="aic-provider-header">
                    <div class="aic-provider-icon ${colorClass}">
                        <i class="fas ${isKey ? 'fa-key' : 'fa-microchip'}"></i>
                    </div>
                    <span class="aic-provider-name">${providerName}</span>
                    <span class="aic-provider-count">${items.length} item</span>
                    <button type="button" class="aic-provider-toggle" onclick="toggleProviderAll(this, '${inputName}')">
                        ${checkedCount === items.length ? 'Hapus Semua' : 'Pilih Semua'}
                    </button>
                </div>
                <div class="aic-items-grid"></div>
            `;
            const grid = block.querySelector('.aic-items-grid');
            items.forEach(item => {
                const isChecked = checkedIds.includes(item.id);
                const itemEl = document.createElement('label');
                itemEl.className = `aic-item ${isChecked ? 'aic-item-checked' : ''} ${isKey ? 'aic-item-key' : ''}`;
                itemEl.dataset.name = (item[nameField] || '').toLowerCase();
                itemEl.innerHTML = `
                    <input type="checkbox" name="${inputName}" value="${item.id}" ${isChecked ? 'checked' : ''}>
                    <span class="aic-item-name" title="${item[nameField]}">${item[nameField]}</span>
                    <i class="fas fa-check aic-item-check-icon"></i>
                `;
                
                // Cukup gunakan event change pada input. 
                // Karena dibungkus <label>, klik pada teks otomatis trigger input ini.
                itemEl.querySelector('input').addEventListener('change', function() {
                    itemEl.classList.toggle('aic-item-checked', this.checked);
                    updateAicStats();
                });

                grid.appendChild(itemEl);
            });

            container.appendChild(block);
        });
    }

    function updateAicStats() {
        const modelCount = document.querySelectorAll('#aiModelsGrouped input[type=checkbox]:checked').length;
        const keyCount   = document.querySelectorAll('#aiKeysGrouped input[type=checkbox]:checked').length;

        document.getElementById('statModels').textContent = modelCount;
        document.getElementById('statKeys').textContent   = keyCount;
        document.getElementById('badgeModels').textContent = modelCount;
        document.getElementById('badgeKeys').textContent   = keyCount;

        const providerSet = new Set();
        document.querySelectorAll('#aiModelsGrouped .aic-item-checked, #aiKeysGrouped .aic-item-checked').forEach(el => {
            const block = el.closest('.aic-provider-block');
            if (block) providerSet.add(block.dataset.provider);
        });
        document.getElementById('statProviders').textContent = providerSet.size;
    }

    function switchAicTab(tab) {
        _aicActiveTab = tab;
        document.getElementById('aicPanelModels').style.display = tab === 'models' ? 'flex' : 'none';
        document.getElementById('aicPanelKeys').style.display   = tab === 'keys'   ? 'flex' : 'none';

        document.getElementById('tabBtnModels').classList.toggle('active', tab === 'models');
        document.getElementById('tabBtnKeys').classList.toggle('active', tab === 'keys');
        document.getElementById('tabBtnKeys').classList.toggle('key-tab', tab === 'keys');
    }

    function filterAicItems(type) {
        const query = document.getElementById(type === 'models' ? 'searchModels' : 'searchKeys').value.toLowerCase().trim();
        const container = document.getElementById(type === 'models' ? 'aiModelsGrouped' : 'aiKeysGrouped');
        container.querySelectorAll('.aic-provider-block').forEach(block => {
            let visibleCount = 0;
            block.querySelectorAll('.aic-item').forEach(item => {
                const match = !query || item.dataset.name.includes(query);
                item.classList.toggle('aic-item-hidden', !match);
                if (match) visibleCount++;
            });
            block.classList.toggle('aic-block-hidden', visibleCount === 0);
        });
    }

    function selectAllVisible(type, checked) {
        const container = document.getElementById(type === 'models' ? 'aiModelsGrouped' : 'aiKeysGrouped');
        container.querySelectorAll('.aic-item:not(.aic-item-hidden)').forEach(item => {
            const cb = item.querySelector('input');
            if (cb.checked !== checked) {
                cb.checked = checked;
                item.classList.toggle('aic-item-checked', checked);
            }
        });
        updateAicStats();
    }

    function toggleProviderAll(btn, inputName) {
        const block = btn.closest('.aic-provider-block');
        const checkboxes = block.querySelectorAll(`input[name="${inputName}"]`);
        const allChecked = Array.from(checkboxes).every(c => c.checked);
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
            cb.closest('.aic-item').classList.toggle('aic-item-checked', !allChecked);
        });
        btn.textContent = allChecked ? 'Pilih Semua' : 'Hapus Semua';
        updateAicStats();
    }

    function showAiConfig(user) {
        currentEditingUserId = user.id;

        const name = user.name || '?';
        document.getElementById('aiConfigUserName').innerText = name;
        document.getElementById('aiConfigAvatar').innerText = name.charAt(0).toUpperCase();

        const allModels = @json($aiModels);
        const allKeys   = @json($aiKeys);
        const userModels = user.ai_models ? user.ai_models.map(m => m.id) : [];
        const userKeys   = user.ai_keys   ? user.ai_keys.map(k => k.id)   : [];

        const groupedModels = {};
        allModels.forEach(m => {
            const p = m.provider.name;
            if (!groupedModels[p]) groupedModels[p] = [];
            groupedModels[p].push(m);
        });
        const groupedKeys = {};
        allKeys.forEach(k => {
            const p = k.provider.name;
            if (!groupedKeys[p]) groupedKeys[p] = [];
            groupedKeys[p].push(k);
        });

        renderAicGroup(document.getElementById('aiModelsGrouped'), groupedModels, userModels, 'display_name', 'ai_models[]', false);
        renderAicGroup(document.getElementById('aiKeysGrouped'),   groupedKeys,   userKeys,   'key_name',     'ai_keys[]',   true);

        updateAicStats();
        switchAicTab('models');
        document.getElementById('searchModels').value = '';
        document.getElementById('searchKeys').value   = '';

        document.getElementById('aiConfigModal').style.display = 'flex';
    }

    function saveAiConfig() {
        const btn = document.getElementById('btnSaveAiConfig');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

        const formData = new FormData(document.getElementById('aiConfigForm'));

        fetch(`/admin/users/${currentEditingUserId}/ai-config`, {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(async res => {
            const data = await res.json();
            if (data.success) {
                Swal.fire({
                    icon: 'success', title: 'Berhasil',
                    text: 'Konfigurasi AI diperbarui!',
                    timer: 1500, showConfirmButton: false
                }).then(() => location.reload());
            } else {
                throw new Error(data.message || 'Gagal menyimpan konfigurasi');
            }
        })
        .catch(err => {
            Swal.fire({ icon: 'error', title: 'Gagal', text: err.message || 'Terjadi kesalahan.' });
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    }

    function hideAiConfig() { document.getElementById('aiConfigModal').style.display = 'none'; }
    document.getElementById('btnSaveAiConfig').onclick = saveAiConfig;

    function showModal(type, user = null) {
        if (type === 'import') {
            document.getElementById('importModal').style.display = 'flex';
            return;
        }
        const modal = document.getElementById('userModal');
        const form  = document.getElementById('userForm');
        modal.style.display = 'flex';
        if (type === 'create') {
            document.getElementById('modalTitle').innerText = 'Tambah User';
            form.action = "{{ route('admin.users.store') }}";
            document.getElementById('formMethod').value = 'POST';
            form.reset();
            document.getElementById('userMaxTokens').value = 32768;
        } else {
            document.getElementById('modalTitle').innerText = 'Edit User';
            form.action = `/admin/users/${user.id}`;
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('userName').value = user.name;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userRole').value = user.role;
            document.getElementById('userIsAdmin').checked = user.is_admin;
            document.getElementById('userScopeLimited').checked = user.analysis_scope_limited ?? true;
            document.getElementById('userMaxTokens').value = user.max_tokens || 32768;
        }
    }

    function hideModal()       { document.getElementById('userModal').style.display = 'none'; }
    function hideImportModal() { document.getElementById('importModal').style.display = 'none'; }
    function downloadTemplate() { window.location.href = "{{ route('admin.users.template') }}"; }
    function exportUsers()      { window.location.href = "{{ route('admin.users.export') }}"; }

    window.onclick = function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            hideModal(); hideImportModal(); hideAiConfig();
        }
    }
</script>
@endsection
