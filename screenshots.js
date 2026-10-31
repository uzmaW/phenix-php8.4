const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:7073';
const SCREENSHOT_DIR = './screenshots';

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function shot(page, filename, label, waitMs = 600) {
    await sleep(waitMs);
    const filepath = path.join(SCREENSHOT_DIR, filename);
    await page.screenshot({ path: filepath, fullPage: true });
    console.log(`  ✓ ${filename}  (${label})`);
    return filepath;
}

async function takeScreenshots() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 }
    });
    const page = await context.newPage();

    // Silence console noise from the page
    page.on('console', () => {});

    try {
        console.log('\n── Auth pages ──────────────────────────────────────');

        await page.goto(`${BASE_URL}/admin/login`);
        await page.waitForLoadState('networkidle');
        await shot(page, '01-login.png', 'Login page');

        await page.goto(`${BASE_URL}/admin/register`);
        await page.waitForLoadState('networkidle');
        await shot(page, '02-register.png', 'Register page');

        // Register a fresh test user (ignore duplicate errors)
        try {
            await page.fill('input[name="name"]', 'Phoenix Admin');
            await page.fill('input[name="email"]', 'admin@phoenix.dev');
            await page.fill('input[name="password"]', 'secret123');
            await page.fill('input[name="password_confirmation"]', 'secret123');
            await page.click('button[type="submit"]');
            await page.waitForURL('**/admin/login', { timeout: 4000 });
        } catch (_) { /* user may already exist */ }

        // Login
        await page.goto(`${BASE_URL}/admin/login`);
        await page.waitForLoadState('networkidle');
        await page.fill('input[name="email"]', 'admin@phoenix.dev');
        await page.fill('input[name="password"]', 'secret123');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin', { timeout: 6000 });

        console.log('\n── Dashboard with Charts ───────────────────────────');

        // Wait for Chart.js to render
        await page.waitForLoadState('networkidle');
        await sleep(2500); // give Chart.js time to animate
        await shot(page, '03-dashboard.png', 'Dashboard with charts', 0);

        // Scroll to show charts section
        await page.evaluate(() => window.scrollTo(0, 400));
        await sleep(800);
        await shot(page, '03b-dashboard-charts.png', 'Dashboard charts section', 0);

        // Scroll to bottom charts row
        await page.evaluate(() => window.scrollTo(0, 800));
        await sleep(500);
        await shot(page, '03c-dashboard-charts-bottom.png', 'Dashboard activity + traffic charts', 0);

        // Reset scroll
        await page.evaluate(() => window.scrollTo(0, 0));

        console.log('\n── Analytics Dashboard ─────────────────────────────');

        await page.goto(`${BASE_URL}/admin/analytics`);
        await page.waitForLoadState('networkidle');
        await sleep(2500);
        await shot(page, '08-analytics.png', 'Analytics dashboard - top', 0);

        await page.evaluate(() => window.scrollTo(0, 500));
        await sleep(600);
        await shot(page, '08b-analytics-revenue.png', 'Analytics - revenue + requests charts', 0);

        await page.evaluate(() => window.scrollTo(0, 1100));
        await sleep(600);
        await shot(page, '08c-analytics-radar.png', 'Analytics - performance radar chart', 0);

        await page.evaluate(() => window.scrollTo(0, 0));

        console.log('\n── User Management ─────────────────────────────────');

        await page.goto(`${BASE_URL}/admin/users`);
        await page.waitForLoadState('networkidle');
        await shot(page, '04-users.png', 'Users list');

        // Create user modal
        try {
            const newUserBtn = page.locator('button:has-text("New User"), button:has-text("+ New User")').first();
            await newUserBtn.click();
            await sleep(500);
            await shot(page, '05-create-user-modal.png', 'Create user modal', 0);
            // Close modal
            const cancelBtn = page.locator('button:has-text("Cancel")').first();
            await cancelBtn.click();
            await sleep(300);
        } catch (_) {
            console.log('    (skipped modal - button not found)');
        }

        // User profile
        try {
            const viewBtn = page.locator('a:has-text("View"), a[href*="/admin/users/"]').first();
            await viewBtn.click();
            await page.waitForLoadState('networkidle');
            await shot(page, '06-user-profile.png', 'User profile');

            // Edit modal
            try {
                const editBtn = page.locator('button:has-text("Edit User"), button:has-text("Edit")').first();
                await editBtn.click();
                await sleep(500);
                await shot(page, '07-edit-user-modal.png', 'Edit user modal', 0);
                const cancelBtn = page.locator('button:has-text("Cancel")').first();
                await cancelBtn.click();
                await sleep(300);
            } catch (_) {
                console.log('    (skipped edit modal)');
            }
        } catch (_) {
            console.log('    (skipped user profile - no users in list)');
        }

        console.log('\n── Payments Hub ────────────────────────────────────');

        await page.goto(`${BASE_URL}/admin/payments`);
        await page.waitForLoadState('networkidle');
        await shot(page, '09-payments.png', 'Payments hub - Stripe + PayPal cards');

        // Scroll down to see setup guide
        await page.evaluate(() => window.scrollTo(0, 400));
        await sleep(400);
        await shot(page, '09b-payments-setup-guide.png', 'Payments hub - setup guide', 0);
        await page.evaluate(() => window.scrollTo(0, 0));

        console.log('\n── Stripe flow ─────────────────────────────────────');

        // Stripe cancel page (simulate cancelled checkout)
        await page.goto(`${BASE_URL}/admin/payments/stripe/cancel`);
        await page.waitForLoadState('networkidle');
        await shot(page, '10-stripe-cancel.png', 'Stripe checkout cancelled');

        // Stripe success page (simulate successful checkout)
        await page.goto(`${BASE_URL}/admin/payments/stripe/success?session_id=cs_test_demo_session_123`);
        await page.waitForLoadState('networkidle');
        await shot(page, '11-stripe-success.png', 'Stripe checkout success');

        console.log('\n── PayPal flow ─────────────────────────────────────');

        // PayPal cancel page
        await page.goto(`${BASE_URL}/admin/payments/paypal/cancel`);
        await page.waitForLoadState('networkidle');
        await shot(page, '12-paypal-cancel.png', 'PayPal payment cancelled');

        // PayPal success page
        await page.goto(`${BASE_URL}/admin/payments/paypal/success?token=PAYPAL_ORDER_DEMO_123`);
        await page.waitForLoadState('networkidle');
        await shot(page, '13-paypal-success.png', 'PayPal payment success');

        console.log('\n── Final overview ──────────────────────────────────');

        // Back to dashboard for a final full-page shot
        await page.goto(`${BASE_URL}/admin`);
        await page.waitForLoadState('networkidle');
        await sleep(2500);
        await shot(page, '00-dashboard-overview.png', 'Full dashboard overview', 0);

        console.log('\n✅  All screenshots saved to ./screenshots/');
        console.log('─────────────────────────────────────────────────────\n');

        // Print summary
        const files = fs.readdirSync(SCREENSHOT_DIR)
            .filter(f => f.endsWith('.png') && f !== 'error.png')
            .sort();
        console.log(`Total: ${files.length} screenshots`);
        files.forEach(f => console.log(`  ${f}`));

    } catch (error) {
        console.error('\n❌  Fatal error:', error.message);
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'error.png'), fullPage: true });
        console.log('  error.png saved for inspection');
        process.exit(1);
    } finally {
        await browser.close();
    }
}

takeScreenshots();
