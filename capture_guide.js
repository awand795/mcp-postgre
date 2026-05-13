/**
 * capture_guide.js — Screenshot Panduan DarkoAI Admin Panel
 * Jalankan: node capture_guide.js
 * Server: http://74.48.112.31:5000
 */

import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';

const OUT_DIR  = path.resolve('public', 'admin_guide');
const BASE_URL = 'http://74.48.112.31:5000';
const LOGIN_EMAIL    = 'awanda@darkotech.id';
const LOGIN_PASSWORD = 'awanda21345';

if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

let page, browser;

// ─── HELPERS ──────────────────────────────────────────────────────────────────

async function wait(ms) { await new Promise(r => setTimeout(r, ms)); }

async function nav(url, w = 'networkidle2') {
    if (page.url() !== url) await page.goto(url, { waitUntil: w, timeout: 30000 });
}

/** Highlight satu elemen + scroll ke sana */
async function hl(sel) {
    if (!sel) return;
    await page.evaluate(s => {
        const el = document.querySelector(s);
        if (el) { el.style.outline='5px solid #ef4444'; el.style.outlineOffset='3px'; el.style.borderRadius='6px'; el.scrollIntoView({behavior:'instant',block:'center'}); }
    }, sel);
    await wait(500);
}

/** Highlight banyak elemen sekaligus */
async function hlAll(sels) {
    await page.evaluate(ss => ss.forEach(s => {
        const el = document.querySelector(s);
        if (el) { el.style.outline='5px solid #ef4444'; el.style.outlineOffset='3px'; el.style.borderRadius='6px'; }
    }), sels);
    await wait(500);
}

/** Hapus semua highlight */
async function unhl(sels) {
    const arr = Array.isArray(sels) ? sels : [sels];
    await page.evaluate(ss => ss.forEach(s => {
        const el = document.querySelector(s);
        if (el) { el.style.outline=''; el.style.outlineOffset=''; }
    }), arr);
}

/** Highlight via JS inject (untuk elemen yang tidak bisa di-querySelector biasa) */
async function hlBox(x, y, w, h) {
    await page.evaluate((x,y,w,h) => {
        const div = document.createElement('div');
        div.id = '__hlbox__';
        div.style.cssText = `position:fixed;left:${x}px;top:${y}px;width:${w}px;height:${h}px;border:5px solid #ef4444;border-radius:8px;z-index:99999;pointer-events:none;`;
        document.body.appendChild(div);
    }, x, y, w, h);
    await wait(400);
}

async function removeHlBox() {
    await page.evaluate(() => { const d = document.getElementById('__hlbox__'); if(d) d.remove(); });
}

/** Screenshot */
async function shot(filename) {
    await page.screenshot({ path: path.join(OUT_DIR, filename), type: 'png' });
    console.log(`  ✓ ${filename}`);
}

/** Capture all-in-one */
async function capture(filename, opts = {}) {
    const { url=null, sel=null, sels=null, fn=null, waitMs=1200, navWait='networkidle2' } = opts;
    try {
        if (url) await nav(url, navWait);
        if (fn) { await page.evaluate(fn); await wait(waitMs); }
        if (sels) await hlAll(sels);
        else if (sel) { await page.waitForSelector(sel,{timeout:8000}).catch(()=>{}); await hl(sel); }
        await shot(filename);
        if (sels) await unhl(sels);
        else if (sel) await unhl(sel);
    } catch(e) { console.error(`  ✗ ${filename}: ${e.message}`); }
}

/** Login robust */
async function doLogin() {
    try { const c = await page.cookies(); if (c.length) await page.deleteCookie(...c); } catch(_){}
    await wait(300);
    await page.goto(`${BASE_URL}/login`, { waitUntil:'networkidle2', timeout:30000 });
    await wait(800);
    const url = page.url();
    if (!url.includes('/login')) { console.log(`  ✓ Sudah login: ${url}`); return; }
    await page.waitForSelector('input[name="email"]', { timeout:20000 });
    await page.$eval('input[name="email"]', el => el.value='');
    await page.$eval('input[name="password"]', el => el.value='');
    await page.type('input[name="email"]', LOGIN_EMAIL, {delay:60});
    await page.type('input[name="password"]', LOGIN_PASSWORD, {delay:60});
    await wait(400);
    await Promise.all([
        page.waitForNavigation({waitUntil:'networkidle0', timeout:30000}),
        page.click('button[type="submit"]'),
    ]);
    const after = page.url();
    if (after.includes('/login')) throw new Error(`Login gagal: ${after}`);
    console.log(`  ✓ Login OK: ${after}`);
}

// ─── MAIN ─────────────────────────────────────────────────────────────────────
async function run() {
    browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox','--disable-setuid-sandbox','--window-size=1440,900'],
    });
    page = await browser.newPage();
    await page.setViewport({ width:1440, height:900 });

    // ══════════════════════════════════════════════════════
    //  BAGIAN 1: AUTENTIKASI
    // ══════════════════════════════════════════════════════
    console.log('\n═══ BAGIAN 1: AUTENTIKASI ═══');

    // Screenshot halaman login SEBELUM login — pastikan kita belum login
    try { const c = await page.cookies(); if(c.length) await page.deleteCookie(...c); } catch(_){}
    await page.goto(`${BASE_URL}/login`, {waitUntil:'networkidle2',timeout:30000});
    await wait(1000);

    await capture('real_login_page.png',     { sel: 'form' });
    await capture('real_login_email.png',    { sel: 'input[name="email"]' });
    await capture('real_login_password.png', { sel: 'input[name="password"]' });
    await capture('real_login_button.png',   { sel: 'button[type="submit"]' });

    // Screenshot halaman lupa password — dari halaman login, cari link forgot
    await capture('real_login_forgot_link.png', { sel: 'a[href*="forgot"], a[href*="password"]' });

    // Halaman forgot password
    await capture('real_forgot_email_field.png', {
        url: `${BASE_URL}/forgot-password`,
        sel: 'form',
    });

    // Halaman verifikasi OTP
    await capture('real_verify_otp_page.png', {
        url: `${BASE_URL}/verify-otp?email=admin@darkotech.id`,
        sel: 'form',
    });

    // Halaman reset password
    await capture('real_reset_password_page.png', {
        url: `${BASE_URL}/reset-password?email=admin@darkotech.id&otp=000000`,
        sel: 'form',
    });

    // Login untuk lanjut ke bagian berikutnya
    console.log('\n  Login...');
    await doLogin();
    await capture('real_login_success.png', {});

    // ══════════════════════════════════════════════════════
    //  BAGIAN 2: CHATBOT
    // ══════════════════════════════════════════════════════
    console.log('\n═══ BAGIAN 2: CHATBOT ═══');

    await page.goto(`${BASE_URL}/chatbot`, {waitUntil:'networkidle2'});
    await wait(1500);

    // 2A-1: Halaman utama chatbot — highlight input area
    await capture('real_chatbot_page.png', {
        sel: '#chat-input, textarea[placeholder], .chat-input-area, form.chat-form',
    });

    // 2A-2: Sidebar riwayat — klik tombol hamburger/new chat untuk buka sidebar
    await page.evaluate(() => {
        // Coba klik tombol buka sidebar
        const btns = ['#sidebarToggle','.sidebar-toggle','[data-bs-toggle="offcanvas"]','button.hamburger'];
        for (const s of btns) { const el = document.querySelector(s); if(el){el.click();return;} }
        // Atau paksa tampilkan element sidebar
        const sidebar = document.querySelector('.chat-sidebar,.sidebar-history,#chatSidebar,[class*="history"]');
        if (sidebar) sidebar.style.display = 'block';
    });
    await wait(1500);
    await capture('real_chatbot_sidebar.png', {
        sel: '.chat-sidebar, #chatSidebar, [class*="history"], .offcanvas.show, .sidebar',
    });

    // Tutup sidebar jika terbuka
    await page.evaluate(() => {
        const backdrop = document.querySelector('.offcanvas-backdrop,.modal-backdrop');
        if (backdrop) backdrop.click();
        const sidebar = document.querySelector('.offcanvas.show');
        if (sidebar) sidebar.classList.remove('show');
    });
    await wait(800);

    // 2A-3: Dialog hapus riwayat — klik tombol "Hapus Riwayat" di header
    await capture('real_chatbot_delete_confirm.png', {
        fn: () => {
            // Klik tombol hapus riwayat di topbar
            const hapusBtn = document.querySelector(
                '[onclick*="hapus"],[onclick*="delete"],[onclick*="clear"],.btn-hapus-riwayat,button[title*="Hapus"],.hapus-riwayat'
            );
            if (hapusBtn) hapusBtn.click();
            else {
                // Coba tombol "Hapus Riwayat" di navbar chatbot
                const allBtns = [...document.querySelectorAll('button,a')];
                const found = allBtns.find(b => b.textContent.includes('Hapus') || b.title?.includes('Hapus'));
                if (found) found.click();
            }
        },
        sel: '.swal2-container, .modal.show, [role="dialog"]',
        waitMs: 2000,
    });
    await page.evaluate(() => { try{Swal.close();}catch(e){} document.querySelectorAll('.swal2-container').forEach(el=>el.remove()); });

    // ══════════════════════════════════════════════════════
    //  BAGIAN 3: DASHBOARD
    // ══════════════════════════════════════════════════════
    console.log('\n═══ BAGIAN 3: DASHBOARD ═══');

    await capture('real_dashboard.png', { url:`${BASE_URL}/admin`, sel:'.stats-grid,.stat-card,[class*="stat"]', waitMs:2000 });
    await capture('real_sidebar.png',   { sel:'nav.sidebar, .sidebar, #sidebar, aside' });

    // Dark mode toggle
    await capture('real_dash_darkmode.png', {
        sel: '#themeToggle, .theme-toggle, [onclick*="theme"],[onclick*="Theme"], .theme-switch, label[for*="theme"]',
    });
    // Aktifkan dark mode
    await page.evaluate(() => {
        const toggle = document.querySelector('#themeToggle,.theme-toggle,[onclick*="theme"]');
        if (toggle) toggle.click();
        else document.documentElement.classList.add('dark');
    });
    await wait(1200);
    await capture('real_dashboard_dark.png', { sel: '.stats-grid,.stat-card,body' });
    // Balik ke light
    await page.evaluate(() => {
        const toggle = document.querySelector('#themeToggle,.theme-toggle,[onclick*="theme"]');
        if (toggle) toggle.click();
        else document.documentElement.classList.remove('dark');
    });
    await wait(800);

    // ══════════════════════════════════════════════════════
    //  BAGIAN 4: DATABASE MANAGEMENT
    // ══════════════════════════════════════════════════════
    console.log('\n═══ BAGIAN 4: DATABASE ═══');
    const dbUrl = `${BASE_URL}/admin/databases`;

    await capture('real_db_list.png',     { url:dbUrl, sel:'.database-grid,.db-card-grid,[class*="database"]', waitMs:2000 });
    await capture('real_db_toolbar.png',  { sel:'.toolbar,[class*="toolbar"]' });
    await capture('real_db_test_all.png', { sel:'#testAllBtn,button[onclick*="testAll"],button[onclick*="TestAll"]' });
    await capture('real_db_tambah_btn.png',{ sel:'button[onclick*="showDatabaseModal"],button[onclick*="createDatabase"],button[onclick*="addDatabase"]' });

    // Wizard tambah step 1
    await page.evaluate(()=>{ if(typeof showDatabaseModal==='function') showDatabaseModal('create'); });
    await wait(1500);
    await capture('real_db_modal_step1.png', { sel:'#databaseModal .wizard-step.active, #databaseModal #panel1, #databaseModal' });
    await page.evaluate(()=>{ if(typeof goStep==='function') goStep(2); });
    await wait(800);
    await capture('real_db_modal_step2.png', { sel:'#panel2, #databaseModal #dbHostInput, #databaseModal' });
    await page.evaluate(()=>{ if(typeof goStep==='function') goStep(3); });
    await wait(800);
    await capture('real_db_modal_step3.png', { sel:'#panel3, .test-preview-box, #databaseModal' });
    // Tutup modal
    await page.evaluate(()=>{ const m=document.getElementById('databaseModal'); if(m) m.style.display='none'; });
    await wait(400);

    await capture('real_db_edit_btn.png',    { sel:'.btn-icon-edit, [onclick*="edit"][onclick*="atabase"], button[title*="Edit"]' });
    await capture('real_db_status_badge.png',{ sel:'.status-chip,.chip-success,.chip-failed,.chip-pending,[class*="status"]' });
    await capture('real_db_copy_btn.png',    { sel:'.copy-btn,[onclick*="copy"],[title*="Copy"]' });
    await capture('real_db_delete_confirm.png', {
        fn: () => { if(typeof deleteDatabase==='function') deleteDatabase(9999,'Test DB'); },
        sel: '.swal2-container',
        waitMs: 1800,
    });
    await page.evaluate(()=>{ try{Swal.close();}catch(e){} });
    await wait(400);

    // Modal edit
    await page.evaluate(()=>{
        const btn = document.querySelector('.btn-icon-edit,[onclick*="edit"][onclick*="atabase"]');
        if(btn) btn.click();
    });
    await wait(1500);
    await capture('real_db_edit_modal.png', { sel:'#databaseModal .modal-container, #databaseModal' });
    await page.evaluate(()=>{ const m=document.getElementById('databaseModal'); if(m) m.style.display='none'; });
    await wait(300);

    // ══════════════════════════════════════════════════════
    //  BAGIAN 5: AI MANAGEMENT
    // ══════════════════════════════════════════════════════
    console.log('\n═══ BAGIAN 5: AI MANAGEMENT ═══');
    const aiUrl = `${BASE_URL}/admin/ai-management`;

    await capture('real_ai_management.png', { url:aiUrl, sel:'.aim-stats,[class*="stats"]', waitMs:2000 });
    await capture('real_ai_providers.png',  { sel:'.aim-provider-grid,.pcard,[class*="provider-grid"]' });
    await capture('real_ai_add_provider_btn.png', { sel:'button[onclick*="openAddProvider"],.aim-btn-primary,button[onclick*="addProvider"]' });

    // Modal tambah provider
    await page.evaluate(()=>{ const btn=document.querySelector('[onclick*="openAddProvider"],[onclick*="addProvider"],.aim-btn-primary'); if(btn) btn.click(); else { const m=document.getElementById('providerModal'); if(m) m.style.display='flex'; } });
    await wait(1500);
    await capture('real_ai_provider_modal.png', { sel:'#providerModal .modal-box, #providerModal .glass-card, #providerModal' });
    await page.evaluate(()=>{ const m=document.getElementById('providerModal'); if(m) m.style.display='none'; document.querySelectorAll('.swal2-container').forEach(el=>el.remove()); });
    await wait(400);

    await capture('real_ai_toggle_provider.png', { sel:'.pcard-toggle,[onclick*="toggleProvider"] input[type="checkbox"], .pcard .toggle-switch' });

    // ── Hapus Provider: highlight tombol trash di header kartu ──
    // DeepSeek card punya trash icon di header — highlight icon trash yang ada di header pcard
    await page.evaluate(() => {
        // Cari semua tombol hapus provider di header kartu (bukan di baris key)
        const trashBtns = document.querySelectorAll('.pcard-header .btn-danger, .pcard-header [onclick*="deleteProvider"], .pcard-header .fa-trash');
        trashBtns.forEach(el => {
            el.style.outline = '5px solid #ef4444';
            el.style.outlineOffset = '3px';
            el.style.borderRadius = '6px';
        });
        // Juga cari di luar header
        const trashBtns2 = document.querySelectorAll('[onclick*="deleteProvider"]');
        trashBtns2.forEach(el => {
            el.style.outline = '5px solid #ef4444';
            el.style.outlineOffset = '3px';
            el.style.borderRadius = '6px';
            el.scrollIntoView({behavior:'instant',block:'center'});
        });
    });
    await wait(600);
    await shot('real_ai_delete_provider_btn.png');
    await page.evaluate(() => {
        document.querySelectorAll('[onclick*="deleteProvider"],.pcard-header .btn-danger').forEach(el => {
            el.style.outline=''; el.style.outlineOffset='';
        });
    });

    // Tab Keys
    await page.evaluate(()=>{ const t=document.querySelector('[data-tab="keys"],.tab-keys,[onclick*="keys"]'); if(t) t.click(); });
    await wait(800);
    await capture('real_ai_keys_tab.png', { sel:'.pcard-tabs,.tab-btn,[class*="tabs"]' });
    await capture('real_ai_add_key_btn.png', { sel:'button[onclick*="openAddKey"],.btn-add-key,button[onclick*="addKey"]' });

    // Modal tambah key
    await page.evaluate(()=>{ const btn=document.querySelector('[onclick*="openAddKey"],[onclick*="addKey"],.btn-add-key'); if(btn) btn.click(); else { const m=document.getElementById('keyModal'); if(m) m.style.display='flex'; } });
    await wait(1500);
    await capture('real_ai_key_modal.png', { sel:'#keyModal .modal-box, #keyModal .glass-card, #keyModal' });
    await page.evaluate(()=>{ const m=document.getElementById('keyModal'); if(m) m.style.display='none'; });
    await wait(300);

    // ── Edit Key: highlight tombol edit (ikon pensil) di baris key ──
    await page.evaluate(() => {
        // Cari semua tombol edit key (ikon fa-edit / fa-pencil di dalam baris key)
        const editBtns = document.querySelectorAll(
            '.key-row [onclick*="editKey"], .key-item [onclick*="editKey"], [onclick*="editKey"], .key-actions .btn-edit, button[title*="Edit Key"]'
        );
        editBtns.forEach(el => {
            el.style.outline = '5px solid #ef4444';
            el.style.outlineOffset = '3px';
            el.style.borderRadius = '6px';
        });
        // Fallback: cari ikon edit di dalam key list
        const editIcons = document.querySelectorAll('.key-list .fa-edit, .key-list .fa-pencil, .key-list [class*="edit"]');
        editIcons.forEach(el => {
            el.style.outline = '5px solid #ef4444';
            el.style.outlineOffset = '3px';
        });
        // Scroll ke elemen pertama yang ditemukan
        const first = document.querySelector('[onclick*="editKey"],.key-list .fa-edit');
        if (first) first.scrollIntoView({behavior:'instant',block:'center'});
    });
    await wait(600);
    await shot('real_ai_edit_key_btn.png');
    await page.evaluate(() => {
        document.querySelectorAll('[onclick*="editKey"],.key-list .fa-edit,.key-list .fa-pencil').forEach(el => {
            el.style.outline=''; el.style.outlineOffset='';
        });
    });

    // ── Reset Limit: highlight tombol reset limit di baris key ──
    await page.evaluate(() => {
        const resetBtns = document.querySelectorAll(
            '[onclick*="resetLimit"], [onclick*="resetUsage"], button[title*="Reset"], .btn-reset-limit, .key-actions [class*="reset"]'
        );
        resetBtns.forEach(el => {
            el.style.outline = '5px solid #ef4444';
            el.style.outlineOffset = '3px';
            el.style.borderRadius = '6px';
        });
        const first = document.querySelector('[onclick*="resetLimit"],[onclick*="resetUsage"]');
        if (first) first.scrollIntoView({behavior:'instant',block:'center'});
    });
    await wait(600);
    await shot('real_ai_reset_limit_btn.png');
    await page.evaluate(() => {
        document.querySelectorAll('[onclick*="resetLimit"],[onclick*="resetUsage"]').forEach(el => {
            el.style.outline=''; el.style.outlineOffset='';
        });
    });

    // Tab Models
    await page.evaluate(()=>{ const t=document.querySelector('[data-tab="models"],.tab-models,[onclick*="models"]'); if(t) t.click(); });
    await wait(800);
    await capture('real_ai_models_tab.png', { sel:'.pcard-tabs,[class*="tabs"]' });
    await capture('real_ai_add_model_btn.png', { sel:'button[onclick*="openAddModel"],.btn-add-model,button[onclick*="addModel"]' });

    // Modal tambah model
    await page.evaluate(()=>{ const btn=document.querySelector('[onclick*="openAddModel"],[onclick*="addModel"],.btn-add-model'); if(btn) btn.click(); else { const m=document.getElementById('modelModal'); if(m) m.style.display='flex'; } });
    await wait(1500);
    await capture('real_ai_model_modal.png', { sel:'#modelModal .modal-box, #modelModal .glass-card, #modelModal' });
    await page.evaluate(()=>{ const m=document.getElementById('modelModal'); if(m) m.style.display='none'; });
    await wait(300);

    // Health check
    await capture('real_ai_health_btn.png', { sel:'button[onclick*="runHealthCheck"],button[onclick*="healthCheck"],.btn-health,.mb-hc' });
    await page.evaluate(()=>{ const btn=document.querySelector('[onclick*="runHealthCheck"],[onclick*="healthCheck"],.btn-health'); if(btn) btn.click(); else { const m=document.getElementById('hcModal'); if(m) m.style.display='flex'; } });
    await wait(2500);
    await capture('real_ai_health_modal.png', { sel:'#hcModal, .hc-modal, .health-modal, .swal2-container, [class*="health"]' });
    await page.evaluate(()=>{ try{Swal.close();}catch(e){} ['hcModal','providerModal','keyModal','modelModal'].forEach(id=>{ const m=document.getElementById(id); if(m) m.style.display='none'; }); });
    await wait(400);

    // ══════════════════════════════════════════════════════
    //  BAGIAN 6: ROLE MANAGEMENT
    // ══════════════════════════════════════════════════════
    console.log('\n═══ BAGIAN 6: ROLE ═══');
    const roleUrl = `${BASE_URL}/admin/roles`;

    await capture('real_role_list.png',      { url:roleUrl, sel:'.roles-container,.role-list-card,[class*="roles"]', waitMs:2000 });
    await capture('real_role_tambah_btn.png',{ sel:'button[onclick*="showRoleModal"],button[onclick*="addRole"]' });

    await page.evaluate(()=>{ if(typeof showRoleModal==='function') showRoleModal('create'); });
    await wait(1500);
    await capture('real_role_modal.png', { sel:'#roleModal .glass-card, #roleModal .modal-content, #roleModal' });
    await page.evaluate(()=>{ const m=document.getElementById('roleModal'); if(m) m.style.display='none'; });
    await wait(300);

    await capture('real_role_permissions.png',     { sel:'.permissions-card,#permissions-area,[class*="permission"]' });
    await capture('real_role_filter_bar.png',       { sel:'.filter-bar,[class*="filter-bar"]' });
    await capture('real_role_bulk_select.png',      { sels:['button[onclick*="bulkAction"]','.btn-bulk-select','.btn-bulk-deselect','button[onclick*="selectAll"]','button[onclick*="deselectAll"]'] });
    await capture('real_role_save_permissions.png', { sel:'button[onclick*="savePermissions"],.btn-save-permissions' });
    await capture('real_role_edit_btn.png',         { sel:'.fa-edit[onclick*="showRoleModal"],.role-item .btn-edit,[onclick*="editRole"]' });

    await page.evaluate(()=>{ const btn=document.querySelector('.fa-edit[onclick*="showRoleModal"],[onclick*="editRole"]'); if(btn) btn.click(); else if(typeof showRoleModal==='function') showRoleModal('edit',{id:1,name:'Sample',description:''}); });
    await wait(1500);
    await capture('real_role_edit_modal.png', { sel:'#roleModal' });
    await page.evaluate(()=>{ const m=document.getElementById('roleModal'); if(m) m.style.display='none'; });
    await wait(300);

    await capture('real_role_hapus_btn.png', { sel:'.fa-trash[onclick*="deleteRole"],.role-item .btn-delete,[onclick*="deleteRole"]' });
    await page.evaluate(()=>{ if(typeof deleteRole==='function') deleteRole(9999,'Role Test'); });
    await wait(1800);
    await capture('real_role_delete_confirm.png', { sel:'.swal2-container' });
    await page.evaluate(()=>{ try{Swal.close();}catch(e){} });
    await wait(400);

    // ══════════════════════════════════════════════════════
    //  BAGIAN 7: USER MANAGEMENT
    // ══════════════════════════════════════════════════════
    console.log('\n═══ BAGIAN 7: USER MANAGEMENT ═══');
    const userUrl = `${BASE_URL}/admin/users`;

    await capture('real_user_list.png',        { url:userUrl, sel:'table,.table-responsive', waitMs:2000 });
    await capture('real_user_header_btns.png', { sels:['button[onclick*="downloadTemplate"]','button[onclick*="import"]','button[onclick*="exportUsers"]','button[onclick*="showModal"][onclick*="create"]'] });
    await capture('real_user_filter_form.png', { sel:'.filter-card,.filter-form,[class*="filter"]' });

    // ── Tambah User: tombol di header ──
    await capture('real_user_tambah_btn.png', { sel:'button[onclick*="showModal"][onclick*="create"],button[onclick*="addUser"],#tambahUserBtn' });

    // Modal tambah user — gambar real_user_tambah_modal2.png sudah benar, pakai itu
    await page.evaluate(()=>{ if(typeof showModal==='function') showModal('create'); });
    await wait(1500);
    // Highlight semua field wajib di modal
    await page.evaluate(()=>{
        ['input[name="name"]','input[name="email"]','input[name="password"]','select[name="role_id"]','input[name="max_tokens"]','.form-check-input[name="is_admin"]'].forEach(s=>{
            const el = document.querySelector(s);
            if(el){ el.style.outline='5px solid #ef4444'; el.style.outlineOffset='3px'; el.style.borderRadius='6px'; }
        });
    });
    await wait(500);
    await shot('real_user_modal.png');
    await page.evaluate(()=>{
        ['input[name="name"]','input[name="email"]','input[name="password"]','select[name="role_id"]','input[name="max_tokens"]','.form-check-input[name="is_admin"]'].forEach(s=>{
            const el=document.querySelector(s); if(el){el.style.outline='';el.style.outlineOffset='';}
        });
    });

    await capture('real_user_field_name.png', { sels:['input[name="name"]','input[name="email"]'] });
    await capture('real_user_field_role.png', { sel:'select[name="role_id"], #userModal select[name*="role"]' });
    await page.evaluate(()=>{ const m=document.getElementById('userModal'); if(m) m.style.display='none'; if(typeof hideModal==='function') hideModal(); });
    await wait(300);

    // ── Tombol Edit User — highlight tombol edit (kuning) di baris pertama ──
    await page.evaluate(()=>{
        const editBtns = document.querySelectorAll(
            'button[onclick*="showModal"][onclick*="edit"], button[onclick*="editUser"], .action-buttons .btn-warning, [title*="Edit User"]'
        );
        editBtns.forEach(el=>{
            el.style.outline='5px solid #ef4444';
            el.style.outlineOffset='3px';
            el.style.borderRadius='6px';
        });
        const first = editBtns[0];
        if(first) first.scrollIntoView({behavior:'instant',block:'center'});
    });
    await wait(500);
    await shot('real_user_edit_btn.png');
    await page.evaluate(()=>{
        document.querySelectorAll('button[onclick*="editUser"],.action-buttons .btn-warning').forEach(el=>{
            el.style.outline=''; el.style.outlineOffset='';
        });
    });

    // Modal edit user — klik tombol edit baris pertama
    await page.evaluate(()=>{
        const btn = document.querySelector('button[onclick*="showModal"][onclick*="edit"],button[onclick*="editUser"],.action-buttons .btn-warning');
        if(btn) btn.click();
    });
    await wait(1500);
    await capture('real_edit_user_modal.png', { sel:'#userModal .modal-content, #userModal' });
    await page.evaluate(()=>{ const m=document.getElementById('userModal'); if(m) m.style.display='none'; if(typeof hideModal==='function') hideModal(); });
    await wait(300);

    // ── Template & Export — highlight tombol di header (bukan modal import) ──
    // Ambil screenshot halaman user list TANPA modal terbuka
    await page.goto(userUrl, {waitUntil:'networkidle2'});
    await wait(1500);

    // Template button
    await page.evaluate(()=>{
        const btn = document.querySelector('button[onclick*="downloadTemplate"]');
        if(btn){ btn.style.outline='5px solid #ef4444'; btn.style.outlineOffset='3px'; btn.style.borderRadius='6px'; btn.scrollIntoView({behavior:'instant',block:'center'}); }
    });
    await wait(500);
    await shot('real_user_template_btn.png');
    await page.evaluate(()=>{ const btn=document.querySelector('button[onclick*="downloadTemplate"]'); if(btn){btn.style.outline='';btn.style.outlineOffset='';} });

    // Export button
    await page.evaluate(()=>{
        const btn = document.querySelector('button[onclick*="exportUsers"],button[onclick*="export"]');
        if(btn){ btn.style.outline='5px solid #ef4444'; btn.style.outlineOffset='3px'; btn.style.borderRadius='6px'; btn.scrollIntoView({behavior:'instant',block:'center'}); }
    });
    await wait(500);
    await shot('real_user_export_btn.png');
    await page.evaluate(()=>{ const btn=document.querySelector('button[onclick*="exportUsers"]'); if(btn){btn.style.outline='';btn.style.outlineOffset='';} });

    // Import modal — ini memang modal import, benar
    await page.evaluate(()=>{ if(typeof showModal==='function') showModal('import'); });
    await wait(1500);
    await capture('real_user_import_modal.png', { sel:'#userModal .modal-content, #userModal, #importModal' });
    await page.evaluate(()=>{ const m=document.getElementById('userModal'); if(m) m.style.display='none'; if(typeof hideModal==='function') hideModal(); });
    await wait(300);

    // ── AI Config per User — highlight tombol robot/chip biru di baris user ──
    await page.evaluate(()=>{
        const aiBtns = document.querySelectorAll(
            'button[onclick*="showAiConfig"],button[onclick*="aiConfig"],[title*="AI Config"],[title*="Konfigurasi AI"],.btn-ai-config'
        );
        aiBtns.forEach(el=>{
            el.style.outline='5px solid #ef4444';
            el.style.outlineOffset='3px';
            el.style.borderRadius='6px';
        });
        const first = aiBtns[0];
        if(first) first.scrollIntoView({behavior:'instant',block:'center'});
    });
    await wait(500);
    await shot('real_user_ai_btn.png');
    await page.evaluate(()=>{
        document.querySelectorAll('button[onclick*="showAiConfig"],button[onclick*="aiConfig"]').forEach(el=>{
            el.style.outline=''; el.style.outlineOffset='';
        });
    });

    // Modal AI Config — buka
    await page.evaluate(()=>{
        const btn = document.querySelector('button[onclick*="showAiConfig"],button[onclick*="aiConfig"]');
        if(btn) btn.click();
    });
    await wait(2000);
    // Screenshot modal AI config — tab AI Models aktif dulu
    await capture('real_user_ai_modal.png', {
        sel: '#aiConfigModal .glass-card, #aiConfigModal, [class*="ai-config"], [class*="aiConfig"]',
    });
    // Screenshot tab API Keys
    await page.evaluate(()=>{
        const apiTab = document.querySelector('[data-tab="api-keys"],[onclick*="apikeys"],button:has(.fa-key)');
        if(apiTab) apiTab.click();
    });
    await wait(800);
    await capture('real_user_ai_apikeys_tab.png', {
        sel: '#aiConfigModal, [class*="ai-config"]',
    });
    await page.evaluate(()=>{ const m=document.getElementById('aiConfigModal'); if(m) m.style.display='none'; });
    await wait(300);

    // ── RLS Filter ──
    await page.evaluate(()=>{
        const rlsBtns = document.querySelectorAll(
            'button[onclick*="showTableFilters"],button[onclick*="tableFilter"],.btn-filter,[title*="Filter Data"],[title*="RLS"]'
        );
        rlsBtns.forEach(el=>{
            el.style.outline='5px solid #ef4444';
            el.style.outlineOffset='3px';
            el.style.borderRadius='6px';
        });
        const first = rlsBtns[0];
        if(first) first.scrollIntoView({behavior:'instant',block:'center'});
    });
    await wait(500);
    await shot('real_user_rls_btn.png');
    await page.evaluate(()=>{
        document.querySelectorAll('button[onclick*="showTableFilters"],button[onclick*="tableFilter"],.btn-filter').forEach(el=>{
            el.style.outline=''; el.style.outlineOffset='';
        });
    });

    await page.evaluate(()=>{ const btn=document.querySelector('button[onclick*="showTableFilters"],button[onclick*="tableFilter"]'); if(btn) btn.click(); });
    await wait(2000);
    await capture('real_user_rls_modal.png',       { sel:'#tableFilterModal .tf-modal, #tableFilterModal, [class*="tableFilter"], [class*="rls"]' });
    await capture('real_rls_table_select.png',     { sel:'.tf-table-list,.table-list,[class*="table-list"]' });
    // Klik tabel pertama di list
    await page.evaluate(()=>{ const tbl=document.querySelector('.tf-table-item,.table-item,.tf-table-list li,[class*="table-item"]'); if(tbl) tbl.click(); });
    await wait(1000);
    await capture('real_rls_add_rule.png',  { sel:'.tf-rule-builder,.rule-builder,[class*="rule"],button[onclick*="addRule"],button[onclick*="tambahKondisi"]' });
    await capture('real_rls_preview.png',   { sel:'button[onclick*="previewData"],.btn-preview,[class*="preview"]' });
    await page.evaluate(()=>{ const m=document.getElementById('tableFilterModal'); if(m) m.style.display='none'; });
    await wait(300);

    // ── Hapus User — highlight tombol trash merah di baris user ──
    await page.evaluate(()=>{
        const delBtns = document.querySelectorAll(
            'button[onclick*="deleteUser"],button[onclick*="hapusUser"],.action-buttons .btn-danger,[title*="Hapus User"]'
        );
        delBtns.forEach(el=>{
            el.style.outline='5px solid #ef4444';
            el.style.outlineOffset='3px';
            el.style.borderRadius='6px';
        });
        const first = delBtns[0];
        if(first) first.scrollIntoView({behavior:'instant',block:'center'});
    });
    await wait(500);
    await shot('real_user_delete_btn.png');
    await page.evaluate(()=>{
        document.querySelectorAll('button[onclick*="deleteUser"],.action-buttons .btn-danger').forEach(el=>{
            el.style.outline=''; el.style.outlineOffset='';
        });
    });

    // Konfirmasi hapus user
    await page.evaluate(()=>{ const btn=document.querySelector('button[onclick*="deleteUser"],.action-buttons .btn-danger'); if(btn) btn.click(); });
    await wait(1800);
    await capture('real_user_delete_confirm.png', { sel:'.swal2-container' });
    await page.evaluate(()=>{ try{Swal.close();}catch(e){} });
    await wait(400);

    // Badges & pills
    await capture('real_user_badges.png',    { sel:'.status-yes,.status-no,.role-badge,[class*="badge"]' });
    await capture('real_user_ai_pills.png',  { sel:'.ai-pill-group,.ai-pill,[class*="pill"]' });
    await capture('real_user_scope_badge.png',{ sel:'.scope-badge,[class*="scope"]' });
    await capture('real_user_row_btns.png',   { sel:'.action-buttons,.td-sticky' });

    // ══════════════════════════════════════════════════════
    //  BAGIAN 8: PANDUAN (halaman guide)
    // ══════════════════════════════════════════════════════
    console.log('\n═══ BAGIAN 8: PANDUAN ═══');
    await capture('real_guide_page.png', { url:`${BASE_URL}/admin/guide`, sel:'.guide-wrap,.guide-content', waitMs:2000 });
    await capture('real_guide_toc.png',  { sel:'.guide-toc' });

    // ══════════════════════════════════════════════════════
    console.log('\n═══════════════════════════════════════════');
    console.log('  ✅ SEMUA SCREENSHOT SELESAI');
    console.log(`  📁 Tersimpan di: ${OUT_DIR}`);
    console.log('═══════════════════════════════════════════');
    await browser.close();
}

run().catch(err => {
    console.error('Fatal Error:', err.message);
    if(browser) browser.close();
    process.exit(1);
});
