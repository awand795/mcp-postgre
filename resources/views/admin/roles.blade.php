@extends('layouts.admin')

@section('content')
    <div class="header">
        <h1>Management Role & Permissions</h1>
        <button class="btn btn-primary" onclick="showRoleModal('create')"><i class="fas fa-plus"></i> <span>Tambah Role</span></button>
    </div>

    <div class="roles-container">
        <!-- Role List -->
        <div class="glass-card role-list-card">
            <h3 style="margin-bottom: 1.5rem; font-size: 1.1rem; color: #94a3b8;">Daftar Role</h3>
            <ul style="list-style: none;">
                @foreach($roles as $role)
                    <li style="margin-bottom: 0.8rem;">
                        <button class="btn role-item {{ $loop->first ? 'active' : '' }}"
                            style="width: 100%; justify-content: space-between; background: rgba(255,255,255,0.05); text-align: left;"
                            onclick="selectRole({{ $role->id }}, this)">
                            <span><i class="fas fa-user-shield" style="margin-right: 10px;"></i> {{ $role->name }}</span>
                            <div style="display: flex; gap: 8px;">
                                <i class="fas fa-edit btn-edit"
                                    onclick="event.stopPropagation(); showRoleModal('edit', {{ json_encode($role) }})"
                                    style="font-size: 0.8rem; padding: 6px; border-radius: 8px; cursor: pointer;" title="Edit Role"></i>
                                <i class="fas fa-trash" onclick="event.stopPropagation(); deleteRole({{ $role->id }})"
                                    style="font-size: 0.8rem; padding: 6px; opacity: 0.7; cursor: pointer; color: #ef4444;"
                                    title="Hapus Role"></i>
                            </div>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- Checkbox Permissions -->
        <div class="glass-card permissions-card" id="permissions-area">
            <div class="permissions-header">
                <div>
                    <h2 id="selected-role-name">{{ $roles[0]->name ?? 'Select Role' }}</h2>
                    <p id="selected-role-desc" style="color: #94a3b8; font-size: 0.9rem;">{{ $roles[0]->description ?? '' }}
                    </p>
                    <span id="unsaved-indicator"
                        style="display: none; color: #f59e0b; font-size: 0.85rem; margin-top: 5px;">
                        <i class="fas fa-exclamation-triangle"></i> Ada perubahan yang belum disimpan
                    </span>
                </div>
                <div class="permissions-actions">
                    <button class="btn btn-primary" onclick="savePermissions()"><i class="fas fa-save"></i> <span class="btn-text">Simpan Akses</span></button>
                </div>
            </div>

            <!-- Advanced Filter Bar -->
            <div class="filter-bar">
                <div class="filter-group search-group">
                    <label><i class="fas fa-search"></i> Cari</label>
                    <input type="text" id="table-search" placeholder="Cari nama tabel..." oninput="applyFilters()">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-database"></i> Database</label>
                    <select id="db-filter" onchange="handleDbFilterChange(this.value)">
                        <option value="">Semua Database</option>
                        @foreach($databases as $db)
                            <option value="{{ $db->database }}">{{ $db->database }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-layer-group"></i> Schema</label>
                    <select id="schema-filter" onchange="applyFilters()">
                        <option value="">Semua Schema</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Status</label>
                    <select id="status-filter" onchange="applyFilters()">
                        <option value="all">Semua</option>
                        <option value="allowed">Diizinkan</option>
                        <option value="not_allowed">Belum Diizinkan</option>
                    </select>
                </div>
            </div>

            <div class="selection-controls">
                <div class="stats-info">
                    Menampilkan <span id="visible-count">0</span> dari <span id="total-count">0</span> tabel
                    (<span id="selected-count" style="color: #10b981; font-weight: 600;">0</span> terpilih)
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-sm" onclick="bulkAction('select')"><i class="fas fa-check-square"></i> Pilih Semua</button>
                    <button class="btn btn-sm" onclick="bulkAction('deselect')"><i class="fas fa-square"></i> Hapus Semua</button>
                </div>
            </div>

            <div class="tables-container">
                <div id="tables-list" class="tables-list">
                    <!-- Tables will be rendered here by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Store all tables and roles data for JS -->
    <script>
        window.allTables = @json($allTables);
        window.allRoles = @json($roles->load('permissions')->toArray());
        window.allDatabases = @json($databases);
    </script>

    <!-- Role Modal -->
    <div id="roleModal"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div class="glass-card modal-content">
            <h3 id="roleModalTitle">Tambah Role</h3>
            <form id="roleForm" method="POST" style="margin-top: 1.5rem;">
                @csrf
                <input type="hidden" name="_method" id="roleFormMethod" value="POST">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Nama Role</label>
                    <input type="text" name="name" id="roleNameInput"
                        style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;"
                        required>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Deskripsi</label>
                    <textarea name="description" id="roleDescInput"
                        style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white; resize: none;"
                        rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" class="btn btn-cancel"
                        onclick="document.getElementById('roleModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .roles-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        .role-list-card {
            padding: 1.5rem;
            max-height: calc(100vh - 150px);
            overflow-y: auto;
            position: sticky;
            top: 20px;
        }

        .permissions-card {
            padding: 1.5rem;
            min-height: calc(100vh - 150px);
            display: flex;
            flex-direction: column;
        }

        .permissions-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        /* Filter Bar Styles */
        .filter-bar {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.03);
            padding: 1.25rem;
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-group input, .filter-group select {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            padding: 0.6rem 0.8rem;
            border-radius: 8px;
            color: white;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.8);
        }

        .selection-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0 0.5rem;
        }

        .stats-info {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
        }

        /* Tables List Styles */
        .tables-container {
            flex: 1;
            overflow-y: auto;
            max-height: 600px;
            padding-right: 5px;
        }

        .tables-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .table-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            padding: 1rem;
            border-radius: 12px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .table-item:hover {
            background: rgba(255, 255, 255, 0.06);
            transform: translateX(4px);
        }

        .table-item.allowed {
            border-left: 4px solid #10b981;
            background: rgba(16, 185, 129, 0.05);
        }

        .table-checkbox-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid #475569;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            color: transparent;
        }

        .table-item.allowed .custom-checkbox {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }

        .table-info {
            flex: 1;
            min-width: 0;
        }

        .table-main-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.25rem;
        }

        .table-name {
            font-weight: 600;
            color: #f1f5f9;
            font-size: 0.95rem;
        }

        .table-meta {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .badge {
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .badge-db { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .badge-schema { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; }
        .badge-type { background: rgba(168, 85, 247, 0.2); color: #c084fc; }

        .table-description {
            font-size: 0.8rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .role-item.active {
            background: var(--primary) !important;
            color: white !important;
        }

        .role-item.has-changes {
            border: 2px solid #f59e0b !important;
        }

        /* Modal responsive */
        .modal-content {
            width: 100%;
            max-width: 400px;
        }

        @media (max-width: 1024px) {
            .roles-container { grid-template-columns: 1fr; }
            .role-list-card { position: static; max-height: 250px; }
            .filter-bar { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 640px) {
            .filter-bar { grid-template-columns: 1fr; }
            .permissions-header { flex-direction: column; align-items: stretch; }
            .selection-controls { flex-direction: column; gap: 10px; align-items: flex-start; }
        }
    </style>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let currentRoleId = {{ $roles[0]->id ?? 'null' }};
            let allRoles = window.allRoles || [];
            let allTables = window.allTables || [];
            let selectedTables = new Set();
            let originalSelectedTables = new Set();
            let hasChanges = false;

            const tablesList = document.getElementById('tables-list');
            const unsavedIndicator = document.getElementById('unsaved-indicator');
            const visibleCountEl = document.getElementById('visible-count');
            const totalCountEl = document.getElementById('total-count');
            const selectedCountEl = document.getElementById('selected-count');

            // Initialize data
            function init() {
                if (allRoles.length > 0) {
                    currentRoleId = allRoles[0].id;
                    loadRolePermissions(currentRoleId);
                }
                totalCountEl.textContent = allTables.length;
            }

            function loadRolePermissions(roleId) {
                const role = allRoles.find(r => r.id == roleId);
                if (!role) return;

                selectedTables.clear();
                originalSelectedTables.clear();

                (role.permissions || []).forEach(p => {
                    const key = p.database_code && p.schema_name 
                        ? `${p.database_code}|${p.schema_name}|${p.table_name}`
                        : p.table_name;
                    selectedTables.add(key);
                    originalSelectedTables.add(key);
                });

                document.getElementById('selected-role-name').innerText = role.name;
                document.getElementById('selected-role-desc').innerText = role.description || '';
                
                setHasChanges(false);
                applyFilters();
            }

            function setHasChanges(value) {
                hasChanges = value;
                unsavedIndicator.style.display = value ? 'inline' : 'none';
                
                document.querySelectorAll('.role-item').forEach(btn => btn.classList.remove('has-changes'));
                if (value) {
                    const activeBtn = document.querySelector('.role-item.active');
                    if (activeBtn) activeBtn.classList.add('has-changes');
                }
            }

            function checkIfChanged() {
                if (selectedTables.size !== originalSelectedTables.size) return true;
                for (let item of selectedTables) {
                    if (!originalSelectedTables.has(item)) return true;
                }
                return false;
            }

            window.toggleTable = function(key) {
                if (selectedTables.has(key)) {
                    selectedTables.delete(key);
                } else {
                    selectedTables.add(key);
                }
                
                setHasChanges(checkIfChanged());
                renderTablesList(); // Update UI
                updateCounts();
            };

            function updateCounts() {
                selectedCountEl.textContent = selectedTables.size;
            }

            function renderTablesList(filteredTables = null) {
                const tablesToRender = filteredTables || getFilteredTables();
                tablesList.innerHTML = '';
                
                if (tablesToRender.length === 0) {
                    tablesList.innerHTML = '<div style="text-align: center; padding: 3rem; color: #64748b;">Tidak ada tabel yang ditemukan.</div>';
                    visibleCountEl.textContent = 0;
                    return;
                }

                tablesToRender.forEach(table => {
                    const key = `${table.database_code}|${table.schema_name}|${table.table_name}`;
                    const isAllowed = selectedTables.has(key);
                    
                    const item = document.createElement('div');
                    item.className = `table-item ${isAllowed ? 'allowed' : ''}`;
                    item.onclick = () => toggleTable(key);
                    
                    item.innerHTML = `
                        <div class="table-checkbox-wrapper">
                            <div class="custom-checkbox">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        <div class="table-info">
                            <div class="table-main-info" style="gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                                <span class="badge badge-db">${table.database_name}</span>
                                <span class="badge badge-schema">${table.schema_name}</span>
                                <span class="table-name">${table.table_name}</span>
                                <span class="badge badge-type" style="${table.table_type === 'view' ? 'background: rgba(168, 85, 247, 0.2); color: #c084fc;' : 'background: rgba(148, 163, 184, 0.1); color: #94a3b8;'}">${table.table_type === 'view' ? 'VIEW' : 'TABLE'}</span>
                            </div>
                            <div class="table-description" style="font-size: 0.7rem; margin-top: 4px; opacity: 0.8;">${table.description || 'Tidak ada deskripsi'}</div>
                        </div>
                    `;
                    tablesList.appendChild(item);
                });

                visibleCountEl.textContent = tablesToRender.length;
                updateCounts();
            }

            function getFilteredTables() {
                const searchTerm = document.getElementById('table-search').value.toLowerCase();
                const dbFilter = document.getElementById('db-filter').value;
                const schemaFilter = document.getElementById('schema-filter').value;
                const statusFilter = document.getElementById('status-filter').value;

                return allTables.filter(table => {
                    const key = `${table.database_code}|${table.schema_name}|${table.table_name}`;
                    const nameMatch = table.table_name.toLowerCase().includes(searchTerm) || 
                                     (table.description && table.description.toLowerCase().includes(searchTerm));
                    const dbMatch = !dbFilter || table.database_code === dbFilter;
                    const schemaMatch = !schemaFilter || table.schema_name === schemaFilter;
                    
                    let statusMatch = true;
                    if (statusFilter === 'allowed') statusMatch = selectedTables.has(key);
                    else if (statusFilter === 'not_allowed') statusMatch = !selectedTables.has(key);

                    return nameMatch && dbMatch && schemaMatch && statusMatch;
                });
            }

            window.applyFilters = function() {
                renderTablesList();
            };

            window.handleDbFilterChange = async function(dbCode) {
                const schemaSelect = document.getElementById('schema-filter');
                schemaSelect.innerHTML = '<option value="">Semua Schema</option>';
                
                if (dbCode) {
                    try {
                        const db = window.allDatabases.find(d => d.database === dbCode);
                        if (db) {
                            const response = await fetch(`/admin/databases/${db.id}/schemas`);
                            const data = await response.json();
                            data.schemas.forEach(s => {
                                const opt = document.createElement('option');
                                opt.value = s;
                                opt.textContent = s;
                                schemaSelect.appendChild(opt);
                            });
                        }
                    } catch (e) { console.error(e); }
                }
                
                applyFilters();
            };

            window.bulkAction = function(action) {
                const filtered = getFilteredTables();
                filtered.forEach(table => {
                    const key = `${table.database_code}|${table.schema_name}|${table.table_name}`;
                    if (action === 'select') selectedTables.add(key);
                    else selectedTables.delete(key);
                });
                
                setHasChanges(checkIfChanged());
                renderTablesList();
            };

            window.selectRole = function(roleId, el) {
                if (hasChanges) {
                    Swal.fire({
                        title: 'Perubahan Belum Disimpan',
                        text: 'Yakin ingin pindah role?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Pindah',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) doSelectRole(roleId, el);
                    });
                } else {
                    doSelectRole(roleId, el);
                }
            };

            function doSelectRole(roleId, el) {
                currentRoleId = roleId;
                document.querySelectorAll('.role-item').forEach(btn => btn.classList.remove('active'));
                el.classList.add('active');
                loadRolePermissions(roleId);
            }

            window.savePermissions = function() {
                const tables = Array.from(selectedTables);
                
                Swal.fire({
                    title: 'Simpan Perubahan?',
                    text: `Anda akan menyimpan ${tables.length} tabel untuk role ini.`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Simpan'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch(`/admin/roles/${currentRoleId}/permissions`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ tables })
                        }).then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Update local data
                                const role = allRoles.find(r => r.id == currentRoleId);
                                role.permissions = tables.map(t => {
                                    const parts = t.split('|');
                                    return { database_code: parts[0], schema_name: parts[1], table_name: parts[2] };
                                });
                                originalSelectedTables = new Set(selectedTables);
                                setHasChanges(false);
                                Swal.fire('Berhasil!', 'Hak akses disimpan.', 'success');
                            }
                        });
                    }
                });
            };

            window.showRoleModal = function (type, role = null) {
                const modal = document.getElementById('roleModal');
                const form = document.getElementById('roleForm');
                const method = document.getElementById('roleFormMethod');

                modal.style.display = 'flex';
                if (type === 'create') {
                    document.getElementById('roleModalTitle').innerText = 'Tambah Role';
                    form.action = "{{ route('admin.roles.store') }}";
                    method.value = 'POST';
                    form.reset();
                } else {
                    document.getElementById('roleModalTitle').innerText = 'Edit Role';
                    form.action = `/admin/roles/${role.id}`;
                    method.value = 'PUT';
                    document.getElementById('roleNameInput').value = role.name;
                    document.getElementById('roleDescInput').value = role.description;
                }
            };

            window.deleteRole = function (roleId) {
                Swal.fire({
                    title: 'Hapus Role?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: 'Ya, Hapus'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch(`/admin/roles/${roleId}`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        }).then(res => res.json())
                        .then(data => {
                            if (data.success) location.reload();
                        });
                    }
                });
            };

            init();
        });
    </script>
@endsection