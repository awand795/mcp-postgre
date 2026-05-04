@extends('layouts.admin')

@section('content')

{{-- ═══════════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════════ --}}
<div class="db-page-header">
    <div class="header-left">
        <h1 class="page-title">
            <span class="title-icon"><i class="fas fa-database"></i></span>
            Management Database
        </h1>
        <p class="page-subtitle">Kelola koneksi database yang terhubung ke sistem</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-secondary" onclick="testAllConnections()" id="testAllBtn">
            <i class="fas fa-heartbeat"></i>
            <span>Test All</span>
        </button>
        <button class="btn btn-primary" onclick="showDatabaseModal('create')">
            <i class="fas fa-plus"></i>
            <span>Tambah Database</span>
        </button>
    </div>
</div>

{{-- Alerts --}}
@if(session('success'))
    <div class="alert alert-success" id="flash-alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    </div>
@endif
@if(session('warning'))
    <div class="alert alert-warning" id="flash-alert">
        <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
        <button class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger" id="flash-alert">
        <i class="fas fa-times-circle"></i> {{ $errors->first() }}
        <button class="alert-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    </div>
@endif

{{-- ═══════════════════════════════════════════════════════
     HEALTH SUMMARY BAR (hidden by default, shown after test all)
═══════════════════════════════════════════════════════ --}}
<div class="health-bar glass-card" id="healthBar" style="display:none;">
    <div class="health-stat">
        <span class="health-num" id="hTotal">0</span>
        <span class="health-label">Total</span>
    </div>
    <div class="health-divider"></div>
    <div class="health-stat success">
        <span class="health-num" id="hHealthy">0</span>
        <span class="health-label"><i class="fas fa-check-circle"></i> Connected</span>
    </div>
    <div class="health-divider"></div>
    <div class="health-stat danger">
        <span class="health-num" id="hUnhealthy">0</span>
        <span class="health-label"><i class="fas fa-times-circle"></i> Failed</span>
    </div>
    <button class="health-close" onclick="document.getElementById('healthBar').style.display='none'">
        <i class="fas fa-times"></i>
    </button>
</div>

{{-- ═══════════════════════════════════════════════════════
     TOOLBAR: Search + Filter + View Toggle
═══════════════════════════════════════════════════════ --}}
<div class="toolbar glass-card">
    <div class="toolbar-search">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" placeholder="Cari nama, kode, host..." oninput="filterDatabases()" />
        <button class="search-clear" id="searchClear" onclick="clearSearch()" style="display:none;">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="toolbar-filters">
        <select id="filterDriver" onchange="filterDatabases()" class="filter-select">
            <option value="">Semua Driver</option>
            <option value="pgsql">PostgreSQL</option>
            <option value="mysql">MySQL</option>
            <option value="mariadb">MariaDB</option>
            <option value="sqlsrv">SQL Server</option>
            <option value="sqlite">SQLite</option>
        </select>
        <select id="filterStatus" onchange="filterDatabases()" class="filter-select">
            <option value="">Semua Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="connected">Connected</option>
            <option value="failed">Failed</option>
            <option value="untested">Not Tested</option>
        </select>
    </div>
    <div class="toolbar-view">
        <button class="view-btn active" id="viewGrid" onclick="setView('grid')" title="Grid View">
            <i class="fas fa-th-large"></i>
        </button>
        <button class="view-btn" id="viewList" onclick="setView('list')" title="List View">
            <i class="fas fa-list"></i>
        </button>
    </div>
</div>

{{-- Filter count --}}
<div class="filter-info" id="filterInfo" style="display:none;">
    <span id="filterCount"></span>
    <button onclick="clearAllFilters()">Reset Filter <i class="fas fa-times"></i></button>
</div>

{{-- ═══════════════════════════════════════════════════════
     DATABASE GRID / LIST
═══════════════════════════════════════════════════════ --}}
<div class="database-grid" id="databaseGrid">
    @forelse($databases as $db)
        <div class="database-card glass-card {{ !$db->is_active ? 'inactive' : '' }}"
             data-name="{{ strtolower($db->name) }}"
             data-code="{{ strtolower($db->code) }}"
             data-host="{{ strtolower($db->host ?? '') }}"
             data-driver="{{ $db->driver }}"
             data-active="{{ $db->is_active ? 'active' : 'inactive' }}"
             data-teststatus="{{ $db->test_status ?? 'untested' }}"
             id="dbCard{{ $db->id }}">

            {{-- Pulsing status dot --}}
            <div class="status-dot
                @if($db->test_status === 'success') dot-success
                @elseif($db->test_status === 'failed') dot-failed
                @else dot-pending
                @endif"
                title="{{ $db->test_status === 'success' ? 'Connected' : ($db->test_status === 'failed' ? 'Connection Failed' : 'Not Tested') }}">
            </div>

            {{-- Card Header --}}
            <div class="db-card-header">
                <div class="db-icon driver-icon-{{ $db->driver }}">
                    <i class="fas {{ $db->driver === 'mysql' || $db->driver === 'mariadb' ? 'fa-database' : ($db->driver === 'sqlsrv' ? 'fa-server' : ($db->driver === 'sqlite' ? 'fa-file-code' : 'fa-database')) }}"></i>
                </div>
                <div class="db-title-block">
                    <h3 class="db-name">{{ $db->name }}</h3>
                    <div class="db-badges">
                        <span class="badge badge-driver">{{ strtoupper($db->driver) }}</span>
                        @if($db->is_default)
                            <span class="badge badge-default"><i class="fas fa-star"></i> Default</span>
                        @endif
                        @if(!$db->is_active)
                            <span class="badge badge-inactive">Inactive</span>
                        @endif
                    </div>
                    <p class="db-code"><i class="fas fa-tag"></i> {{ $db->code }}</p>
                </div>
                <div class="db-card-actions">
                    <button class="btn-icon" onclick="testConnection({{ $db->id }})" title="Test Connection">
                        <i class="fas fa-plug"></i>
                    </button>
                    <button class="btn-icon btn-icon-edit" onclick="showDatabaseModal('edit', {{ json_encode($db) }})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    @if(!$db->is_default)
                        <button class="btn-icon btn-icon-danger" onclick="deleteDatabase({{ $db->id }}, '{{ $db->name }}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Connection Details --}}
            <div class="db-details">
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-server"></i> Host</span>
                    <span class="detail-value">
                        {{ $db->host }}:{{ $db->port }}
                        <button class="copy-btn" onclick="copyToClipboard('{{ $db->host }}:{{ $db->port }}')" title="Copy">
                            <i class="fas fa-copy"></i>
                        </button>
                    </span>
                </div>
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-database"></i> Database</span>
                    <span class="detail-value">
                        {{ $db->database }}
                        <button class="copy-btn" onclick="copyToClipboard('{{ $db->database }}')" title="Copy">
                            <i class="fas fa-copy"></i>
                        </button>
                    </span>
                </div>
                @if($db->schema)
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-layer-group"></i> Schema</span>
                    <span class="detail-value">{{ $db->schema }}</span>
                </div>
                @endif
                @if($db->description)
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-info-circle"></i> Info</span>
                    <span class="detail-value muted">{{ Str::limit($db->description, 60) }}</span>
                </div>
                @endif
            </div>

            {{-- Status Footer --}}
            <div class="db-status-footer">
                <div class="status-left">
                    @if($db->test_status === 'success')
                        <span class="status-chip chip-success">
                            <i class="fas fa-check-circle"></i> Connected
                        </span>
                    @elseif($db->test_status === 'failed')
                        <span class="status-chip chip-failed">
                            <i class="fas fa-times-circle"></i> Failed
                        </span>
                        @if($db->test_error)
                            <span class="error-hint" title="{{ $db->test_error }}">
                                <i class="fas fa-exclamation-circle"></i>
                                {{ Str::limit($db->test_error, 40) }}
                            </span>
                        @endif
                    @else
                        <span class="status-chip chip-pending">
                            <i class="fas fa-question-circle"></i> Not Tested
                        </span>
                    @endif
                </div>
                <div class="status-right">
                    @if($db->last_tested_at)
                        <span class="last-tested">
                            <i class="fas fa-clock"></i>
                            {{ $db->last_tested_at->diffForHumans() }}
                        </span>
                    @endif
                    @if(isset($db->response_time_ms) && $db->test_status === 'success')
                        <span class="latency-badge">{{ $db->response_time_ms }}ms</span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="empty-state glass-card" id="emptyState">
            <div class="empty-animation">
                <svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
                    <ellipse cx="60" cy="30" rx="40" ry="12" fill="none" stroke="#6366f1" stroke-width="2" opacity="0.5"/>
                    <ellipse cx="60" cy="30" rx="40" ry="12" fill="rgba(99,102,241,0.08)"/>
                    <path d="M20,30 L20,75 Q20,87 60,87 Q100,87 100,75 L100,30" fill="none" stroke="#6366f1" stroke-width="2" opacity="0.5"/>
                    <path d="M20,30 L20,75 Q20,87 60,87 Q100,87 100,75 L100,30" fill="rgba(99,102,241,0.04)"/>
                    <ellipse cx="60" cy="52" rx="40" ry="12" fill="none" stroke="#6366f1" stroke-width="1.5" opacity="0.3" stroke-dasharray="4,4"/>
                    <circle cx="60" cy="58" r="8" fill="none" stroke="#10b981" stroke-width="2" opacity="0" class="db-empty-pulse"/>
                </svg>
            </div>
            <h3>Belum Ada Database</h3>
            <p>Tambahkan koneksi database untuk mulai mengelola dan menganalisis data Anda.</p>
            <button class="btn btn-primary" onclick="showDatabaseModal('create')">
                <i class="fas fa-plus"></i> Tambah Database Pertama
            </button>
        </div>
    @endforelse
</div>

{{-- No results state (for filter) --}}
<div class="no-results" id="noResults" style="display:none;">
    <i class="fas fa-search"></i>
    <p>Tidak ada database yang cocok dengan filter.</p>
    <button onclick="clearAllFilters()">Reset Filter</button>
</div>


{{-- ═══════════════════════════════════════════════════════
     MODAL — Add / Edit Database
═══════════════════════════════════════════════════════ --}}
<div id="databaseModal" class="modal-backdrop" style="display:none;" onclick="backdropClose(event)">
    <div class="modal-container glass-card">

        {{-- Modal Header --}}
        <div class="modal-header">
            <h3 id="databaseModalTitle">Tambah Database</h3>
            <button class="modal-close" onclick="document.getElementById('databaseModal').style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- Wizard Steps Indicator --}}
        <div class="wizard-steps">
            <div class="wz-step active" id="wz1" onclick="goStep(1)">
                <div class="wz-circle">1</div>
                <span>Identitas</span>
            </div>
            <div class="wz-line"></div>
            <div class="wz-step" id="wz2" onclick="goStep(2)">
                <div class="wz-circle">2</div>
                <span>Koneksi</span>
            </div>
            <div class="wz-line"></div>
            <div class="wz-step" id="wz3" onclick="goStep(3)">
                <div class="wz-circle">3</div>
                <span>Advanced</span>
            </div>
        </div>

        <form id="databaseForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="databaseFormMethod" value="POST">

            {{-- ── STEP 1: Identitas ── --}}
            <div class="wizard-panel" id="panel1">
                <div class="form-row two-col">
                    <div class="form-group">
                        <label>Nama Koneksi / Alias <span class="req">*</span></label>
                        <input type="text" name="name" id="dbNameInput" placeholder="MBI Production" required>
                    </div>
                    <div class="form-group">
                        <label>Kode <span class="req">*</span></label>
                        <input type="text" name="code" id="dbCodeInput" placeholder="mbi_prod" required>
                        <small>Huruf kecil, angka, underscore saja</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Driver Database <span class="req">*</span></label>
                    <div class="driver-selector" id="driverSelector">
                        <div class="driver-option active" data-driver="pgsql" onclick="selectDriver('pgsql')">
                            <div class="driver-opt-icon pgsql-icon"><i class="fas fa-database"></i></div>
                            <span>PostgreSQL</span>
                        </div>
                        <div class="driver-option" data-driver="mysql" onclick="selectDriver('mysql')">
                            <div class="driver-opt-icon mysql-icon"><i class="fas fa-database"></i></div>
                            <span>MySQL</span>
                        </div>
                        <div class="driver-option" data-driver="mariadb" onclick="selectDriver('mariadb')">
                            <div class="driver-opt-icon mariadb-icon"><i class="fas fa-database"></i></div>
                            <span>MariaDB</span>
                        </div>
                        <div class="driver-option" data-driver="sqlsrv" onclick="selectDriver('sqlsrv')">
                            <div class="driver-opt-icon sqlsrv-icon"><i class="fas fa-server"></i></div>
                            <span>SQL Server</span>
                        </div>
                        <div class="driver-option" data-driver="sqlite" onclick="selectDriver('sqlite')">
                            <div class="driver-opt-icon sqlite-icon"><i class="fas fa-file-code"></i></div>
                            <span>SQLite</span>
                        </div>
                    </div>
                    <input type="hidden" name="driver" id="dbDriverSelect" value="pgsql">
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" id="dbDescriptionInput" rows="2"
                        placeholder="Database production untuk MBI..."></textarea>
                </div>

                <div class="form-row two-col">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" id="dbIsActiveInput" value="1" checked>
                        <span class="custom-check"></span>
                        Aktif
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_default" id="dbIsDefaultInput" value="1">
                        <span class="custom-check"></span>
                        Jadikan Default
                    </label>
                </div>

                <div class="wizard-nav">
                    <span></span>
                    <button type="button" class="btn btn-primary" onclick="goStep(2)">
                        Selanjutnya <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            {{-- ── STEP 2: Koneksi ── --}}
            <div class="wizard-panel" id="panel2" style="display:none;">
                <div class="form-row two-col" id="hostPortRow">
                    <div class="form-group">
                        <label id="hostLabel">Host <span class="req">*</span></label>
                        <input type="text" name="host" id="dbHostInput" placeholder="db.example.com">
                    </div>
                    <div class="form-group">
                        <label>Port <span class="req">*</span></label>
                        <input type="number" name="port" id="dbPortInput" value="5432" required>
                    </div>
                </div>

                <div class="form-group">
                    <label id="databaseLabel">Nama Database <span class="req">*</span></label>
                    <input type="text" name="database" id="dbDatabaseInput" placeholder="my_database" required>
                    <small id="databaseHint">Nama database sebenarnya pada server</small>
                </div>

                <div class="form-row two-col" id="credentialsRow">
                    <div class="form-group" id="usernameGroup">
                        <label>Username <span class="req" id="usernameRequiredMark">*</span></label>
                        <input type="text" name="username" id="dbUsernameInput" placeholder="postgres">
                    </div>
                    <div class="form-group" id="passwordGroup">
                        <label>Password <span class="req" id="passwordRequiredMark">*</span></label>
                        <div class="input-with-icon">
                            <input type="password" name="password" id="dbPasswordInput" placeholder="••••••••">
                            <button type="button" class="toggle-pass" onclick="togglePassword()" title="Tampilkan/sembunyikan">
                                <i class="fas fa-eye" id="passEyeIcon"></i>
                            </button>
                        </div>
                        <small id="passwordHint" style="display:none;">Kosongkan jika tidak ingin mengubah</small>
                    </div>
                </div>

                <div class="form-row two-col">
                    <div class="form-group">
                        <label>SSL Mode</label>
                        <select name="ssl_mode" id="dbSslModeInput">
                            <option value="">None</option>
                            <option value="prefer">Prefer</option>
                            <option value="require">Require</option>
                            <option value="verify-ca">Verify CA</option>
                            <option value="verify-full">Verify Full</option>
                        </select>
                        <small>Keamanan koneksi SSL/TLS</small>
                    </div>
                    <div class="form-group">
                        <label>Connection Timeout (detik)</label>
                        <input type="number" name="connection_timeout" id="dbTimeoutInput" value="30" min="5" max="300">
                        <small>Default: 30 detik</small>
                    </div>
                </div>

                <div class="form-group" id="schemaGroup">
                    <label id="schemaLabel">Schema <span class="req">*</span></label>
                    <div class="schema-input-row">
                        <input type="text" name="schema" id="dbSchemaInput" placeholder="public">
                        <button type="button" class="btn btn-secondary" onclick="loadSchemas()" id="loadSchemasBtn">
                            <i class="fas fa-sync-alt"></i> Load
                        </button>
                    </div>
                    <select id="dbSchemaSelect" style="display:none;"></select>
                    <small id="schemaHint">PostgreSQL: sch_nama / public, SQL Server: dbo</small>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="btn btn-cancel" onclick="goStep(1)">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goStep(3)">
                        Selanjutnya <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            {{-- ── STEP 3: Advanced ── --}}
            <div class="wizard-panel" id="panel3" style="display:none;">
                {{-- Test Connection Preview --}}
                <div class="test-preview-box" id="testPreviewBox">
                    <div class="test-preview-header">
                        <i class="fas fa-plug"></i> Test Koneksi Sebelum Simpan
                    </div>
                    <p class="test-preview-desc">Uji koneksi sebelum menyimpan untuk memastikan parameter sudah benar.</p>
                    <button type="button" class="btn btn-secondary" onclick="testConnectionPreview()" id="testPreviewBtn">
                        <i class="fas fa-play-circle"></i> Test Sekarang
                    </button>
                    <div class="test-preview-result" id="testPreviewResult" style="display:none;"></div>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="btn btn-cancel" onclick="goStep(2)">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Simpan Database
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     TOAST NOTIFICATION
═══════════════════════════════════════════════════════ --}}
<div id="toastContainer" class="toast-container"></div>


{{-- ═══════════════════════════════════════════════════════
     STYLES
═══════════════════════════════════════════════════════ --}}
<style>
/* ── Page Header ── */
.db-page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    gap: 1rem;
    flex-wrap: wrap;
}
.page-title {
    font-size: 1.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}
.title-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(99,102,241,0.35);
}
.page-subtitle {
    color: #64748b;
    margin: 0.3rem 0 0 3.5rem;
    font-size: 0.9rem;
}
.header-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }

/* ── Health Bar ── */
.health-bar {
    display: flex;
    align-items: center;
    gap: 0;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    border-radius: 16px;
    position: relative;
    animation: slideDown 0.3s ease;
}
@keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
.health-stat { display: flex; flex-direction: column; align-items: center; padding: 0 1.5rem; }
.health-stat.success .health-num { color: #10b981; }
.health-stat.danger .health-num { color: #ef4444; }
.health-num { font-size: 1.8rem; font-weight: 700; line-height: 1; }
.health-label { font-size: 0.78rem; color: #64748b; margin-top: 0.25rem; display: flex; align-items: center; gap: 4px; }
.health-divider { width: 1px; height: 40px; background: var(--glass-border2); }
.health-close {
    position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: #64748b; cursor: pointer; font-size: 0.9rem;
}
.health-close:hover { color: white; }

/* ── Toolbar ── */
.toolbar {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1.25rem !important;
    margin-bottom: 1rem;
    border-radius: 16px;
    flex-wrap: wrap;
}
.toolbar-search {
    flex: 1;
    min-width: 200px;
    position: relative;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute; left: 12px; color: #64748b; pointer-events: none; font-size: 0.9rem;
}
.toolbar-search input {
    width: 100%;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    padding: 0.6rem 2.5rem 0.6rem 2.2rem;
    border-radius: 10px;
    color: var(--text-main);
    font-size: 0.9rem;
    transition: all 0.2s;
    font-family: 'Outfit', sans-serif;
}
.toolbar-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.toolbar-search input::placeholder { color: var(--text-muted); opacity: 0.7; }
.search-clear {
    position: absolute; right: 10px;
    background: none; border: none; color: #64748b; cursor: pointer;
    padding: 2px 5px; border-radius: 4px;
}
.search-clear:hover { color: white; }
.toolbar-filters { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.filter-select {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    padding: 0.6rem 0.9rem;
    border-radius: 10px;
    color: var(--text-main);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Outfit', sans-serif;
}
.filter-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.filter-select option { background: var(--card-bg); color: var(--text-main); }
.toolbar-view { display: flex; gap: 0.25rem; }
.view-btn {
    background: rgba(99,102,241,0.04);
    border: 1px solid var(--glass-border);
    color: var(--text-muted);
    padding: 0.6rem 0.8rem;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
}
.view-btn.active, .view-btn:hover { background: rgba(99,102,241,0.15); color: #818cf8; border-color: rgba(99,102,241,0.3); }

/* Filter Info */
.filter-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.85rem;
    color: #94a3b8;
}
.filter-info button {
    background: rgba(99,102,241,0.1);
    border: 1px solid rgba(99,102,241,0.2);
    color: #818cf8;
    padding: 0.25rem 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.2s;
}
.filter-info button:hover { background: rgba(99,102,241,0.2); }

/* ── Database Grid ── */
.database-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.25rem;
}
.database-grid.list-view {
    grid-template-columns: 1fr;
}
.database-grid.list-view .database-card {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 1.5rem;
    padding: 1rem 1.5rem;
}
.database-grid.list-view .db-details { display: none; }
.database-grid.list-view .db-status-footer { flex-direction: row; align-items: center; justify-content: flex-end; }
.database-grid.list-view .status-dot { position: static; margin-right: 0.5rem; }

/* ── Database Card ── */
.database-card {
    padding: 1.5rem;
    border-radius: 20px;
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
    overflow: hidden;
}
.database-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(99,102,241,0.04), transparent);
    pointer-events: none;
}
.database-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.35);
}
.database-card.inactive { opacity: 0.55; }
.database-card.card-hidden { display: none; }

/* Status Dot */
.status-dot {
    position: absolute;
    top: 1.1rem;
    right: 1.1rem;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    z-index: 2;
}
.dot-success { background: #10b981; box-shadow: 0 0 0 0 rgba(16,185,129,0.6); animation: pulseDot 2s infinite; }
.dot-failed  { background: #ef4444; box-shadow: 0 0 0 0 rgba(239,68,68,0.6); animation: pulseDot 2s infinite; }
.dot-pending { background: #f59e0b; }
@keyframes pulseDot {
    0%   { box-shadow: 0 0 0 0 currentColor; }
    70%  { box-shadow: 0 0 0 7px rgba(0,0,0,0); }
    100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); }
}
.dot-success { --pulse-color: rgba(16,185,129,0.5); }

/* Card Header */
.db-card-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.1rem;
    padding-right: 1.5rem;
}
.db-icon {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.15rem;
    color: white;
}
.driver-icon-pgsql   { background: linear-gradient(135deg, #336791, #4a8bc7); }
.driver-icon-mysql, .driver-icon-mariadb { background: linear-gradient(135deg, #00758f, #f29111); }
.driver-icon-sqlsrv  { background: linear-gradient(135deg, #e04e3d, #f47b2b); }
.driver-icon-sqlite  { background: linear-gradient(135deg, #003b57, #44a0d3); }

.db-title-block { flex: 1; min-width: 0; }
.db-name { font-size: 1rem; font-weight: 700; margin: 0 0 0.35rem 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.db-badges { display: flex; gap: 0.3rem; flex-wrap: wrap; margin-bottom: 0.3rem; }
.db-code { color: #64748b; font-size: 0.78rem; margin: 0; font-family: monospace; display: flex; align-items: center; gap: 4px; }

.badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 0.18rem 0.5rem;
    border-radius: 5px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}
.badge-driver  { background: rgba(139,92,246,0.15); color: #a78bfa; border: 1px solid rgba(139,92,246,0.25); }
.badge-default { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.25); }
.badge-inactive { background: rgba(100,116,139,0.15); color: #94a3b8; border: 1px solid rgba(100,116,139,0.25); }

.db-card-actions { display: flex; flex-direction: row; gap: 0.35rem; flex-shrink: 0; align-items: flex-start; }

.btn-icon {
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(99,102,241,0.05);
    border: 1px solid var(--glass-border2);
    color: var(--text-muted);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.8rem;
}
.btn-icon:hover { background: rgba(99,102,241,0.1); color: var(--primary); }
.btn-icon.btn-icon-edit:hover { background: rgba(245,158,11,0.15); color: #f59e0b; border-color: rgba(245,158,11,0.3); }
.btn-icon.btn-icon-danger:hover { background: rgba(239,68,68,0.15); color: #ef4444; border-color: rgba(239,68,68,0.3); }

/* Card Details */
.db-details {
    background: rgba(99,102,241,0.03);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 1rem;
}
.db-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.45rem 0.85rem;
    border-bottom: 1px solid var(--glass-border2);
    font-size: 0.82rem;
    gap: 0.5rem;
}
.db-detail-row:last-child { border-bottom: none; }
.detail-label { color: var(--text-muted); display: flex; align-items: center; gap: 6px; white-space: nowrap; flex-shrink: 0; }
.detail-label i { width: 12px; text-align: center; }
.detail-value { color: var(--text-main); font-family: monospace; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; text-align: right; word-break: break-all; }
.detail-value.muted { color: var(--text-muted); font-family: inherit; font-size: 0.8rem; }

.copy-btn {
    background: none; border: none; color: #475569; cursor: pointer;
    padding: 2px 4px; border-radius: 4px; font-size: 0.7rem;
    transition: all 0.2s; flex-shrink: 0;
}
.copy-btn:hover { color: #818cf8; background: rgba(99,102,241,0.15); }

/* Status Footer */
.db-status-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.status-left { display: flex; flex-direction: column; gap: 0.25rem; }
.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0.3rem 0.7rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
}
.chip-success { background: rgba(16,185,129,0.12); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
.chip-failed  { background: rgba(239,68,68,0.12);  color: #ef4444;  border: 1px solid rgba(239,68,68,0.2); }
.chip-pending { background: rgba(245,158,11,0.12); color: #f59e0b;  border: 1px solid rgba(245,158,11,0.2); }
.error-hint {
    font-size: 0.72rem; color: #ef4444; opacity: 0.7;
    max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    cursor: help;
}
.status-right { display: flex; align-items: center; gap: 0.5rem; }
.last-tested { font-size: 0.75rem; color: #475569; display: flex; align-items: center; gap: 4px; }
.latency-badge {
    background: rgba(99,102,241,0.12);
    color: #818cf8;
    border: 1px solid rgba(99,102,241,0.2);
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    font-family: monospace;
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
}
.empty-animation svg {
    width: 120px; height: 120px;
    margin-bottom: 1.5rem;
    animation: floatSvg 3s ease-in-out infinite;
}
@keyframes floatSvg {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.empty-state h3 { font-size: 1.3rem; margin-bottom: 0.5rem; }
.empty-state p { color: #64748b; margin-bottom: 1.5rem; }

/* No Results */
.no-results {
    text-align: center;
    padding: 3rem;
    color: #64748b;
}
.no-results i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.4; display: block; }
.no-results button {
    margin-top: 1rem;
    background: rgba(99,102,241,0.1);
    border: 1px solid rgba(99,102,241,0.2);
    color: #818cf8;
    padding: 0.5rem 1.2rem;
    border-radius: 10px;
    cursor: pointer;
}

/* ── Alert ── */
.alert {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 14px;
    margin-bottom: 1rem;
    position: relative;
    animation: slideDown 0.3s ease;
}
.alert-close {
    margin-left: auto; background: none; border: none;
    color: currentColor; opacity: 0.6; cursor: pointer; padding: 2px 6px;
}
.alert-close:hover { opacity: 1; }

/* ── Modal ── */
.modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.modal-container {
    width: 100%;
    max-width: 560px;
    max-height: 92vh;
    overflow-y: auto;
    border-radius: 24px;
    padding: 2rem;
    animation: modalIn 0.25s ease;
}
@keyframes modalIn { from { opacity:0; transform:scale(0.95) translateY(10px); } to { opacity:1; transform:scale(1) translateY(0); } }
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 1.5rem;
}
.modal-header h3 { font-size: 1.25rem; margin: 0; }
.modal-close {
    background: var(--input-bg); border: 1px solid var(--input-border);
    color: var(--text-muted); width: 34px; height: 34px; border-radius: 10px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.modal-close:hover { background: rgba(239,68,68,0.15); color: #ef4444; border-color: rgba(239,68,68,0.3); }

/* Wizard Steps */
.wizard-steps {
    display: flex; align-items: center; margin-bottom: 1.75rem; gap: 0;
}
.wz-step {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    cursor: pointer; flex-shrink: 0;
}
.wz-circle {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 700;
    background: var(--input-bg);
    border: 2px solid var(--input-border);
    color: var(--text-muted);
    transition: all 0.3s;
}
.wz-step span { font-size: 0.72rem; color: var(--text-muted); transition: color 0.3s; }
.wz-step.active .wz-circle { background: var(--primary); border-color: var(--primary); color: white; box-shadow: 0 0 15px rgba(99,102,241,0.4); }
.wz-step.active span { color: var(--primary); font-weight: 600; }
.wz-step.done .wz-circle { background: rgba(16,185,129,0.2); border-color: #10b981; color: #10b981; }
.wz-step.done span { color: #10b981; }
.wz-line { flex: 1; height: 2px; background: var(--glass-border2); margin: 0 0.5rem; margin-bottom: 1.4rem; transition: background 0.3s; }
.wz-line.done { background: rgba(16,185,129,0.4); }

/* Form Elements */
.wizard-panel { animation: fadePanel 0.2s ease; }
@keyframes fadePanel { from { opacity:0; transform:translateX(8px); } to { opacity:1; transform:translateX(0); } }
.form-group { margin-bottom: 1rem; }
.form-group label {
    display: block; margin-bottom: 0.5rem;
    color: var(--text-muted); font-size: 0.88rem; font-weight: 600;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    padding: 0.75rem 1rem;
    border-radius: 12px;
    color: var(--text-main);
    font-size: 0.9rem;
    transition: all 0.2s;
    font-family: inherit;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: rgba(99,102,241,0.07);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.form-group input::placeholder { color: var(--text-muted); opacity: 0.7; }
.form-group small { display: block; margin-top: 0.35rem; color: var(--text-muted); font-size: 0.75rem; }
.form-group select option { background: var(--card-bg); }
.form-group textarea { resize: none; }
.req { color: #ef4444; }
.form-row.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: start; }

/* Driver Selector */
.driver-selector {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}
.driver-option {
    display: flex; flex-direction: column; align-items: center; gap: 0.4rem;
    padding: 0.75rem 0.5rem;
    border: 2px solid var(--glass-border2);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.72rem; color: var(--text-muted);
    text-align: center;
}
.driver-option:hover { border-color: rgba(99,102,241,0.4); background: rgba(99,102,241,0.06); color: var(--text-main); }
.driver-option.active { border-color: var(--primary); background: rgba(99,102,241,0.12); color: var(--text-main); font-weight: 600; }
.driver-opt-icon {
    width: 34px; height: 34px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; color: white;
}
.pgsql-icon   { background: linear-gradient(135deg, #336791, #4a8bc7); }
.mysql-icon   { background: linear-gradient(135deg, #00758f, #f29111); }
.mariadb-icon { background: linear-gradient(135deg, #003545, #c0765a); }
.sqlsrv-icon  { background: linear-gradient(135deg, #e04e3d, #f47b2b); }
.sqlite-icon  { background: linear-gradient(135deg, #003b57, #44a0d3); }

/* Checkbox */
.checkbox-label {
    display: flex; align-items: center; gap: 0.6rem;
    color: var(--text-main); cursor: pointer; font-size: 0.9rem;
    user-select: none;
}
.checkbox-label input[type=checkbox] { display: none; }
.custom-check {
    width: 20px; height: 20px;
    border: 2px solid var(--glass-border);
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all 0.2s;
    background: var(--input-bg);
}
.checkbox-label input:checked + .custom-check {
    background: var(--primary); border-color: var(--primary);
}
.checkbox-label input:checked + .custom-check::after {
    content: '✓'; color: white; font-size: 0.75rem; font-weight: 700;
}

/* Password toggle */
.input-with-icon { position: relative; }
.input-with-icon input { padding-right: 2.5rem; }
.toggle-pass {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: #64748b; cursor: pointer; font-size: 0.9rem;
    transition: color 0.2s;
}
.toggle-pass:hover { color: #94a3b8; }

/* Schema Row */
.schema-input-row { display: flex; gap: 0.5rem; }
.schema-input-row input { flex: 1; }
.schema-input-row .btn { white-space: nowrap; padding: 0.75rem 1rem; }
#dbSchemaSelect {
    width: 100%;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    padding: 0.75rem 1rem;
    border-radius: 12px;
    color: var(--text-main);
    margin-top: 0.5rem;
    font-family: 'Outfit', sans-serif;
}

/* Test Preview */
.test-preview-box {
    background: var(--bg-main);
    border: 1px dashed var(--primary);
    border-radius: 14px;
    padding: 1.25rem;
    margin-bottom: 1rem;
}
.test-preview-header {
    font-weight: 700; color: #818cf8; margin-bottom: 0.5rem;
    display: flex; align-items: center; gap: 0.5rem;
}
.test-preview-desc { color: #64748b; font-size: 0.85rem; margin-bottom: 1rem; }
.test-preview-result {
    margin-top: 1rem; padding: 0.75rem; border-radius: 10px;
    font-size: 0.85rem;
}
.test-preview-result.success { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
.test-preview-result.error   { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }

/* Wizard Nav */
.wizard-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--glass-border2);
}

/* ── Toast ── */
.toast-container {
    position: fixed; bottom: 1.5rem; right: 1.5rem;
    z-index: 9999;
    display: flex; flex-direction: column; gap: 0.5rem;
    pointer-events: none;
}
.toast {
    background: rgba(30,41,59,0.95);
    border: 1px solid var(--glass-border);
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 250px;
    backdrop-filter: blur(10px);
    pointer-events: all;
    animation: toastIn 0.3s ease;
}
@keyframes toastIn { from { opacity:0; transform:translateX(30px); } to { opacity:1; transform:translateX(0); } }
.toast.toast-success { border-color: rgba(16,185,129,0.3); }
.toast.toast-success i { color: #10b981; }
.toast.toast-error { border-color: rgba(239,68,68,0.3); }
.toast.toast-error i { color: #ef4444; }
.toast.toast-info { border-color: rgba(99,102,241,0.3); }
.toast.toast-info i { color: #818cf8; }

/* Responsive */
@media (max-width: 768px) {
    .database-grid { grid-template-columns: 1fr; }
    .form-row.two-col { grid-template-columns: 1fr; }
    .driver-selector { grid-template-columns: repeat(3, 1fr); }
    .toolbar { flex-direction: column; align-items: stretch; }
    .toolbar-search, .toolbar-filters { width: 100%; }
    .toolbar-view { justify-content: flex-end; }
    .db-page-header { flex-direction: column; }
    .health-bar { flex-wrap: wrap; justify-content: center; }
}
</style>
@endsection


@section('scripts')
<script>
// ═══════════════════════════════════════
// STATE
// ═══════════════════════════════════════
let editingDatabaseId = null;
let currentStep = 1;
let currentView = 'grid';

const driverConfig = {
    pgsql:   { port: 5432,  usesSchema: true,  defaultSchema: 'public', hostLabel: 'Host', dbLabel: 'Nama Database', dbHint: 'Nama database pada server PostgreSQL', schemaHint: 'PostgreSQL: public, sch_nama' },
    mysql:   { port: 3306,  usesSchema: false, defaultSchema: '',       hostLabel: 'Host', dbLabel: 'Nama Database', dbHint: 'Nama database MySQL', schemaHint: 'MySQL: kosongkan untuk otomatis' },
    mariadb: { port: 3306,  usesSchema: false, defaultSchema: '',       hostLabel: 'Host', dbLabel: 'Nama Database', dbHint: 'Nama database MariaDB', schemaHint: 'MariaDB: kosongkan untuk otomatis' },
    sqlsrv:  { port: 1433,  usesSchema: true,  defaultSchema: 'dbo',    hostLabel: 'Host', dbLabel: 'Nama Database', dbHint: 'Nama database SQL Server', schemaHint: 'SQL Server: dbo, sch_nama' },
    sqlite:  { port: 0,     usesSchema: false, defaultSchema: '',       hostLabel: 'Path File', dbLabel: 'Path File SQLite', dbHint: 'Path lengkap ke file .sqlite atau .db', schemaHint: '' },
};

// ═══════════════════════════════════════
// DRIVER SELECTOR
// ═══════════════════════════════════════
window.selectDriver = function(driver) {
    document.querySelectorAll('.driver-option').forEach(el => el.classList.remove('active'));
    document.querySelector(`.driver-option[data-driver="${driver}"]`).classList.add('active');
    document.getElementById('dbDriverSelect').value = driver;
    applyDriverConfig(driver);
};

function applyDriverConfig(driver) {
    const cfg = driverConfig[driver] || driverConfig.pgsql;
    document.getElementById('dbPortInput').value = cfg.port || '';
    document.getElementById('hostLabel').innerHTML = `${cfg.hostLabel} <span class="req">*</span>`;
    document.getElementById('databaseLabel').innerHTML = `${cfg.dbLabel} <span class="req">*</span>`;
    document.getElementById('databaseHint').textContent = cfg.dbHint;
    document.getElementById('schemaHint').textContent = cfg.schemaHint;

    const schemaGroup = document.getElementById('schemaGroup');
    if (driver === 'sqlite') {
        document.getElementById('usernameGroup').style.display = 'none';
        document.getElementById('passwordGroup').style.display = 'none';
        document.getElementById('loadSchemasBtn').style.display = 'none';
        schemaGroup.style.display = 'none';
        document.getElementById('dbHostInput').required = false;
    } else {
        document.getElementById('usernameGroup').style.display = 'block';
        document.getElementById('passwordGroup').style.display = 'block';
        document.getElementById('loadSchemasBtn').style.display = 'inline-flex';
        document.getElementById('dbHostInput').required = true;
        schemaGroup.style.display = (cfg.usesSchema || driver === 'mysql' || driver === 'mariadb') ? 'block' : 'none';
        if (cfg.usesSchema) {
            document.getElementById('dbSchemaInput').value = cfg.defaultSchema;
        }
    }
}

// ═══════════════════════════════════════
// WIZARD
// ═══════════════════════════════════════
window.goStep = function(step) {
    // Hide all panels
    [1,2,3].forEach(i => {
        document.getElementById(`panel${i}`).style.display = 'none';
        const wzEl = document.getElementById(`wz${i}`);
        wzEl.classList.remove('active', 'done');
        if (i < step) wzEl.classList.add('done');
    });
    document.querySelectorAll('.wz-line').forEach((el, i) => {
        el.classList.toggle('done', i < step - 1);
    });
    document.getElementById(`panel${step}`).style.display = 'block';
    document.getElementById(`wz${step}`).classList.add('active');
    currentStep = step;
    // Reset test preview on step 3 entry
    if (step === 3) {
        document.getElementById('testPreviewResult').style.display = 'none';
    }
};

// ═══════════════════════════════════════
// MODAL
// ═══════════════════════════════════════
window.showDatabaseModal = function(type, db = null) {
    const modal = document.getElementById('databaseModal');
    const form  = document.getElementById('databaseForm');

    // Reset
    form.reset();
    document.getElementById('dbSchemaInput').style.display = 'block';
    document.getElementById('dbSchemaSelect').style.display = 'none';
    document.getElementById('testPreviewResult').style.display = 'none';
    goStep(1);

    if (type === 'create') {
        document.getElementById('databaseModalTitle').textContent = 'Tambah Database';
        form.action = "{{ route('admin.databases.store') }}";
        document.getElementById('databaseFormMethod').value = 'POST';
        editingDatabaseId = null;

        selectDriver('pgsql');
        document.getElementById('dbPasswordInput').required = true;
        document.getElementById('passwordHint').style.display = 'none';
        document.getElementById('passwordRequiredMark').style.display = 'inline';
        document.getElementById('dbIsActiveInput').checked = true;
        document.getElementById('dbIsDefaultInput').checked = false;
    } else {
        document.getElementById('databaseModalTitle').textContent = 'Edit Database';
        form.action = `/admin/databases/${db.id}`;
        document.getElementById('databaseFormMethod').value = 'PUT';
        editingDatabaseId = db.id;

        // Set driver visual selector dulu (tanpa override port/schema)
        const driver = db.driver || 'pgsql';
        document.querySelectorAll('.driver-option').forEach(el => el.classList.remove('active'));
        document.querySelector(`.driver-option[data-driver="${driver}"]`).classList.add('active');
        document.getElementById('dbDriverSelect').value = driver;
        // Apply config untuk visibility field (sqlite hide username dll), tapi JANGAN timpa nilai
        applyDriverConfig(driver);

        // Isi semua field SETELAH applyDriverConfig, agar nilai dari DB menang
        document.getElementById('dbNameInput').value = db.name || '';
        document.getElementById('dbCodeInput').value = db.code || '';
        document.getElementById('dbHostInput').value = db.host || '';
        document.getElementById('dbPortInput').value = db.port || '';          // override port dari applyDriverConfig
        document.getElementById('dbDatabaseInput').value = db.database || '';
        document.getElementById('dbUsernameInput').value = db.username || '';
        document.getElementById('dbSchemaInput').value = db.schema || '';      // override schema dari applyDriverConfig
        document.getElementById('dbDescriptionInput').value = db.description || '';
        document.getElementById('dbIsActiveInput').checked = !!db.is_active;
        document.getElementById('dbIsDefaultInput').checked = !!db.is_default;
        document.getElementById('dbSslModeInput').value = db.ssl_mode || '';
        document.getElementById('dbTimeoutInput').value = db.connection_timeout || 30;

        document.getElementById('dbPasswordInput').required = false;
        document.getElementById('passwordHint').style.display = 'block';
        document.getElementById('passwordRequiredMark').style.display = 'none';
    }

    modal.style.display = 'flex';
};

window.backdropClose = function(e) {
    if (e.target === document.getElementById('databaseModal')) {
        document.getElementById('databaseModal').style.display = 'none';
    }
};

// ═══════════════════════════════════════
// PASSWORD TOGGLE
// ═══════════════════════════════════════
window.togglePassword = function() {
    const input = document.getElementById('dbPasswordInput');
    const icon  = document.getElementById('passEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
};

// ═══════════════════════════════════════
// COPY TO CLIPBOARD
// ═══════════════════════════════════════
window.copyToClipboard = function(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Disalin ke clipboard!', 'success');
    }).catch(() => {
        showToast('Gagal menyalin', 'error');
    });
};

// ═══════════════════════════════════════
// LOAD SCHEMAS
// ═══════════════════════════════════════
window.loadSchemas = async function() {
    const driver   = document.getElementById('dbDriverSelect').value;
    const host     = document.getElementById('dbHostInput').value;
    const port     = document.getElementById('dbPortInput').value;
    const dbName   = document.getElementById('dbDatabaseInput').value;
    const username = document.getElementById('dbUsernameInput').value;
    const password = document.getElementById('dbPasswordInput').value;
    const ssl_mode = document.getElementById('dbSslModeInput').value;
    const connection_timeout = document.getElementById('dbTimeoutInput').value;

    if (!host || !dbName || !username || !password) {
        showToast('Isi host, database, username & password dulu', 'error');
        return;
    }

    const btn = document.getElementById('loadSchemasBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const r = await fetch('/admin/databases/load-schemas', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf() 
            },
            body: JSON.stringify({ 
                driver, host, port: parseInt(port), database: dbName, 
                username, password, ssl_mode, connection_timeout: parseInt(connection_timeout) 
            })
        });
        const data = await r.json();

        if (data.schemas && data.schemas.length > 0) {
            showSchemaSelect(data.schemas);
            showToast(`${data.schemas.length} schema ditemukan`, 'success');
        } else {
            showToast('Tidak ada schema ditemukan', 'info');
        }
    } catch (err) {
        showToast('Gagal load schema: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Load';
    }
};

function showSchemaSelect(schemas) {
    const input  = document.getElementById('dbSchemaInput');
    const select = document.getElementById('dbSchemaSelect');
    const cur    = input.value;
    select.innerHTML = '<option value="">-- Pilih Schema --</option>';
    schemas.forEach(s => {
        const o = document.createElement('option');
        o.value = s; o.textContent = s;
        if (s === cur) o.selected = true;
        select.appendChild(o);
    });
    input.style.display  = 'none';
    select.style.display = 'block';
    select.value = cur || '';
    select.onchange = () => { input.value = select.value; };
}

// ═══════════════════════════════════════
// TEST CONNECTION (individual card)
// ═══════════════════════════════════════
window.testConnection = async function(dbId) {
    const card = document.getElementById(`dbCard${dbId}`);
    const dot  = card ? card.querySelector('.status-dot') : null;

    if (dot) { dot.className = 'status-dot dot-pending'; }

    showToast('Menguji koneksi...', 'info');

    try {
        const r = await fetch(`/admin/databases/${dbId}/test`, {
            method: 'POST',
            headers: { 
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf() 
            }
        });
        const data = await r.json();

        if (data.success) {
            if (dot) { dot.className = 'status-dot dot-success'; dot.title = 'Connected'; }
            showToast(`✓ ${data.version || 'Connected'} (${data.response_time_ms || '–'}ms)`, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            if (dot) { dot.className = 'status-dot dot-failed'; dot.title = data.error || 'Failed'; }
            showToast('Koneksi gagal: ' + (data.error || 'Unknown'), 'error');
            setTimeout(() => location.reload(), 1500);
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
};

// ═══════════════════════════════════════
// TEST CONNECTION PREVIEW (modal step 3)
// ═══════════════════════════════════════
window.testConnectionPreview = async function() {
    const btn    = document.getElementById('testPreviewBtn');
    const result = document.getElementById('testPreviewResult');
    const driver = document.getElementById('dbDriverSelect').value;
    const host   = document.getElementById('dbHostInput').value;
    const port   = document.getElementById('dbPortInput').value;
    const dbName = document.getElementById('dbDatabaseInput').value;
    const user   = document.getElementById('dbUsernameInput').value;
    const pass   = document.getElementById('dbPasswordInput').value;
    const ssl    = document.getElementById('dbSslModeInput').value;
    const timeout = document.getElementById('dbTimeoutInput').value;

    if (!host || !dbName || !user) {
        result.className = 'test-preview-result error';
        result.style.display = 'block';
        result.innerHTML = '<i class="fas fa-exclamation-circle"></i> Lengkapi data koneksi di step 2 dulu.';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    result.style.display = 'none';

    try {
        const r = await fetch('/admin/databases/load-schemas', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf() 
            },
            body: JSON.stringify({ 
                driver, host, port: parseInt(port), database: dbName, 
                username: user, password: pass, test_only: true,
                ssl_mode: ssl, connection_timeout: parseInt(timeout)
            })
        });
        const data = await r.json();

        if (data.success) {
            result.className = 'test-preview-result success';
            result.innerHTML = `<i class="fas fa-check-circle"></i> Koneksi berhasil! ${data.version ? '– ' + data.version : ''} ${data.response_time_ms ? '(' + data.response_time_ms + 'ms)' : ''}`;
        } else {
            result.className = 'test-preview-result error';
            result.innerHTML = `<i class="fas fa-times-circle"></i> Gagal: ${data.error || 'Unknown error'}`;
        }
        result.style.display = 'block';
    } catch (err) {
        result.className = 'test-preview-result error';
        result.innerHTML = `<i class="fas fa-times-circle"></i> ${err.message}`;
        result.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play-circle"></i> Test Sekarang';
    }
};

// ═══════════════════════════════════════
// TEST ALL CONNECTIONS
// ═══════════════════════════════════════
window.testAllConnections = async function() {
    const btn = document.getElementById('testAllBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    showToast('Menguji semua koneksi...', 'info');

    try {
        const r = await fetch("{{ route('admin.databases.test-all') }}");
        const data = await r.json();

        // Show health bar
        document.getElementById('hTotal').textContent = data.total;
        document.getElementById('hHealthy').textContent = data.healthy;
        document.getElementById('hUnhealthy').textContent = data.unhealthy;
        document.getElementById('healthBar').style.display = 'flex';

        if (data.unhealthy === 0) {
            showToast(`Semua ${data.total} koneksi aktif ✓`, 'success');
        } else {
            showToast(`${data.healthy} OK, ${data.unhealthy} gagal`, 'error');
        }

        setTimeout(() => location.reload(), 2500);
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-heartbeat"></i> <span>Test All</span>';
    }
};

// ═══════════════════════════════════════
// DELETE
// ═══════════════════════════════════════
window.deleteDatabase = function(dbId, dbName) {
    Swal.fire({
        title: 'Hapus Database?',
        html: `Koneksi <strong>${dbName}</strong> akan dihapus.<br><small style="color:#64748b;">Data pada database tidak akan terpengaruh.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        background: '#1e293b',
        color: '#e2e8f0',
    }).then(result => {
        if (result.isConfirmed) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = `/admin/databases/${dbId}`;
            f.innerHTML = `<input type="hidden" name="_token" value="${csrf()}">
                           <input type="hidden" name="_method" value="DELETE">`;
            document.body.appendChild(f);
            f.submit();
        }
    });
};

// ═══════════════════════════════════════
// FILTER & SEARCH
// ═══════════════════════════════════════
window.filterDatabases = function() {
    const search       = document.getElementById('searchInput').value.toLowerCase().trim();
    const filterDriver = document.getElementById('filterDriver').value;
    const filterStatus = document.getElementById('filterStatus').value;
    const cards        = document.querySelectorAll('.database-card');
    const searchClear  = document.getElementById('searchClear');

    searchClear.style.display = search ? 'block' : 'none';

    let visible = 0;
    cards.forEach(card => {
        const name    = card.dataset.name || '';
        const code    = card.dataset.code || '';
        const host    = card.dataset.host || '';
        const driver  = card.dataset.driver || '';
        const active  = card.dataset.active || '';
        const status  = card.dataset.teststatus || '';

        const matchSearch = !search || name.includes(search) || code.includes(search) || host.includes(search);
        const matchDriver = !filterDriver || driver === filterDriver;

        let matchStatus = true;
        if (filterStatus) {
            if (filterStatus === 'active')    matchStatus = active === 'active';
            if (filterStatus === 'inactive')  matchStatus = active === 'inactive';
            if (filterStatus === 'connected') matchStatus = status === 'success';
            if (filterStatus === 'failed')    matchStatus = status === 'failed';
            if (filterStatus === 'untested')  matchStatus = status === 'untested' || status === '';
        }

        const show = matchSearch && matchDriver && matchStatus;
        card.classList.toggle('card-hidden', !show);
        if (show) visible++;
    });

    const filterInfo = document.getElementById('filterInfo');
    const noResults  = document.getElementById('noResults');
    const hasFilter  = search || filterDriver || filterStatus;

    filterInfo.style.display = hasFilter ? 'flex' : 'none';
    if (hasFilter) {
        document.getElementById('filterCount').textContent = `${visible} dari ${cards.length} database ditampilkan`;
    }
    noResults.style.display = (hasFilter && visible === 0) ? 'block' : 'none';
};

window.clearSearch = function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchClear').style.display = 'none';
    filterDatabases();
};

window.clearAllFilters = function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterDriver').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('searchClear').style.display = 'none';
    filterDatabases();
};

// ═══════════════════════════════════════
// VIEW TOGGLE
// ═══════════════════════════════════════
window.setView = function(view) {
    currentView = view;
    const grid = document.getElementById('databaseGrid');
    document.getElementById('viewGrid').classList.toggle('active', view === 'grid');
    document.getElementById('viewList').classList.toggle('active', view === 'list');
    grid.classList.toggle('list-view', view === 'list');
    localStorage.setItem('dbView', view);
};

// ═══════════════════════════════════════
// TOAST
// ═══════════════════════════════════════
function showToast(msg, type = 'info') {
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> ${msg}`;
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(30px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ═══════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════
function csrf() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

// ═══════════════════════════════════════
// INIT
// ═══════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    // Restore view preference
    const savedView = localStorage.getItem('dbView');
    if (savedView) setView(savedView);

    // Auto-dismiss flash alert
    const flash = document.getElementById('flash-alert');
    if (flash) setTimeout(() => flash.remove(), 5000);
});
</script>
@endsection
