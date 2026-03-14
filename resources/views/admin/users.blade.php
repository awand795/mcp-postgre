@extends('layouts.admin')

@section('content')
<div class="header">
    <h1>Management User</h1>
    <div class="header-actions">
        <button class="btn btn-success" onclick="downloadTemplate()"><i class="fas fa-download"></i> <span>Format Excel</span></button>
        <button class="btn btn-info" onclick="showModal('import')"><i class="fas fa-file-import"></i> <span>Import Excel</span></button>
        <button class="btn btn-secondary" onclick="exportUsers()"><i class="fas fa-file-export"></i> <span>Export Excel</span></button>
        <button class="btn btn-primary" onclick="showModal('create')"><i class="fas fa-plus"></i> <span>Tambah User</span></button>
    </div>
</div>

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert-error">
        {{ session('error') }}
    </div>
@endif

@if($errors->has('file'))
    <div class="alert-error">
        <ul>
            @foreach($errors->get('file') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Search & Filter -->
<div class="glass-card filter-card">
    <form method="GET" action="{{ route('admin.users') }}" class="filter-form">
        <div class="filter-group">
            <label><i class="fas fa-search"></i> Cari User</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nama atau Email..."
                   onkeypress="if(event.key === 'Enter') this.form.submit()">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-filter"></i> Filter Role</label>
            <select name="role_filter" onchange="this.form.submit()">
                <option value="">Semua Role</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ request('role_filter') == $role->id ? 'selected' : '' }} style="color: black;">{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> <span class="btn-text">Cari</span></button>
            @if(request('search') || request('role_filter'))
                <a href="{{ route('admin.users') }}" class="btn btn-reset"><i class="fas fa-times"></i> <span class="btn-text">Reset</span></a>
            @endif
        </div>
    </form>
</div>

<!-- User Table -->
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
        {{-- Previous Button --}}
        @if($users->onFirstPage())
            <span class="btn btn-page disabled">
                <i class="fas fa-chevron-left"></i> <span class="btn-text">Prev</span>
            </span>
        @else
            <a href="{{ $users->previousPageUrl() }}" class="btn btn-page">
                <i class="fas fa-chevron-left"></i> <span class="btn-text">Prev</span>
            </a>
        @endif

        {{-- Page Numbers --}}
        @foreach($users->getUrlRange(1, $users->lastPage()) as $page => $url)
            @if($page == $users->currentPage())
                <span class="btn btn-page active">{{ $page }}</span>
            @else
                <a href="{{ $url }}" class="btn btn-page">{{ $page }}</a>
            @endif
        @endforeach

        {{-- Next Button --}}
        @if($users->hasMorePages())
            <a href="{{ $users->nextPageUrl() }}" class="btn btn-page">
                <span class="btn-text">Next</span> <i class="fas fa-chevron-right"></i>
            </a>
        @else
            <span class="btn btn-page disabled">
                <span class="btn-text">Next</span> <i class="fas fa-chevron-right"></i>
            </span>
        @endif
    </nav>
</div>
@endif

<!-- Modal User -->
<div id="userModal" class="modal-overlay">
    <div class="glass-card modal-content">
        <h3 id="modalTitle" style="margin-bottom: 1.5rem;">Tambah User</h3>
        <form id="userForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <div class="form-group">
                <label>Nama</label>
                <input type="text" name="name" id="userName" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="userEmail" required>
            </div>
            <div class="form-group">
                <label>Password (Kosongkan jika tidak ganti)</label>
                <input type="password" name="password">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="userRole" required>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" style="color: black;">{{ $role->name }}</option>
                    @endforeach
                </select>
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

<!-- Modal Import Excel -->
<div id="importModal" class="modal-overlay">
    <div class="glass-card modal-content">
        <h3 style="margin-bottom: 1.5rem;">Import User dari Excel</h3>
        <form id="importForm" method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>File Excel (.xlsx, .xls, .csv)</label>
                <input type="file" name="file" id="importFile" accept=".xlsx,.xls,.csv" required>
                <small style="color: #94a3b8; display: block; margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i> Download format template terlebih dahulu untuk memastikan format yang benar.
                </small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="hideImportModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import</button>
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

    .btn-edit {
        background: rgba(255,255,255,0.1);
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

    .btn-page {
        background: rgba(255,255,255,0.1);
        padding: 8px 16px;
    }

    .btn-page.active {
        background: var(--primary);
        color: white;
    }

    .btn-page.disabled {
        opacity: 0.5;
        pointer-events: none;
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

    .btn-cancel {
        background: transparent;
        color: #94a3b8;
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

        .pagination-container {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .pagination-nav {
            justify-content: center;
        }

        .btn-page {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
    }

    @media (max-width: 480px) {
        .glass-card {
            padding: 1rem !important;
            border-radius: 16px !important;
        }

        th, td {
            padding: 1rem !important;
            font-size: 0.9rem;
        }

        .role-badge {
            font-size: 0.8rem;
            padding: 3px 8px;
        }

        .btn-edit,
        .btn-delete {
            padding: 6px 10px;
        }

        .modal-content {
            padding: 1.2rem !important;
        }

        h3 {
            font-size: 1.2rem !important;
        }

        .form-group input,
        .form-group select {
            font-size: 0.9rem;
            padding: 0.7rem;
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
        } else if (type === 'import') {
            document.getElementById('importModal').style.display = 'flex';
            return;
        } else {
            title.innerText = 'Edit User';
            const currentParams = new URLSearchParams(window.location.search);
            const redirectParams = new URLSearchParams();
            if (currentParams.get('search')) redirectParams.set('search', currentParams.get('search'));
            if (currentParams.get('role_filter')) redirectParams.set('role_filter', currentParams.get('role_filter'));
            const redirectUrl = redirectParams.toString() ? '/admin/users?' + redirectParams.toString() : '/admin/users';

            form.action = `/admin/users/${user.id}`;
            form.setAttribute('data-redirect', redirectUrl);
            method.value = 'PUT';
            document.getElementById('userName').value = user.name;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userRole').value = user.role;
            document.getElementById('userIsAdmin').checked = user.is_admin;
        }
    }

    function hideModal() {
        document.getElementById('userModal').style.display = 'none';
    }

    function hideImportModal() {
        document.getElementById('importModal').style.display = 'none';
    }

    function downloadTemplate() {
        window.location.href = "{{ route('admin.users.template') }}";
    }

    function exportUsers() {
        window.location.href = "{{ route('admin.users.export') }}";
    }

    document.getElementById('userForm')?.addEventListener('submit', function(e) {
        const redirectUrl = this.getAttribute('data-redirect');
        if (redirectUrl) {
            let redirectInput = document.createElement('input');
            redirectInput.type = 'hidden';
            redirectInput.name = 'redirect_url';
            redirectInput.value = redirectUrl;
            this.appendChild(redirectInput);
        }
    });

    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === document.getElementById('userModal')) {
            hideModal();
        }
        if (e.target === document.getElementById('importModal')) {
            hideImportModal();
        }
    });
</script>
@endsection
