@extends('layouts.admin')

@section('content')
<div class="header">
    <h1>Management User</h1>
    <button class="btn btn-primary" onclick="showModal('create')"><i class="fas fa-plus"></i> Tambah User</button>
</div>

@if(session('success'))
    <div style="background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.3);">
        {{ session('success') }}
    </div>
@endif

<!-- Search & Filter -->
<div class="glass-card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <form method="GET" action="{{ route('admin.users') }}" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
        <div>
            <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.9rem;"><i class="fas fa-search"></i> Cari User</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nama atau Email..." 
                   style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                   onkeypress="if(event.key === 'Enter') this.form.submit()">
        </div>
        <div>
            <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.9rem;"><i class="fas fa-filter"></i> Filter Role</label>
            <select name="role_filter" onchange="this.form.submit()" 
                    style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;">
                <option value="">Semua Role</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ request('role_filter') == $role->id ? 'selected' : '' }} style="color: black;">{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
            @if(request('search') || request('role_filter'))
                <a href="{{ route('admin.users') }}" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-times"></i> Reset</a>
            @endif
        </div>
    </form>
</div>

<!-- User Table -->
<div class="glass-card" style="padding: 0; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse; color: #cbd5e1;">
        <thead>
            <tr style="background: rgba(255,255,255,0.05); text-align: left;">
                <th style="padding: 1.5rem;">Nama</th>
                <th style="padding: 1.5rem;">Email</th>
                <th style="padding: 1.5rem;">Role</th>
                <th style="padding: 1.5rem;">Admin?</th>
                <th style="padding: 1.5rem;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr style="border-bottom: 1px solid var(--glass-border);">
                <td style="padding: 1.5rem; color: white;">{{ $user->name }}</td>
                <td style="padding: 1.5rem;">{{ $user->email }}</td>
                <td style="padding: 1.5rem;">
                    <span style="background: rgba(99, 102, 241, 0.1); color: var(--primary); padding: 4px 10px; border-radius: 8px; font-size: 0.85rem;">
                        {{ $user->roleModel->name ?? 'No Role' }}
                    </span>
                </td>
                <td style="padding: 1.5rem;">
                    @if($user->is_admin)
                        <span style="color: #10b981;"><i class="fas fa-check-circle"></i> Yes</span>
                    @else
                        <span style="color: #64748b;"><i class="fas fa-times-circle"></i> No</span>
                    @endif
                </td>
                <td style="padding: 1.5rem; display: flex; gap: 10px;">
                    <button class="btn" style="background: rgba(255,255,255,0.1); padding: 8px 12px;" onclick="showModal('edit', {{ json_encode($user) }})"><i class="fas fa-edit"></i></button>
                    <form action="{{ route('admin.users.delete', $user->id) }}" method="POST" onsubmit="return confirm('Hapus user ini?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 8px 12px;"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="padding: 3rem; text-align: center; color: #94a3b8;">
                    <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>Tidak ada user yang ditemukan</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Pagination -->
@if($users->hasPages())
<div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <p style="color: #94a3b8; font-size: 0.9rem;">
        Menampilkan {{ $users->firstItem() }} - {{ $users->lastItem() }} dari {{ $users->total() }} user
    </p>
    <nav style="display: flex; gap: 8px;">
        {{-- Previous Button --}}
        @if($users->onFirstPage())
            <span style="opacity: 0.5; pointer-events: none;" class="btn" style="background: rgba(255,255,255,0.1); padding: 8px 16px;">
                <i class="fas fa-chevron-left"></i> Prev
            </span>
        @else
            <a href="{{ $users->previousPageUrl() }}" class="btn" style="background: rgba(255,255,255,0.1); padding: 8px 16px;">
                <i class="fas fa-chevron-left"></i> Prev
            </a>
        @endif

        {{-- Page Numbers --}}
        @foreach($users->getUrlRange(1, $users->lastPage()) as $page => $url)
            @if($page == $users->currentPage())
                <span class="btn" style="background: var(--primary); color: white; padding: 8px 16px;">{{ $page }}</span>
            @else
                <a href="{{ $url }}" class="btn" style="background: rgba(255,255,255,0.1); padding: 8px 16px;">{{ $page }}</a>
            @endif
        @endforeach

        {{-- Next Button --}}
        @if($users->hasMorePages())
            <a href="{{ $users->nextPageUrl() }}" class="btn" style="background: rgba(255,255,255,0.1); padding: 8px 16px;">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        @else
            <span style="opacity: 0.5; pointer-events: none;" class="btn" style="background: rgba(255,255,255,0.1); padding: 8px 16px;">
                Next <i class="fas fa-chevron-right"></i>
            </span>
        @endif
    </nav>
</div>
@endif

<!-- Modal User -->
<div id="userModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-card" style="width: 100%; max-width: 500px;">
        <h3 id="modalTitle" style="margin-bottom: 1.5rem;">Tambah User</h3>
        <form id="userForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Nama</label>
                <input type="text" name="name" id="userName" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;" required>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Email</label>
                <input type="email" name="email" id="userEmail" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;" required>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Password (Kosongkan jika tidak ganti)</label>
                <input type="password" name="password" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Role</label>
                <select name="role" id="userRole" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;" required>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" style="color: black;">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="is_admin" id="userIsAdmin" value="1">
                <label for="userIsAdmin">Jadikan Admin</label>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" style="background: transparent; color: #94a3b8;" onclick="hideModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
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
        } else {
            title.innerText = 'Edit User';
            // Preserve current query params (search, filter) after redirect
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

    // Handle form submit to redirect back with search params
    document.getElementById('userForm')?.addEventListener('submit', function(e) {
        const redirectUrl = this.getAttribute('data-redirect');
        if (redirectUrl) {
            this.action = this.action;
            // Store redirect URL in hidden input for controller to use
            let redirectInput = document.createElement('input');
            redirectInput.type = 'hidden';
            redirectInput.name = 'redirect_url';
            redirectInput.value = redirectUrl;
            this.appendChild(redirectInput);
        }
    });
</script>
@endsection
