import puppeteer from 'puppeteer';
import fs from 'fs';
import path from 'path';

const OUT_DIR = path.resolve('public', 'admin_guide');
const BASE_URL = 'http://74.48.112.31:5000'; 

if (!fs.existsSync(OUT_DIR)) {
    fs.mkdirSync(OUT_DIR, { recursive: true });
}

async function run() {
    console.log('Launching browser...');
    const browser = await puppeteer.launch({ 
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1440,900']
    });
    
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });

    async function capture(url, selector, filename, options = {}) {
        const { 
            clickSelector = null, 
            runScript = null, 
            waitAfter = 2000, 
            outlineOffset = '4px',
            outlineWidth = '4px'
        } = options;

        console.log(`Capturing ${filename}...`);
        
        try {
            if (url && page.url() !== url) {
                await page.goto(url, { waitUntil: 'networkidle2' });
            }
            
            if (runScript) {
                await page.evaluate(runScript);
                await new Promise(r => setTimeout(r, waitAfter));
            }

            if (clickSelector) {
                await page.waitForSelector(clickSelector, { timeout: 5000 });
                await page.click(clickSelector);
                await new Promise(r => setTimeout(r, waitAfter));
            }

            if (selector) {
                await page.waitForSelector(selector, { timeout: 5000 });
                await page.evaluate((sel, offset, width) => {
                    const el = document.querySelector(sel);
                    if (el) {
                        el.style.outline = `${width} solid red`;
                        el.style.outlineOffset = offset;
                        el.scrollIntoView({ behavior: 'instant', block: 'center' });
                    }
                }, selector, outlineOffset, outlineWidth);
                await new Promise(r => setTimeout(r, 500)); 
            }

            const savePath = path.join(OUT_DIR, filename);
            await page.screenshot({ path: savePath });
            
            if (selector) {
                await page.evaluate((sel) => {
                    const el = document.querySelector(sel);
                    if (el) {
                        el.style.outline = '';
                        el.style.outlineOffset = '';
                    }
                }, selector);
            }
        } catch (e) {
            console.error(`  [ERROR] Failed to capture ${filename}: ${e.message}`);
        }
    }

    try {
        // --- 0. LOGIN ---
        console.log('\n--- Capturing Menu 0: Login ---');
        await capture(`${BASE_URL}/login`, '.auth-card', 'real_login_page.png');
        
        console.log('Logging in...');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', 'awanda@darkotech.id');
        await page.type('input[name="password"]', 'awanda21345');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle0' }),
            page.click('button[type="submit"]')
        ]);

        // --- 1. DASHBOARD ---
        console.log('\n--- Capturing Menu 1: Dashboard ---');
        const dashUrl = `${BASE_URL}/admin`;
        await capture(dashUrl, '.stats-grid', 'real_dashboard.png');
        await capture(dashUrl, '.sidebar', 'real_sidebar.png');
        await capture(dashUrl, '.theme-switch-wrap', 'real_dash_darkmode.png');
        
        await page.evaluate(() => typeof toggleTheme === 'function' && toggleTheme());
        await new Promise(r => setTimeout(r, 1000));
        await capture(dashUrl, '.stats-grid', 'real_dashboard_dark.png');
        await capture(dashUrl, '.welcome-card', 'real_welcome_dark.png');
        await page.evaluate(() => typeof toggleTheme === 'function' && toggleTheme());
        await new Promise(r => setTimeout(r, 1000));

        // --- 2. DATABASE ---
        console.log('\n--- Capturing Menu 2: Databases ---');
        const dbUrl = `${BASE_URL}/admin/databases`;
        await capture(dbUrl, '.database-grid', 'real_db_list.png');
        
        // Modal Database Steps
        await capture(dbUrl, '#databaseModal .modal-container', 'real_db_modal_step1.png', { runScript: () => showDatabaseModal('create') });
        await capture(null, '#dbHostInput', 'real_db_modal_step2.png', { runScript: () => goStep(1) });
        await capture(null, '#dbSchemaInput', 'real_db_modal_step3.png', { runScript: () => goStep(2) });
        
        // Delete Confirmation
        await capture(null, '.swal2-confirm', 'real_db_delete_confirm.png', { runScript: () => { document.getElementById('databaseModal').style.display='none'; deleteDatabase(99, 'Contoh DB'); } });
        await page.evaluate(() => Swal.close());

        // --- 3. AI MANAGEMENT ---
        console.log('\n--- Capturing Menu 3: AI ---');
        const aiUrl = `${BASE_URL}/admin/ai-management`;
        await capture(aiUrl, '.aim-stats', 'real_ai_management.png');
        await capture(aiUrl, '#providerModal .glass-card', 'real_ai_provider_modal.png', { runScript: () => document.getElementById('providerModal').style.display='flex' });
        await capture(null, '#keyModal .glass-card', 'real_ai_key_modal.png', { runScript: () => { closeModal('providerModal'); openAddKey(1, 'OpenAI'); } });
        await capture(null, '#modelModal .glass-card', 'real_ai_model_modal.png', { runScript: () => { closeModal('keyModal'); openAddModel(1, 'OpenAI'); } });
        await capture(null, '#hcModal .glass-card', 'real_ai_health_modal.png', { runScript: () => { closeModal('modelModal'); const btn = document.querySelector('.mb-hc'); if(btn) btn.click(); } });

        // --- 4. ROLES ---
        console.log('\n--- Capturing Menu 4: Roles ---');
        const roleUrl = `${BASE_URL}/admin/roles`;
        await capture(roleUrl, '.role-list-card', 'real_role_list.png');
        await capture(null, '#roleModal .glass-card', 'real_role_modal.png', { runScript: () => showRoleModal('create') });
        await capture(null, '.swal2-confirm', 'real_role_delete_confirm.png', { runScript: () => { document.getElementById('roleModal').style.display='none'; deleteRole(99, 'Role Staff'); } });
        await page.evaluate(() => Swal.close());

        // --- 5. USERS ---
        console.log('\n--- Capturing Menu 5: Users ---');
        const userUrl = `${BASE_URL}/admin/users`;
        await capture(userUrl, '.table-responsive', 'real_user_list.png');
        await capture(null, '#userModal .modal-content', 'real_user_modal.png', { runScript: () => showModal('create') });
        await capture(null, '#aiConfigModal .glass-card', 'real_user_ai_modal.png', { runScript: () => { hideModal(); const btn = document.querySelector('button[onclick*="showAiConfig"]'); if(btn) btn.click(); } });
        await capture(null, '#tableFilterModal .modal-content', 'real_user_rls_modal.png', { runScript: () => { document.getElementById('aiConfigModal').style.display='none'; const btn = document.querySelector('button[onclick*="showTableFilters"]'); if(btn) btn.click(); } });
        await capture(null, '.swal2-confirm', 'real_user_delete_confirm.png', { runScript: () => { document.getElementById('tableFilterModal').style.display='none'; const btn = document.querySelector('.btn-delete'); if(btn) btn.click(); } });

        console.log('\nAll screenshots captured successfully!');
    } catch (err) {
        console.error('Error during execution:', err);
    } finally {
        await browser.close();
    }
}

run();
