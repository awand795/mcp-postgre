@extends('layouts.admin')

@section('content')
    <div class="header">
        <h1>Management Database</h1>
        <div style="display: flex; gap: 0.75rem;">
            <button class="btn btn-secondary" onclick="testAllConnections()" id="testAllBtn">
                <i class="fas fa-heartbeat"></i> <span>Test All Connections</span>
            </button>
            <button class="btn btn-primary" onclick="showDatabaseModal('create')">
                <i class="fas fa-plus"></i> <span>Tambah Database</span>
            </button>
        </div>
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
                    <div class="db-icon driver-icon-{{ $db->driver }}">
                        <i
                            class="fas {{ $db->driver === 'mysql' || $db->driver === 'mariadb' ? 'fa-database' : ($db->driver === 'sqlsrv' ? 'fa-server' : ($db->driver === 'sqlite' ? 'fa-file-code' : 'fa-database')) }}"></i>
                    </div>
                    <div class="db-info">
                        <h3>{{ $db->name }}
                            <span class="badge badge-driver">{{ strtoupper($db->driver) }}</span>
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
                            <button class="btn-icon btn-danger" onclick="deleteDatabase({{ $db->id }}, '{{ $db->name }}')"
                                title="Delete">
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
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Nama Database <span
                                style="color: #ef4444;">*</span></label>
                        <input type="text" name="name" id="dbNameInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            placeholder="MBI Production" required>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Kode <span
                                style="color: #ef4444;">*</span></label>
                        <input type="text" name="code" id="dbCodeInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            placeholder="mbi_prod" required>
                        <small style="color: #64748b; font-size: 0.75rem;">Huruf kecil, angka, underscore saja</small>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Driver Database <span
                            style="color: #ef4444;">*</span></label>
                    <select name="driver" id="dbDriverSelect"
                        style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                        required onchange="onDriverChange()">
                        <option value="pgsql">PostgreSQL</option>
                        <option value="mysql">MySQL</option>
                        <option value="mariadb">MariaDB</option>
                        <option value="sqlsrv">Microsoft SQL Server</option>
                        <option value="sqlite">SQLite</option>
                    </select>
                    <small style="color: #64748b; font-size: 0.75rem;">Pilih jenis database yang ingin dihubungkan</small>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;" id="hostLabel">Host <span
                                style="color: #ef4444;">*</span></label>
                        <input type="text" name="host" id="dbHostInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            placeholder="db.example.com">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Port <span
                                style="color: #ef4444;">*</span></label>
                        <input type="number" name="port" id="dbPortInput" value="5432"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            required>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;" id="databaseLabel">Nama Database
                        (PostgreSQL/MySQL/etc)
                        <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="database" id="dbDatabaseInput"
                        style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                        placeholder="my_database" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div style="margin-bottom: 1rem;" id="usernameGroup">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Username <span
                                style="color: #ef4444;" id="usernameRequiredMark">*</span></label>
                        <input type="text" name="username" id="dbUsernameInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;">
                    </div>
                    <div style="margin-bottom: 1rem;" id="passwordGroup">
                        <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Password <span
                                style="color: #ef4444;" id="passwordRequiredMark">*</span></label>
                        <input type="password" name="password" id="dbPasswordInput"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;">
                        <small style="color: #64748b; font-size: 0.75rem;" id="passwordHint">Kosongkan jika tidak ingin
                            mengubah</small>
                    </div>
                </div>

                <div style="margin-bottom: 1rem;" id="schemaGroup">
                    <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;" id="schemaLabel">Schema <span
                            style="color: #ef4444;">*</span></label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="schema" id="dbSchemaInput"
                            style="flex: 1; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                            placeholder="sch_mbi">
                        <button type="button" class="btn" onclick="loadSchemas()" id="loadSchemasBtn"
                            style="white-space: nowrap;">
                            <i class="fas fa-sync-alt"></i> Load Schemas
                        </button>
                    </div>
                    <select id="dbSchemaSelect"
                        style="display: none; width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white; margin-top: 0.5rem;">
                    </select>
                    <small style="color: #64748b; font-size: 0.75rem;" id="schemaHint">PostgreSQL: sch_nama, SQL Server:
                        dbo, MySQL/MariaDB: otomatis</small>
                </div>

                <!-- Advanced Options -->
                <details style="margin-bottom: 1rem; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px;">
                    <summary style="color: #94a3b8; cursor: pointer; font-weight: 600; margin-bottom: 1rem;">
                        <i class="fas fa-cog"></i> Advanced Options
                    </summary>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">SSL Mode</label>
                            <select name="ssl_mode" id="dbSslModeInput"
                                style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;">
                                <option value="">None</option>
                                <option value="prefer">Prefer</option>
                                <option value="require">Require</option>
                                <option value="verify-ca">Verify CA</option>
                                <option value="verify-full">Verify Full</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Connection Timeout
                                (seconds)</label>
                            <input type="number" name="connection_timeout" id="dbTimeoutInput" value="30" min="5" max="300"
                                style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;">
                        </div>
                    </div>
                </details>

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

        select option {
            background-color: #1e293b;
            color: white;
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

        .badge-driver {
            background: #8b5cf6;
            color: white;
        }

        .driver-icon-pgsql {
            background: linear-gradient(135deg, #336791, #4a8bc7);
        }

        .driver-icon-mysql,
        .driver-icon-mariadb {
            background: linear-gradient(135deg, #00758f, #f29111);
        }

        .driver-icon-sqlsrv {
            background: linear-gradient(135deg, #e04e3d, #f47b2b);
        }

        .driver-icon-sqlite {
            background: linear-gradient(135deg, #003b57, #44a0d3);
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

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: #94a3b8;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-color: rgba(255, 255, 255, 0.2);
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

        /**
         * Driver configuration map
         */
        const driverConfig = {
            pgsql: { port: 5432, usesSchema: true, defaultSchema: 'public', hostPlaceholder: 'db.example.com', dbLabel: 'Nama Database' },
            mysql: { port: 3306, usesSchema: false, defaultSchema: '', hostPlaceholder: 'db.example.com', dbLabel: 'Nama Database' },
            mariadb: { port: 3306, usesSchema: false, defaultSchema: '', hostPlaceholder: 'db.example.com', dbLabel: 'Nama Database' },
            sqlsrv: { port: 1433, usesSchema: true, defaultSchema: 'dbo', hostPlaceholder: 'db.example.com', dbLabel: 'Nama Database' },
            sqlite: { port: 0, usesSchema: false, defaultSchema: '', hostPlaceholder: '(tidak diperlukan)', dbLabel: 'Path File SQLite' },
        };

        /**
         * Handle driver selection change
         */
        window.onDriverChange = function () {
            const driver = document.getElementById('dbDriverSelect').value;
            const config = driverConfig[driver] || driverConfig.pgsql;

            // Update port
            document.getElementById('dbPortInput').value = config.port;

            // Update labels and placeholders
            document.getElementById('hostLabel').innerHTML = (driver === 'sqlite')
                ? 'Path File <span style="color: #ef4444;">*</span>'
                : 'Host <span style="color: #ef4444;">*</span>';
            document.getElementById('dbHostInput').placeholder = config.hostPlaceholder;
            document.getElementById('databaseLabel').innerHTML = config.dbLabel + ' <span style="color: #ef4444;">*</span>';

            // Handle schema field visibility
            const schemaGroup = document.getElementById('schemaGroup');
            if (config.usesSchema) {
                schemaGroup.style.display = 'block';
                document.getElementById('dbSchemaInput').value = config.defaultSchema;
                document.getElementById('schemaHint').textContent = driver === 'sqlsrv'
                    ? 'SQL Server: dbo, sch_nama'
                    : 'PostgreSQL: sch_nama, public';
            } else {
                if (driver === 'mysql' || driver === 'mariadb') {
                    schemaGroup.style.display = 'block';
                    document.getElementById('dbSchemaInput').value = '';
                    document.getElementById('dbSchemaInput').placeholder = '(otomatis dari nama database)';
                    document.getElementById('schemaHint').textContent = 'MySQL/MariaDB: schema = database name, kosongkan untuk otomatis';
                } else {
                    schemaGroup.style.display = 'none';
                }
            }

            // Handle SQLite - hide username/password
            if (driver === 'sqlite') {
                document.getElementById('usernameGroup').style.display = 'none';
                document.getElementById('passwordGroup').style.display = 'none';
                document.getElementById('dbUsernameInput').required = false;
                document.getElementById('dbPasswordInput').required = false;
                document.getElementById('dbHostInput').required = false;
                document.getElementById('loadSchemasBtn').style.display = 'none';
            } else {
                document.getElementById('usernameGroup').style.display = 'block';
                document.getElementById('passwordGroup').style.display = 'block';
                document.getElementById('loadSchemasBtn').style.display = 'inline-block';
            }
        };

        window.showDatabaseModal = function (type, db = null) {
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

                // Set default driver to PostgreSQL
                document.getElementById('dbDriverSelect').value = 'pgsql';
                onDriverChange();

                passwordInput.required = true;
                passwordHint.style.display = 'none';
                passwordRequiredMark.style.display = 'inline';
            } else {
                document.getElementById('databaseModalTitle').innerText = 'Edit Database';
                form.action = `/admin/databases/${db.id}`;
                method.value = 'PUT';
                editingDatabaseId = db.id;

                document.getElementById('dbDriverSelect').value = db.driver || 'pgsql';
                onDriverChange();

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

                // Set advanced options if available
                if (db.ssl_mode) document.getElementById('dbSslModeInput').value = db.ssl_mode;
                if (db.connection_timeout) document.getElementById('dbTimeoutInput').value = db.connection_timeout;

                passwordInput.required = false;
                passwordHint.style.display = 'block';
                passwordRequiredMark.style.display = 'none';
            }

            modal.style.display = 'flex';
        };

        window.loadSchemas = async function () {
            const driver = document.getElementById('dbDriverSelect').value;
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
                            driver: driver,
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
            select.onchange = function () {
                input.value = this.value;
            };
        }

        window.testConnection = async function (dbId) {
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

        window.deleteDatabase = function (dbId, dbName) {
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
        document.getElementById('databaseModal').addEventListener('click', function (e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });

        /**
         * Test all database connections and show health report
         */
        window.testAllConnections = async function () {
            const btn = document.getElementById('testAllBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';

            try {
                const response = await fetch("{{ route('admin.databases.test-all') }}");
                const data = await response.json();

                let html = `
                            <div style="text-align: left; margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>Total Databases:</span>
                                    <strong>${data.total}</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #10b981;">
                                    <span><i class="fas fa-check-circle"></i> Healthy:</span>
                                    <strong>${data.healthy}</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; color: #ef4444;">
                                    <span><i class="fas fa-times-circle"></i> Unhealthy:</span>
                                    <strong>${data.unhealthy}</strong>
                                </div>
                            </div>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0.5rem;">
                        `;

                data.databases.forEach(db => {
                    const statusIcon = db.success
                        ? '<i class="fas fa-check-circle" style="color: #10b981;"></i>'
                        : '<i class="fas fa-times-circle" style="color: #ef4444;"></i>';

                    const statusText = db.success
                        ? `${db.version || 'Connected'} (${db.response_time_ms}ms)`
                        : `Error: ${db.error || 'Unknown'}`;

                    html += `
                                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    ${statusIcon}
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600;">${db.name} <span style="color: #8b5cf6; font-size: 0.75rem;">[${db.driver.toUpperCase()}]</span></div>
                                        <div style="color: #64748b; font-size: 0.8rem;">${db.host}:${db.port}/${db.database}</div>
                                        <div style="color: #94a3b8; font-size: 0.8rem;">${statusText}</div>
                                    </div>
                                </div>
                            `;
                });

                html += '</div>';

                Swal.fire({
                    title: 'Database Health Report',
                    html: html,
                    icon: data.unhealthy === 0 ? 'success' : 'warning',
                    width: '600px',
                    confirmButtonText: 'Tutup'
                });

                // Reload page to update status badges
                if (data.unhealthy > 0 || data.healthy > 0) {
                    setTimeout(() => window.location.reload(), 3000);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to test connections: ' + error.message
                });
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        };
    </script>
@endsection