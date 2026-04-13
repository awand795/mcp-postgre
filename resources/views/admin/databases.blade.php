@extends('layouts.admin')

@section('content')
    <div class="header">
        <h1>Management Database</h1>
        <button class="btn btn-primary" onclick="showDatabaseModal('create')"><i class="fas fa-plus"></i> <span>Tambah Database</span></button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i> {{ $errors->first() }}
        </div>
    @endif

    <div class="database-grid">
        @foreach($databases as $db)
            <div class="glass-card database-card {{ !$db->is_active ? 'inactive' : '' }}">
                <div class="db-header">
                    <div class="db-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="db-info">
                        <h3>{{ $db->name }}
                            @if($db->is_default)
                                <span class="badge badge-default">Default</span>
                            @endif
                            @if(!$db->is_active)
                                <span class="badge badge-inactive">Inactive</span>
                            @endif
                        </h3>
                        <p class="db-code">{{ $db->code }}</p>
                    </div>
                    <div class="db-actions">
                        <button class="btn-icon" onclick="testConnection({{ $db->id }})" title="Test Connection">
                            <i class="fas fa-plug"></i>
                        </button>
                        <button class="btn-icon" onclick="showDatabaseModal('edit', {{ json_encode($db) }})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        @if(!$db->is_default)
                            <button class="btn-icon btn-danger" onclick="deleteDatabase({{ $db->id }}, '{{ $db->name }}')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="db-details">
                    <div class="db-detail-row">
                        <span class="label">Host:</span>
                        <span class="value">{{ $db->host }}:{{ $db->port }}</span>
                    </div>
                    <div class="db-detail-row">
                        <span class="label">Database:</span>
                        <span class="value">{{ $db->database }}</span>
                    </div>
                    <div class="db-detail-row">
                        <span class="label">Schema:</span>
                        <span class="value">{{ $db->schema }}</span>
                    </div>
                    @if($db->description)
                        <div class="db-detail-row">
                            <span class="label">Description:</span>
                            <span class="value">{{ Str::limit($db->description, 80) }}</span>
                        </div>
                    @endif
                </div>

                <div class="db-status">
                    @if($db->test_status === 'success')
                        <span class="status-success"><i class="fas fa-check-circle"></i> Connected</span>
                        <small>Last tested: {{ $db->last_tested_at?->diffForHumans() ?? 'Never' }}</small>
                    @elseif($db->test_status === 'failed')
                        <span class="status-failed"><i class="fas fa-times-circle"></i> Failed</span>
                        <small>Last tested: {{ $db->last_tested_at?->diffForHumans() ?? 'Never' }}</small>
                    @else
                        <span class="status-pending"><i class="fas fa-question-circle"></i> Not Tested</span>
                    @endif
                </div>
            </div>
        @endforeach

        @if($databases->isEmpty())
            <div class="glass-card empty-state">
                <i class="fas fa-database"></i>
                <h3>Belum Ada Database</h3>
                <p>Tambahkan database PostgreSQL untuk mulai mengelola akses role.</p>
                <button class="btn btn-primary" onclick="showDatabaseModal('create')">
                    <i class="fas fa-plus"></i> Tambah Database Pertama
                </button>
            </div>
        @endif
    </div>

    <!-- Database Modal -->
    <div id="databaseModal"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div class="glass-card modal-content" style="width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto;">
            <h3 id="databaseModalTitle">Tambah Database</h3>
            <form id="databaseForm" method="POST" style="margin-top: 1.5rem;">
                @csrf
                <input type="hidden" name="_method" id="databaseFormMethod" value="POST">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Nama Database <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="name" id="dbNameInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            placeholder="MBI Production" required>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Kode <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="code" id="dbCodeInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            placeholder="mbi_prod" required>
                        <small style="color: #64748b; font-size: 0.75rem;">Huruf kecil, angka, underscore saja</small>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Host <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="host" id="dbHostInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            placeholder="db.example.com" required>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Port <span style="color: #ef4444;">*</span></label>
                        <input type="number" name="port" id="dbPortInput" value="5432"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            required>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Nama Database (PostgreSQL) <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="database" id="dbDatabaseInput"
                        style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                        placeholder="my_database" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Username <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="username" id="dbUsernameInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            required>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Password <span style="color: #ef4444;" id="passwordRequiredMark">*</span></label>
                        <input type="password" name="password" id="dbPasswordInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;">
                        <small style="color: #64748b; font-size: 0.75rem;" id="passwordHint">Kosongkan jika tidak ingin mengubah</small>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Schema <span style="color: #ef4444;">*</span></label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="schema" id="dbSchemaInput"
                            style="flex: 1; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            placeholder="sch_mbi" required>
                        <button type="button" class="btn" onclick="loadSchemas()" id="loadSchemasBtn" style="white-space: nowrap;">
                            <i class="fas fa-sync-alt"></i> Load Schemas
                        </button>
                    </div>
                    <select id="dbSchemaSelect" 
                        style="display: none; width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white; margin-top: 0.5rem;">
                    </select>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Deskripsi</label>
                    <textarea name="description" id="dbDescriptionInput"
                        style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white; resize: none;"
                        rows="2" placeholder="Database production untuk MBI"></textarea>
                </div>

                <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: #e2e8f0; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="dbIsActiveInput" value="1" checked>
                        Aktif
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: #e2e8f0; cursor: pointer;">
                        <input type="checkbox" name="is_default" id="dbIsDefaultInput" value="1">
                        Jadikan Default
                    </label>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn"
                        onclick="document.getElementById('databaseModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .database-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .database-card {
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .database-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .database-card.inactive {
            opacity: 0.6;
        }

        .db-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .db-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            border-radius: 12px;
            font-size: 1.2rem;
            color: white;
        }

        .db-info h3 {
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .db-code {
            color: #64748b;
            font-size: 0.8rem;
            margin: 0.25rem 0 0 0;
            font-family: monospace;
        }

        .db-actions {
            margin-left: auto;
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: #94a3b8;
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-icon.btn-danger:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .db-details {
            background: rgba(0, 0, 0, 0.2);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .db-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.85rem;
        }

        .db-detail-row:last-child {
            border-bottom: none;
        }

        .db-detail-row .label {
            color: #64748b;
        }

        .db-detail-row .value {
            color: #e2e8f0;
            font-family: monospace;
            text-align: right;
            max-width: 60%;
            word-break: break-all;
        }

        .db-status {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.85rem;
        }

        .status-success {
            color: #10b981;
        }

        .status-failed {
            color: #ef4444;
        }

        .status-pending {
            color: #f59e0b;
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-default {
            background: #3b82f6;
            color: white;
        }

        .badge-inactive {
            background: #64748b;
            color: white;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: #64748b;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin: 0.5rem 0;
        }

        .empty-state p {
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #f59e0b;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        @media (max-width: 768px) {
            .database-grid {
                grid-template-columns: 1fr;
            }
            
            .db-header {
                flex-wrap: wrap;
            }
        }
    </style>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let editingDatabaseId = null;

        window.showDatabaseModal = function(type, db = null) {
            const modal = document.getElementById('databaseModal');
            const form = document.getElementById('databaseForm');
            const method = document.getElementById('databaseFormMethod');
            const passwordInput = document.getElementById('dbPasswordInput');
            const passwordHint = document.getElementById('passwordHint');
            const passwordRequiredMark = document.getElementById('passwordRequiredMark');

            // Reset form
            document.getElementById('databaseForm').reset();
            // Reset schema input/select visibility
            document.getElementById('dbSchemaInput').style.display = 'block';
            document.getElementById('dbSchemaSelect').style.display = 'none';

            if (type === 'create') {
                document.getElementById('databaseModalTitle').innerText = 'Tambah Database';
                form.action = "{{ route('admin.databases.store') }}";
                method.value = 'POST';
                editingDatabaseId = null;
                passwordInput.required = true;
                passwordHint.style.display = 'none';
                passwordRequiredMark.style.display = 'inline';
            } else {
                document.getElementById('databaseModalTitle').innerText = 'Edit Database';
                form.action = `/admin/databases/${db.id}`;
                method.value = 'PUT';
                editingDatabaseId = db.id;

                document.getElementById('dbNameInput').value = db.name;
                document.getElementById('dbCodeInput').value = db.code;
                document.getElementById('dbHostInput').value = db.host;
                document.getElementById('dbPortInput').value = db.port;
                document.getElementById('dbDatabaseInput').value = db.database;
                document.getElementById('dbUsernameInput').value = db.username;
                document.getElementById('dbSchemaInput').value = db.schema;
                document.getElementById('dbDescriptionInput').value = db.description || '';
                document.getElementById('dbIsActiveInput').checked = db.is_active;
                document.getElementById('dbIsDefaultInput').checked = db.is_default;

                passwordInput.required = false;
                passwordHint.style.display = 'block';
                passwordRequiredMark.style.display = 'none';
            }

            modal.style.display = 'flex';
        };

        window.loadSchemas = async function() {
            const dbHost = document.getElementById('dbHostInput').value;
            const dbPort = document.getElementById('dbPortInput').value;
            const dbName = document.getElementById('dbDatabaseInput').value;
            const dbUsername = document.getElementById('dbUsernameInput').value;
            const dbPassword = document.getElementById('dbPasswordInput').value;

            if (!dbHost || !dbName || !dbUsername || !dbPassword) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Data Belum Lengkap',
                    text: 'Isi host, database, username, dan password terlebih dahulu untuk load schemas.'
                });
                return;
            }

            const btn = document.getElementById('loadSchemasBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                if (editingDatabaseId) {
                    // Edit mode - use database ID
                    const response = await fetch(`/admin/databases/${editingDatabaseId}/schemas`);
                    const data = await response.json();
                    
                    if (data.schemas && data.schemas.length > 0) {
                        showSchemaSelect(data.schemas);
                    } else {
                        Swal.fire('Info', 'Tidak ada schema yang ditemukan.', 'info');
                    }
                } else {
                    // Create mode - use form params
                    const response = await fetch('/admin/databases/load-schemas', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            host: dbHost,
                            port: parseInt(dbPort),
                            database: dbName,
                            username: dbUsername,
                            password: dbPassword,
                        })
                    });

                    const data = await response.json();
                    
                    if (data.schemas && data.schemas.length > 0) {
                        showSchemaSelect(data.schemas);
                    } else {
                        Swal.fire('Info', 'Tidak ada schema yang ditemukan.', 'info');
                    }
                }
            } catch (error) {
                Swal.fire('Error', 'Gagal memuat schemas: ' + error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        };

        function showSchemaSelect(schemas) {
            const input = document.getElementById('dbSchemaInput');
            const select = document.getElementById('dbSchemaSelect');
            const currentValue = input.value;

            select.innerHTML = '<option value="">-- Pilih Schema --</option>';
            schemas.forEach(schema => {
                const option = document.createElement('option');
                option.value = schema;
                option.textContent = schema;
                if (schema === currentValue) option.selected = true;
                select.appendChild(option);
            });

            input.style.display = 'none';
            select.style.display = 'block';
            select.value = currentValue || '';
            select.onchange = function() {
                input.value = this.value;
            };
        }

        window.testConnection = async function(dbId) {
            const result = await Swal.fire({
                title: 'Testing Connection...',
                text: 'Sedang menguji koneksi database',
                allowOutsideClick: false,
                didOpen: async () => {
                    Swal.showLoading();
                    try {
                        const response = await fetch(`/admin/databases/${dbId}/test`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            }
                        });
                        const data = await response.json();

                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Koneksi Berhasil',
                                html: `<p>PostgreSQL Version: <code>${data.version}</code></p>`
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Koneksi Gagal',
                                text: data.error || 'Unknown error'
                            });
                        }

                        // Reload page to show updated status
                        setTimeout(() => window.location.reload(), 2000);
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message
                        });
                    }
                }
            });
        };

        window.deleteDatabase = function(dbId, dbName) {
            Swal.fire({
                title: 'Hapus Database?',
                html: `Database <strong>${dbName}</strong> akan dihapus.<br>Tidak ada data tabel yang akan terpengaruh.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/admin/databases/${dbId}`;

                    const csrf = document.querySelector('meta[name="csrf-token"]').content;
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrf;
                    form.appendChild(csrfInput);

                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'DELETE';
                    form.appendChild(methodInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        };

        // Close modal on backdrop click
        document.getElementById('databaseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    </script>
@endsection
