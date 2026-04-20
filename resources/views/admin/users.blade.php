@extends('layouts.admin')

@section('content')
<div class="header">
    <h1>Management User</h1>
    <div class="header-actions">
        <button class="btn btn-success" onclick="downloadTemplate()"><i class="fas fa-download"></i> <span class="btn-text">Template</span></button>
        <button class="btn btn-info" onclick="showModal('import')"><i class="fas fa-file-import"></i> <span class="btn-text">Import</span></button>
        <button class="btn btn-secondary" onclick="exportUsers()"><i class="fas fa-file-export"></i> <span class="btn-text">Export</span></button>
        <button class="btn btn-primary" onclick="showModal('create')"><i class="fas fa-plus"></i> <span class="btn-text">Tambah User</span></button>
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
        <ul>
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
            <label>Cari User</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nama atau Email...">
        </div>
        <div class="filter-group">
            <label>Filter Role</label>
            <select name="role_filter">
                <option value="">Semua Role</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ request('role_filter') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
            <a href="{{ route('admin.users') }}" class="btn btn-reset btn-cancel"><i class="fas fa-sync"></i> Reset</a>
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
                    <td colspan="5" class="empty-state">
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

<!-- User Modal (Create/Edit) -->
<div id="userModal" class="modal-overlay">
    <div class="glass-card modal-content">
        <h3 id="modalTitle">Tambah User</h3>
        <form id="userForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="name" id="userName" required placeholder="Nama User">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="userEmail" required placeholder="email@domain.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="userPassword" placeholder="Kosongkan jika tidak ingin mengubah">
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
                <input type="number" name="max_tokens" id="userMaxTokens" value="32768" required min="1">
                <small style="color: #64748b; font-size: 0.75rem;">Default: 32768 (Kontrol panjang respon AI)</small>
            </div>
            <div class="form-group checkbox-group">
                <input type="checkbox" name="is_admin" id="userIsAdmin" value="1">
                <label for="userIsAdmin">Jadikan Admin</label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="hideModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- AI Configuration Modal -->
<div id="aiConfigModal" class="modal-overlay">
    <div class="glass-card modal-content" style="max-width: 600px;">
        <h3>AI Configuration - <span id="aiConfigUserName"></span></h3>
        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem;">Aktifkan atau nonaktifkan akses model dan API key spesifik untuk user ini.</p>
        
        <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
            <h4 style="color: var(--primary); font-size: 0.95rem; margin-bottom: 1rem;"><i class="fas fa-brain"></i> AI Models Access</h4>
            <div class="ai-config-list" id="aiModelsList">
                <!-- Dynamic Content -->
            </div>

            <h4 style="color: #10b981; font-size: 0.95rem; margin-top: 2rem; margin-bottom: 1rem;"><i class="fas fa-key"></i> API Keys Access</h4>
            <div class="ai-config-list" id="aiKeysList">
                <!-- Dynamic Content -->
            </div>
        </div>

        <div class="modal-actions" style="margin-top: 2rem;">
            <button type="button" class="btn btn-primary" onclick="location.reload()">Selesai & Refresh</button>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="modal-overlay">
    <div class="glass-card modal-content">
        <h3>Import User</h3>
        <form action="{{ route('admin.users.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>File Excel (.xlsx, .xls)</label>
                <input type="file" name="file" required accept=".xlsx, .xls">
                <small style="color: #64748b;">Gunakan template yang tersedia untuk format yang benar.</small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="hideImportModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<style>
    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-success {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        background: rgba(16, 185, 129, 0.3);
    }

    .btn-info {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .btn-info:hover {
        background: rgba(59, 130, 246, 0.3);
    }

    .btn-secondary {
        background: rgba(107, 114, 128, 0.2);
        color: #9ca3af;
        border: 1px solid rgba(107, 114, 128, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(107, 114, 128, 0.3);
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .alert-error ul {
        margin: 0.5rem 0 0 0;
        padding-left: 1.5rem;
    }

    .alert-error li {
        margin-bottom: 0.25rem;
    }
    .alert-success {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .filter-card {
        margin-bottom: 2rem;
        padding: 1.5rem;
    }

    .filter-form {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .filter-group label {
        color: #94a3b8;
        font-size: 0.9rem;
    }

    .filter-group input,
    .filter-group select {
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--glass-border);
        padding: 0.8rem;
        border-radius: 12px;
        color: white;
        font-size: 0.95rem;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
    }

    .btn-reset {
        background: rgba(255,255,255,0.1);
    }

    /* Table Styles */
    .table-card {
        padding: 0;
        overflow: hidden;
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        color: #cbd5e1;
        min-width: 800px;
    }

    thead tr {
        background: rgba(255,255,255,0.05);
        text-align: left;
    }

    th {
        padding: 1.5rem;
        font-weight: 600;
        color: #94a3b8;
        white-space: nowrap;
    }

    td {
        padding: 1.5rem;
        border-bottom: 1px solid var(--glass-border);
    }

    tbody tr:hover {
        background: rgba(255,255,255,0.02);
    }

    .role-badge {
        background: rgba(99, 102, 241, 0.1);
        color: var(--primary);
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.85rem;
        white-space: nowrap;
    }

    .status-yes {
        color: #10b981;
    }

    .status-no {
        color: #64748b;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }

    .btn-edit,
    .btn-delete {
        padding: 8px 12px;
    }

    .btn-delete {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .empty-state {
        text-align: center;
        padding: 3rem !important;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    /* Pagination */
    .pagination-container {
        margin-top: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .pagination-info {
        color: #94a3b8;
        font-size: 0.9rem;
    }

    .pagination-nav {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.8);
        backdrop-filter: blur(5px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .modal-content {
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: #94a3b8;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--glass-border);
        padding: 0.8rem;
        border-radius: 12px;
        color: white;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 1.5rem;
    }

    .checkbox-group input {
        width: auto;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    /* AI Config Items */
    .ai-config-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .ai-config-item {
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--glass-border);
        padding: 1rem;
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ai-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .ai-name {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .ai-status-badge {
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        width: fit-content;
    }

    .status-enabled { background: rgba(16, 185, 129, 0.2); color: #10b981; }
    .status-disabled { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
    .status-noaccess { background: rgba(148, 163, 184, 0.1); color: #94a3b8; }

    .btn-toggle {
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid var(--glass-border);
        background: rgba(255,255,255,0.05);
        color: white;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-toggle.btn-active {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border-color: rgba(239, 68, 68, 0.2);
    }

    .btn-toggle:not(.btn-active) {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border-color: rgba(16, 185, 129, 0.2);
    }

    /* Responsive Styles */
    @media (max-width: 768px) {
        .header-actions {
            width: 100%;
            justify-content: center;
        }

        .header-actions .btn {
            flex: 1;
            min-width: 120px;
            justify-content: center;
        }

        .filter-form {
            grid-template-columns: 1fr;
        }

        .filter-actions {
            width: 100%;
        }

        .filter-actions .btn {
            flex: 1;
            justify-content: center;
        }

        .btn-text {
            display: none;
        }
    }
</style>

<script>
    function showModal(type, user = null) {
        const modal = document.getElementById('userModal');
        const form = document.getElementById('userForm');
        const title = document.getElementById('modalTitle');
        const method = document.getElementById('formMethod');

        modal.style.display = 'flex';

        if (type === 'create') {
            title.innerText = 'Tambah User';
            form.action = "{{ route('admin.users.store') }}";
            method.value = 'POST';
            form.reset();
            document.getElementById('userMaxTokens').value = 32768;
        } else if (type === 'import') {
            document.getElementById('importModal').style.display = 'flex';
            return;
        } else {
            title.innerText = 'Edit User';
            form.action = `/admin/users/${user.id}`;
            method.value = 'PUT';
            document.getElementById('userName').value = user.name;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userRole').value = user.role;
            document.getElementById('userIsAdmin').checked = user.is_admin;
            document.getElementById('userMaxTokens').value = user.max_tokens || 32768;
        }
    }

    function showAiConfig(user) {
        const modal = document.getElementById('aiConfigModal');
        document.getElementById('aiConfigUserName').innerText = user.name;
        
        const modelsList = document.getElementById('aiModelsList');
        const keysList = document.getElementById('aiKeysList');
        
        const allModels = @json($aiModels);
        const allKeys = @json($aiKeys);
        
        const userModels = user.ai_models || [];
        const userKeys = user.ai_keys || [];

        // Build Models List
        modelsList.innerHTML = '';
        allModels.forEach(model => {
            const assignment = userModels.find(m => m.id === model.id);
            const isAssigned = !!assignment;
            const isEnabled = isAssigned ? !!assignment.pivot.is_enabled : false;
            
            const item = document.createElement('div');
            item.className = 'ai-config-item';
            item.innerHTML = `
                <div class="ai-info">
                    <span class="ai-name">${model.provider.name} - ${model.display_name}</span>
                    <span class="ai-status-badge ${isAssigned ? (isEnabled ? 'status-enabled' : 'status-disabled') : 'status-noaccess'}">
                        ${isAssigned ? (isEnabled ? 'ACTIVE' : 'DISABLED') : 'NO ACCESS'}
                    </span>
                </div>
                <div class="ai-toggle-action">
                    ${isAssigned ? `
                        <button class="btn-toggle ${isEnabled ? 'btn-active' : ''}" onclick="toggleModelAccess(${user.id}, ${model.id})">
                            ${isEnabled ? 'Disable' : 'Enable'}
                        </button>
                    ` : '<small style="color:#475569">Use Edit User</small>'}
                </div>
            `;
            modelsList.appendChild(item);
        });

        // Build Keys List
        keysList.innerHTML = '';
        allKeys.forEach(key => {
            const assignment = userKeys.find(k => k.id === key.id);
            const isAssigned = !!assignment;
            const isEnabled = isAssigned ? !!assignment.pivot.is_enabled : false;
            
            const item = document.createElement('div');
            item.className = 'ai-config-item';
            item.innerHTML = `
                <div class="ai-info">
                    <span class="ai-name">${key.provider.name} - ${key.key_name}</span>
                    <span class="ai-status-badge ${isAssigned ? (isEnabled ? 'status-enabled' : 'status-disabled') : 'status-noaccess'}">
                        ${isAssigned ? (isEnabled ? 'ACTIVE' : 'DISABLED') : 'NO ACCESS'}
                    </span>
                </div>
                <div class="ai-toggle-action">
                    ${isAssigned ? `
                        <button class="btn-toggle ${isEnabled ? 'btn-active' : ''}" onclick="toggleKeyAccess(${user.id}, ${key.id})">
                            ${isEnabled ? 'Disable' : 'Enable'}
                        </button>
                    ` : '<small style="color:#475569">Use Edit User</small>'}
                </div>
            `;
            keysList.appendChild(item);
        });

        modal.style.display = 'flex';
    }

    function toggleModelAccess(userId, modelId) {
        fetch(`/admin/users/${userId}/ai-models/${modelId}/toggle`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(() => location.reload());
    }

    function toggleKeyAccess(userId, keyId) {
        fetch(`/admin/users/${userId}/ai-keys/${keyId}/toggle`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(() => location.reload());
    }

    function hideModal() { document.getElementById('userModal').style.display = 'none'; }
    function hideImportModal() { document.getElementById('importModal').style.display = 'none'; }
    function hideAiConfig() { document.getElementById('aiConfigModal').style.display = 'none'; }

    function downloadTemplate() { window.location.href = "{{ route('admin.users.template') }}"; }
    function exportUsers() { window.location.href = "{{ route('admin.users.export') }}"; }

    window.onclick = function(event) {
        if (event.target == document.getElementById('userModal')) hideModal();
        if (event.target == document.getElementById('importModal')) hideImportModal();
        if (event.target == document.getElementById('aiConfigModal')) hideAiConfig();
    }
</script>
@endsection
