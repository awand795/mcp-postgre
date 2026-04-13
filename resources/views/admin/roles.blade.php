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
                                <i class="fas fa-edit"
                                    onclick="event.stopPropagation(); showRoleModal('edit', {{ json_encode($role) }})"
                                    style="font-size: 0.9rem; opacity: 0.7; cursor: pointer;" title="Edit Role"></i>
                                <i class="fas fa-trash" onclick="event.stopPropagation(); deleteRole({{ $role->id }})"
                                    style="font-size: 0.9rem; opacity: 0.7; cursor: pointer; color: #ef4444;"
                                    title="Hapus Role"></i>
                            </div>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- Drag & Drop Permissions -->
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
                    <button class="btn" onclick="selectAll()"><i class="fas fa-check-double"></i> <span class="btn-text">Pilih Semua</span></button>
                    <button class="btn" onclick="clearAll()"><i class="fas fa-times-circle"></i> <span class="btn-text">Hapus Semua</span></button>
                    <button class="btn btn-primary" onclick="savePermissions()"><i class="fas fa-save"></i> <span class="btn-text">Simpan Akses</span></button>
                </div>
            </div>

            <!-- Database Filter -->
            <div class="db-filter-bar" style="margin-bottom: 1rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                <label style="color: #94a3b8; font-size: 0.85rem;"><i class="fas fa-filter"></i> Filter Database:</label>
                <select id="db-filter" onchange="filterByDatabase(this.value)"
                    style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.5rem 0.8rem; border-radius: 8px; color: white; font-size: 0.85rem;">
                    <option value="">Semua Database</option>
                    @foreach($databases as $db)
                        <option value="{{ $db->code }}">{{ $db->name }} ({{ $db->code }})</option>
                    @endforeach
                </select>
                <select id="schema-filter" onchange="filterBySchema(this.value)"
                    style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.5rem 0.8rem; border-radius: 8px; color: white; font-size: 0.85rem; display: none;">
                    <option value="">Semua Schema</option>
                </select>
            </div>

            <div class="tables-grid">
                <!-- Available Tables -->
                <div class="table-column">
                    <h4 style="margin-bottom: 1rem; color: #94a3b8;"><i class="fas fa-table"></i> Tabel Tersedia</h4>
                    <div class="search-wrapper" style="margin-bottom: 0.75rem;">
                        <input type="text" id="available-tables-search" placeholder="🔍 Cari tabel..."
                            class="table-search-input"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.6rem 0.8rem; border-radius: 8px; color: white; font-size: 0.85rem;"
                            oninput="filterTables('available-tables', this.value)">
                    </div>
                    <div id="available-tables"
                        class="drop-zone"
                        style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 1rem; border: 2px dashed var(--glass-border);">
                    </div>
                </div>

                <!-- Allowed Tables -->
                <div class="table-column">
                    <h4 style="margin-bottom: 1rem; color: #10b981;"><i class="fas fa-check-circle"></i> Tabel Diizinkan
                    </h4>
                    <div class="search-wrapper" style="margin-bottom: 0.75rem;">
                        <input type="text" id="allowed-tables-search" placeholder="🔍 Cari tabel..."
                            class="table-search-input"
                            style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.6rem 0.8rem; border-radius: 8px; color: white; font-size: 0.85rem;"
                            oninput="filterTables('allowed-tables', this.value)">
                    </div>
                    <div id="allowed-tables"
                        class="drop-zone"
                        style="background: rgba(16, 185, 129, 0.05); border-radius: 12px; padding: 1rem; border: 2px solid rgba(16, 185, 129, 0.2);">
                    </div>
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
                    <button type="button" class="btn"
                        onclick="document.getElementById('roleModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .roles-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            align-items: start;
        }

        .role-list-card {
            padding: 1.5rem;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .permissions-card {
            padding: 1.5rem;
        }

        .permissions-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .permissions-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: stretch;
        }

        .table-column {
            display: flex;
            flex-direction: column;
        }

        .drop-zone {
            flex: 1;
            min-height: 400px;
        }

        .table-search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .table-search-input::placeholder {
            color: rgba(148, 163, 184, 0.6);
        }

        select option {
            background-color: #1e293b;
            color: white;
        }

        .role-item.active {
            background: var(--primary) !important;
            color: white !important;
        }

        .role-item.has-changes {
            border: 2px solid #f59e0b !important;
            border-left: 4px solid #f59e0b !important;
        }

        .table-tag {
            transition: transform 0.2s, background 0.2s;
            background: rgba(255,255,255,0.05);
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 8px;
            cursor: move;
            border: 1px solid var(--glass-border);
        }

        .table-tag-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .table-identifier {
            font-family: monospace;
            font-size: 0.85rem;
            color: #e2e8f0;
            word-break: break-all;
        }

        .db-badge {
            display: inline-block;
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-right: 6px;
            font-weight: 600;
        }

        .view-badge {
            display: inline-block;
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.65rem;
            margin-right: 6px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .table-desc {
            margin: 0 0 4px 0;
            font-size: 0.75rem;
            color: #64748b;
            line-height: 1.4;
        }

        .table-type-label {
            margin: 0 0 4px 0;
            font-size: 0.65rem;
            color: #94a3b8;
            font-style: italic;
        }

        .drag-handle {
            color: #64748b;
            cursor: grab;
            opacity: 0.5;
            transition: opacity 0.2s;
        }

        .table-tag:hover .drag-handle {
            opacity: 1;
        }

        .table-tag:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.01);
        }

        .sortable-ghost {
            opacity: 0.4;
            background: var(--primary);
        }

        /* Modal responsive */
        .modal-content {
            width: 100%;
            max-width: 400px;
        }

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .roles-container {
                grid-template-columns: 1fr;
            }

            .role-list-card {
                max-height: 250px;
            }

            .tables-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .permissions-header {
                flex-direction: column;
                align-items: stretch;
            }

            .permissions-actions {
                width: 100%;
                justify-content: stretch;
            }

            .permissions-actions .btn {
                flex: 1;
                justify-content: center;
                min-width: 120px;
            }

            .btn-text {
                display: none;
            }

            .tables-grid {
                gap: 1rem;
            }

            #available-tables,
            #allowed-tables {
                min-height: 200px;
            }
        }

        @media (max-width: 480px) {
            .glass-card {
                padding: 1rem !important;
            }

            .permissions-actions .btn {
                padding: 0.6rem 0.8rem;
                font-size: 0.85rem;
            }

            h2 {
                font-size: 1.2rem !important;
            }

            h4 {
                font-size: 0.95rem !important;
            }
        }
    </style>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let currentRoleId = {{ $roles[0]->id ?? 'null' }};
            let allRoles = window.allRoles || [];
            let allTables = window.allTables || [];
            let hasChanges = false;

            const availableList = document.getElementById('available-tables');
            const allowedList = document.getElementById('allowed-tables');
            const unsavedIndicator = document.getElementById('unsaved-indicator');

            // Create table tag element with multi-database format
            function createTableTag(tableData) {
                const div = document.createElement('div');
                div.className = 'table-tag';

                // tableData can be string (legacy) or object (new format)
                let dbCode, schemaName, tableName, description, fullIdentifier, searchStr, isView;

                if (typeof tableData === 'string') {
                    // Legacy format - just table name
                    tableName = tableData;
                    dbCode = 'default';
                    schemaName = 'public';
                    description = '';
                    fullIdentifier = tableName;
                    searchStr = tableName.toLowerCase();
                    isView = false;
                } else {
                    // New format: { database_code, database_name, schema_name, table_name, description, table_type }
                    dbCode = tableData.database_code || 'unknown';
                    let dbName = tableData.database_name || dbCode;
                    schemaName = tableData.schema_name || 'public';
                    tableName = tableData.table_name;
                    description = tableData.description || '';
                    isView = tableData.table_type === 'view';
                    fullIdentifier = `${dbCode}|${schemaName}|${tableName}`;
                    searchStr = dbName.toLowerCase() + ' ' + schemaName.toLowerCase() + ' ' + tableName.toLowerCase() + ' ' + description.toLowerCase();
                }

                div.setAttribute('data-id', fullIdentifier);
                div.setAttribute('data-db', dbCode);
                div.setAttribute('data-schema', schemaName);
                div.setAttribute('data-table', tableName);
                div.setAttribute('data-search', searchStr);
                div.setAttribute('data-type', isView ? 'view' : 'table');

                const dbBadge = `<span class="db-badge">${dbName}</span>`;
                const typeLabel = isView ? 'View' : 'Table';
                const descHtml = description ? `<p class="table-desc">${truncate(description, 150)}</p>` : '';

                div.innerHTML = `
                    <div class="table-tag-header">
                        <span class="table-identifier">${dbBadge} ${schemaName}.${tableName}</span>
                        <i class="fas fa-grip-vertical drag-handle"></i>
                    </div>
                    <p class="table-type-label">${typeLabel}</p>
                    ${descHtml}
                `;

                return div;
            }

            function truncate(str, len) {
                if (str.length <= len) return str;
                return str.substring(0, len) + '...';
            }

            // Filter tables based on search input
            window.filterTables = function(containerId, searchTerm) {
                const container = document.getElementById(containerId);
                if (!container) return;

                const terms = searchTerm.toLowerCase().trim().split(/\s+/);
                const tags = container.querySelectorAll('.table-tag');

                tags.forEach(tag => {
                    const searchStr = tag.getAttribute('data-search') || '';
                    if (searchTerm.trim() === '') {
                        tag.style.display = '';
                    } else {
                        const match = terms.every(term => searchStr.includes(term));
                        tag.style.display = match ? '' : 'none';
                    }
                });
            };

            // Show/hide unsaved indicator
            function setHasChanges(value) {
                hasChanges = value;
                unsavedIndicator.style.display = value ? 'inline' : 'none';

                // Add highlight to current role in list
                document.querySelectorAll('.role-item').forEach(btn => {
                    btn.classList.remove('has-changes');
                });
                if (value && currentRoleId) {
                    const activeBtn = document.querySelector('.role-item.active');
                    if (activeBtn) {
                        activeBtn.classList.add('has-changes');
                    }
                }
            }

            // Render tables based on role
            function renderTablesForRole(roleId) {
                const role = allRoles.find(r => r.id == roleId);
                if (!role) {
                    return;
                }

                // Build allowed set from permissions (new format: db.schema.table)
                const allowedSet = new Set();
                (role.permissions || []).forEach(p => {
                    if (p.database_code && p.schema_name) {
                        // New format
                        allowedSet.add(`${p.database_code}|${p.schema_name}|${p.table_name}`);
                    } else {
                        // Legacy format
                        allowedSet.add(p.table_name);
                    }
                });

                // Clear both lists
                availableList.innerHTML = '';
                allowedList.innerHTML = '';

                // Populate tables
                allTables.forEach(table => {
                    const tag = createTableTag(table);
                    
                    // Check if this table is allowed for this role
                    let tableKey;
                    if (typeof table === 'string') {
                        tableKey = table;
                    } else {
                        tableKey = `${table.database_code}|${table.schema_name}|${table.table_name}`;
                    }
                    
                    if (allowedSet.has(tableKey)) {
                        allowedList.appendChild(tag);
                    } else {
                        availableList.appendChild(tag);
                    }
                });

                // Reset changes indicator
                setHasChanges(false);
            }

            // Initialize Sortable with change tracking
            new Sortable(availableList, {
                group: 'tables',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onSort: function () {
                    setHasChanges(true);
                }
            });

            new Sortable(allowedList, {
                group: 'tables',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onSort: function () {
                    setHasChanges(true);
                }
            });

            function selectRole(roleId, el) {
                const role = allRoles.find(r => r.id == roleId);
                if (!role) return;

                // Check for unsaved changes
                if (hasChanges) {
                    Swal.fire({
                        title: 'Perubahan Belum Disimpan',
                        text: 'Anda memiliki perubahan yang belum disimpan. Yakin ingin pindah role?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3b82f6',
                        cancelButtonColor: '#64748b',
                        confirmButtonText: 'Ya, Pindah',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            doSelectRole(roleId, el);
                        }
                    });
                } else {
                    doSelectRole(roleId, el);
                }
            }

            function doSelectRole(roleId, el) {
                const role = allRoles.find(r => r.id == roleId);
                currentRoleId = roleId;
                document.getElementById('selected-role-name').innerText = role.name;
                document.getElementById('selected-role-desc').innerText = role.description || '';

                document.querySelectorAll('.role-item').forEach(btn => btn.classList.remove('active'));
                el.classList.add('active');

                // Re-render tables for this role
                renderTablesForRole(roleId);
            }

            // Initialize tables for first role on page load
            if (allRoles.length > 0 && allTables.length > 0) {
                currentRoleId = allRoles[0].id;
                renderTablesForRole(currentRoleId);
            }

            // Select All - move all from available to allowed
            window.selectAll = function () {
                if (!currentRoleId) return;

                Array.from(availableList.children).forEach(tag => {
                    allowedList.appendChild(tag);
                });
                setHasChanges(true);
            };

            // Clear All - move all from allowed to available
            window.clearAll = function () {
                if (!currentRoleId) return;

                Array.from(allowedList.children).forEach(tag => {
                    availableList.appendChild(tag);
                });
                setHasChanges(true);
            };

            // Filter by database
            let currentDbFilter = '';
            let currentSchemaFilter = '';

            window.filterByDatabase = function(dbCode) {
                currentDbFilter = dbCode;
                currentSchemaFilter = '';
                document.getElementById('schema-filter').value = '';
                
                // Show/hide schema filter based on db selection
                const schemaSelect = document.getElementById('schema-filter');
                if (dbCode) {
                    schemaSelect.style.display = 'inline-block';
                    // Load schemas for this database
                    loadSchemasForFilter(dbCode);
                } else {
                    schemaSelect.style.display = 'none';
                    schemaSelect.innerHTML = '<option value="">Semua Schema</option>';
                }
                
                applyFilters();
            };

            window.filterBySchema = function(schemaName) {
                currentSchemaFilter = schemaName;
                applyFilters();
            };

            async function loadSchemasForFilter(dbCode) {
                try {
                    const db = window.allDatabases?.find(d => d.code === dbCode);
                    if (!db) return;

                    const response = await fetch(`/admin/databases/${db.id}/schemas`);
                    const data = await response.json();
                    
                    const select = document.getElementById('schema-filter');
                    select.innerHTML = '<option value="">Semua Schema</option>';
                    data.schemas.forEach(schema => {
                        const option = document.createElement('option');
                        option.value = schema;
                        option.textContent = schema;
                        select.appendChild(option);
                    });
                } catch (e) {
                    console.error('Failed to load schemas:', e);
                }
            }

            function applyFilters() {
                const tags = document.querySelectorAll('.table-tag');
                tags.forEach(tag => {
                    const db = tag.getAttribute('data-db') || '';
                    const schema = tag.getAttribute('data-schema') || '';
                    
                    let show = true;
                    if (currentDbFilter && db !== currentDbFilter) show = false;
                    if (currentSchemaFilter && schema !== currentSchemaFilter) show = false;
                    
                    tag.style.display = show ? '' : 'none';
                });
            }

            // Expose functions globally
            window.selectRole = selectRole;
            window.savePermissions = function () {
                if (!currentRoleId) return;

                // Get the full identifiers including database and schema
                const tables = Array.from(allowedList.children).map(item => {
                    const db = item.dataset.db || 'default';
                    const schema = item.dataset.schema || 'public';
                    const table = item.dataset.table || item.dataset.id;
                    return `${db}|${schema}|${table}`;
                });

                // Get current role data to compare
                const currentRole = allRoles.find(r => r.id == currentRoleId);
                const oldPermissions = (currentRole.permissions || []).map(p => {
                    if (p.database_code && p.schema_name) {
                        return `${p.database_code}|${p.schema_name}|${p.table_name}`;
                    }
                    return p.table_name; // Legacy
                });

                // Find added and removed tables
                const added = tables.filter(t => !oldPermissions.includes(t));
                const removed = oldPermissions.filter(t => !tables.includes(t));

                // Show confirmation with preview
                let html = '<div style="text-align: left; max-height: 300px; overflow-y: auto;">';

                if (added.length > 0) {
                    html += '<div style="margin-bottom: 15px;">';
                    html += '<p style="color: #10b981; margin-bottom: 5px;"><i class="fas fa-plus-circle"></i> Tabel yang akan ditambahkan:</p>';
                    html += '<ul style="margin: 0; padding-left: 20px;">';
                    added.forEach(t => html += `<li>${t}</li>`);
                    html += '</ul></div>';
                }

                if (removed.length > 0) {
                    html += '<div>';
                    html += '<p style="color: #ef4444; margin-bottom: 5px;"><i class="fas fa-minus-circle"></i> Tabel yang akan dihapus:</p>';
                    html += '<ul style="margin: 0; padding-left: 20px;">';
                    removed.forEach(t => html += `<li>${t}</li>`);
                    html += '</ul></div>';
                }

                if (added.length === 0 && removed.length === 0) {
                    html += '<p style="color: #94a3b8;">Tidak ada perubahan.</p>';
                }

                html += '</div>';

                Swal.fire({
                    title: 'Konfirmasi Simpan',
                    html: html,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Ya, Simpan',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Proceed with save
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
                                    // Update role data in allRoles array with new format
                                    const roleIndex = allRoles.findIndex(r => r.id == currentRoleId);
                                    if (roleIndex !== -1) {
                                        allRoles[roleIndex].permissions = tables.map(t => {
                                            const parts = t.split('|');
                                            if (parts.length === 3) {
                                                return {
                                                    database_code: parts[0],
                                                    schema_name: parts[1],
                                                    table_name: parts[2]
                                                };
                                            }
                                            return { table_name: t }; // Legacy
                                        });
                                    }
                                    setHasChanges(false);
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Berhasil!',
                                        text: 'Hak akses berhasil disimpan!',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                }
                            })
                            .catch(err => {
                                console.error('Save error:', err);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: 'Gagal menyimpan hak akses'
                                });
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
                    text: "Role yang dihapus tidak dapat dikembalikan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch(`/admin/roles/${roleId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Terhapus!',
                                        text: 'Role berhasil dihapus',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Gagal!',
                                        text: data.message || 'Gagal menghapus role'
                                    });
                                }
                            })
                            .catch(err => {
                                console.error('Delete error:', err);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: 'Terjadi kesalahan saat menghapus role'
                                });
                            });
                    }
                });
            };
        });
    </script>
@endsection