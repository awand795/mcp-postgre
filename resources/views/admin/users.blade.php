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


<!-- Filter & Search -->
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
                    <th>Hak Akses</th>
                    <th>AI Models</th>
                    <th>API Keys</th>
                    <th>Cakupan</th>
                    <th>Dibuat</th>
                    <th class="th-sticky">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td class="td-name">{{ $user->name }}</td>
                    <td class="td-email" title="{{ $user->email }}">{{ $user->email }}</td>
                    <td>
                        <span class="role-badge">
                            <i class="fas fa-user-tag"></i>
                            {{ $user->roleModel->name ?? 'No Role' }}
                        </span>
                    </td>
                    <td>
                        @if($user->is_super_admin)
                            <span class="status-yes" style="background: rgba(99,102,241,0.1); color: #4338ca; border-color: rgba(99,102,241,0.2);"><i class="fas fa-crown"></i> Super Admin</span>
                        @elseif($user->is_admin)
                            <span class="status-yes"><i class="fas fa-user-shield"></i> Admin</span>
                        @else
                            <span class="status-no"><i class="fas fa-user"></i> User Biasa</span>
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
                            <span class="scope-badge scope-limited"><i class="fas fa-database"></i> Database</span>
                        @else
                            <span class="scope-badge scope-free"><i class="fas fa-globe"></i> Bebas</span>
                        @endif
                    </td>
                    <td>
                        <div class="metadata-wrap">
                            <span class="metadata-user"><i class="fas fa-user-edit"></i> {{ $user->addedBy->name ?? 'System' }}</span>
                            <span class="metadata-date"><i class="far fa-calendar-alt"></i> {{ $user->created_at->format('d/m/y') }}</span>
                            <span class="metadata-time" style="font-size: 0.65rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; opacity: 0.8;">
                                <i class="far fa-clock"></i> {{ $user->created_at->format('H:i') }}
                            </span>
                        </div>
                    </td>
                    <td class="td-sticky">
                        <div class="action-buttons">
                            <button class="btn btn-filter {{ $user->tableFilters->count() > 0 ? 'btn-filter-active' : '' }}" onclick="showTableFilters({{ json_encode($user) }})" title="Data Filter (RLS)" style="position:relative;">
                                <i class="fas fa-filter"></i>
                                @if($user->tableFilters->count() > 0)
                                    <span class="filter-count-badge">{{ $user->tableFilters->count() }}</span>
                                @endif
                            </button>
                            <button class="btn btn-info" onclick="showAiConfig({{ json_encode($user) }})" title="AI Config">
                                <i class="fas fa-robot"></i>
                            </button>
                            <button class="btn btn-edit" onclick="showModal('edit', {{ json_encode($user) }})"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('admin.users.delete', $user->id) }}" method="POST" onsubmit="confirmDelete(event, 'Hapus User?', 'Akses user ini ke sistem akan segera dicabut.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="empty-state">
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
                        <option value="{{ $role->id }}" style="color: black;">{{ $role->name }} (by {{ $role->addedBy->name ?? 'System' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Max Tokens</label>
                <input type="number" name="max_tokens" id="userMaxTokens" value="32768" required>
            </div>
            
            @if(auth()->user()->is_super_admin)
            <div class="form-group checkbox-group">
                <input type="checkbox" name="is_admin" id="userIsAdmin" value="1" onchange="document.getElementById('userIsSuperAdmin').checked = false;">
                <label for="userIsAdmin">Jadikan Admin</label>
            </div>
            <div class="form-group checkbox-group" style="margin-top: 0.5rem;">
                <input type="checkbox" name="is_super_admin" id="userIsSuperAdmin" value="1" onchange="document.getElementById('userIsAdmin').checked = false;">
                <label for="userIsSuperAdmin">Jadikan Super Admin</label>
            </div>
            @endif
            
            <div class="form-group checkbox-group" style="margin-top: 0.5rem;">
                <input type="checkbox" name="analysis_scope_limited" id="userScopeLimited" value="1" checked>
                <label for="userScopeLimited">Hanya dari database</label>
            </div>
            <p style="color: #64748b; font-size: 0.78rem; margin: -0.75rem 0 1rem 0; padding-left: 0.25rem;">
                <i class="fas fa-info-circle" style="color:#6366f1"></i>
                &#10003; Dicentang = Hanya dari database
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

<!-- ═══════════════════════════════════════════════════════════
     TABLE FILTER MODAL (RLS) — VISUAL RULE BUILDER
     ═══════════════════════════════════════════════════════════ -->
<div id="tableFilterModal" class="modal-overlay">
    <div class="tf-modal glass-card">
        <!-- Header -->
        <div class="tf-header">
            <div class="tf-header-left">
                <div class="tf-icon-circle"><i class="fas fa-shield-halved"></i></div>
                <div>
                    <p class="tf-label">Pembatasan Data (Row-Level Security)</p>
                    <h3 class="tf-username" id="tfUserName">—</h3>
                </div>
            </div>
            <div class="tf-header-actions">
                <button class="tf-copy-btn" onclick="showCopyFilterModal()" title="Salin Filter dari User Lain">
                    <i class="fas fa-copy"></i> Salin
                </button>
                <button class="tf-close-btn" onclick="hideTableFilters()" title="Tutup">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="tf-body">
            <div class="tf-sidebar">
                <div class="tf-sidebar-header">
                    <i class="fas fa-database"></i> <span>Tabel Terdeteksi</span>
                </div>
                <div class="tf-table-list" id="tfTableList"></div>
            </div>

            <div class="tf-content">
                <div id="tfEmptyState" class="tf-empty-panel">
                    <i class="fas fa-mouse-pointer"></i>
                    <p>Pilih tabel di sebelah kiri untuk mengatur kondisi filter.</p>
                </div>

                <div id="tfConfigPanel" class="tf-config-panel" style="display: none;">
                    <div class="tf-table-header-info">
                        <h4 id="tfActiveTableName">Nama Tabel</h4>
                        <span id="tfActiveDbName" class="tf-db-badge">Database Name</span>
                    </div>

                    <!-- Rule Builder -->
                    <div class="tf-rules-area">
                        <div class="tf-rules-header">
                            <label class="tf-input-label"><i class="fas fa-filter"></i> Aturan Filter</label>
                            <button type="button" class="tf-add-rule-btn" onclick="addRuleRow()">
                                <i class="fas fa-plus"></i> Tambah Kondisi
                            </button>
                        </div>
                        <div id="tfRulesGridHeader" class="tf-rules-grid-header" style="display: none;">
                            <span>Kolom</span>
                            <span>Kondisi</span>
                            <span>Isi Kondisi / Nilai</span>
                            <span></span>
                        </div>
                        <div id="tfRulesContainer"></div>
                        <p class="tf-help-text">
                            <i class="fas fa-info-circle"></i> Kosongkan semua untuk mengizinkan akses ke seluruh data tabel ini.
                        </p>
                    </div>

                    <!-- Preview -->
                    <div class="tf-preview-area">
                        <button type="button" class="tf-preview-btn" onclick="previewFilter()">
                            <i class="fas fa-eye"></i> Preview Data (5 Baris)
                        </button>
                        <div id="tfPreviewResult" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tf-footer">
            <button type="button" class="btn btn-cancel" onclick="hideTableFilters()">Batal</button>
            <button type="button" class="btn btn-primary" id="btnSaveTableFilters">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
        </div>
    </div>
</div>

<style>
    /* ── Existing styles (unchanged) ─────────────────────────────────── */
    .filter-group select option { background: var(--select-bg); color: var(--input-text); }
    .action-buttons { display: flex; gap: 6px; flex-wrap: nowrap; }
    td.td-sticky { white-space: nowrap; }

    /* Active filter button — green when user has RLS rules */
    .btn-filter-active {
        background: rgba(16,185,129,0.18) !important; color: #059669 !important;
        border-color: rgba(16,185,129,0.35) !important;
    }
    html.dark .btn-filter-active {
        background: rgba(16,185,129,0.25) !important; color: #34d399 !important;
        border-color: rgba(16,185,129,0.4) !important;
    }
    /* Number badge indicator */
    .filter-count-badge {
        position: absolute; top: -6px; right: -6px;
        min-width: 16px; height: 16px; padding: 0 4px;
        border-radius: 10px; display: flex; align-items: center; justify-content: center;
        background: #10b981; color: white;
        font-size: 0.58rem; font-weight: 800; line-height: 1;
        border: 2px solid var(--card-bg);
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .btn-edit, .btn-delete, .btn-info, .btn-filter { padding: 8px 10px; font-size: 0.8rem; border-radius: 8px; }
    .btn-delete { color: #ef4444 !important; }
    html.dark .btn-delete { color: #f87171 !important; }

    .ai-pill-group { display: flex; flex-wrap: wrap; gap: 3px; max-width: 160px; }
    .ai-pill {
        display: inline-flex; align-items: center;
        padding: 2px 7px; border-radius: 20px;
        font-size: 0.68rem; font-weight: 700;
        white-space: nowrap; max-width: 120px;
        overflow: hidden; text-overflow: ellipsis; cursor: default;
    }
    .ai-pill-model { background: rgba(99,102,241,0.13); color: #4338ca; border: 1.5px solid rgba(99,102,241,0.3); }
    .ai-pill-key   { background: rgba(16,185,129,0.12); color: #047857; border: 1.5px solid rgba(16,185,129,0.3); }
    html.dark .ai-pill-model { color: #a5b4fc; border-color: rgba(99,102,241,0.25); background: rgba(99,102,241,0.15); }
    html.dark .ai-pill-key { color: #6ee7b7; border-color: rgba(16,185,129,0.2); background: rgba(16,185,129,0.13); }
    .ai-pill-more  { background: rgba(99,102,241,0.07); color: var(--text-muted); border: 1.5px solid var(--glass-border); cursor: pointer; position: relative; }
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
    .scope-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
    .scope-limited { background: rgba(99,102,241,0.12); color: #4338ca; border: 1.5px solid rgba(99,102,241,0.28); }
    .scope-free    { background: rgba(16,185,129,0.12); color: #047857; border: 1.5px solid rgba(16,185,129,0.28); }
    html.dark .scope-limited { color: #818cf8; border-color: rgba(99,102,241,0.2); background: rgba(99,102,241,0.15); }
    html.dark .scope-free    { color: #34d399; border-color: rgba(16,185,129,0.2); background: rgba(16,185,129,0.15); }

    .role-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 20px; background: rgba(99,102,241,0.1); color: #4338ca; border: 1.5px solid rgba(99,102,241,0.2); font-size: 0.72rem; font-weight: 700; }
    html.dark .role-badge { color: #a5b4fc; border-color: rgba(99,102,241,0.25); background: rgba(99,102,241,0.15); }
    
    .status-yes, .status-no { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
    .status-yes { background: rgba(16,185,129,0.1); color: #047857; border: 1.5px solid rgba(16,185,129,0.2); }
    .status-no { background: rgba(239,68,68,0.1); color: #b91c1c; border: 1.5px solid rgba(239,68,68,0.2); }
    html.dark .status-yes { color: #6ee7b7; border-color: rgba(16,185,129,0.2); background: rgba(16,185,129,0.13); }
    html.dark .status-no { color: #f87171; border-color: rgba(239,68,68,0.2); background: rgba(239,68,68,0.13); }

    .metadata-wrap { display: flex; flex-direction: column; gap: 2px; }
    .metadata-user { font-size: 0.78rem; font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 5px; }
    .metadata-user i { color: var(--primary); font-size: 0.75rem; }
    .metadata-date { font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
    .metadata-date i { font-size: 0.7rem; }

    .td-email { max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .td-name { font-weight: 600; color: var(--text-main); }
    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .table-card { padding: 0; overflow: visible; margin-top: 1rem; }
    .table-responsive { 
        overflow-x: auto; 
        -webkit-overflow-scrolling: touch; 
        padding-bottom: 5px;
    }
    /* Custom Scrollbar for Table */
    .table-responsive::-webkit-scrollbar { height: 8px; display: block; }
    .table-responsive::-webkit-scrollbar-track { background: var(--glass-border2); border-radius: 10px; }
    .table-responsive::-webkit-scrollbar-thumb { 
        background: var(--primary); 
        border-radius: 10px; 
        border: 2px solid transparent; 
        background-clip: content-box; 
    }
    .table-responsive::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }

    table { width: 100%; min-width: auto; border-collapse: collapse; color: var(--text-main); table-layout: auto; }
    th { padding: 0.75rem 0.6rem; text-align: left; color: var(--table-head-color); background: var(--table-head-bg); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid var(--glass-border); }
    td { padding: 0.75rem 0.6rem; border-bottom: 1px solid var(--table-border); font-size: 0.82rem; color: var(--text-main); }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--table-row-hover); }
    .th-sticky { 
        position: sticky; right: 0; z-index: 3; 
        background: var(--table-head-bg) !important; 
        box-shadow: -6px 0 12px rgba(0,0,0,0.08); 
    }
    .td-sticky { 
        position: sticky; right: 0; z-index: 2; 
        background: var(--card-bg) !important; 
        box-shadow: -6px 0 12px rgba(0,0,0,0.06); 
        transition: background 0.2s; 
    }
    tr:hover .td-sticky { background: rgba(99,102,241,0.04); }
    html.dark .th-sticky { background: #1e1b4b !important; box-shadow: -6px 0 15px rgba(0,0,0,0.4); }
    html.dark .td-sticky { background: #111827 !important; box-shadow: -6px 0 15px rgba(0,0,0,0.4); }
    html.dark tr:hover .td-sticky { background: #1e293b !important; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(6px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; }
    .modal-content { width: 100%; max-width: 500px; max-height: 95vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); background: var(--card-bg); }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.6rem; color: var(--text-muted); font-weight: 600; font-size: 0.9rem; }
    .form-group input, .form-group select { width: 100%; background: var(--input-bg); border: 1.5px solid var(--input-border); padding: 0.8rem 1rem; border-radius: 12px; color: var(--input-text); font-family: 'Outfit', sans-serif; transition: all 0.3s; box-shadow: inset 0 1px 3px rgba(99,102,241,0.05); }
    .form-group input:focus, .form-group select:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    .checkbox-group { display: flex; align-items: center; gap: 12px; background: var(--input-bg); padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid var(--input-border); }
    .checkbox-group input { width: 18px !important; height: 18px !important; cursor: pointer; accent-color: var(--primary); }
    .checkbox-group label { margin-bottom: 0; cursor: pointer; color: var(--text-main); }
    .pagination-container { margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
    .pagination-info { color: var(--text-muted); font-size: 0.9rem; }

    .btn-cancel {
        background: #fff1f2; color: #e11d48; border: 1px solid #fda4af;
        padding: 0.6rem 1.25rem; border-radius: 10px;
        font-weight: 600; cursor: pointer; transition: all 0.2s;
        font-family: inherit; font-size: 0.85rem;
    }
    .btn-cancel:hover { background: #ffe4e6; color: #be123c; border-color: #f43f5e; transform: translateY(-1px); }
    html.dark .btn-cancel {
        background: rgba(225, 29, 72, 0.1); color: #fb7185; border-color: rgba(225, 29, 72, 0.2);
    }
    html.dark .btn-cancel:hover { background: rgba(225, 29, 72, 0.2); color: #fda4af; border-color: rgba(225, 29, 72, 0.3); }

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
        background: var(--bg-secondary);
        border-bottom: 1px solid var(--glass-border);
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
    .aic-item-checked .aic-item-name { color: #4338ca; font-weight: 700; }
    html.dark .aic-item-checked .aic-item-name { color: #c7d2fe; }
    .aic-item-checked.aic-item-key .aic-item-name { color: #047857; }
    html.dark .aic-item-checked.aic-item-key .aic-item-name { color: #6ee7b7; }
    .aic-item-owner { font-size: 0.65rem; color: var(--text-muted); opacity: 0.7; margin-top: -1px; }
    .aic-item-checked .aic-item-owner { opacity: 0.9; }

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

<style>
    /* ── Table Filter Modal (TF) — Visual Rule Builder ── */
    .tf-modal { 
        width: 100%; max-width: 920px; height: 650px; 
        display: flex; flex-direction: column; overflow: hidden; border-radius: 20px;
    }
    .tf-header { padding: 1.25rem 1.75rem; border-bottom: 1px solid var(--glass-border2); display: flex; justify-content: space-between; align-items: center; }
    .tf-header-left { display: flex; align-items: center; gap: 1rem; }
    .tf-header-actions { display: flex; align-items: center; gap: 8px; }
    .tf-icon-circle { width: 42px; height: 42px; border-radius: 12px; background: rgba(99,102,241,0.1); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .tf-label { font-size: 0.72rem; font-weight: 700; color: #6366f1; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
    .tf-username { font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0; }
    .tf-close-btn { background: none; border: none; color: var(--text-muted); font-size: 1.1rem; cursor: pointer; padding: 6px; }
    .tf-copy-btn { 
        padding: 6px 14px; border-radius: 8px; border: 1px solid rgba(99,102,241,0.2);
        background: rgba(99,102,241,0.08); color: #818cf8; font-size: 0.75rem; font-weight: 600;
        cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 5px;
    }
    .tf-copy-btn:hover { background: rgba(99,102,241,0.18); }
    
    .tf-body { display: flex; flex: 1; overflow: hidden; }
    .tf-sidebar { width: 260px; border-right: 1px solid var(--glass-border2); background: rgba(0,0,0,0.02); display: flex; flex-direction: column; flex-shrink: 0; }
    .tf-sidebar-header { padding: 1rem 1.25rem; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--glass-border2); flex-shrink: 0; }
    .tf-table-list { flex: 1; overflow-y: auto; padding: 0.75rem; display: flex; flex-direction: column; gap: 4px; }
    
    .tf-table-item { 
        padding: 0.65rem 0.9rem; border-radius: 10px; cursor: pointer; transition: all 0.2s;
        display: flex; align-items: center; gap: 10px; border: 1px solid transparent; position: relative;
    }
    .tf-table-item:hover { background: rgba(99,102,241,0.05); }
    .tf-table-item.active { background: rgba(99,102,241,0.1); border-color: rgba(99,102,241,0.2); }
    .tf-ti-info { display: flex; flex-direction: column; gap: 1px; flex: 1; min-width: 0; }
    .tf-ti-name { font-size: 0.8rem; font-weight: 600; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tf-ti-db { font-size: 0.65rem; color: var(--text-muted); }
    .tf-ti-badge { 
        padding: 2px 6px; border-radius: 5px; font-size: 0.6rem; font-weight: 700;
        background: rgba(16,185,129,0.15); color: #10b981; flex-shrink: 0;
    }

    .tf-content { flex: 1; display: flex; flex-direction: column; background: var(--card-bg); min-width: 0; }
    .tf-empty-panel { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); text-align: center; padding: 2rem; }
    .tf-empty-panel i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3; }
    
    .tf-config-panel { flex: 1; display: flex; flex-direction: column; padding: 1.5rem; overflow-y: auto; min-width: 0; }
    .tf-table-header-info { margin-bottom: 1.25rem; }
    .tf-table-header-info h4 { font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 5px; }
    .tf-db-badge { display: inline-block; padding: 2px 8px; border-radius: 5px; background: rgba(99,102,241,0.1); color: #6366f1; font-size: 0.7rem; font-weight: 600; }
    
    /* Rule Builder */
    .tf-rules-area { margin-bottom: 1.25rem; }
    .tf-rules-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .tf-input-label { font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin: 0; }
    .tf-add-rule-btn { 
        padding: 5px 12px; border-radius: 8px; border: 1px dashed rgba(99,102,241,0.3);
        background: transparent; color: #818cf8; font-size: 0.75rem; font-weight: 600;
        cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 4px;
    }
    .tf-add-rule-btn:hover { background: rgba(99,102,241,0.08); border-style: solid; }

    .tf-rules-grid-header {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) 100px minmax(0, 2fr) 36px;
        gap: 10px;
        padding: 0 12px 8px 12px;
        font-size: 0.68rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .tf-rule-row {
        display: grid;
        grid-template-columns: minmax(0, 1.5fr) 100px minmax(0, 2fr) 36px;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
        padding: 12px;
        border-radius: 14px;
        background: var(--input-bg);
        border: 1px solid var(--glass-border2);
        transition: all 0.2s;
        min-width: 0;
        width: 100%;
    }
    .tf-rule-row:hover { border-color: rgba(99,102,241,0.25); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .tf-rule-row select, .tf-rule-row input {
        width: 100%;
        min-width: 0;
        padding: 8px 12px; border-radius: 10px; border: 1px solid var(--input-border);
        background: var(--card-bg); color: var(--text-main); font-size: 0.82rem;
        font-family: 'Outfit', sans-serif; outline: none; transition: all 0.2s;
        height: 38px;
        box-sizing: border-box;
    }
    .tf-rule-row select:focus, .tf-rule-row input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
    .tf-rule-col { min-width: 0; overflow: hidden; }
    .tf-rule-op { min-width: 0; }
    .tf-rule-val { min-width: 0; }
    .tf-rule-del {
        width: 36px; height: 36px; border-radius: 10px; border: none;
        background: rgba(239,68,68,0.08); color: #ef4444; cursor: pointer;
        display: flex; align-items: center; justify-content: center; font-size: 0.85rem;
        transition: all 0.2s; justify-self: center;
    }
    .tf-rule-del:hover { background: #ef4444; color: white; transform: rotate(90deg); }
    .tf-rule-logic {
        display: flex; align-items: center; justify-content: center;
        margin: 4px 0; padding: 4px 0; position: relative;
    }
    .tf-rule-logic::before, .tf-rule-logic::after {
        content: ''; height: 1px; flex: 1; background: var(--glass-border2);
    }
    .tf-logic-select {
        margin: 0 15px; padding: 4px 12px; border-radius: 8px; border: 1px solid rgba(99,102,241,0.25);
        background: var(--card-bg); color: #6366f1; font-size: 0.7rem; font-weight: 800;
        text-transform: uppercase; cursor: pointer; outline: none;
        font-family: 'Outfit', sans-serif; text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .tf-logic-select:focus { border-color: #6366f1; }
    
    .tf-help-text { font-size: 0.75rem; color: var(--text-muted); margin-top: 8px; }
    
    /* Preview */
    .tf-preview-area { margin-top: auto; padding-top: 1rem; min-width: 0; }
    .tf-preview-btn {
        padding: 8px 16px; border-radius: 10px; border: 1px solid rgba(16,185,129,0.2);
        background: rgba(16,185,129,0.08); color: #10b981; font-size: 0.8rem; font-weight: 600;
        cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;
    }
    .tf-preview-btn:hover { background: rgba(16,185,129,0.15); }
    .tf-preview-table { min-width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.75rem; table-layout: auto; }
    .tf-preview-table th { background: rgba(99,102,241,0.08); color: #6366f1; font-weight: 700; padding: 8px 12px; text-align: left; border-bottom: 1px solid var(--glass-border2); white-space: nowrap; }
    .tf-preview-table td { padding: 8px 12px; border-bottom: 1px solid var(--glass-border2); color: var(--text-main); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tf-preview-total { font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; }

    .tf-footer { padding: 1.25rem 1.75rem; border-top: 1px solid var(--glass-border2); display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; }
    
    .btn-filter { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
    .btn-filter:hover { background: rgba(16,185,129,0.2); }
    html.dark .btn-filter { background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.25); color: #34d399; }

    @media (max-width: 768px) {
        .tf-modal { height: 95vh; max-height: 95vh; }
        .tf-body { flex-direction: column; }
        .tf-sidebar { width: 100%; height: 180px; border-right: none; border-bottom: 1px solid var(--glass-border2); }
        .tf-rule-row { display: flex; flex-direction: column; gap: 8px; }
        .tf-rule-col, .tf-rule-op, .tf-rule-val { width: 100% !important; }
        .tf-rule-del { align-self: flex-end; }
        .tf-rules-grid-header { display: none; }
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
                const ownerName = item.added_by ? item.added_by.name : 'System';
                const ownerHtml = isKey ? `<span class="aic-item-owner">by ${ownerName}</span>` : '';
                itemEl.dataset.name = (item[nameField] || '').toLowerCase();
                itemEl.innerHTML = `
                    <input type="checkbox" name="${inputName}" value="${item.id}" ${isChecked ? 'checked' : ''}>
                    <div style="display:flex; flex-direction:column; gap:0; flex:1; min-width:0;">
                        <span class="aic-item-name" title="${item[nameField]}">${item[nameField]}</span>
                        ${ownerHtml}
                    </div>
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
            background: isDark ? 'rgba(15,23,42,0.97)' : '#ffffff',
            color: isDark ? '#f1f5f9' : '#1e293b',
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
        return false;
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
            const isDark = document.documentElement.classList.contains('dark');
            const toastBase = {
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: isDark ? 'rgba(15,23,42,0.97)' : '#ffffff',
                color: isDark ? '#f1f5f9' : '#1e293b',
            };

            if (data.success) {
                Swal.fire({
                    ...toastBase,
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Konfigurasi AI diperbarui!',
                    iconColor: '#10b981'
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
            if(document.getElementById('userIsAdmin')) document.getElementById('userIsAdmin').checked = user.is_admin;
            if(document.getElementById('userIsSuperAdmin')) document.getElementById('userIsSuperAdmin').checked = user.is_super_admin;
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
    /* ── TABLE FILTER (RLS) — VISUAL RULE BUILDER LOGIC ── */
    let _tfTargetUser = null;
    let _tfTables = [];
    let _tfActiveTableIdx = -1;
    let _tfColumns = []; // columns for active table

    async function showTableFilters(user) {
        _tfTargetUser = user;
        document.getElementById('tfUserName').innerText = user.name;
        document.getElementById('tfTableList').innerHTML = '<div style="padding:1rem; font-size:0.8rem; color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Memuat tabel...</div>';
        document.getElementById('tfEmptyState').style.display = 'flex';
        document.getElementById('tfConfigPanel').style.display = 'none';
        document.getElementById('tableFilterModal').style.display = 'flex';
        _tfActiveTableIdx = -1;

        try {
            const res = await fetch(`/admin/users/${user.id}/table-filters`);
            const data = await res.json();
            _tfTables = data.tables || [];
            renderTfTableList();
        } catch (e) {
            Swal.fire('Error', 'Gagal memuat daftar tabel.', 'error');
        }
    }

    function renderTfTableList() {
        const list = document.getElementById('tfTableList');
        if (_tfTables.length === 0) {
            list.innerHTML = '<div style="padding:1rem; font-size:0.8rem; color:var(--text-muted);">Tidak ada tabel yang diizinkan untuk role ini.</div>';
            return;
        }

        list.innerHTML = _tfTables.map((t, idx) => {
            const hasRules = t.rules && t.rules.length > 0;
            const badge = hasRules ? `<span class="tf-ti-badge">${t.rules.length} filter</span>` : '';
            return `
                <div class="tf-table-item ${idx === _tfActiveTableIdx ? 'active' : ''}" onclick="selectTfTable(${idx})">
                    <div class="tf-ti-info">
                        <span class="tf-ti-name">${t.table_name}</span>
                        <span class="tf-ti-db">${t.db_name}</span>
                    </div>
                    ${badge}
                </div>
            `;
        }).join('');
    }

    async function selectTfTable(idx) {
        // Save current rules before switching
        if (_tfActiveTableIdx !== -1) {
            _tfTables[_tfActiveTableIdx].rules = collectRulesFromUI();
        }

        _tfActiveTableIdx = idx;
        const table = _tfTables[idx];

        document.getElementById('tfActiveTableName').innerText = table.table_name;
        document.getElementById('tfActiveDbName').innerText = table.db_name;
        document.getElementById('tfEmptyState').style.display = 'none';
        document.getElementById('tfConfigPanel').style.display = 'flex';
        document.getElementById('tfPreviewResult').style.display = 'none';

        // Load columns for dropdown
        _tfColumns = [];
        try {
            const res = await fetch(`/admin/users/table-columns?db_id=${table.db_id}&table_name=${table.table_name}&schema=${table.schema || 'public'}`);
            const data = await res.json();
            _tfColumns = data.columns || [];
        } catch(e) { /* silent */ }

        // Render existing rules
        renderRulesUI(table.rules || []);
        renderTfTableList();
    }

    function updateTfRulesHeader() {
        const container = document.getElementById('tfRulesContainer');
        const header = document.getElementById('tfRulesGridHeader');
        if (!header) return;
        const hasRows = container.querySelectorAll('.tf-rule-row').length > 0;
        header.style.display = hasRows ? 'grid' : 'none';
    }

    function renderRulesUI(rules) {
        const container = document.getElementById('tfRulesContainer');
        container.innerHTML = '';
        if (rules.length === 0) {
            container.innerHTML = '<div style="padding:1.5rem; text-align:center; color:var(--text-muted); font-size:0.8rem; border:1px dashed var(--glass-border2); border-radius:10px;"><i class="fas fa-plus-circle" style="font-size:1.2rem; margin-bottom:8px; display:block; opacity:0.4;"></i>Belum ada filter. Klik "Tambah Kondisi" untuk menambahkan.</div>';
            updateTfRulesHeader();
            return;
        }
        rules.forEach((rule, i) => {
            if (i > 0) {
                const logic = rule.logic || 'AND';
                appendLogicSelector(logic);
            }
            appendRuleRow(rule.column, rule.operator, rule.value);
        });
        updateTfRulesHeader();
    }

    function addRuleRow() {
        const container = document.getElementById('tfRulesContainer');
        const hint = container.querySelector('[style*="dashed"]');
        if (hint) hint.remove();

        const existingRows = container.querySelectorAll('.tf-rule-row');
        if (existingRows.length > 0) {
            appendLogicSelector('AND');
        }
        appendRuleRow('', '=', '');
        updateTfRulesHeader();
    }

    function appendLogicSelector(selected) {
        const container = document.getElementById('tfRulesContainer');
        const div = document.createElement('div');
        div.className = 'tf-rule-logic';
        div.innerHTML = `
            <select class="tf-logic-select">
                <option value="AND" ${selected === 'AND' ? 'selected' : ''}>AND</option>
                <option value="OR" ${selected === 'OR' ? 'selected' : ''}>OR</option>
            </select>
        `;
        container.appendChild(div);
    }

    function appendRuleRow(column, operator, value) {
        const container = document.getElementById('tfRulesContainer');
        const colOptions = _tfColumns.map(c =>
            `<option value="${c.name}" ${c.name === column ? 'selected' : ''}>${c.name} (${c.type})</option>`
        ).join('');

        const ops = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'ILIKE', 'IN', 'NOT IN'];
        const opOptions = ops.map(o =>
            `<option value="${o}" ${o === operator ? 'selected' : ''}>${o}</option>`
        ).join('');

        const row = document.createElement('div');
        row.className = 'tf-rule-row';
        row.innerHTML = `
            <select class="tf-rule-col">${colOptions.length ? colOptions : `<option value="${column}">${column || '-- Pilih Kolom --'}</option>`}</select>
            <select class="tf-rule-op">${opOptions}</select>
            <input class="tf-rule-val" type="text" placeholder="Nilai (misal: B282)" value="${value || ''}">
            <button class="tf-rule-del" onclick="removeRuleRow(this)" title="Hapus kondisi"><i class="fas fa-trash-alt"></i></button>
        `;
        container.appendChild(row);
    }

    function removeRuleRow(btn) {
        const row = btn.closest('.tf-rule-row');
        const prev = row.previousElementSibling;
        const next = row.nextElementSibling;
        // Remove associated logic selector (before or after)
        if (prev && prev.classList.contains('tf-rule-logic')) {
            prev.remove();
        } else if (next && next.classList.contains('tf-rule-logic')) {
            next.remove();
        }
        row.remove();

        const container = document.getElementById('tfRulesContainer');
        // Fix leading logic selector
        const firstChild = container.firstElementChild;
        if (firstChild && firstChild.classList.contains('tf-rule-logic')) firstChild.remove();

        if (container.querySelectorAll('.tf-rule-row').length === 0) {
            container.innerHTML = '<div style="padding:1.5rem; text-align:center; color:var(--text-muted); font-size:0.8rem; border:1px dashed var(--glass-border2); border-radius:10px;"><i class="fas fa-plus-circle" style="font-size:1.2rem; margin-bottom:8px; display:block; opacity:0.4;"></i>Belum ada filter. Klik "Tambah Kondisi" untuk menambahkan.</div>';
        }
        updateTfRulesHeader();
    }

    function collectRulesFromUI() {
        const container = document.getElementById('tfRulesContainer');
        const children = Array.from(container.children);
        const rules = [];
        let nextLogic = 'AND';
        children.forEach(el => {
            if (el.classList.contains('tf-rule-logic')) {
                nextLogic = el.querySelector('.tf-logic-select').value;
            } else if (el.classList.contains('tf-rule-row')) {
                const col = el.querySelector('.tf-rule-col').value;
                const op = el.querySelector('.tf-rule-op').value;
                const val = el.querySelector('.tf-rule-val').value.trim();
                if (col && val) {
                    rules.push({ column: col, operator: op, value: val, logic: rules.length === 0 ? 'AND' : nextLogic });
                }
                nextLogic = 'AND';
            }
        });
        return rules;
    }

    async function previewFilter() {
        const rules = collectRulesFromUI();
        if (rules.length === 0) {
            Swal.fire('Info', 'Tambahkan minimal 1 kondisi untuk preview.', 'info');
            return;
        }

        const table = _tfTables[_tfActiveTableIdx];
        const resultDiv = document.getElementById('tfPreviewResult');
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div style="padding:0.75rem; font-size:0.8rem; color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Memuat preview...</div>';

        try {
            const res = await fetch('/admin/users/preview-filter', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ db_id: table.db_id, table_name: table.table_name, schema: table.schema || 'public', rules })
            });
            const data = await res.json();
            if (data.success && data.rows.length > 0) {
                const cols = Object.keys(data.rows[0]);
                let html = `<p class="tf-preview-total"><strong>${data.total}</strong> baris cocok dengan filter ini</p>`;
                html += '<div style="overflow-x:auto;"><table class="tf-preview-table"><thead><tr>';
                cols.forEach(c => html += `<th>${c}</th>`);
                html += '</tr></thead><tbody>';
                data.rows.forEach(row => {
                    html += '<tr>';
                    cols.forEach(c => html += `<td title="${row[c] ?? ''}">${row[c] ?? '-'}</td>`);
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                resultDiv.innerHTML = html;
            } else if (data.success) {
                resultDiv.innerHTML = '<p style="font-size:0.8rem; color:#ef4444; padding:0.5rem;">⚠ Tidak ada data yang cocok dengan filter ini.</p>';
            } else {
                resultDiv.innerHTML = `<p style="font-size:0.8rem; color:#ef4444; padding:0.5rem;">Error: ${data.error}</p>`;
            }
        } catch(e) {
            resultDiv.innerHTML = `<p style="font-size:0.8rem; color:#ef4444; padding:0.5rem;">Error: ${e.message}</p>`;
        }
    }

    async function saveTableFilters() {
        // Save current rules in memory first
        if (_tfActiveTableIdx !== -1) {
            _tfTables[_tfActiveTableIdx].rules = collectRulesFromUI();
        }

        const btn = document.getElementById('btnSaveTableFilters');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

        const payload = {
            filters: _tfTables.map(t => ({
                db_id: t.db_id,
                table_name: t.table_name,
                rules: t.rules || []
            }))
        };

        try {
            const res = await fetch(`/admin/users/${_tfTargetUser.id}/table-filters`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Pembatasan data berhasil diperbarui!', timer: 2000, showConfirmButton: false });
                hideTableFilters();
            } else {
                throw new Error(data.message || 'Gagal menyimpan.');
            }
        } catch (e) {
            Swal.fire('Gagal', e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    function hideTableFilters() {
        document.getElementById('tableFilterModal').style.display = 'none';
        _tfActiveTableIdx = -1;
    }

    function showCopyFilterModal() {
        document.getElementById('copyFilterModal').style.display = 'flex';
    }

    async function executeCopyFilter() {
        const sourceId = document.getElementById('copySourceUser').value;
        if (!sourceId) { Swal.fire('Info', 'Pilih user sumber terlebih dahulu.', 'info'); return; }

        try {
            const res = await fetch(`/admin/users/${_tfTargetUser.id}/copy-filters`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ source_user_id: sourceId })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('copyFilterModal').style.display = 'none';
                Swal.fire({ icon: 'success', title: 'Berhasil', text: `${data.copied} filter berhasil disalin!`, timer: 2000, showConfirmButton: false });
                // Reload filter data
                showTableFilters(_tfTargetUser);
            } else {
                throw new Error(data.error || 'Gagal menyalin.');
            }
        } catch(e) {
            Swal.fire('Gagal', e.message, 'error');
        }
    }

    document.getElementById('btnSaveTableFilters').onclick = saveTableFilters;
</script>
@endsection
