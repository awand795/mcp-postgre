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
            waitAfter = 1500, 
            fullPage = false,
            outlineOffset = '4px',
            outlineWidth = '4px'
        } = options;

        console.log(`Capturing ${filename}...`);
        
        try {
            if (page.url() !== url) {
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
            await page.screenshot({ path: savePath, fullPage: fullPage });
            
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
        // --- MENU 1: LOGIN ---
        console.log('\n--- Capturing Menu 1: Login ---');
        const loginUrl = `${BASE_URL}/login`;
        await capture(loginUrl, '.auth-card', 'real_login_page.png');
        await capture(loginUrl, 'input[name="email"]', 'real_login_email.png');
        await capture(loginUrl, 'input[name="password"]', 'real_login_password.png');
        await capture(loginUrl, 'button[type="submit"]', 'real_login_button.png');
        await capture(loginUrl, 'a[href*="forgot-password"]', 'real_login_forgot_link.png');

        // Forgot Password Flow
        const forgotUrl = `${BASE_URL}/forgot-password`;
        await capture(forgotUrl, 'input[name="email"]', 'real_forgot_email_field.png');
        await capture(`${BASE_URL}/verify-otp?email=admin@darkotech.id`, '.auth-card', 'real_verify_otp_page.png');
        await capture(`${BASE_URL}/reset-password?email=admin@darkotech.id&otp=123456`, '.auth-card', 'real_reset_password_page.png');

        console.log('Logging in...');
        await page.goto(loginUrl, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', 'awanda@darkotech.id');
        await page.type('input[name="password"]', 'awanda21345');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle0' }),
            page.click('button[type="submit"]')
        ]);

        // --- MENU 2: DASHBOARD ---
        console.log('\n--- Capturing Menu 2: Dashboard ---');
        const dashUrl = `${BASE_URL}/admin`;
        await capture(dashUrl, '.stats-grid', 'real_dashboard.png'); 
        await capture(dashUrl, '.sidebar', 'real_sidebar.png'); 
        await capture(dashUrl, '.theme-switch-wrap', 'real_dash_darkmode.png'); 

        // Capture Dashboard in Dark Mode
        console.log('Switching to Dark Mode...');
        await page.evaluate(() => {
            if (typeof toggleTheme === 'function') {
                toggleTheme();
            } else {
                document.documentElement.classList.toggle('dark');
            }
        });
        await new Promise(r => setTimeout(r, 1000));
        await capture(dashUrl, '.stats-grid', 'real_dashboard_dark.png');
        await capture(dashUrl, '.welcome-card', 'real_welcome_dark.png');
        
        // Switch back to Light Mode for consistency or stay Dark for some?
        // Let's stay Dark for a few more to show examples
        console.log('\n--- Capturing Menu 3: Users (Dark Mode Example) ---');
        const userUrl = `${BASE_URL}/admin/users`;
        await capture(userUrl, '.table-responsive', 'real_user_list_dark.png');

        console.log('Switching back to Light Mode...');
        await page.evaluate(() => {
            if (typeof toggleTheme === 'function') {
                toggleTheme();
            } else {
                document.documentElement.classList.toggle('dark');
            }
        });
        await new Promise(r => setTimeout(r, 1000));

        // --- MENU 3: USERS (Continue Light) ---
        await capture(userUrl, '.table-responsive', 'real_user_list.png');
        await capture(userUrl, '.filter-card', 'real_user_filter_form.png');
        await capture(userUrl, 'button[onclick*="showModal(\'create\')"]', 'real_user_tambah_btn.png');
        await capture(userUrl, 'button[onclick*="downloadTemplate"]', 'real_user_template_btn.png');
        await capture(userUrl, 'button[onclick*="exportUsers"]', 'real_user_export_btn.png');
        
        await capture(userUrl, '#userModal .modal-content', 'real_user_import_modal.png', { runScript: () => showModal('import') });

        // AI Config Modal
        await capture(userUrl, '#aiConfigModal .glass-card', 'real_ai_config_modal.png', { runScript: () => { const btn = document.querySelector('button[onclick*="showAiConfig"]'); if(btn) btn.click(); } });
        await capture(userUrl, '#aic-tab-keys-btn', 'real_ai_config_tab_keys.png', { runScript: () => { const btn = document.querySelector('button[onclick*="showAiConfig"]'); if(btn) { btn.click(); setTimeout(() => { const tab = document.getElementById('aic-tab-keys-btn'); if(tab) tab.click(); }, 500); } } });
        await capture(userUrl, '#btnSaveAiConfig', 'real_ai_config_save.png', { runScript: () => { const btn = document.querySelector('button[onclick*="showAiConfig"]'); if(btn) btn.click(); } });

        // RLS Modal
        await capture(userUrl, '#tableFilterModal .modal-content', 'real_rls_modal.png', { runScript: () => { const btn = document.querySelector('button[onclick*="showTableFilters"]'); if(btn) btn.click(); } });
        await capture(userUrl, '.tf-add-rule-btn', 'real_rls_add_rule.png', { runScript: () => { const btn = document.querySelector('button[onclick*="showTableFilters"]'); if(btn) { btn.click(); setTimeout(() => { const item = document.querySelector(".tf-table-item"); if(item) item.click(); }, 1000); } } });

        // --- MENU 4: ROLES ---
        console.log('\n--- Capturing Menu 4: Roles ---');
        const roleUrl = `${BASE_URL}/admin/roles`;
        await capture(roleUrl, '.role-list-card', 'real_role_list.png');
        await capture(roleUrl, '#tables-list', 'real_role_permissions.png');
        await capture(roleUrl, 'button[onclick*="savePermissions"]', 'real_role_save_permissions.png');

        // --- MENU 5: DATABASES ---
        console.log('\n--- Capturing Menu 5: Databases ---');
        const dbUrl = `${BASE_URL}/admin/databases`;
        await capture(dbUrl, '.database-grid', 'real_db_list.png');
        await capture(dbUrl, 'button[onclick*="showDatabaseModal"]', 'real_db_add_btn.png');
        await capture(dbUrl, 'button[onclick*="testAllConnections"]', 'real_db_test_all.png');

        // --- MENU 6: AI MANAGEMENT ---
        console.log('\n--- Capturing Menu 6: AI Management ---');
        const aiUrl = `${BASE_URL}/admin/ai-management`;
        await capture(aiUrl, '.aim-stats', 'real_ai_management.png');
        await capture(aiUrl, '.sw', 'real_ai_toggle_on.png');
        await capture(aiUrl, '.pcard-tab', 'real_ai_keys_tab.png');
        await capture(aiUrl, '.pf-btn-key', 'real_ai_add_key_btn.png');
        await capture(aiUrl, '.pf-btn-mod', 'real_ai_add_model_btn.png');
        await capture(aiUrl, '.mb-hc', 'real_ai_health_btn.png');
        await capture(aiUrl, '.mb-warn, .mb', 'real_ai_reset_limit_btn.png');

        console.log('\nAll screenshots captured successfully!');
    } catch (err) {
        console.error('Error during execution:', err);
    } finally {
        await browser.close();
    }
}

run();
