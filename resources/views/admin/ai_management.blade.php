@extends('layouts.admin')

@section('content')
    <div class="header">
        <h1>Management AI & API Keys</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <div class="ai-provider-grid">
        @foreach($providers as $provider)
            <div class="glass-card provider-card {{ !$provider->is_active ? 'inactive' : '' }}">
                <div class="provider-header">
                    <div class="provider-icon icon-{{ $provider->code }}">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="provider-info">
                        <h3>{{ $provider->name }}
                            @if(!$provider->is_active)
                                <span class="badge badge-inactive">Disabled</span>
                            @endif
                        </h3>
                        <p class="provider-code">{{ strtoupper($provider->code) }}</p>
                    </div>
                    <div class="provider-actions">
                        <button class="btn-icon {{ $provider->is_active ? 'btn-danger' : 'btn-success' }}" 
                                onclick="toggleProvider({{ $provider->id }})" 
                                title="{{ $provider->is_active ? 'Disable' : 'Enable' }} Provider">
                            <i class="fas {{ $provider->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                        </button>
                    </div>
                </div>

                <!-- API Keys Section -->
                <div class="section-title">
                    <h4><i class="fas fa-key"></i> API Keys</h4>
                    <button class="btn btn-sm btn-primary" onclick="showKeyModal({{ $provider->id }}, '{{ $provider->name }}')">
                        <i class="fas fa-plus"></i> Tambah Key
                    </button>
                </div>
                <div class="keys-list">
                    @forelse($provider->apiKeys as $key)
                        <div class="key-item {{ !$key->is_active || $key->limit_reached ? 'key-disabled' : '' }}">
                            <div class="key-info">
                                <span class="key-name">{{ $key->key_name }}</span>
                                <span class="key-status">
                                    @if($key->limit_reached)
                                        <span class="badge badge-danger">LIMIT</span>
                                    @elseif(!$key->is_active)
                                        <span class="badge badge-inactive">INACTIVE</span>
                                    @else
                                        <span class="badge badge-success">ACTIVE</span>
                                    @endif
                                </span>
                            </div>
                            <div class="key-actions">
                                @if($key->limit_reached)
                                    <form action="{{ route('admin.ai_management.reset_limit', $key->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button class="btn-icon btn-warning" title="Reset Limit"><i class="fas fa-sync"></i></button>
                                    </form>
                                @endif
                                <button class="btn-icon btn-edit" onclick="editKey({{ json_encode($key) }})" title="Edit Key"><i class="fas fa-edit"></i></button>
                                <form action="{{ route('admin.ai_management.delete_key', $key->id) }}" method="POST" onsubmit="return confirm('Hapus key ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-icon btn-danger" title="Delete Key"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="empty-hint">Belum ada API Key</p>
                    @endforelse
                </div>

                <!-- Models Section -->
                <div class="section-title">
                    <h4><i class="fas fa-brain"></i> Models</h4>
                </div>
                <div class="models-grid">
                    @foreach($provider->models as $model)
                        <div class="model-tag {{ $model->is_active ? 'active' : 'disabled' }}" onclick="toggleModel({{ $model->id }})">
                            {{ $model->display_name }}
                            <i class="fas {{ $model->is_active ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <!-- API Key Modal -->
    <div id="keyModal" class="modal-overlay">
        <div class="glass-card modal-content">
            <h3 id="keyModalTitle">Tambah API Key</h3>
            <p id="providerName" style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem;"></p>
            <form id="keyForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="keyFormMethod" value="POST">
                <input type="hidden" name="provider_id" id="providerIdInput">
                <div class="form-group">
                    <label>Nama Key (Alias)</label>
                    <input type="text" name="key_name" id="keyNameInput" required placeholder="Contoh: Key Utama Production">
                </div>
                <div class="form-group">
                    <label id="apiKeyLabel">API Key</label>
                    <input type="password" name="api_key" id="apiKeyInput" placeholder="Masukkan API Key Anda">
                    <small id="keyHint" style="color: #64748b; font-size: 0.75rem; display: none;">Kosongkan jika tidak ingin mengubah</small>
                </div>
                <div class="form-group checkbox-group" id="activeStatusGroup" style="display: none;">
                    <input type="checkbox" name="is_active" id="keyIsActive" value="1">
                    <label for="keyIsActive">Aktifkan Key</label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideKeyModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .ai-provider-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .provider-card {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .provider-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .provider-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .icon-openai { background: #10a37f; }
        .icon-gemini { background: #4285f4; }
        .icon-claude { background: #d97757; }

        .provider-info h3 { margin: 0; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem; }
        .provider-code { color: #64748b; font-size: 0.8rem; margin: 0; }
        .provider-actions { margin-left: auto; }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 0.5rem;
        }

        .section-title h4 { margin: 0; font-size: 0.95rem; color: #94a3b8; }

        .keys-list { display: flex; flex-direction: column; gap: 0.75rem; }
        .key-item {
            background: rgba(0,0,0,0.2);
            padding: 0.75rem 1rem;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255,255,255,0.03);
        }

        .key-info { display: flex; flex-direction: column; gap: 0.25rem; }
        .key-name { font-weight: 600; font-size: 0.9rem; }
        .key-actions { display: flex; gap: 0.5rem; }

        .btn-icon {
            padding: 6px 10px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .btn-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .btn-success { background: rgba(16, 185, 129, 0.2); color: #10b981; }

        .models-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .model-tag {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.2s;
        }

        .model-tag.active { background: rgba(99, 102, 241, 0.1); color: #818cf8; border-color: rgba(99, 102, 241, 0.3); }
        .model-tag.disabled { background: rgba(0,0,0,0.2); color: #475569; opacity: 0.6; }
        .model-tag:hover { transform: translateY(-2px); }

        .empty-hint { color: #475569; font-size: 0.85rem; text-align: center; font-style: italic; }

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

        .modal-content { width: 100%; max-width: 450px; }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.9rem; }
        .form-group input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            padding: 0.8rem;
            border-radius: 12px;
            color: white;
        }

        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }

        .badge { font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; font-weight: 700; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge-inactive { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
    </style>

    <script>
        function toggleProvider(id) {
            fetch(`/admin/ai-management/providers/${id}/toggle`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            }).then(() => location.reload());
        }

        function toggleModel(id) {
            fetch(`/admin/ai-management/models/${id}/toggle`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            }).then(() => location.reload());
        }

        function showKeyModal(providerId, providerName) {
            const modal = document.getElementById('keyModal');
            const form = document.getElementById('keyForm');
            const title = document.getElementById('keyModalTitle');
            const method = document.getElementById('keyFormMethod');
            
            modal.style.display = 'flex';
            title.innerText = 'Tambah API Key';
            document.getElementById('providerName').innerText = providerName;
            document.getElementById('providerIdInput').value = providerId;
            document.getElementById('apiKeyLabel').innerText = 'API Key';
            document.getElementById('keyHint').style.display = 'none';
            document.getElementById('activeStatusGroup').style.display = 'none';
            document.getElementById('apiKeyInput').required = true;
            
            form.action = "{{ route('admin.ai_management.store_key') }}";
            method.value = 'POST';
            form.reset();
            document.getElementById('providerIdInput').value = providerId;
        }

        function editKey(key) {
            const modal = document.getElementById('keyModal');
            const form = document.getElementById('keyForm');
            const title = document.getElementById('keyModalTitle');
            const method = document.getElementById('keyFormMethod');
            
            modal.style.display = 'flex';
            title.innerText = 'Edit API Key';
            document.getElementById('providerName').innerText = 'Provider ID: ' + key.provider_id;
            document.getElementById('keyNameInput').value = key.key_name;
            document.getElementById('apiKeyLabel').innerText = 'API Key (Opsional)';
            document.getElementById('keyHint').style.display = 'block';
            document.getElementById('activeStatusGroup').style.display = 'flex';
            document.getElementById('keyIsActive').checked = key.is_active;
            document.getElementById('apiKeyInput').required = false;
            
            form.action = `/admin/ai-management/keys/${key.id}`;
            method.value = 'PUT';
        }

        function hideKeyModal() {
            document.getElementById('keyModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('keyModal')) {
                hideKeyModal();
            }
        }
    </script>
@endsection
