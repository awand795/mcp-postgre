@extends('layouts.admin')

@section('content')

{{-- ═══════════════════════════════════════════════════════
     HEADER
═══════════════════════════════════════════════════════ --}}
<div class="db-page-header">
    <div class="header-left">
        <h1 class="page-title">
            <span class="title-icon"><i class="fas fa-database"></i></span>
            {{ __('Management Database') }}
        </h1>
        <p class="page-subtitle">{{ __('Kelola koneksi database yang terhubung ke sistem') }}</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-secondary" onclick="testAllConnections()" id="testAllBtn">
            <i class="fas fa-heartbeat"></i>
            <span>{{ __('Test All') }}</span>
        </button>
        <button class="btn btn-primary" onclick="showDatabaseModal('create')">
            <i class="fas fa-plus"></i>
            <span>{{ __('Tambah Database') }}</span>
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     HEALTH SUMMARY BAR (hidden by default, shown after test all)
═══════════════════════════════════════════════════════ --}}
<div class="health-bar glass-card" id="healthBar" style="display:none;">
    <div class="health-stat">
        <span class="health-num" id="hTotal">0</span>
        <span class="health-label">{{ __('Total') }}</span>
    </div>
    <div class="health-divider"></div>
    <div class="health-stat success">
        <span class="health-num" id="hHealthy">0</span>
        <span class="health-label"><i class="fas fa-check-circle"></i> {{ __('Connected') }}</span>
    </div>
    <div class="health-divider"></div>
    <div class="health-stat danger">
        <span class="health-num" id="hUnhealthy">0</span>
        <span class="health-label"><i class="fas fa-times-circle"></i> {{ __('Failed') }}</span>
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
        <input type="text" id="searchInput" placeholder="{{ __('Cari nama, kode, host...') }}" oninput="filterDatabases()" />
        <button class="search-clear" id="searchClear" onclick="clearSearch()" style="display:none;">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="toolbar-filters">
        <select id="filterDriver" onchange="filterDatabases()" class="filter-select">
            <option value="">{{ __('Semua Driver') }}</option>
            <option value="pgsql">PostgreSQL</option>
            <option value="mysql">MySQL</option>
            <option value="mariadb">MariaDB</option>
        </select>
        <select id="filterStatus" onchange="filterDatabases()" class="filter-select">
            <option value="">{{ __('Semua Status') }}</option>
            <option value="active">{{ __('Active') }}</option>
            <option value="inactive">{{ __('Inactive') }}</option>
            <option value="connected">{{ __('Connected') }}</option>
            <option value="failed">{{ __('Failed') }}</option>
            <option value="untested">{{ __('Not Tested') }}</option>
        </select>
    </div>
    <div class="toolbar-view">
        <button class="view-btn active" id="viewGrid" onclick="setView('grid')" title="{{ __('Grid View') }}">
            <i class="fas fa-th-large"></i>
        </button>
        <button class="view-btn" id="viewList" onclick="setView('list')" title="{{ __('List View') }}">
            <i class="fas fa-list"></i>
        </button>
    </div>
</div>

{{-- Filter count --}}
<div class="filter-info" id="filterInfo" style="display:none;">
    <span id="filterCount"></span>
    <button onclick="clearAllFilters()">{{ __('Reset Filter') }} <i class="fas fa-times"></i></button>
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
                title="{{ $db->test_status === 'success' ? __('Connected') : ($db->test_status === 'failed' ? __('Connection Failed') : __('Not Tested')) }}">
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
                            <span class="badge badge-default"><i class="fas fa-star"></i> {{ __('Default') }}</span>
                        @endif
                        @if(!$db->is_active)
                            <span class="badge badge-inactive">{{ __('Inactive') }}</span>
                        @endif
                    </div>
                    <p class="db-code"><i class="fas fa-tag"></i> {{ $db->code }}</p>
                </div>
                <div class="db-card-actions">
                    <button class="btn-icon" onclick="testConnection({{ $db->id }})" title="{{ __('Test Connection') }}">
                        <i class="fas fa-plug"></i>
                    </button>
                    <button class="btn-icon btn-icon-edit" onclick="showDatabaseModal('edit', {{ json_encode($db) }})" title="{{ __('Ubah') }}">
                        <i class="fas fa-edit"></i>
                    </button>
                    @if(!$db->is_default)
                        <button class="btn-icon btn-icon-danger" onclick="deleteDatabase({{ $db->id }}, '{{ $db->name }}')" title="{{ __('Hapus') }}">
                            <i class="fas fa-trash"></i>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Connection Details --}}
            <div class="db-details">
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-server"></i> {{ __('Host') }}</span>
                    <span class="detail-value">
                        {{ $db->host }}:{{ $db->port }}
                        <button class="copy-btn" onclick="copyToClipboard('{{ $db->host }}:{{ $db->port }}')" title="{{ __('Salin') }}">
                            <i class="fas fa-copy"></i>
                        </button>
                    </span>
                </div>
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-database"></i> {{ __('Database') }}</span>
                    <span class="detail-value">
                        {{ $db->database }}
                        <button class="copy-btn" onclick="copyToClipboard('{{ $db->database }}')" title="{{ __('Salin') }}">
                            <i class="fas fa-copy"></i>
                        </button>
                    </span>
                </div>
                @if($db->schema)
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-layer-group"></i> {{ __('Schema') }}</span>
                    <span class="detail-value">{{ $db->schema }}</span>
                </div>
                @endif
                @if($db->description)
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-info-circle"></i> {{ __('Keterangan') }}</span>
                    <span class="detail-value muted">{{ Str::limit($db->description, 60) }}</span>
                </div>
                @endif
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-user-plus"></i> {{ __('Ditambahkan Oleh') }}</span>
                    <span class="detail-value">{{ $db->addedBy->name ?? 'System' }}</span>
                </div>
                <div class="db-detail-row">
                    <span class="detail-label"><i class="fas fa-calendar-alt"></i> {{ __('Tanggal') }}</span>
                    <span class="detail-value">{{ $db->created_at->format('d M Y') }}</span>
                </div>
            </div>

            {{-- Status Footer --}}
            <div class="db-status-footer">
                <div class="status-left">
                    @if($db->test_status === 'success')
                        <span class="status-chip chip-success">
                            <i class="fas fa-check-circle"></i> {{ __('Connected') }}
                        </span>
                    @elseif($db->test_status === 'failed')
                        <span class="status-chip chip-failed">
                            <i class="fas fa-times-circle"></i> {{ __('Failed') }}
                        </span>
                        @if($db->test_error)
                            <span class="error-hint" title="{{ $db->test_error }}">
                                <i class="fas fa-exclamation-circle"></i>
                                {{ Str::limit($db->test_error, 40) }}
                            </span>
                        @endif
                    @else
                        <span class="status-chip chip-pending">
                            <i class="fas fa-question-circle"></i> {{ __('Not Tested') }}
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
            <h3>{{ __('Belum Ada Database') }}</h3>
            <p>{{ __('Tambahkan koneksi database untuk mulai mengelola dan menganalisis data Anda.') }}</p>
            <button class="btn btn-primary" onclick="showDatabaseModal('create')">
                <i class="fas fa-plus"></i> {{ __('Tambah Database Pertama') }}
            </button>
        </div>
    @endforelse
</div>

{{-- No results state (for filter) --}}
<div class="no-results" id="noResults" style="display:none;">
    <i class="fas fa-search"></i>
    <p>{{ __('Tidak ada database yang cocok dengan filter.') }}</p>
    <button onclick="clearAllFilters()">{{ __('Reset Filter') }}</button>
</div>


{{-- ═══════════════════════════════════════════════════════
     MODAL — Add / Edit Database
═══════════════════════════════════════════════════════ --}}
<div id="databaseModal" class="modal-backdrop" style="display:none;" onclick="backdropClose(event)">
    <div class="modal-container glass-card">

        {{-- Modal Header --}}
        <div class="modal-header">
            <h3 id="databaseModalTitle">{{ __('Tambah Database') }}</h3>
            <button class="modal-close" onclick="document.getElementById('databaseModal').style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- Wizard Steps Indicator --}}
        <div class="wizard-steps">
            <div class="wz-step active" id="wz1" onclick="goStep(1)">
                <div class="wz-circle">1</div>
                <span>{{ __('Identitas') }}</span>
            </div>
            <div class="wz-line"></div>
            <div class="wz-step" id="wz2" onclick="goStep(2)">
                <div class="wz-circle">2</div>
                <span>{{ __('Koneksi') }}</span>
            </div>
            <div class="wz-line"></div>
            <div class="wz-step" id="wz3" onclick="goStep(3)">
                <div class="wz-circle">3</div>
                <span>{{ __('Advanced') }}</span>
            </div>
        </div>

        <form id="databaseForm" method="POST">
            @csrf
            <input type="hidden" name="_method" id="databaseFormMethod" value="POST">

            {{-- ── STEP 1: Identitas ── --}}
            <div class="wizard-panel" id="panel1">
                <div class="form-row two-col">
                    <div class="form-group">
                        <label>{{ __('Nama Koneksi / Alias') }} <span class="req">*</span></label>
                        <input type="text" name="name" id="dbNameInput" placeholder="MBI Production" required>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Kode') }} <span class="req">*</span></label>
                        <input type="text" name="code" id="dbCodeInput" placeholder="mbi_prod" required>
                        <small>{{ __('Huruf kecil, angka, underscore saja') }}</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>{{ __('Driver Database') }} <span class="req">*</span></label>
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
                    </div>
                    <input type="hidden" name="driver" id="dbDriverSelect" value="pgsql">
                </div>

                <div class="form-group">
                    <label>{{ __('Deskripsi') }}</label>
                    <textarea name="description" id="dbDescriptionInput" rows="2"
                        placeholder="{{ __('Database production untuk MBI...') }}"></textarea>
                </div>

                <div class="form-row two-col">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" id="dbIsActiveInput" value="1" checked>
                        <span class="custom-check"></span>
                        {{ __('Aktif') }}
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_default" id="dbIsDefaultInput" value="1">
                        <span class="custom-check"></span>
                        {{ __('Jadikan Default') }}
                    </label>
                </div>

                <div class="wizard-nav">
                    <span></span>
                    <button type="button" class="btn btn-primary" onclick="goStep(2)">
                        {{ __('Selanjutnya') }} <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            {{-- ── STEP 2: Koneksi ── --}}
            <div class="wizard-panel" id="panel2" style="display:none;">
                <div class="form-row two-col" id="hostPortRow">
                    <div class="form-group">
                        <label id="hostLabel">{{ __('Host') }} <span class="req">*</span></label>
                        <input type="text" name="host" id="dbHostInput" placeholder="db.example.com">
                    </div>
                    <div class="form-group">
                        <label id="portLabel">{{ __('Port') }} <span class="req">*</span></label>
                        <input type="number" name="port" id="dbPortInput" value="5432" required>
                    </div>
                </div>

                <div class="form-group">
                    <label id="databaseLabel">{{ __('Nama Database') }} <span class="req">*</span></label>
                    <input type="text" name="database" id="dbDatabaseInput" placeholder="my_database" required>
                    <small id="databaseHint">{{ __('Nama database sebenarnya pada server') }}</small>
                </div>

                <div class="form-row two-col" id="credentialsRow">
                    <div class="form-group" id="usernameGroup">
                        <label>{{ __('Username') }} <span class="req" id="usernameRequiredMark">*</span></label>
                        <input type="text" name="username" id="dbUsernameInput" placeholder="postgres">
                    </div>
                    <div class="form-group" id="passwordGroup">
                        <label>{{ __('Password') }} <span class="req" id="passwordRequiredMark">*</span></label>
                        <div class="input-with-icon">
                            <input type="password" name="password" id="dbPasswordInput" placeholder="••••••••">
                            <button type="button" class="toggle-pass" onclick="togglePassword()" title="{{ __('Tampilkan/sembunyikan') }}">
                                <i class="fas fa-eye" id="passEyeIcon"></i>
                            </button>
                        </div>
                        <small id="passwordHint" style="display:none;">{{ __('Kosongkan jika tidak ingin mengubah') }}</small>
                    </div>
                </div>

                <div class="form-row two-col">
                    <div class="form-group">
                        <label>{{ __('SSL Mode') }}</label>
                        <select name="ssl_mode" id="dbSslModeInput">
                            <option value="">None</option>
                            <option value="prefer">Prefer</option>
                            <option value="require">Require</option>
                            <option value="verify-ca">Verify CA</option>
                            <option value="verify-full">Verify Full</option>
                        </select>
                        <small>{{ __('Keamanan koneksi SSL/TLS') }}</small>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Connection Timeout (detik)') }}</label>
                        <input type="number" name="connection_timeout" id="dbTimeoutInput" value="30" min="5" max="300">
                        <small>{{ __('Default: 30 detik') }}</small>
                    </div>
                </div>

                <div class="form-group" id="schemaGroup">
                    <label id="schemaLabel">{{ __('Schema') }} <span class="req">*</span></label>
                    <div class="schema-input-row">
                        <input type="text" name="schema" id="dbSchemaInput" placeholder="public">
                        <button type="button" class="btn btn-secondary" onclick="loadSchemas()" id="loadSchemasBtn">
                            <i class="fas fa-sync-alt"></i> {{ __('Load') }}
                        </button>
                    </div>
                    <select id="dbSchemaSelect" style="display:none;"></select>
                    <small id="schemaHint">{{ __('PostgreSQL: public, sch_nama') }} / {{ __('SQL Server: dbo, sch_nama') }}</small>
                </div>

                {{-- SSH Connection Settings --}}
                <div class="form-group" style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
                    <label class="checkbox-label" style="font-weight: 600;">
                        <input type="checkbox" name="use_ssh" id="dbUseSshInput" value="1" onchange="toggleSshFields()">
                        <span class="custom-check"></span>
                        {{ __('Gunakan Koneksi SSH Tunnel') }}
                    </label>
                    <small style="display: block; margin-left: 28px; color: #64748b;">
                        {{ __('Hubungkan ke database remote melalui server perantara SSH (SSH Tunneling)') }}
                    </small>
                </div>

                <div id="sshFieldsSection" style="display: none; border-top: 1px dashed var(--glass-border); padding-top: 1.5rem; margin-top: 1rem;">
                    <div class="form-row two-col">
                        <div class="form-group">
                            <label>{{ __('SSH Host') }} <span class="req">*</span></label>
                            <input type="text" name="ssh_host" id="dbSshHostInput" placeholder="ssh.example.com">
                        </div>
                        <div class="form-group">
                            <label>{{ __('SSH Port') }} <span class="req">*</span></label>
                            <input type="number" name="ssh_port" id="dbSshPortInput" value="22" placeholder="22">
                        </div>
                    </div>

                    <div class="form-row two-col">
                        <div class="form-group">
                            <label>{{ __('SSH Username') }} <span class="req">*</span></label>
                            <input type="text" name="ssh_username" id="dbSshUsernameInput" placeholder="ubuntu">
                        </div>
                        <div class="form-group">
                            <label>{{ __('Tipe Otentikasi SSH') }} <span class="req">*</span></label>
                            <select name="ssh_auth_type" id="dbSshAuthTypeInput" onchange="toggleSshAuthType()">
                                <option value="password">{{ __('Password') }}</option>
                                <option value="key">{{ __('SSH Key (Private Key)') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="sshPasswordGroup">
                        <label id="sshPasswordLabel">{{ __('SSH Password') }}</label>
                        <div class="input-with-icon">
                            <input type="password" name="ssh_password" id="dbSshPasswordInput" placeholder="••••••••">
                            <button type="button" class="toggle-pass" onclick="toggleSshPassword()" title="{{ __('Tampilkan/sembunyikan') }}">
                                <i class="fas fa-eye" id="sshPassEyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group" id="sshKeyGroup" style="display: none;">
                        <label id="sshKeyLabel">{{ __('SSH Private Key') }}</label>
                        <div class="ssh-key-upload-wrapper" style="margin-bottom: 0.75rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                            <input type="file" id="sshKeyFile" style="display: none;" onchange="handleSshKeyUpload(this)">
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('sshKeyFile').click()" style="padding: 0.5rem 1rem; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-upload"></i> {{ __('Unggah File Private Key') }}
                            </button>
                            <span id="sshKeyFileName" style="font-size: 0.85rem; color: #64748b;">{{ __('Belum ada file dipilih') }}</span>
                        </div>
                        <textarea name="ssh_private_key" id="dbSshPrivateKeyInput" rows="5" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----"></textarea>
                        <small>{{ __('Unggah file private key atau tempelkan isinya di atas. Pastikan private key tidak memiliki passphrase.') }}</small>
                    </div>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="btn btn-cancel" onclick="goStep(1)">
                        <i class="fas fa-arrow-left"></i> {{ __('Kembali') }}
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goStep(3)">
                        {{ __('Selanjutnya') }} <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            {{-- ── STEP 3: Advanced ── --}}
            <div class="wizard-panel" id="panel3" style="display:none;">
                {{-- Test Connection Preview --}}
                <div class="test-preview-box" id="testPreviewBox">
                    <div class="test-preview-header">
                        <i class="fas fa-plug"></i> {{ __('Test Koneksi Sebelum Simpan') }}
                    </div>
                    <p class="test-preview-desc">{{ __('Uji koneksi sebelum menyimpan untuk memastikan parameter sudah benar.') }}</p>
                    <button type="button" class="btn btn-secondary" onclick="testConnectionPreview()" id="testPreviewBtn">
                        <i class="fas fa-play-circle"></i> {{ __('Test Sekarang') }}
                    </button>
                    <div class="test-preview-result" id="testPreviewResult" style="display:none;"></div>
                </div>

                <div class="wizard-nav">
                    <button type="button" class="btn btn-cancel" onclick="goStep(2)">
                        <i class="fas fa-arrow-left"></i> {{ __('Kembali') }}
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> {{ __('Simpan Database') }}
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

/* ── Premium Live Beating Test All Button ── */
#testAllBtn {
    background: linear-gradient(135deg, #4f46e5, #ec4899); /* Deep Indigo to Rose Pink - extremely luxurious */
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white !important;
    padding: 0.7rem 1.6rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
    position: relative;
    overflow: hidden;
}

#testAllBtn::after {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: 0.5s;
}

#testAllBtn:hover::after {
    left: 100%;
    transition: 0.6s ease-in-out;
}

#testAllBtn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(236, 72, 153, 0.45);
    background: linear-gradient(135deg, #4338ca, #d946ef); /* Indigo to Fuchsia */
}

#testAllBtn:active {
    transform: translateY(-1px);
}

/* Dark Mode Overrides for premium cyber glow */
html.dark #testAllBtn {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(236, 72, 153, 0.12));
    border: 1.5px solid rgba(236, 72, 153, 0.45);
    color: #f472b6 !important; /* Soft rose pink */
    box-shadow: 0 4px 15px rgba(236, 72, 153, 0.15), inset 0 1px 1px rgba(255, 255, 255, 0.05);
}

html.dark #testAllBtn:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.22), rgba(236, 72, 153, 0.22));
    border-color: #f472b6;
    color: white !important;
    box-shadow: 0 0 25px rgba(236, 72, 153, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.15);
}

/* Heartbeat Icon Animation - Realistic Double-Beat (Ba-bum... pause) */
@keyframes doubleHeartbeat {
    0% { transform: scale(1); }
    12% { transform: scale(1.35); }
    24% { transform: scale(1.1); }
    36% { transform: scale(1.45); }
    55% { transform: scale(1); }
    100% { transform: scale(1); }
}

#testAllBtn i.fa-heartbeat {
    display: inline-block;
    animation: doubleHeartbeat 1.6s infinite ease-in-out;
    transform-origin: center;
    color: white; /* Clean, matching icon color */
    filter: drop-shadow(0 0 3px rgba(255, 255, 255, 0.4));
}

/* Make it neon glow in dark mode */
html.dark #testAllBtn i.fa-heartbeat {
    color: #f472b6; 
    filter: drop-shadow(0 0 6px rgba(244, 114, 182, 0.8));
}

html.dark #testAllBtn:hover i.fa-heartbeat {
    color: white;
    filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.8));
}

#testAllBtn:hover i.fa-heartbeat {
    animation-duration: 1.0s; /* beats faster on hover! */
}

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
.btn-cancel {
    background: #fff1f2; color: #e11d48; border: 1px solid #fda4af;
    padding: 0.6rem 1.25rem; border-radius: 10px;
    font-weight: 600; cursor: pointer; transition: all 0.2s;
    font-family: inherit; font-size: 0.85rem;
}
.btn-cancel:hover { background: #ffe4e6; color: #be123c; border-color: #f43f5e; transform: translateY(-1px); }
html.dark .btn-cancel {
    background: rgba(225, 29, 72, 0.1); color: #fb7185; border-color: rgba(225, 29, 72, 0.2);
}
html.dark .btn-cancel:hover { background: rgba(225, 29, 72, 0.2); color: #fda4af; border-color: rgba(225, 29, 72, 0.3); }
.modal-close { color: #ef4444 !important; }
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
    border: 1.5px solid var(--input-border);
    padding: 0.6rem 2.5rem 0.6rem 2.2rem;
    border-radius: 10px;
    color: var(--input-text);
    font-size: 0.9rem;
    transition: all 0.2s;
    font-family: 'Outfit', sans-serif;
    box-shadow: inset 0 1px 3px rgba(99,102,241,0.05);
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
    border: 1.5px solid var(--input-border);
    padding: 0.6rem 0.9rem;
    border-radius: 10px;
    color: var(--input-text);
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Outfit', sans-serif;
    box-shadow: inset 0 1px 3px rgba(99,102,241,0.05);
}
.filter-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.filter-select option { background: var(--select-bg); color: var(--input-text); }
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
.badge-driver  { background: rgba(139,92,246,0.15); color: #6d28d9; border: 1.5px solid rgba(139,92,246,0.3); }
.badge-default { background: rgba(59,130,246,0.15); color: #1d4ed8; border: 1.5px solid rgba(59,130,246,0.3); }
.badge-inactive { background: rgba(100,116,139,0.15); color: #475569; border: 1.5px solid rgba(100,116,139,0.25); }
html.dark .badge-driver  { color: #a78bfa; border-color: rgba(139,92,246,0.2); background: rgba(139,92,246,0.1); }
html.dark .badge-default { color: #60a5fa; border-color: rgba(59,130,246,0.2); background: rgba(59,130,246,0.1); }
html.dark .badge-inactive { color: #94a3b8; border-color: rgba(100,116,139,0.2); }

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
.btn-icon.btn-icon-danger { color: #ef4444; }
html.dark .btn-icon.btn-icon-danger { color: #f87171; }
.btn-icon.btn-icon-danger:hover { background: rgba(239,68,68,0.15); color: #dc2626; border-color: rgba(239,68,68,0.3); }

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
.chip-success { background: rgba(16,185,129,0.1); color: #047857; border: 1.5px solid rgba(16,185,129,0.3); }
.chip-failed  { background: rgba(239,68,68,0.1);  color: #b91c1c;  border: 1.5px solid rgba(239,68,68,0.3); }
.chip-pending { background: rgba(245,158,11,0.1); color: #b45309;  border: 1.5px solid rgba(245,158,11,0.3); }
html.dark .chip-success { color: #10b981; border-color: rgba(16,185,129,0.2); }
html.dark .chip-failed  { color: #ef4444; border-color: rgba(239,68,68,0.2); }
html.dark .chip-pending { color: #f59e0b; border-color: rgba(245,158,11,0.2); }
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
    background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2);
    color: #ef4444; width: 34px; height: 34px; border-radius: 10px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.modal-close:hover { background: #ef4444; color: white; border-color: #ef4444; }

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
    border: 1.5px solid var(--input-border);
    padding: 0.75rem 1rem;
    border-radius: 12px;
    color: var(--input-text);
    font-size: 0.9rem;
    transition: all 0.2s;
    font-family: inherit;
    box-shadow: inset 0 1px 3px rgba(99,102,241,0.05);
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
    grid-template-columns: repeat(3, 1fr);
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
    background: var(--card-bg);
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
    color: var(--text-main);
    box-shadow: var(--shadow-lg);
}
html.dark .toast { background: rgba(30,41,59,0.95); }
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
    pgsql:   { port: 5432,  usesSchema: true,  defaultSchema: 'public', hostLabel: "{{ __('Host') }}", dbLabel: "{{ __('Nama Database') }}", dbHint: "{{ __('Nama database pada server PostgreSQL') }}", schemaHint: "{{ __('PostgreSQL: public, sch_nama') }}" },
    mysql:   { port: 3306,  usesSchema: false, defaultSchema: '',       hostLabel: "{{ __('Host') }}", dbLabel: "{{ __('Nama Database') }}", dbHint: "{{ __('Nama database MySQL') }}", schemaHint: "{{ __('MySQL: kosongkan untuk otomatis') }}" },
    mariadb: { port: 3306,  usesSchema: false, defaultSchema: '',       hostLabel: "{{ __('Host') }}", dbLabel: "{{ __('Nama Database') }}", dbHint: "{{ __('Nama database MariaDB') }}", schemaHint: "{{ __('MariaDB: kosongkan untuk otomatis') }}" },
    sqlsrv:  { port: 1433,  usesSchema: true,  defaultSchema: 'dbo',    hostLabel: "{{ __('Host') }}", dbLabel: "{{ __('Nama Database') }}", dbHint: "{{ __('Nama database SQL Server') }}", schemaHint: "{{ __('SQL Server: dbo, sch_nama') }}" },
    sqlite:  { port: 0,     usesSchema: false, defaultSchema: '',       hostLabel: "{{ __('Path File') }}", dbLabel: "{{ __('Path File SQLite') }}", dbHint: "{{ __('Path lengkap ke file .sqlite atau .db') }}", schemaHint: '' },
};

// ═══════════════════════════════════════
// DRIVER SELECTOR
// ═══════════════════════════════════════
window.selectDriver = function(driver) {
    document.querySelectorAll('.driver-option').forEach(el => el.classList.remove('active'));
    document.querySelector(`.driver-option[data-driver="${driver}"]`).classList.add('active');
    document.getElementById('dbDriverSelect').value = driver;
    applyDriverConfig(driver);
    toggleSshFields();
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
window.validateStep = function(step) {
    const errors = [];

    if (step === 1) {
        const name = document.getElementById('dbNameInput').value.trim();
        const code = document.getElementById('dbCodeInput').value.trim();
        const driver = document.getElementById('dbDriverSelect').value;

        if (!name) errors.push("{{ __('Nama Koneksi / Alias harus diisi') }}");
        if (!code) errors.push("{{ __('Kode harus diisi') }}");
        if (!driver) errors.push("{{ __('Driver Database harus dipilih') }}");

        if (code && !/^[a-z0-9_-]+$/i.test(code)) {
            errors.push("{{ __('Kode hanya boleh berisi huruf kecil, angka, underscore, dan dash') }}");
        }
    }

    if (step === 2) {
        const driver = document.getElementById('dbDriverSelect').value;
        const useSsh = document.getElementById('dbUseSshInput').checked;

        if (driver !== 'sqlite') {
            const database = document.getElementById('dbDatabaseInput').value.trim();
            if (!database) errors.push("{{ __('Nama Database harus diisi') }}");

            if (!useSsh) {
                // Normal Connection: validate database host, port, username, password
                const host = document.getElementById('dbHostInput').value.trim();
                const port = document.getElementById('dbPortInput').value.trim();
                const username = document.getElementById('dbUsernameInput').value.trim();
                const password = document.getElementById('dbPasswordInput').value;

                if (!host) errors.push("{{ __('Host Database harus diisi') }}");
                if (!port) errors.push("{{ __('Port Database harus diisi') }}");
                if (!username) errors.push("{{ __('Username Database harus diisi') }}");
                if (editingDatabaseId === null && !password) {
                    errors.push("{{ __('Password Database harus diisi') }}");
                }
            } else {
                // SSH Tunnel Connection: validate SSH host, port, username, credentials
                const sshHost = document.getElementById('dbSshHostInput').value.trim();
                const sshPort = document.getElementById('dbSshPortInput').value.trim();
                const sshUsername = document.getElementById('dbSshUsernameInput').value.trim();
                const sshAuthType = document.getElementById('dbSshAuthTypeInput').value;

                if (!sshHost) errors.push("{{ __('SSH Host harus diisi') }}");
                if (!sshPort) errors.push("{{ __('SSH Port harus diisi') }}");
                if (!sshUsername) errors.push("{{ __('SSH Username harus diisi') }}");

                if (sshAuthType === 'password') {
                    const sshPassword = document.getElementById('dbSshPasswordInput').value;
                    if (editingDatabaseId === null && !sshPassword) {
                        errors.push("{{ __('SSH Password harus diisi') }}");
                    }
                } else if (sshAuthType === 'key') {
                    const sshKey = document.getElementById('dbSshPrivateKeyInput').value.trim();
                    if (editingDatabaseId === null && !sshKey) {
                        errors.push("{{ __('SSH Private Key harus diisi') }}");
                    }
                }
            }
        } else {
            // SQLite connection
            const database = document.getElementById('dbDatabaseInput').value.trim();
            if (!database) errors.push("{{ __('Path File SQLite harus diisi') }}");
        }
    }

    return errors;
};

window.goStep = function(step) {
    // If trying to navigate forward, validate the current/previous steps first
    if (step > currentStep) {
        for (let s = currentStep; s < step; s++) {
            const errors = validateStep(s);
            if (errors.length > 0) {
                errors.forEach(err => showToast(err, 'error'));
                return; // Stop navigation
            }
        }
    }

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
    const sshKeyFileEl = document.getElementById('sshKeyFile');
    if (sshKeyFileEl) sshKeyFileEl.value = '';
    const sshKeyFileNameEl = document.getElementById('sshKeyFileName');
    if (sshKeyFileNameEl) sshKeyFileNameEl.textContent = "{{ __('Belum ada file dipilih') }}";
    document.getElementById('dbSchemaInput').style.display = 'block';
    document.getElementById('dbSchemaSelect').style.display = 'none';
    document.getElementById('testPreviewResult').style.display = 'none';
    goStep(1);

    if (type === 'create') {
        document.getElementById('databaseModalTitle').textContent = "{{ __('Tambah Database') }}";
        form.action = "{{ route('admin.databases.store') }}";
        document.getElementById('databaseFormMethod').value = 'POST';
        editingDatabaseId = null;

        selectDriver('pgsql');
        document.getElementById('dbPasswordInput').required = true;
        document.getElementById('passwordHint').style.display = 'none';
        document.getElementById('passwordRequiredMark').style.display = 'inline';
        document.getElementById('dbIsActiveInput').checked = true;
        document.getElementById('dbIsDefaultInput').checked = false;

        // Reset SSH fields
        document.getElementById('dbUseSshInput').checked = false;
        document.getElementById('dbSshHostInput').value = '';
        document.getElementById('dbSshPortInput').value = 22;
        document.getElementById('dbSshUsernameInput').value = '';
        document.getElementById('dbSshAuthTypeInput').value = 'password';
        document.getElementById('dbSshPasswordInput').value = '';
        document.getElementById('dbSshPrivateKeyInput').value = '';
        toggleSshFields();
        toggleSshAuthType();
    } else {
        document.getElementById('databaseModalTitle').textContent = "{{ __('Edit Database') }}";
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

        // Populate SSH fields
        const useSsh = !!db.use_ssh;
        document.getElementById('dbUseSshInput').checked = useSsh;
        document.getElementById('dbSshHostInput').value = db.ssh_host || '';
        document.getElementById('dbSshPortInput').value = db.ssh_port || 22;
        document.getElementById('dbSshUsernameInput').value = db.ssh_username || '';
        document.getElementById('dbSshAuthTypeInput').value = db.ssh_auth_type || 'password';
        document.getElementById('dbSshPasswordInput').value = '';
        document.getElementById('dbSshPrivateKeyInput').value = db.ssh_private_key || '';
        toggleSshFields();
        toggleSshAuthType();

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

window.toggleSshFields = function() {
    const useSsh = document.getElementById('dbUseSshInput').checked;
    const section = document.getElementById('sshFieldsSection');
    section.style.display = useSsh ? 'block' : 'none';

    const driver = document.getElementById('dbDriverSelect').value;
    const isSqlite = (driver === 'sqlite');

    // Hide hostPortRow when SSH is used or when SQLite is used
    const hostPortRow = document.getElementById('hostPortRow');
    if (hostPortRow) {
        hostPortRow.style.display = (useSsh || isSqlite) ? 'none' : 'flex';
    }

    // Toggle required attributes dynamically for SSH fields
    document.getElementById('dbSshHostInput').required = useSsh;
    document.getElementById('dbSshPortInput').required = useSsh;
    document.getElementById('dbSshUsernameInput').required = useSsh;
    document.getElementById('dbHostInput').required = !useSsh && !isSqlite;
    document.getElementById('dbPortInput').required = !useSsh && !isSqlite;

    // Update visual asterisks for standard fields
    const hostLabel = document.getElementById('hostLabel');
    const portLabel = document.getElementById('portLabel');
    const usernameMark = document.getElementById('usernameRequiredMark');
    const passwordMark = document.getElementById('passwordRequiredMark');
    
    // Host visual required mark
    if (hostLabel) {
        const baseText = driverConfig[driver]?.hostLabel || 'Host';
        hostLabel.innerHTML = (!useSsh && !isSqlite) ? `${baseText} <span class="req">*</span>` : baseText;
    }
    
    // Port visual required mark
    if (portLabel) {
        portLabel.innerHTML = (!useSsh && !isSqlite) ? `Port <span class="req">*</span>` : `Port`;
    }
    
    // Username visual required mark
    if (usernameMark) {
        usernameMark.style.display = (!useSsh && !isSqlite) ? 'inline' : 'none';
    }
    
    // Password visual required mark
    if (passwordMark) {
        const isCreate = (editingDatabaseId === null);
        passwordMark.style.display = (!useSsh && !isSqlite && isCreate) ? 'inline' : 'none';
    }

    // Update SSH Password / Key asterisks
    toggleSshAuthType();
};

window.toggleSshAuthType = function() {
    const useSsh = document.getElementById('dbUseSshInput').checked;
    const authType = document.getElementById('dbSshAuthTypeInput').value;
    const passGroup = document.getElementById('sshPasswordGroup');
    const keyGroup = document.getElementById('sshKeyGroup');
    const isCreate = (editingDatabaseId === null);

    const passLabel = document.getElementById('sshPasswordLabel');
    const keyLabel = document.getElementById('sshKeyLabel');

    if (authType === 'key') {
        passGroup.style.display = 'none';
        keyGroup.style.display = 'block';
        if (keyLabel) {
            keyLabel.innerHTML = (useSsh && isCreate) ? `SSH Private Key <span class="req">*</span>` : `SSH Private Key`;
        }
    } else {
        passGroup.style.display = 'block';
        keyGroup.style.display = 'none';
        if (passLabel) {
            passLabel.innerHTML = (useSsh && isCreate) ? `SSH Password <span class="req">*</span>` : `SSH Password`;
        }
    }
};

window.toggleSshPassword = function() {
    const input = document.getElementById('dbSshPasswordInput');
    const icon  = document.getElementById('sshPassEyeIcon');
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
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast("{{ __('Disalin ke clipboard!') }}", 'success');
        }).catch(() => {
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
};

function fallbackCopyToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.opacity = "0";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast("{{ __('Disalin ke clipboard!') }}", 'success');
        } else {
            showToast("{{ __('Gagal menyalin') }}", 'error');
        }
    } catch (err) {
        showToast("{{ __('Gagal menyalin') }}: " + err.message, 'error');
    }
    document.body.removeChild(textArea);
}

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

    const use_ssh = document.getElementById('dbUseSshInput').checked;
    const ssh_host = document.getElementById('dbSshHostInput').value;
    const ssh_port = document.getElementById('dbSshPortInput').value;
    const ssh_username = document.getElementById('dbSshUsernameInput').value;
    const ssh_auth_type = document.getElementById('dbSshAuthTypeInput').value;
    const ssh_password = document.getElementById('dbSshPasswordInput').value;
    const ssh_private_key = document.getElementById('dbSshPrivateKeyInput').value;

    if (!host || !dbName || !username || !password) {
        showToast("{{ __('Isi host, database, username & password dulu') }}", 'error');
        return;
    }

    if (use_ssh && (!ssh_host || !ssh_username)) {
        showToast("{{ __('Lengkapi data SSH Host dan Username terlebih dahulu') }}", 'error');
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
                username, password, ssl_mode, connection_timeout: parseInt(connection_timeout),
                use_ssh,
                ssh_host,
                ssh_port: ssh_port ? parseInt(ssh_port) : 22,
                ssh_username,
                ssh_auth_type,
                ssh_password,
                ssh_private_key
            })
        });
        const data = await r.json();

        if (data.schemas && data.schemas.length > 0) {
            showSchemaSelect(data.schemas);
            showToast(data.schemas.length + " {{ __('schema ditemukan') }}", 'success');
        } else {
            showToast("{{ __('Tidak ada schema ditemukan') }}", 'info');
        }
    } catch (err) {
        showToast("{{ __('Gagal load schema') }}: " + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> ' + "{{ __('Load') }}";
    }
};

function showSchemaSelect(schemas) {
    const input  = document.getElementById('dbSchemaInput');
    const select = document.getElementById('dbSchemaSelect');
    const cur    = input.value;
    select.innerHTML = '<option value="">-- ' + "{{ __('Pilih Schema') }}" + ' --</option>';
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
// DYNAMIC CARD UPDATE HELPER (NO RELOAD)
// ═══════════════════════════════════════
function updateCardVisuals(dbId, success, responseTime, version, errorMsg, lastTestedAt) {
    const card = document.getElementById(`dbCard${dbId}`);
    if (!card) return;

    // Update dataset status for filtering
    card.dataset.teststatus = success ? 'success' : 'failed';

    // 1. Update Pulsing Status Dot
    const dot = card.querySelector('.status-dot');
    if (dot) {
        dot.className = `status-dot ${success ? 'dot-success' : 'dot-failed'}`;
        dot.title = success ? 'Connected' : 'Connection Failed';
    }

    // 2. Update Status Chip in Footer (Left side)
    const statusLeft = card.querySelector('.status-left');
    if (statusLeft) {
        if (success) {
            statusLeft.innerHTML = `
                <span class="status-chip chip-success">
                    <i class="fas fa-check-circle"></i> Connected
                </span>
            `;
        } else {
            let errorHtml = '';
            if (errorMsg) {
                const truncated = errorMsg.length > 40 ? errorMsg.substring(0, 40) + '...' : errorMsg;
                errorHtml = `
                    <span class="error-hint" title="${errorMsg}">
                        <i class="fas fa-exclamation-circle"></i>
                        ${truncated}
                    </span>
                `;
            }
            statusLeft.innerHTML = `
                <span class="status-chip chip-failed">
                    <i class="fas fa-times-circle"></i> Failed
                </span>
                ${errorHtml}
            `;
        }
    }

    // 3. Update Status Right (Latency and last tested time)
    const statusRight = card.querySelector('.status-right');
    if (statusRight) {
        let lastTestedHtml = '';
        if (lastTestedAt) {
            lastTestedHtml = `
                <span class="last-tested">
                    <i class="fas fa-clock"></i>
                    1 second ago
                </span>
            `;
        }
        let latencyHtml = '';
        if (success && responseTime) {
            latencyHtml = `<span class="latency-badge">${responseTime}ms</span>`;
        }
        statusRight.innerHTML = lastTestedHtml + latencyHtml;
    }
}

// ═══════════════════════════════════════
// TEST CONNECTION (individual card)
// ═══════════════════════════════════════
window.testConnection = async function(dbId) {
    const card = document.getElementById(`dbCard${dbId}`);
    const dot  = card ? card.querySelector('.status-dot') : null;

    if (dot) { dot.className = 'status-dot dot-pending'; }

    showToast("{{ __('Menguji koneksi...') }}", 'info');

    try {
        const r = await fetch(`/admin/databases/${dbId}/test`, {
            method: 'POST',
            headers: { 
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf() 
            }
        });
        const data = await r.json();

        // Update visuals dynamically
        updateCardVisuals(dbId, data.success, data.response_time_ms, data.version, data.error, new Date().toISOString());

        if (data.success) {
            showToast(`✓ ${data.version || 'Connected'} (${data.response_time_ms || '–'}ms)`, 'success');
        } else {
            showToast("{{ __('Koneksi gagal') }}: " + (data.error || 'Unknown'), 'error');
        }
    } catch (err) {
        showToast("{{ __('Error') }}: " + err.message, 'error');
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

    const use_ssh = document.getElementById('dbUseSshInput').checked;
    const ssh_host = document.getElementById('dbSshHostInput').value;
    const ssh_port = document.getElementById('dbSshPortInput').value;
    const ssh_username = document.getElementById('dbSshUsernameInput').value;
    const ssh_auth_type = document.getElementById('dbSshAuthTypeInput').value;
    const ssh_password = document.getElementById('dbSshPasswordInput').value;
    const ssh_private_key = document.getElementById('dbSshPrivateKeyInput').value;

    // Validate step 1 and step 2 first
    const step1Errors = validateStep(1);
    const step2Errors = validateStep(2);
    const allErrors = [...step1Errors, ...step2Errors];
    if (allErrors.length > 0) {
        result.className = 'test-preview-result error';
        result.style.display = 'block';
        result.innerHTML = `<i class="fas fa-exclamation-circle"></i> <strong>{{ __('Harap lengkapi data koneksi terlebih dahulu:') }}</strong><br>` + 
                           allErrors.map(e => `• ${e}`).join('<br>');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + "{{ __('Testing...') }}";
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
                ssl_mode: ssl, connection_timeout: parseInt(timeout),
                use_ssh,
                ssh_host,
                ssh_port: ssh_port ? parseInt(ssh_port) : 22,
                ssh_username,
                ssh_auth_type,
                ssh_password,
                ssh_private_key
            })
        });
        const data = await r.json();

        if (data.success) {
            result.className = 'test-preview-result success';
            result.innerHTML = `<i class="fas fa-check-circle"></i> ` + "{{ __('Koneksi berhasil!') }}" + ` ${data.version ? '– ' + data.version : ''} ${data.response_time_ms ? '(' + data.response_time_ms + 'ms)' : ''}`;
        } else {
            result.className = 'test-preview-result error';
            result.innerHTML = `<i class="fas fa-times-circle"></i> ` + "{{ __('Gagal') }}: " + `${data.error || 'Unknown error'}`;
        }
        result.style.display = 'block';
    } catch (err) {
        result.className = 'test-preview-result error';
        result.innerHTML = `<i class="fas fa-times-circle"></i> ${err.message}`;
        result.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play-circle"></i> ' + "{{ __('Test Sekarang') }}";
    }
};

// ═══════════════════════════════════════
// TEST ALL CONNECTIONS
// ═══════════════════════════════════════
window.testAllConnections = async function() {
    const btn = document.getElementById('testAllBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + "{{ __('Testing...') }}";
    showToast("{{ __('Menguji semua koneksi...') }}", 'info');

    // Turn all dots into pending first
    document.querySelectorAll('.status-dot').forEach(dot => {
        dot.className = 'status-dot dot-pending';
    });

    try {
        const r = await fetch("{{ route('admin.databases.test-all') }}");
        const data = await r.json();

        // Show health bar stats
        const hTotalEl = document.getElementById('hTotal');
        const hHealthyEl = document.getElementById('hHealthy');
        const hUnhealthyEl = document.getElementById('hUnhealthy');
        const healthBarEl = document.getElementById('healthBar');

        if (hTotalEl) hTotalEl.textContent = data.total;
        if (hHealthyEl) hHealthyEl.textContent = data.healthy;
        if (hUnhealthyEl) hUnhealthyEl.textContent = data.unhealthy;
        if (healthBarEl) healthBarEl.style.display = 'flex';

        // Dynamically update each card
        if (data.databases && Array.isArray(data.databases)) {
            data.databases.forEach(db => {
                updateCardVisuals(db.id, db.success, db.response_time_ms, db.version, db.error, db.last_tested_at);
            });
        }

        if (data.unhealthy === 0) {
            showToast("{{ __('Semua') }} " + data.total + " {{ __('koneksi aktif') }} ✓", 'success');
        } else {
            showToast(data.healthy + " OK, " + data.unhealthy + " {{ __('gagal') }}", 'error');
        }
    } catch (err) {
        showToast("{{ __('Error') }}: " + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-heartbeat"></i> <span>' + "{{ __('Test All') }}" + '</span>';
    }
};

// ═══════════════════════════════════════
// DELETE
// ═══════════════════════════════════════
window.deleteDatabase = function(dbId, dbName) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        title: "{{ __('Hapus Database?') }}",
        html: "{{ __('Koneksi') }} <strong>" + dbName + "</strong> {{ __('akan dihapus.<br><small style=\"color:#64748b;\">Data pada database tidak akan terpengaruh.</small>') }}",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#475569',
        confirmButtonText: "{{ __('Ya, Hapus') }}",
        cancelButtonText: "{{ __('Batal') }}",
        background: isDark ? '#1e293b' : '#ffffff',
        color: isDark ? '#e2e8f0' : '#0f172a',
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
        document.getElementById('filterCount').textContent = visible + " " + "{{ __('dari') }}" + " " + cards.length + " " + "{{ __('database ditampilkan') }}";
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
// SSH KEY FILE UPLOAD
// ═══════════════════════════════════════
window.handleSshKeyUpload = function(input) {
    const file = input.files[0];
    if (!file) return;

    document.getElementById('sshKeyFileName').textContent = file.name;

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('dbSshPrivateKeyInput').value = e.target.result;
        showToast("{{ __('File private key berhasil dimuat!') }}", 'success');
    };
    reader.onerror = function() {
        showToast("{{ __('Gagal membaca file private key') }}", 'error');
    };
    reader.readAsText(file);
};

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

    // Form submit validation
    const form = document.getElementById('databaseForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validate step 1 and step 2
            for (let s = 1; s <= 2; s++) {
                const errors = validateStep(s);
                if (errors.length > 0) {
                    e.preventDefault();
                    errors.forEach(err => showToast(err, 'error'));
                    goStep(s); // Focus on the first invalid step panel
                    return;
                }
            }
        });
    }
});
</script>
@endsection
