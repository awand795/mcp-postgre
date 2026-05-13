/**
 * capture_guide.js — Panduan Screenshot Komprehensif
 * DarkoAI Admin Panel
 * 
 * Jalankan: node capture_guide.js
 * Pastikan server berjalan di http://localhost:5000
 */

import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';

// ─── CONFIG ───────────────────────────────────────────────────────────────────
const OUT_DIR  = path.resolve('public', 'admin_guide');
const BASE_URL = 'http://localhost:5000';

// Kredensial login — sesuaikan dengan akun admin aktif
const LOGIN_EMAIL    = 'awanda@darkotech.id';
const LOGIN_PASSWORD = 'awanda21345';

// Outline merah untuk highlight elemen
const RED_OUTLINE = '5px solid #ef4444';
const RED_OFFSET  = '4px';

// ─── INIT ─────────────────────────────────────────────────────────────────────
if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

let page;
let browser;

// ─── HELPERS ──────────────────────────────────────────────────────────────────

/** Navigasi ke URL jika belum di sana */
async function nav(url, wait = 'networkidle2') {
    if (page.url() !== url) {
        await page.goto(url, { waitUntil: wait, timeout: 30000 });
    }
}

/** Highlight elemen dengan border merah */
async function highlight(selector, offset = RED_OFFSET) {
    if (!selector) return;
    await page.evaluate((sel, ofs) => {
        const el = document.querySelector(sel);
        if (el) {
            el.style.outline       = '5px solid #ef4444';
            el.style.outlineOffset = ofs;
            el.style.borderRadius  = '6px';
            el.scrollIntoView({ behavior: 'instant', block: 'center' });
        }
    }, selector, offset);
    await wait(600);
}

/** Hapus highlight */
async function unhighlight(selector) {
    if (!selector) return;
    await page.evaluate((sel) => {
        const el = document.querySelector(sel);
        if (el) {
            el.style.outline = '';
            el.style.outlineOffset = '';
        }
    }, selector);
}

/** Highlight BANYAK elemen sekaligus */
async function highlightAll(selectors) {
    await page.evaluate((sels) => {
        sels.forEach(sel => {
            const el = document.querySelector(sel);
            if (el) {
                el.style.outline       = '5px solid #ef4444';
                el.style.outlineOffset = '4px';
                el.style.borderRadius  = '6px';
            }
        });
    }, selectors);
    await wait(600);
}

async function unhighlightAll(selectors) {
    await page.evaluate((sels) => {
        sels.forEach(sel => {
            const el = document.querySelector(sel);
            if (el) { el.style.outline = ''; el.style.outlineOffset = ''; }
        });
    }, selectors);
}

/** Wait ms */
async function wait(ms) {
    await new Promise(r => setTimeout(r, ms));
}

/** Screenshot ke file */
async function shot(filename) {
    const savePath = path.join(OUT_DIR, filename);
    await page.screenshot({ path: savePath, type: 'png' });
    console.log(`  ✓ ${filename}`);
}

/**
 * Capture lengkap:
 * 1. Navigasi (jika url diberikan)
 * 2. Jalankan script custom (opsional)
 * 3. Highlight selector (opsional)
 * 4. Screenshot
 * 5. Hapus highlight
 */
async function capture(filename, options = {}) {
    const {
        url         = null,
        selector    = null,
        selectors   = null,   // array selector
        script      = null,
        waitMs      = 1500,
        navWait     = 'networkidle2',
    } = options;

    try {
        if (url) await nav(url, navWait);

        if (script) {
            await page.evaluate(script);
            await wait(waitMs);
        }

        if (selectors) {
            await highlightAll(selectors);
        } else if (selector) {
            await page.waitForSelector(selector, { timeout: 8000 }).catch(() => {});
            await highlight(selector);
        }

        await shot(filename);

        if (selectors) await unhighlightAll(selectors);
        else if (selector) await unhighlight(selector);

    } catch (err) {
        console.error(`  ✗ ${filename}: ${err.message}`);
    }
}

// ─── MAIN ─────────────────────────────────────────────────────────────────────
async function run() {
    browser = await puppeteer.launch({
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--window-size=1440,900',
            '--disable-web-security',
        ],
    });

    page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });

    // Inject CSS global untuk highlight lebih rapi
    await page.evaluateOnNewDocument(() => {
        const style = document.createElement('style');
        style.textContent = '.guide-highlight { outline: 5px solid #ef4444 !important; outline-offset: 4px !important; border-radius: 6px !important; }';
        document.head.appendChild(style);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // BAGIAN 0: AUTENTIKASI
    // ─────────────────────────────────────────────────────────────────────────
    console.log('\n═══════════════════════════════');
    console.log('  BAGIAN 1: AUTENTIKASI');
    console.log('═══════════════════════════════');

    // 1. Halaman login
    await capture('real_login_page.png', {
        url:      `${BASE_URL}/login`,
        selector: 'form',
    });

    // 2. Field email di-highlight
    await capture('real_login_email.png', {
        url:      `${BASE_URL}/login`,
        selector: 'input[name="email"]',
    });

    // 3. Field password
    await capture('real_login_password.png', {
        selector: 'input[name="password"]',
    });

    // 4. Tombol submit login
    await capture('real_login_button.png', {
        selector: 'button[type="submit"]',
    });

    // 5. Login — arahkan ke sistem
    console.log('\n  Melakukan login...');
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
    await page.type('input[name="email"]',    LOGIN_EMAIL,    { delay: 50 });
    await page.type('input[name="password"]', LOGIN_PASSWORD, { delay: 50 });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }),
        page.click('button[type="submit"]'),
    ]);
    console.log(`  Sesudah login, URL: ${page.url()}`);

    // 6. Halaman awal setelah login
    await capture('real_login_success.png', {
        selectors: ['body'],
    });

    // 6b. Lupa password
    await capture('real_login_forgot_link.png', {
        url:      `${BASE_URL}/login`,
        selector: 'a[href*="forgot"]',
    });

    // 7. Halaman forgot password
    await capture('real_forgot_email_field.png', {
        url:      `${BASE_URL}/forgot-password`,
        selector: 'form',
    });

    // 8. Halaman OTP
    await capture('real_verify_otp_page.png', {
        url:      `${BASE_URL}/verify-otp?email=admin@darkotech.id`,
        selector: 'form',
    });

    // 9. Halaman reset password
    await capture('real_reset_password_page.png', {
        url: `${BASE_URL}/reset-password?email=admin@darkotech.id&otp=000000`,
        selector: 'form',
    });

    // Kembali login dulu
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
    await page.type('input[name="email"]',    LOGIN_EMAIL,    { delay: 50 });
    await page.type('input[name="password"]', LOGIN_PASSWORD, { delay: 50 });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }),
        page.click('button[type="submit"]'),
    ]);

    // ─────────────────────────────────────────────────────────────────────────
    // BAGIAN 1: CHATBOT
    // ─────────────────────────────────────────────────────────────────────────
    console.log('\n═══════════════════════════════');
    console.log('  BAGIAN 2: CHATBOT');
    console.log('═══════════════════════════════');

    // 10. Tampilan chatbot utama
    await capture('real_chatbot_page.png', {
        url:      BASE_URL + '/',
        selector: '#chat-input, textarea, input[type="text"]',
        waitMs:   2000,
    });

    // 11. Sidebar riwayat
    await capture('real_chatbot_sidebar.png', {
        script: () => {
            // Coba buka sidebar
            const hamburger = document.querySelector('[onclick*="sidebar"], .hamburger, .sidebar-toggle, [data-target*="sidebar"], #sidebarToggle');
            if (hamburger) hamburger.click();
            else {
                const sidebar = document.getElementById('chat-sidebar') || document.querySelector('.sidebar, .chat-history');
                if (sidebar) sidebar.style.display = 'block';
            }
        },
        selector: '#chat-sidebar, .chat-history, .sidebar',
        waitMs:   1500,
    });

    // 12. Dialog hapus chat
    await capture('real_chatbot_delete_confirm.png', {
        script: () => {
            if (typeof showDeleteModal === 'function') showDeleteModal(1, () => {});
            else {
                // Trigger Swal atau modal konfirmasi
                const trashBtn = document.querySelector('.delete-chat, [onclick*="delete"], .btn-delete');
                if (trashBtn) trashBtn.click();
            }
        },
        selector: '.swal2-container, .modal-backdrop, .delete-modal, [class*="swal"]',
        waitMs:   2000,
    });

    // Tutup modal jika ada
    await page.evaluate(() => {
        if (typeof Swal !== 'undefined') Swal.close();
        document.querySelectorAll('.swal2-container').forEach(el => el.remove());
    });

    // ─────────────────────────────────────────────────────────────────────────
    // BAGIAN 2: DASHBOARD
    // ─────────────────────────────────────────────────────────────────────────
    console.log('\n═══════════════════════════════');
    console.log('  BAGIAN 3: DASHBOARD');
    console.log('═══════════════════════════════');

    const dashUrl = `${BASE_URL}/admin`;

    // 13. Dashboard overview — stats cards
    await capture('real_dashboard.png', {
        url:      dashUrl,
        selector: '.stats-grid',
        waitMs:   2000,
    });

    // 14. Sidebar navigasi
    await capture('real_sidebar.png', {
        selector: '.sidebar, nav.sidebar, #sidebar',
    });

    // 15. Tombol dark mode toggle
    await capture('real_dash_darkmode.png', {
        selector: '.theme-switch-wrap, .theme-toggle, [onclick*="theme"], [onclick*="Theme"], #themeToggle',
    });

    // 16. Dark mode aktif
    await page.evaluate(() => {
        if (typeof toggleTheme === 'function') toggleTheme();
        else {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        }
    });
    await wait(1500);
    await capture('real_dashboard_dark.png', {
        selector: '.stats-grid',
    });

    // Balik ke light
    await page.evaluate(() => {
        if (typeof toggleTheme === 'function') toggleTheme();
        else document.documentElement.classList.remove('dark');
    });
    await wait(1000);

    // ─────────────────────────────────────────────────────────────────────────
    // BAGIAN 3: DATABASE MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────
    console.log('\n═══════════════════════════════');
    console.log('  BAGIAN 4: DATABASE MANAGEMENT');
    console.log('═══════════════════════════════');

    const dbUrl = `${BASE_URL}/admin/databases`;

    // 17. Daftar database
    await capture('real_db_list.png', {
        url:      dbUrl,
        selector: '.database-grid, .db-page-header',
        waitMs:   2000,
    });

    // 18. Tombol Tambah Database
    await capture('real_db_tambah_btn.png', {
        selector: 'button[onclick*="showDatabaseModal"]',
    });

    // 19. Tombol Test All
    await capture('real_db_test_all.png', {
        selector: '#testAllBtn, button[onclick*="testAllConnections"]',
    });

    // 20. Toolbar search + filter
    await capture('real_db_toolbar.png', {
        selector: '.toolbar',
    });

    // 21. Modal Tambah — Step 1
    await page.evaluate(() => {
        if (typeof showDatabaseModal === 'function') showDatabaseModal('create');
    });
    await wait(1500);
    await capture('real_db_modal_step1.png', {
        selector: '.modal-container, #databaseModal .wizard-steps',
    });

    // 22. Step 2 — Koneksi
    await page.evaluate(() => {
        if (typeof goStep === 'function') goStep(2);
    });
    await wait(800);
    await capture('real_db_modal_step2.png', {
        selector: '#panel2, #dbHostInput',
    });

    // 23. Step 3 — Advanced & Test Koneksi
    await page.evaluate(() => {
        if (typeof goStep === 'function') goStep(3);
    });
    await wait(800);
    await capture('real_db_modal_step3.png', {
        selector: '#panel3, .test-preview-box',
    });

    // Tutup modal
    await page.evaluate(() => {
        const modal = document.getElementById('databaseModal');
        if (modal) modal.style.display = 'none';
    });
    await wait(500);

    // 24. Tombol Edit pada card database
    await capture('real_db_edit_btn.png', {
        selector: '.btn-icon.btn-icon-edit, button[onclick*="showDatabaseModal"][onclick*="edit"]',
    });

    // 25. Modal Edit database
    await page.evaluate(() => {
        const editBtn = document.querySelector('.btn-icon.btn-icon-edit, [onclick*="showDatabaseModal"][onclick*="edit"]');
        if (editBtn) editBtn.click();
    });
    await wait(1500);
    await capture('real_db_edit_modal.png', {
        selector: '.modal-container',
    });

    await page.evaluate(() => {
        const modal = document.getElementById('databaseModal');
        if (modal) modal.style.display = 'none';
    });

    // 26. Badge status koneksi
    await capture('real_db_status_badge.png', {
        selector: '.status-chip, .chip-success, .chip-pending, .chip-failed',
    });

    // 27. Tombol delete database
    await capture('real_db_delete_btn.png', {
        selector: '.btn-icon.btn-icon-danger, button[onclick*="deleteDatabase"]',
    });

    // 28. Dialog hapus database (SweetAlert)
    await page.evaluate(() => {
        if (typeof deleteDatabase === 'function') deleteDatabase(9999, 'Contoh Database');
    });
    await wait(2000);
    await capture('real_db_delete_confirm.png', {
        selector: '.swal2-container',
    });
    await page.evaluate(() => { if (typeof Swal !== 'undefined') Swal.close(); });
    await wait(500);

    // 29. Copy button pada detail card
    await capture('real_db_copy_btn.png', {
        selector: '.copy-btn',
    });

    // 30. View toggle (grid/list)
    await capture('real_db_view_toggle.png', {
        selector: '.toolbar-view',
    });

    // 31. Filter dropdown
    await capture('real_db_filter.png', {
        selector: '.toolbar-filters',
    });

    // ─────────────────────────────────────────────────────────────────────────
    // BAGIAN 4: AI MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────
    console.log('\n═══════════════════════════════');
    console.log('  BAGIAN 5: AI MANAGEMENT');
    console.log('═══════════════════════════════');

    const aiUrl = `${BASE_URL}/admin/ai-management`;

    // 32. Halaman AI Management — stats + provider grid
    await capture('real_ai_management.png', {
        url:      aiUrl,
        selector: '.aim-stats',
        waitMs:   2000,
    });

    // 33. Stats row detail
    await capture('real_ai_stats.png', {
        selector: '.aim-stats',
    });

    // 34. Provider grid
    await capture('real_ai_providers.png', {
        selector: '.aim-provider-grid, .pcard',
    });

    // 35. Tombol Tambah Provider
    await capture('real_ai_add_provider_btn.png', {
        selector: '.aim-btn-primary, button[onclick*="openAddProvider"]',
    });

    // 36. Modal Tambah Provider
    await page.evaluate(() => {
        const btn = document.querySelector('[onclick*="openAddProvider"], .aim-btn-primary');
        if (btn) btn.click();
        else {
            const modal = document.getElementById('providerModal');
            if (modal) modal.style.display = 'flex';
        }
    });
    await wait(1500);
    await capture('real_ai_provider_modal.png', {
        selector: '#providerModal .modal-box, #providerModal .glass-card, #providerModal',
    });

    // Tutup modal provider
    await page.evaluate(() => {
        const m = document.getElementById('providerModal');
        if (m) m.style.display = 'none';
        document.querySelectorAll('.swal2-container').forEach(el => el.remove());
    });
    await wait(500);

    // 37. Tab Keys pada provider card
    await capture('real_ai_keys_tab.png', {
        selector: '.pcard-tabs, .tab-btn, [data-tab="keys"]',
        script: () => {
            // Klik tab keys jika ada
            const tabKeys = document.querySelector('[data-tab="keys"], .tab-keys, [onclick*="keys"]');
            if (tabKeys) tabKeys.click();
        },
        waitMs: 1000,
    });

    // 38. Tombol Tambah API Key
    await capture('real_ai_add_key_btn.png', {
        selector: 'button[onclick*="openAddKey"], .btn-add-key',
    });

    // 39. Modal Tambah API Key
    await page.evaluate(() => {
        const btn = document.querySelector('[onclick*="openAddKey"], .btn-add-key');
        if (btn) btn.click();
        else {
            const m = document.getElementById('keyModal');
            if (m) m.style.display = 'flex';
        }
    });
    await wait(1500);
    await capture('real_ai_key_modal.png', {
        selector: '#keyModal .modal-box, #keyModal .glass-card, #keyModal',
    });

    await page.evaluate(() => {
        const m = document.getElementById('keyModal');
        if (m) m.style.display = 'none';
    });
    await wait(300);

    // 40. Tab Models pada provider card
    await capture('real_ai_models_tab.png', {
        script: () => {
            const tabModel = document.querySelector('[data-tab="models"], .tab-models, [onclick*="models"]');
            if (tabModel) tabModel.click();
        },
        selector: '.pcard-tabs, [data-tab="models"]',
        waitMs: 1000,
    });

    // 41. Tombol Tambah Model
    await capture('real_ai_add_model_btn.png', {
        selector: 'button[onclick*="openAddModel"], .btn-add-model',
    });

    // 42. Modal Tambah Model
    await page.evaluate(() => {
        const btn = document.querySelector('[onclick*="openAddModel"], .btn-add-model');
        if (btn) btn.click();
        else {
            const m = document.getElementById('modelModal');
            if (m) m.style.display = 'flex';
        }
    });
    await wait(1500);
    await capture('real_ai_model_modal.png', {
        selector: '#modelModal .modal-box, #modelModal .glass-card, #modelModal',
    });

    await page.evaluate(() => {
        const m = document.getElementById('modelModal');
        if (m) m.style.display = 'none';
    });
    await wait(300);

    // 43. Toggle Aktif/Nonaktif Provider
    await capture('real_ai_toggle_provider.png', {
        selector: '.pcard-toggle, [onclick*="toggleProvider"], input[type="checkbox"]',
    });

    // 44. Tombol Health Check
    await capture('real_ai_health_btn.png', {
        selector: 'button[onclick*="runHealthCheck"], .btn-health, .mb-hc',
    });

    // 45. Modal Health Check
    await page.evaluate(() => {
        const btn = document.querySelector('[onclick*="runHealthCheck"], .btn-health, .mb-hc');
        if (btn) btn.click();
        else {
            const m = document.getElementById('hcModal');
            if (m) m.style.display = 'flex';
        }
    });
    await wait(2500);
    await capture('real_ai_health_modal.png', {
        selector: '#hcModal, .hc-modal, .health-modal',
    });

    await page.evaluate(() => {
        ['hcModal', 'providerModal', 'keyModal', 'modelModal'].forEach(id => {
            const m = document.getElementById(id);
            if (m) m.style.display = 'none';
        });
        if (typeof Swal !== 'undefined') Swal.close();
    });
    await wait(500);

    // 46. Tombol Reset Limit
    await capture('real_ai_reset_limit_btn.png', {
        selector: 'button[onclick*="resetLimit"], .btn-reset-limit',
    });

    // 47. Tombol Edit Key
    await capture('real_ai_edit_key_btn.png', {
        selector: 'button[onclick*="editKey"], .btn-edit-key, [title*="Edit Key"]',
    });

    // 48. Tombol Hapus Provider
    await capture('real_ai_delete_provider_btn.png', {
        selector: 'button[onclick*="deleteProvider"], .btn-delete-provider, [title*="Hapus"]',
    });

    // ─────────────────────────────────────────────────────────────────────────
    // BAGIAN 5: ROLE MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────
    console.log('\n═══════════════════════════════');
    console.log('  BAGIAN 6: ROLE MANAGEMENT');
    console.log('═══════════════════════════════');

    const roleUrl = `${BASE_URL}/admin/roles`;

    // 49. Daftar Role (split layout kiri-kanan)
    await capture('real_role_list.png', {
        url:      roleUrl,
        selector: '.roles-container, .role-list-card',
        waitMs:   2000,
    });

    // 50. Tombol Tambah Role
    await capture('real_role_tambah_btn.png', {
        selector: 'button[onclick*="showRoleModal"]',
    });

    // 51. Modal Tambah Role
    await page.evaluate(() => {
        if (typeof showRoleModal === 'function') showRoleModal('create');
    });
    await wait(1500);
    await capture('real_role_modal.png', {
        selector: '#roleModal .glass-card, #roleModal .modal-content, #roleModal',
    });

    await page.evaluate(() => {
        const m = document.getElementById('roleModal');
        if (m) m.style.display = 'none';
    });
    await wait(300);

    // 52. Area Permissions (tabel checklist)
    await capture('real_role_permissions.png', {
        selector: '.permissions-card, #permissions-area',
    });

    // 53. Tombol Simpan Akses (permissions)
    await capture('real_role_save_permissions.png', {
        selector: 'button[onclick*="savePermissions"]',
    });

    // 54. Filter bar di permissions
    await capture('real_role_filter_bar.png', {
        selector: '.filter-bar',
    });

    // 55. Bulk select/deselect tombol
    await capture('real_role_bulk_select.png', {
        selectors: ['button[onclick*="bulkAction"]', '.btn-bulk-select', '.btn-bulk-deselect'],
    });

    // 56. Tombol Edit Role
    await capture('real_role_edit_btn.png', {
        selector: '.fas.fa-edit[onclick*="showRoleModal"], .role-item .fa-edit',
    });

    // 57. Modal Edit Role
    await page.evaluate(() => {
        const editIcon = document.querySelector('.fa-edit[onclick*="showRoleModal"]');
        if (editIcon) editIcon.click();
        else {
            const role = { id: 1, name: 'Sample Role', description: 'Role contoh' };
            if (typeof showRoleModal === 'function') showRoleModal('edit', role);
        }
    });
    await wait(1500);
    await capture('real_role_edit_modal.png', {
        selector: '#roleModal',
    });

    await page.evaluate(() => {
        const m = document.getElementById('roleModal');
        if (m) m.style.display = 'none';
    });
    await wait(300);

    // 58. Tombol hapus role
    await capture('real_role_hapus_btn.png', {
        selector: '.fa-trash[onclick*="deleteRole"], .role-item .fa-trash',
    });

    // 59. Konfirmasi hapus role (SweetAlert)
    await page.evaluate(() => {
        if (typeof deleteRole === 'function') deleteRole(9999, 'Role Test');
    });
    await wait(2000);
    await capture('real_role_delete_confirm.png', {
        selector: '.swal2-container',
    });
    await page.evaluate(() => { if (typeof Swal !== 'undefined') Swal.close(); });
    await wait(500);

    // ─────────────────────────────────────────────────────────────────────────
    // BAGIAN 6: USER MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────
    console.log('\n═══════════════════════════════');
    console.log('  BAGIAN 7: USER MANAGEMENT');
    console.log('═══════════════════════════════');

    const userUrl = `${BASE_URL}/admin/users`;

    // 60. Daftar users (tabel)
    await capture('real_user_list.png', {
        url:      userUrl,
        selector: '.table-responsive, table',
        waitMs:   2000,
    });

    // 61. Tombol-tombol header (Template, Import, Export, Tambah)
    await capture('real_user_header_btns.png', {
        selectors: [
            'button[onclick*="downloadTemplate"]',
            'button[onclick*="showModal"][onclick*="import"]',
            'button[onclick*="exportUsers"]',
            'button[onclick*="showModal"][onclick*="create"]',
        ],
    });

    // 62. Tombol Tambah User
    await capture('real_user_tambah_btn.png', {
        selector: 'button[onclick*="showModal"][onclick*="create"]',
    });

    // 63. Modal Tambah User
    await page.evaluate(() => {
        if (typeof showModal === 'function') showModal('create');
    });
    await wait(1500);
    await capture('real_user_modal.png', {
        selector: '#userModal .modal-content, #userModal',
    });

    // 64. Field nama & email di modal
    await capture('real_user_field_name.png', {
        selectors: ['input[name="name"]', 'input[name="email"]'],
    });

    // 65. Dropdown role di modal
    await capture('real_user_field_role.png', {
        selector: 'select[name="role_id"], #userModal select[name*="role"]',
    });

    await page.evaluate(() => {
        const m = document.getElementById('userModal');
        if (m) m.style.display = 'none';
        if (typeof hideModal === 'function') hideModal();
    });
    await wait(300);

    // 66. Search & filter form
    await capture('real_user_filter_form.png', {
        selector: '.filter-card, .filter-form',
    });

    // 67. Kolom tabel (header)
    await capture('real_user_table_header.png', {
        selector: 'table thead, table thead tr',
    });

    // 68. Tombol-tombol aksi per baris (RLS, AI, Edit, Delete)
    await capture('real_user_row_btns.png', {
        selector: '.action-buttons, .td-sticky',
    });

    // 69. Tombol Import
    await page.evaluate(() => {
        if (typeof showModal === 'function') showModal('import');
    });
    await wait(1500);
    await capture('real_user_import_modal.png', {
        selector: '#userModal .modal-content, #userModal, #importModal',
    });

    await page.evaluate(() => {
        const m = document.getElementById('userModal');
        if (m) m.style.display = 'none';
        if (typeof hideModal === 'function') hideModal();
    });
    await wait(300);

    // 70. Tombol Export
    await capture('real_user_export_btn.png', {
        selector: 'button[onclick*="exportUsers"]',
    });

    // 71. Tombol Download Template
    await capture('real_user_template_btn.png', {
        selector: 'button[onclick*="downloadTemplate"]',
    });

    // 72. Modal Edit User
    await page.evaluate(() => {
        const editBtn = document.querySelector('button[onclick*="showModal"][onclick*="edit"], .btn-edit-user');
        if (editBtn) editBtn.click();
        else {
            const firstEdit = document.querySelector('.action-buttons button:nth-child(3), button[title*="Edit"]');
            if (firstEdit) firstEdit.click();
        }
    });
    await wait(1500);
    await capture('real_edit_user_modal.png', {
        selector: '#userModal .modal-content, #userModal',
    });

    await page.evaluate(() => {
        const m = document.getElementById('userModal');
        if (m) m.style.display = 'none';
        if (typeof hideModal === 'function') hideModal();
    });
    await wait(300);

    // 73. Tombol AI Config
    await capture('real_user_ai_btn.png', {
        selector: 'button[onclick*="showAiConfig"], [title*="AI Config"]',
    });

    // 74. Modal AI Config
    await page.evaluate(() => {
        const btn = document.querySelector('button[onclick*="showAiConfig"]');
        if (btn) btn.click();
    });
    await wait(2000);
    await capture('real_user_ai_modal.png', {
        selector: '#aiConfigModal .glass-card, #aiConfigModal',
    });

    await page.evaluate(() => {
        const m = document.getElementById('aiConfigModal');
        if (m) m.style.display = 'none';
    });
    await wait(300);

    // 75. Tombol RLS (Data Filter)
    await capture('real_user_rls_btn.png', {
        selector: 'button[onclick*="showTableFilters"], .btn-filter',
    });

    // 76. Modal RLS / Table Filters
    await page.evaluate(() => {
        const btn = document.querySelector('button[onclick*="showTableFilters"]');
        if (btn) btn.click();
    });
    await wait(2000);
    await capture('real_user_rls_modal.png', {
        selector: '#tableFilterModal .tf-modal, #tableFilterModal',
    });

    await page.evaluate(() => {
        const m = document.getElementById('tableFilterModal');
        if (m) m.style.display = 'none';
    });
    await wait(300);

    // 77. Tombol MCP Config (jika ada)
    await capture('real_user_mcp_btn.png', {
        selector: 'button[onclick*="showMcpConfig"], button[title*="MCP"]',
    });

    // 78. Tombol Hapus User
    await capture('real_user_delete_btn.png', {
        selector: 'button[onclick*="deleteUser"], .btn-delete, [title*="Hapus"]',
    });

    // 79. Konfirmasi hapus user
    await page.evaluate(() => {
        const deleteBtn = document.querySelector('button[onclick*="deleteUser"], .btn-delete');
        if (deleteBtn) deleteBtn.click();
    });
    await wait(2000);
    await capture('real_user_delete_confirm.png', {
        selector: '.swal2-container',
    });
    await page.evaluate(() => { if (typeof Swal !== 'undefined') Swal.close(); });
    await wait(500);

    // 80. Badge status admin/super admin/user
    await capture('real_user_badges.png', {
        selector: '.status-yes, .status-no, .role-badge',
    });

    // 81. AI Pills (badge model/key)
    await capture('real_user_ai_pills.png', {
        selector: '.ai-pill-group, .ai-pill',
    });

    // 82. Scope badge (cakupan analisis)
    await capture('real_user_scope_badge.png', {
        selector: '.scope-badge',
    });

    // ─────────────────────────────────────────────────────────────────────────
    // BAGIAN 7: PANDUAN (halaman guide itu sendiri)
    // ─────────────────────────────────────────────────────────────────────────
    console.log('\n═══════════════════════════════');
    console.log('  BAGIAN 8: PANDUAN');
    console.log('═══════════════════════════════');

    await capture('real_guide_page.png', {
        url:      `${BASE_URL}/admin/guide`,
        selector: '.guide-wrap, .guide-content',
        waitMs:   2000,
    });

    await capture('real_guide_toc.png', {
        selector: '.guide-toc',
    });

    // ─────────────────────────────────────────────────────────────────────────
    // DONE
    // ─────────────────────────────────────────────────────────────────────────
    console.log('\n═══════════════════════════════════════════════════════');
    console.log('  ✅ SEMUA SCREENSHOT SELESAI DIAMBIL');
    console.log(`  📁 Tersimpan di: ${OUT_DIR}`);
    console.log('═══════════════════════════════════════════════════════');

    await browser.close();
}

run().catch(err => {
    console.error('Fatal Error:', err);
    if (browser) browser.close();
    process.exit(1);
});
