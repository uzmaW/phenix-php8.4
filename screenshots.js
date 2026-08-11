const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:7073';
const SCREENSHOT_DIR = './screenshots';

async function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function takeScreenshots() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 800 }
    });
    const page = await context.newPage();

    try {
        // 1. Login page
        console.log('Taking screenshot: Login page...');
        await page.goto(`${BASE_URL}/admin/login`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOT_DIR}/01-login.png`, fullPage: true });
        console.log('  -> 01-login.png');

        // 2. Register page
        console.log('Taking screenshot: Register page...');
        await page.goto(`${BASE_URL}/admin/register`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOT_DIR}/02-register.png`, fullPage: true });
        console.log('  -> 02-register.png');

        // 3. Register a user
        console.log('Registering test user...');
        await page.fill('input[name="name"]', 'Admin User');
        await page.fill('input[name="email"]', 'admin@test.com');
        await page.fill('input[name="password"]', 'secret123');
        await page.fill('input[name="password_confirmation"]', 'secret123');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin/login');
        await sleep(1000);

        // 4. Login
        console.log('Logging in...');
        await page.fill('input[name="email"]', 'admin@test.com');
        await page.fill('input[name="password"]', 'secret123');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin');
        await sleep(2000);

        // 5. Dashboard
        console.log('Taking screenshot: Dashboard...');
        await page.screenshot({ path: `${SCREENSHOT_DIR}/03-dashboard.png`, fullPage: true });
        console.log('  -> 03-dashboard.png');

        // 6. Users list
        console.log('Taking screenshot: Users list...');
        await page.goto(`${BASE_URL}/admin/users`);
        await page.waitForLoadState('networkidle');
        await sleep(1000);
        await page.screenshot({ path: `${SCREENSHOT_DIR}/04-users.png`, fullPage: true });
        console.log('  -> 04-users.png');

        // 7. Create user modal
        console.log('Taking screenshot: Create user modal...');
        await page.click('button:has-text("+ New User")');
        await sleep(500);
        await page.screenshot({ path: `${SCREENSHOT_DIR}/05-create-user-modal.png`, fullPage: true });
        console.log('  -> 05-create-user-modal.png');

        // Close modal
        await page.click('button:has-text("Cancel")');
        await sleep(300);

        // 8. User profile
        console.log('Taking screenshot: User profile...');
        const viewBtn = page.locator('a:has-text("View")').first();
        await viewBtn.click();
        await page.waitForLoadState('networkidle');
        await sleep(1000);
        await page.screenshot({ path: `${SCREENSHOT_DIR}/06-user-profile.png`, fullPage: true });
        console.log('  -> 06-user-profile.png');

        // 9. Edit user modal
        console.log('Taking screenshot: Edit user modal...');
        await page.click('button:has-text("Edit User")');
        await sleep(500);
        await page.screenshot({ path: `${SCREENSHOT_DIR}/07-edit-user-modal.png`, fullPage: true });
        console.log('  -> 07-edit-user-modal.png');

        console.log('\nAll screenshots saved to ./screenshots/');
    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({ path: `${SCREENSHOT_DIR}/error.png` });
    } finally {
        await browser.close();
    }
}

takeScreenshots();
