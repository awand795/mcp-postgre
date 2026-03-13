@extends('layouts.admin')

@section('content')
<div class="header">
    <h1>Management Role & Permissions</h1>
    <button class="btn btn-primary" onclick="showRoleModal('create')"><i class="fas fa-plus"></i> Tambah Role</button>
</div>

<div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start;">
    <!-- Role List -->
    <div class="glass-card" style="padding: 1.5rem;">
        <h3 style="margin-bottom: 1.5rem; font-size: 1.1rem; color: #94a3b8;">Daftar Role</h3>
        <ul style="list-style: none;">
            @foreach($roles as $role)
                <li style="margin-bottom: 0.8rem;">
                    <button class="btn role-item {{ $loop->first ? 'active' : '' }}"
                            style="width: 100%; justify-content: space-between; background: rgba(255,255,255,0.05); text-align: left;"
                            onclick="selectRole({{ $role->id }}, this)">
                        <span><i class="fas fa-user-shield" style="margin-right: 10px;"></i> {{ $role->name }}</span>
                        <div style="display: flex; gap: 8px;">
                            <i class="fas fa-edit" onclick="event.stopPropagation(); showRoleModal('edit', {{ json_encode($role) }})" style="font-size: 0.9rem; opacity: 0.7; cursor: pointer;" title="Edit Role"></i>
                            <i class="fas fa-trash" onclick="event.stopPropagation(); deleteRole({{ $role->id }})" style="font-size: 0.9rem; opacity: 0.7; cursor: pointer; color: #ef4444;" title="Hapus Role"></i>
                        </div>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>

    <!-- Drag & Drop Permissions -->
    <div class="glass-card" id="permissions-area">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h2 id="selected-role-name">{{ $roles[0]->name ?? 'Select Role' }}</h2>
                <p id="selected-role-desc" style="color: #94a3b8; font-size: 0.9rem;">{{ $roles[0]->description ?? '' }}</p>
                <span id="unsaved-indicator" style="display: none; color: #f59e0b; font-size: 0.85rem; margin-top: 5px;">
                    <i class="fas fa-exclamation-triangle"></i> Ada perubahan yang belum disimpan
                </span>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button class="btn" onclick="selectAll()"><i class="fas fa-check-double"></i> Pilih Semua</button>
                <button class="btn" onclick="clearAll()"><i class="fas fa-times-circle"></i> Lepaskan Semua</button>
                <button class="btn btn-primary" onclick="savePermissions()"><i class="fas fa-save"></i> Simpan Akses</button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Available Tables -->
            <div>
                <h4 style="margin-bottom: 1rem; color: #94a3b8;"><i class="fas fa-list"></i> Tabel Tersedia</h4>
                <div id="available-tables" style="min-height: 300px; background: rgba(0,0,0,0.2); border-radius: 12px; padding: 1rem; border: 2px dashed var(--glass-border);">
                </div>
            </div>

            <!-- Allowed Tables -->
            <div>
                <h4 style="margin-bottom: 1rem; color: #10b981;"><i class="fas fa-check-circle"></i> Tabel Diizinkan</h4>
                <div id="allowed-tables" style="min-height: 300px; background: rgba(16, 185, 129, 0.05); border-radius: 12px; padding: 1rem; border: 2px solid rgba(16, 185, 129, 0.2);">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Store all tables and roles data for JS -->
<script>
    window.allTables = @json($allTables);
    window.allRoles = @json($roles->load('permissions')->toArray());
</script>

<!-- Role Modal -->
<div id="roleModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-card" style="width: 100%; max-width: 400px;">
        <h3 id="roleModalTitle">Tambah Role</h3>
        <form id="roleForm" method="POST" style="margin-top: 1.5rem;">
            @csrf
            <input type="hidden" name="_method" id="roleFormMethod" value="POST">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Nama Role</label>
                <input type="text" name="name" id="roleNameInput" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white;" required>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8;">Deskripsi</label>
                <textarea name="description" id="roleDescInput" style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 12px; color: white; resize: none;" rows="3"></textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" onclick="document.getElementById('roleModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<style>
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
    }
    .table-tag:hover {
        background: rgba(255,255,255,0.1);
        transform: scale(1.02);
    }
    .sortable-ghost {
        opacity: 0.4;
        background: var(--primary);
    }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentRoleId = {{ $roles[0]->id ?? 'null' }};
    let allRoles = window.allRoles || [];
    let allTables = window.allTables || [];
    let hasChanges = false;

    console.log('allTables:', allTables);
    console.log('allRoles:', allRoles);

    const availableList = document.getElementById('available-tables');
    const allowedList = document.getElementById('allowed-tables');
    const unsavedIndicator = document.getElementById('unsaved-indicator');

    // Create table tag element
    function createTableTag(tableName) {
        const div = document.createElement('div');
        div.className = 'table-tag';
        div.setAttribute('data-id', tableName);
        div.style.cssText = 'background: rgba(255,255,255,0.05); padding: 10px 15px; border-radius: 10px; margin-bottom: 8px; cursor: move; border: 1px solid var(--glass-border);';
        div.innerHTML = '<i class="fas fa-table" style="margin-right: 8px; color: #94a3b8;"></i> ' + tableName;
        return div;
    }

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
        console.log('Rendering tables for roleId:', roleId);
        const role = allRoles.find(r => r.id == roleId);
        if (!role) {
            console.log('Role not found!');
            return;
        }

        const allowedTables = (role.permissions || []).map(p => p.table_name);
        console.log('Allowed tables for this role:', allowedTables);

        // Clear both lists
        availableList.innerHTML = '';
        allowedList.innerHTML = '';

        // Populate based on allowed tables
        allTables.forEach(table => {
            const tag = createTableTag(table);
            if (allowedTables.includes(table)) {
                allowedList.appendChild(tag);
            } else {
                availableList.appendChild(tag);
            }
        });

        // Reset changes indicator
        setHasChanges(false);
        console.log('Rendered. Available:', availableList.children.length, 'Allowed:', allowedList.children.length);
    }

    // Initialize Sortable with change tracking
    new Sortable(availableList, {
        group: 'tables',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onSort: function() {
            setHasChanges(true);
        }
    });

    new Sortable(allowedList, {
        group: 'tables',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onSort: function() {
            setHasChanges(true);
        }
    });

    function selectRole(roleId, el) {
        console.log('Selecting role:', roleId);
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
    } else {
        console.log('No roles or tables to initialize');
    }

    // Select All - move all from available to allowed
    window.selectAll = function() {
        if (!currentRoleId) return;
        
        Array.from(availableList.children).forEach(tag => {
            allowedList.appendChild(tag);
        });
        setHasChanges(true);
    };

    // Clear All - move all from allowed to available
    window.clearAll = function() {
        if (!currentRoleId) return;
        
        Array.from(allowedList.children).forEach(tag => {
            availableList.appendChild(tag);
        });
        setHasChanges(true);
    };

    // Expose functions globally
    window.selectRole = selectRole;
    window.savePermissions = function() {
        if (!currentRoleId) return;

        const tables = Array.from(allowedList.children).map(item => item.dataset.id);
        
        // Get current role data to compare
        const currentRole = allRoles.find(r => r.id == currentRoleId);
        const oldTables = (currentRole.permissions || []).map(p => p.table_name);
        
        // Find added and removed tables
        const added = tables.filter(t => !oldTables.includes(t));
        const removed = oldTables.filter(t => !tables.includes(t));
        
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
                      console.log('Save response:', data);
                      if (data.success) {
                          // Update role data in allRoles array
                          const roleIndex = allRoles.findIndex(r => r.id == currentRoleId);
                          if (roleIndex !== -1) {
                              allRoles[roleIndex].permissions = tables.map(t => ({ table_name: t }));
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

    window.showRoleModal = function(type, role = null) {
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

    window.deleteRole = function(roleId) {
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
