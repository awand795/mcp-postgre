import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';

const OUT_DIR = path.resolve('public', 'admin_guide');
if (!fs.existsSync(OUT_DIR)) {
    fs.mkdirSync(OUT_DIR, { recursive: true });
}

async function run() {
    console.log('Launching browser...');
    const browser = await puppeteer.launch({ 
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1366,768']
    });
    
    const page = await browser.newPage();
    await page.setViewport({ width: 1366, height: 768 });

    console.log('Logging in...');
    await page.goto('http://74.48.112.31:5000/login', { waitUntil: 'networkidle2' });
    await page.type('input[name="email"]', 'awanda@darkotech.id');
    await page.type('input[name="password"]', 'awanda21345');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 60000 }),
        page.click('button[type="submit"]')
    ]);
    console.log('Logged in successfully!');

    // Helper to highlight an element, take screenshot, and remove highlight
    async function captureHighlight(url, selector, filename, clickSelector = null, waitAfterClick = 1000) {
        console.log(`\nCapturing ${filename} at ${url}...`);
        await page.goto(url, { waitUntil: 'networkidle2' });
        
        if (clickSelector) {
            console.log(`Clicking ${clickSelector}...`);
            await page.click(clickSelector);
            await new Promise(r => setTimeout(r, waitAfterClick));
        }

        try {
            await page.waitForSelector(selector, { timeout: 5000 });
            await page.evaluate((sel) => {
                const el = document.querySelector(sel);
                if (el) {
                    el.style.outline = '4px solid red';
                    el.style.outlineOffset = '4px';
                    // Scroll into view safely
                    el.scrollIntoView({ behavior: 'instant', block: 'center' });
                }
            }, selector);

            await new Promise(r => setTimeout(r, 500)); // Wait for scroll
            const savePath = path.join(OUT_DIR, filename);
            await page.screenshot({ path: savePath, fullPage: true });
            console.log(`Saved ${filename}`);
            
            // Remove highlight
            await page.evaluate((sel) => {
                const el = document.querySelector(sel);
                if (el) {
                    el.style.outline = '';
                    el.style.outlineOffset = '';
                }
            }, selector);
        } catch (e) {
            console.error(`Failed to capture ${filename} using selector ${selector}:`, e.message);
        }
    }

    try {
        // --- MENU 3: USERS ---
        // Step 6: Filter Form
        await captureHighlight('http://74.48.112.31:5000/admin/users', '.filter-form', 'real_user_filter_form.png');
        // Step 19: Edit User
        await captureHighlight('http://74.48.112.31:5000/admin/users', 'tbody tr:first-child .btn-edit', 'real_user_edit_btn.png');
        // Step 24: Hapus User
        await captureHighlight('http://74.48.112.31:5000/admin/users', 'tbody tr:first-child .btn-delete', 'real_user_delete_btn.png');
        // Step 27: Konfigurasi AI
        await captureHighlight('http://74.48.112.31:5000/admin/users', 'tbody tr:first-child button[onclick^="showAiConfig"]', 'real_user_ai_btn.png');
        // Step 32: MCP Token (If it exists, we'll try to find any button with fa-key, else fallback to action-buttons)
        await captureHighlight('http://74.48.112.31:5000/admin/users', 'tbody tr:first-child .action-buttons', 'real_user_mcp_btn.png');
        // Step 37: RLS Button
        await captureHighlight('http://74.48.112.31:5000/admin/users', 'tbody tr:first-child .btn-filter', 'real_user_rls_btn.png');
        // Step 41 & 42: RLS Modal
        await captureHighlight('http://74.48.112.31:5000/admin/users', '.tf-modal', 'real_user_rls_modal.png', 'tbody tr:first-child .btn-filter', 1500);

        // --- MENU 5: DATABASES ---
        // Step 11 to 16: Tab Koneksi
        // Open Tambah DB modal, click Tab Koneksi
        console.log(`\nCapturing real_db_tambah_modal_s2.png...`);
        await page.goto('http://74.48.112.31:5000/admin/databases', { waitUntil: 'networkidle2' });
        await page.evaluate(() => showDatabaseModal('create'));
        await new Promise(r => setTimeout(r, 1000));
        await page.evaluate(() => goStep(2));
        await new Promise(r => setTimeout(r, 500));
        await page.evaluate(() => {
            const el = document.querySelector('#panel2');
            if (el) { el.style.outline = '4px solid red'; el.style.outlineOffset = '4px'; }
        });
        await page.screenshot({ path: path.join(OUT_DIR, 'real_db_tambah_modal_s2.png'), fullPage: true });

        // Step 25: Edit Database
        await captureHighlight('http://74.48.112.31:5000/admin/databases', '.btn-edit, .btn-icon-edit', 'real_db_edit_btn.png');
        // Step 29: Status Terhubung (Connected badge)
        await captureHighlight('http://74.48.112.31:5000/admin/databases', '.badge-success, .badge-connected, .status-connected, .status-yes, .chip-success', 'real_db_status_badge.png');
        // Step 35: Lihat Schema
        await captureHighlight('http://74.48.112.31:5000/admin/databases', '.btn-info, button[title*="Schema"], button[title*="Tabel"]', 'real_db_schema_btn.png');

        // --- MENU 6: AI MANAGEMENT ---
        // Step 5: Add Provider Modal
        console.log(`\nCapturing real_add_provider_modal.png...`);
        await page.goto('http://74.48.112.31:5000/admin/ai-management', { waitUntil: 'networkidle2' });
        await page.evaluate(() => document.getElementById('providerModal').style.display='flex');
        await new Promise(r => setTimeout(r, 1000));
        await page.evaluate(() => {
            const el = document.querySelector('#providerModal .modal-content');
            if (el) { el.style.outline = '4px solid red'; }
        });
        await page.screenshot({ path: path.join(OUT_DIR, 'real_add_provider_modal.png'), fullPage: true });

        // Provider Toggle
        await captureHighlight('http://74.48.112.31:5000/admin/ai-management', '.provider-toggle, .toggle-switch, input[type="checkbox"], .pcard-toggle button', 'real_ai_toggle_on.png');
        
        // Tab Keys
        await captureHighlight('http://74.48.112.31:5000/admin/ai-management', '.tab-btn[data-tab="keys"], .tab-keys, button[onclick*="keys"]', 'real_ai_keys_tab.png');

        // Add Key Button
        await captureHighlight('http://74.48.112.31:5000/admin/ai-management', 'button[onclick*="openAddKey"]', 'real_ai_add_key_btn.png');

        // Edit Key Button
        await captureHighlight('http://74.48.112.31:5000/admin/ai-management', 'button[onclick*="openEditKey"]', 'real_ai_edit_key_btn.png');
        
        // Health Check Button
        await captureHighlight('http://74.48.112.31:5000/admin/ai-management', 'button[onclick*="runHealthCheck"]', 'real_ai_health_btn.png');

        // Tab Models
        // First click Tab Models, then highlight the tab
        console.log(`\nCapturing real_models_tab.png...`);
        await page.goto('http://74.48.112.31:5000/admin/ai-management', { waitUntil: 'networkidle2' });
        await page.evaluate(() => {
            const tabs = document.querySelectorAll('.pcard-tab, button');
            tabs.forEach(t => { if(t.innerText.includes('Models')) t.click(); });
        });
        await new Promise(r => setTimeout(r, 500));
        await page.evaluate(() => {
            const tabs = Array.from(document.querySelectorAll('.pcard-tab, button'));
            const el = tabs.find(t => t.innerText.includes('Models'));
            if (el) { el.style.outline = '4px solid red'; el.style.outlineOffset = '4px'; }
        });
        await page.screenshot({ path: path.join(OUT_DIR, 'real_models_tab.png'), fullPage: true });

        // Add Model Button
        await captureHighlight('http://74.48.112.31:5000/admin/ai-management', 'button[onclick*="openAddModel"]', 'real_ai_add_model_btn.png');

        console.log('\nAll screenshots captured successfully!');
    } catch (err) {
        console.error('Error during execution:', err);
    } finally {
        await browser.close();
    }
}

run();
