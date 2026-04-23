@extends('layouts.admin')

@section('content')
<div class="header">
    <h1>Management User</h1>
    <div class="header-actions">
        <button class="btn btn-success" onclick="downloadTemplate()"><i class="fas fa-download"></i> <span>Template</span></button>
        <button class="btn btn-info" onclick="showModal('import')"><i class="fas fa-file-import"></i> <span>Import</span></button>
        <button class="btn btn-secondary" onclick="exportUsers()"><i class="fas fa-file-export"></i> <span>Export</span></button>
        <button class="btn btn-primary" onclick="showModal('create')"><i class="fas fa-plus"></i> <span>Tambah User</span></button>
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
                    <th>Cakupan</th>
                    <th>AI Models</th>
                    <th>API Keys</th>
                    <th>Aksi</th>
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
                        @if($user->analysis_scope_limited)
                            <span class="scope-badge scope-limited"><i class="fas fa-database"></i> DB & ERP</span>
                        @else
                            <span class="scope-badge scope-free"><i class="fas fa-globe"></i> Bebas</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $enabledModels = $user->aiModels->where('pivot.is_enabled', true)->values();
                            $visibleModels = $enabledModels->take(2);
                            $extraModels   = $enabledModels->slice(2);
                        @endphp
                        @if($enabledModels->isEmpty())
                            <span class="ai-none">—</span>
                        @else
                            <div class="ai-pill-group">
                                @foreach($visibleModels as $m)
                                    <span class="ai-pill ai-pill-model" title="{{ $m->display_name }}">{{ Str::limit($m->display_name, 18) }}</span>
                                @endforeach
                                @if($extraModels->count())
                                    <span class="ai-pill ai-pill-more"
                                          data-tooltip="{{ $extraModels->pluck('display_name')->implode(', ') }}">
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
                            <span class="ai-none">—</span>
                        @else
                            <div class="ai-pill-group">
                                @foreach($visibleKeys as $k)
                                    <span class="ai-pill ai-pill-key" title="{{ $k->key_name }}">{{ Str::limit($k->key_name, 18) }}</span>
                                @endforeach
                                @if($extraKeys->count())
                                    <span class="ai-pill ai-pill-more"
                                          data-tooltip="{{ $extraKeys->pluck('key_name')->implode(', ') }}">
                                        +{{ $extraKeys->count() }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>
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

<!-- AI Configuration Modal -->
<div id="aiConfigModal" class="modal-overlay">
    <div class="glass-card modal-content" style="max-width: 700px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin:0">AI Access: <span id="aiConfigUserName"></span></h3>
            <button class="btn btn-cancel" onclick="hideAiConfig()" style="padding: 5px 12px; border-radius: 50%">&times;</button>
        </div>
        
        <form id="aiConfigForm">
            @csrf
            <div style="max-height: 60vh; overflow-y: auto; padding-right: 15px;" id="aiConfigScrollArea">
                <h4 class="section-divider"><i class="fas fa-brain"></i> AI Models Access</h4>
                <div id="aiModelsGrouped"></div>

                <h4 class="section-divider" style="color: #10b981; margin-top: 2rem;"><i class="fas fa-key"></i> API Keys Access</h4>
                <div id="aiKeysGrouped"></div>
            </div>

            <div class="modal-actions" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05);">
                <button type="button" class="btn btn-cancel" onclick="hideAiConfig()">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSaveAiConfig">
                    <i class="fas fa-save"></i> Simpan Akses AI
                </button>
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
    .filter-group select option { background: #1e293b; color: white; }
    .action-buttons { display: flex; gap: 8px; }
    .btn-edit, .btn-delete, .btn-info { padding: 8px 12px; }

    /* AI Pill Badges in Table */
    .ai-pill-group { display: flex; flex-wrap: wrap; gap: 4px; max-width: 200px; }
    .ai-pill {
        display: inline-flex; align-items: center;
        padding: 3px 9px; border-radius: 20px;
        font-size: 0.72rem; font-weight: 600;
        white-space: nowrap; max-width: 140px;
        overflow: hidden; text-overflow: ellipsis;
        cursor: default;
    }
    .ai-pill-model {
        background: rgba(99,102,241,0.15); color: #a5b4fc;
        border: 1px solid rgba(99,102,241,0.3);
    }
    .ai-pill-key {
        background: rgba(16,185,129,0.13); color: #6ee7b7;
        border: 1px solid rgba(16,185,129,0.25);
    }
    .ai-pill-more {
        background: rgba(255,255,255,0.06); color: #94a3b8;
        border: 1px solid rgba(255,255,255,0.1);
        cursor: pointer; position: relative;
    }
    .ai-pill-more:hover::after {
        content: attr(data-tooltip);
        position: absolute; top: calc(100% + 6px); left: 0;
        background: #1e293b; color: #e2e8f0;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 8px; padding: 8px 12px;
        font-size: 0.75rem; font-weight: 500;
        white-space: pre-wrap; word-break: break-word;
        max-width: 260px; z-index: 999;
        box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        line-height: 1.6;
    }
    .ai-none { color: #475569; font-size: 0.85rem; }

    /* AI Config Grouping */
    .section-divider {
        font-size: 0.95rem; font-weight: 700; color: var(--primary);
        display: flex; align-items: center; gap: 10px; margin-bottom: 1.25rem;
    }
    .ai-group-box {
        background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem;
    }
    .ai-group-name {
        font-size: 0.75rem; font-weight: 800; color: #64748b;
        text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.75rem;
    }
    .ai-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem;
    }
    .ai-check-label {
        display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.03);
        padding: 10px 12px; border-radius: 8px; cursor: pointer; transition: all 0.2s;
        border: 1px solid transparent;
    }
    .ai-check-label:hover { background: rgba(255,255,255,0.07); }
    .ai-check-label input { width: 16px !important; height: 16px !important; margin: 0; }
    .ai-check-label span { font-size: 0.85rem; color: #cbd5e1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ai-check-label.active { border-color: rgba(99, 102, 241, 0.3); background: rgba(99, 102, 241, 0.1); }

    .scope-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
    .scope-limited { background: rgba(99,102,241,0.15); color: #818cf8; border: 1px solid rgba(99,102,241,0.25); }
    .scope-free    { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.25); }
    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .table-card { padding: 0; overflow: hidden; margin-top: 1rem; }
    table { width: 100%; border-collapse: collapse; color: #cbd5e1; }
    th { padding: 1.25rem 1.5rem; text-align: left; color: #94a3b8; background: rgba(255,255,255,0.03); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }
    td { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--glass-border); font-size: 0.95rem; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.01); }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
    .modal-content { width: 100%; max-width: 500px; max-height: 95vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.6rem; color: #94a3b8; font-weight: 600; font-size: 0.9rem; }
    .form-group input, .form-group select { width: 100%; background: rgba(15, 23, 42, 0.5); border: 1px solid var(--glass-border); padding: 0.8rem 1rem; border-radius: 12px; color: white; transition: all 0.3s; }
    .form-group input:focus, .form-group select:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }
    .checkbox-group { display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,0.03); padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid var(--glass-border); }
    .checkbox-group input { width: 18px !important; height: 18px !important; cursor: pointer; }
    .checkbox-group label { margin-bottom: 0; cursor: pointer; color: #e2e8f0; }
    
    .pagination-container { margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
    .pagination-info { color: #64748b; font-size: 0.9rem; }
</style>

<script>
    let currentEditingUserId = null;

    function showModal(type, user = null) {
        if (type === 'import') {
            document.getElementById('importModal').style.display = 'flex';
            return;
        }

        const modal = document.getElementById('userModal');
        const form = document.getElementById('userForm');
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

    function hideModal() { document.getElementById('userModal').style.display = 'none'; }
    function hideImportModal() { document.getElementById('importModal').style.display = 'none'; }

    function downloadTemplate() {
        window.location.href = "{{ route('admin.users.template') }}";
    }

    function exportUsers() {
        window.location.href = "{{ route('admin.users.export') }}";
    }

    function showAiConfig(user) {
        currentEditingUserId = user.id;
        document.getElementById('aiConfigUserName').innerText = user.name;
        
        const modelsContainer = document.getElementById('aiModelsGrouped');
        const keysContainer = document.getElementById('aiKeysGrouped');
        
        const allModels = @json($aiModels);
        const allKeys = @json($aiKeys);
        const userModels = user.ai_models ? user.ai_models.map(m => m.id) : [];
        const userKeys = user.ai_keys ? user.ai_keys.map(k => k.id) : [];

        // Grouping logic
        const groupedModels = {};
        allModels.forEach(m => {
            const provider = m.provider.name;
            if (!groupedModels[provider]) groupedModels[provider] = [];
            groupedModels[provider].push(m);
        });

        const groupedKeys = {};
        allKeys.forEach(k => {
            const provider = k.provider.name;
            if (!groupedKeys[provider]) groupedKeys[provider] = [];
            groupedKeys[provider].push(k);
        });

        // Render Models
        modelsContainer.innerHTML = '';
        Object.keys(groupedModels).forEach(provider => {
            const group = document.createElement('div');
            group.className = 'ai-group-box';
            group.innerHTML = `<div class="ai-group-name">${provider}</div><div class="ai-grid"></div>`;
            const grid = group.querySelector('.ai-grid');
            groupedModels[provider].forEach(model => {
                const isChecked = userModels.includes(model.id);
                grid.innerHTML += `
                    <label class="ai-check-label ${isChecked ? 'active' : ''}">
                        <input type="checkbox" name="ai_models[]" value="${model.id}" ${isChecked ? 'checked' : ''} onchange="this.parentElement.classList.toggle('active', this.checked)">
                        <span>${model.display_name}</span>
                    </label>
                `;
            });
            modelsContainer.appendChild(group);
        });

        // Render Keys
        keysContainer.innerHTML = '';
        Object.keys(groupedKeys).forEach(provider => {
            const group = document.createElement('div');
            group.className = 'ai-group-box';
            group.innerHTML = `<div class="ai-group-name">${provider}</div><div class="ai-grid"></div>`;
            const grid = group.querySelector('.ai-grid');
            groupedKeys[provider].forEach(key => {
                const isChecked = userKeys.includes(key.id);
                grid.innerHTML += `
                    <label class="ai-check-label ${isChecked ? 'active' : ''}">
                        <input type="checkbox" name="ai_keys[]" value="${key.id}" ${isChecked ? 'checked' : ''} onchange="this.parentElement.classList.toggle('active', this.checked)">
                        <span>${key.key_name}</span>
                    </label>
                `;
            });
            keysContainer.appendChild(group);
        });

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
                    icon: 'success', 
                    title: 'Berhasil', 
                    text: 'Konfigurasi AI diperbarui!', 
                    timer: 1500, 
                    showConfirmButton: false 
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error(data.message || 'Gagal menyimpan konfigurasi');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: err.message || 'Terjadi kesalahan saat menyimpan.'
            });
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    }

    function hideAiConfig() { document.getElementById('aiConfigModal').style.display = 'none'; }
    document.getElementById('btnSaveAiConfig').onclick = saveAiConfig;

    window.onclick = function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            hideModal(); hideImportModal(); hideAiConfig();
        }
    }
</script>
@endsection
