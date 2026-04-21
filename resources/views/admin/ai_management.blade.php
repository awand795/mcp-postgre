@extends('layouts.admin')

@section('content')
    <div class="header">
        <h1>Management AI & API Keys</h1>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="showProviderModal()">
                <i class="fas fa-plus"></i> Tambah Provider
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
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
                        @if(!in_array($provider->code, ['openai','gemini','claude','mistral']))
                            <form action="{{ route('admin.ai_management.delete_provider', $provider->id) }}" method="POST"
                                  onsubmit="return confirm('Hapus provider \'{{ $provider->name }}\' beserta semua modelnya?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon btn-danger" title="Hapus Provider">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        @endif
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
                <div class="section-title" style="margin-top: 1rem;">
                    <h4><i class="fas fa-brain"></i> Models</h4>
                    <button class="btn btn-sm btn-info" onclick="showModelModal({{ $provider->id }}, '{{ $provider->name }}')">
                        <i class="fas fa-plus"></i> Tambah Model
                    </button>
                </div>
                <div class="models-grid">
                    @foreach($provider->models as $model)
                        <div class="model-tag-wrapper">
                            <div class="model-tag {{ $model->is_active ? 'active' : 'disabled' }}" onclick="toggleModel({{ $model->id }})">
                                {{ $model->display_name }}
                                <i class="fas {{ $model->is_active ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                            </div>
                            <form action="{{ route('admin.ai_management.delete_model', $model->id) }}" method="POST" onsubmit="return confirm('Hapus model ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-delete-model" title="Hapus Model"><i class="fas fa-times"></i></button>
                            </form>
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
        .icon-mistral { background: #ff7000; }
        .icon-groq { background: #f55036; }
        .icon-openrouter { background: #7c3aed; }
        .icon-deepseek { background: #0ea5e9; }
        /* Fallback warna untuk provider custom apapun yang tidak dikenal */
        [class^="icon-"] { background: #6366f1; }

        /* ── Form Tambah Provider ───────────────────────────────────────── */
        .add-provider-card {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px dashed rgba(99, 102, 241, 0.4);
        }

        .add-provider-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .add-provider-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(99, 102, 241, 0.15);
            border: 1px dashed rgba(99, 102, 241, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #818cf8;
            flex-shrink: 0;
        }

        .add-provider-header h3 { margin: 0; font-size: 1.1rem; }

        .add-provider-form {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr auto;
            gap: 1rem;
            align-items: end;
        }

        @media (max-width: 900px) {
            .add-provider-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .add-provider-form {
                grid-template-columns: 1fr;
            }
        }

        .hint {
            color: #64748b;
            font-size: 0.75rem;
            display: block;
            margin-top: 0.25rem;
        }

        .required { color: #ef4444; }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

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

        .model-tag-wrapper {
            position: relative;
            display: inline-block;
        }

        .btn-delete-model {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
        }

        .model-tag-wrapper:hover .btn-delete-model {
            opacity: 1;
        }

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

    <!-- AI Model Modal -->
    <div id="modelModal" class="modal-overlay">
        <div class="glass-card modal-content">
            <h3>Tambah Model AI</h3>
            <p id="modelProviderName" style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem;"></p>
            <form action="{{ route('admin.ai_management.store_model') }}" method="POST">
                @csrf
                <input type="hidden" name="provider_id" id="modelProviderIdInput">
                <div class="form-group">
                    <label>ID Model (System Name)</label>
                    <input type="text" name="model_name" required placeholder="Contoh: gpt-5.4-turbo">
                    <small style="color: #64748b; font-size: 0.75rem;">ID teknis yang dikirim ke API (misal: gpt-4o)</small>
                </div>
                <div class="form-group">
                    <label>Nama Display</label>
                    <input type="text" name="display_name" required placeholder="Contoh: GPT-5.4 Turbo">
                    <small style="color: #64748b; font-size: 0.75rem;">Nama yang muncul di antarmuka user</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideModelModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Model</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Provider Modal -->
    <div id="providerModal" class="modal-overlay">
        <div class="glass-card modal-content">
            <h3>Tambah Provider AI Baru</h3>
            <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 1.5rem;">
                <i class="fas fa-info-circle"></i>
                Mendukung semua provider OpenAI-compatible: Groq, OpenRouter, DeepSeek, LM Studio, dan lainnya.
            </p>
            <form action="{{ route('admin.ai_management.store_provider') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Nama Provider <span style="color:#ef4444">*</span></label>
                    <input type="text" name="name" required placeholder="Contoh: Groq, OpenRouter, DeepSeek"
                           value="{{ old('name') }}">
                </div>
                <div class="form-group">
                    <label>Kode Unik <span style="color:#ef4444">*</span></label>
                    <input type="text" name="code" required placeholder="Contoh: groq, openrouter, deepseek"
                           value="{{ old('code') }}" pattern="[a-z0-9_]+" title="Huruf kecil, angka, underscore saja">
                    <small style="color:#64748b;font-size:0.75rem;">Huruf kecil, angka, underscore. Identifier internal sistem.</small>
                </div>
                <div class="form-group">
                    <label>Base URL API</label>
                    <input type="url" name="base_url" placeholder="Contoh: https://api.groq.com/openai/v1"
                           value="{{ old('base_url') }}">
                    <small style="color:#64748b;font-size:0.75rem;">Wajib diisi untuk provider custom. Kosongkan jika sudah built-in.</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="hideProviderModal()">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Provider</button>
                </div>
            </form>
        </div>
    </div>

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

        function showModelModal(providerId, providerName) {
            document.getElementById('modelModal').style.display = 'flex';
            document.getElementById('modelProviderName').innerText = providerName;
            document.getElementById('modelProviderIdInput').value = providerId;
        }

        function hideModelModal() {
            document.getElementById('modelModal').style.display = 'none';
        }

        function showProviderModal() {
            document.getElementById('providerModal').style.display = 'flex';
        }

        function hideProviderModal() {
            document.getElementById('providerModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('keyModal')) hideKeyModal();
            if (event.target == document.getElementById('modelModal')) hideModelModal();
            if (event.target == document.getElementById('providerModal')) hideProviderModal();
        }
    </script>
@endsection
