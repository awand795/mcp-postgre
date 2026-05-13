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
            waitAfter = 2500, 
            outlineOffset = '6px',
            outlineWidth = '6px'
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
                await page.waitForSelector(clickSelector, { timeout: 10000 });
                await page.click(clickSelector);
                await new Promise(r => setTimeout(r, waitAfter));
            }

            if (selector) {
                await page.waitForSelector(selector, { timeout: 10000 });
                await page.evaluate((sel, offset, width) => {
                    const el = document.querySelector(sel);
                    if (el) {
                        el.style.outline = `${width} solid red`;
                        el.style.outlineOffset = offset;
                        el.scrollIntoView({ behavior: 'instant', block: 'center' });
                    }
                }, selector, outlineOffset, outlineWidth);
                await new Promise(r => setTimeout(r, 1000)); 
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
        // --- 0. LOGIN & AUTH ---
        console.log('\n--- Capturing Menu 0: Auth ---');
        await capture(`${BASE_URL}/login`, 'form', 'real_login_page.png');
        await capture(`${BASE_URL}/forgot-password`, 'form', 'real_login_forgot_link.png');
        await capture(`${BASE_URL}/verify-otp?email=admin@darkotech.id`, 'form', 'real_verify_otp_page.png');
        await capture(`${BASE_URL}/reset-password?email=admin@darkotech.id&otp=123456`, 'form', 'real_reset_password_page.png');

        console.log('Logging in...');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', 'awanda@darkotech.id');
        await page.type('input[name="password"]', 'awanda21345');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle0' }),
            page.click('button[type="submit"]')
        ]);

        // --- 1. CHATBOT ---
        console.log('\n--- Capturing Menu 1: Chatbot ---');
        await capture(`${BASE_URL}/`, null, 'real_chatbot_page.png');
        await capture(`${BASE_URL}/`, '#chat-sidebar', 'real_chatbot_sidebar.png', { runScript: () => { if(typeof toggleSidebar === "function") toggleSidebar(true); } });
        await capture(`${BASE_URL}/`, '.delete-modal-content', 'real_chatbot_delete_confirm.png', { runScript: () => { 
            if(typeof showDeleteModal === "function") showDeleteModal(1, ()=>{});
        } });

        // --- 2. DASHBOARD ---
        console.log('\n--- Capturing Menu 2: Dashboard ---');
        const dashUrl = `${BASE_URL}/admin`;
        await capture(dashUrl, '.stats-grid', 'real_dashboard.png');
        await capture(dashUrl, '.sidebar', 'real_sidebar.png');
        await capture(dashUrl, '.theme-switch-wrap', 'real_dash_darkmode.png');
        
        await page.evaluate(() => typeof toggleTheme === "function" && toggleTheme());
        await new Promise(r => setTimeout(r, 1500));
        await capture(dashUrl, '.stats-grid', 'real_dashboard_dark.png');
        await page.evaluate(() => typeof toggleTheme === "function" && toggleTheme());

        // --- 3. DATABASE ---
        console.log('\n--- Capturing Menu 3: Databases ---');
        const dbUrl = `${BASE_URL}/admin/databases`;
        await capture(dbUrl, '.database-grid', 'real_db_list.png');
        await capture(dbUrl, 'button[onclick*="testAllConnections"]', 'real_db_test_all.png');
        
        // Modal Database Steps
        await capture(dbUrl, '#databaseModal .modal-container', 'real_db_modal_step1.png', { runScript: () => showDatabaseModal('create') });
        await capture(null, '#dbHostInput', 'real_db_modal_step2.png', { runScript: () => { if(typeof goStep === "function") goStep(1); } });
        await capture(null, '#dbSchemaInput', 'real_db_modal_step3.png', { runScript: () => { if(typeof goStep === "function") goStep(2); } });
        
        // Delete Confirmation
        await capture(null, '.swal2-confirm', 'real_db_delete_confirm.png', { runScript: () => { 
            document.getElementById('databaseModal').style.display='none';
            if(typeof deleteDatabase === "function") deleteDatabase(99, 'Contoh DB');
        } });
        await page.evaluate(() => typeof Swal !== "undefined" && Swal.close());

        // --- 4. AI INFRASTRUCTURE ---
        console.log('\n--- Capturing Menu 4: AI ---');
        const aiUrl = `${BASE_URL}/admin/ai-management`;
        await capture(aiUrl, '.aim-stats', 'real_ai_management.png');
        await capture(aiUrl, '#providerModal .modal-box', 'real_ai_provider_modal.png', { runScript: () => { document.getElementById('providerModal').style.display='flex'; } });
        await capture(null, '#keyModal .modal-box', 'real_ai_key_modal.png', { runScript: () => { document.getElementById('providerModal').style.display='none'; if(typeof openAddKey === "function") openAddKey(1, 'OpenAI'); } });
        await capture(null, '#modelModal .modal-box', 'real_ai_model_modal.png', { runScript: () => { document.getElementById('keyModal').style.display='none'; if(typeof openAddModel === "function") openAddModel(1, 'OpenAI'); } });
        await capture(null, '#hcModal .modal-box', 'real_ai_health_modal.png', { runScript: () => { 
            document.getElementById('modelModal').style.display='none';
            const btn = document.querySelector('.mb-hc'); 
            if(btn) btn.click();
            else if(typeof runHealthCheck === "function") runHealthCheck(1, 'OpenAI', 1, 'Key');
        } });

        // --- 5. ROLES ---
        console.log('\n--- Capturing Menu 5: Roles ---');
        const roleUrl = `${BASE_URL}/admin/roles`;
        await capture(roleUrl, '.role-list-card', 'real_role_list.png');
        await capture(null, '#roleModal .glass-card', 'real_role_modal.png', { runScript: () => showRoleModal('create') });
        await capture(null, '.swal2-confirm', 'real_role_delete_confirm.png', { runScript: () => {
            document.getElementById('roleModal').style.display='none';
            if(typeof deleteRole === "function") deleteRole(99, 'Role Staff');
        } });
        await page.evaluate(() => typeof Swal !== "undefined" && Swal.close());

        // --- 6. USERS ---
        console.log('\n--- Capturing Menu 6: Users ---');
        const userUrl = `${BASE_URL}/admin/users`;
        await capture(userUrl, '.table-responsive', 'real_user_list.png');
        await capture(null, '#userModal .modal-content', 'real_user_modal.png', { runScript: () => showModal('create') });
        await capture(null, '#aiConfigModal .glass-card', 'real_user_ai_modal.png', { runScript: () => { if(typeof hideModal === "function") hideModal(); const btn = document.querySelector('button[onclick*="showAiConfig"]'); if(btn) btn.click(); } });
        await capture(null, '#tableFilterModal .tf-modal', 'real_user_rls_modal.png', { runScript: () => { 
            document.getElementById('aiConfigModal').style.display='none'; 
            const btn = document.querySelector('button[onclick*="showTableFilters"]'); 
            if(btn) btn.click(); 
        } });
        await capture(null, '.swal2-confirm', 'real_user_delete_confirm.png', { runScript: () => {
            document.getElementById('tableFilterModal').style.display='none';
            const btn = document.querySelector('.btn-delete');
            if(btn) btn.click();
        } });

        console.log('\nAll screenshots captured successfully!');
    } catch (err) {
        console.error('Error during execution:', err);
    } finally {
        await browser.close();
    }
}

run();
