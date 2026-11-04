const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:7073';
const SCREENSHOT_DIR = './screenshots';
const VIDEO_DIR = './videos';

if (!fs.existsSync(SCREENSHOT_DIR)) fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
if (!fs.existsSync(VIDEO_DIR)) fs.mkdirSync(VIDEO_DIR, { recursive: true });
for (const f of fs.readdirSync(VIDEO_DIR)) {
    if (f.endsWith('.webm')) fs.unlinkSync(path.join(VIDEO_DIR, f));
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function shot(page, filename, label, waitMs = 600) {
    await sleep(waitMs);
    const filepath = path.join(SCREENSHOT_DIR, filename);
    await page.screenshot({ path: filepath, fullPage: true });
    console.log(`  [shot] ${filename}  (${label})`);
    return filepath;
}

async function saveVideo(context, page, name) {
    const video = page.video();
    const tmpPath = video ? await video.path().catch(() => null) : null;
    await context.close();
    if (video) {
        await video.saveAs(path.join(VIDEO_DIR, name));
        console.log(`  [video] ${name}`);
        if (tmpPath && fs.existsSync(tmpPath)) fs.rmSync(tmpPath);
    }
}

async function registerAdmin(page) {
    try {
        await page.goto(`${BASE_URL}/admin/register`);
        await page.waitForLoadState('networkidle');
        await page.fill('input[name="name"]', 'Phoenix Admin');
        await page.fill('input[name="email"]', 'admin@phoenix.dev');
        await page.fill('input[name="password"]', 'secret123');
        await page.fill('input[name="password_confirmation"]', 'secret123');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin/login', { timeout: 4000 });
    } catch (_) {
        try {
            await page.goto(`${BASE_URL}/admin/login`);
            await page.waitForLoadState('networkidle');
        } catch (_e) {}
    }
}

async function login(page) {
    await page.goto(`${BASE_URL}/admin/login`);
    await page.waitForLoadState('networkidle');
    await page.fill('input[name="email"]', 'admin@phoenix.dev');
    await page.fill('input[name="password"]', 'secret123');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin', { timeout: 6000 });
    await page.waitForLoadState('networkidle');
}

/* ------------------------------------------------------------------ */
/* 1. Complete workflow: every page, every feature, one continuous run */
/* ------------------------------------------------------------------ */
async function completeWorkflow() {
    console.log('\n=== Complete admin workflow (screenshots + video) ===');
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        recordVideo: { dir: VIDEO_DIR, size: { width: 1440, height: 900 } },
    });
    const page = await context.newPage();
    page.on('console', () => {});

    try {
        console.log('\n-- Auth pages --');
        await page.goto(`${BASE_URL}/admin/login`);
        await page.waitForLoadState('networkidle');
        await shot(page, '01-login.png', 'Login page');

        await page.goto(`${BASE_URL}/admin/register`);
        await page.waitForLoadState('networkidle');
        await shot(page, '02-register.png', 'Register page');

        await registerAdmin(page);

        console.log('\n-- Login --');
        await login(page);

        console.log('\n-- Dashboard with charts --');
        await sleep(2500);
        await shot(page, '03-dashboard.png', 'Dashboard with charts', 0);

        await page.evaluate(() => window.scrollTo(0, 400));
        await sleep(800);
        await shot(page, '03b-dashboard-charts.png', 'Dashboard charts section', 0);

        await page.evaluate(() => window.scrollTo(0, 800));
        await sleep(500);
        await shot(page, '03c-dashboard-charts-bottom.png', 'Dashboard activity + traffic charts', 0);
        await page.evaluate(() => window.scrollTo(0, 0));

        console.log('\n-- Analytics dashboard --');
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

        console.log('\n-- User management (full CRUD) --');
        await page.goto(`${BASE_URL}/admin/users`);
        await page.waitForLoadState('networkidle');
        await shot(page, '04-users.png', 'Users list');

        // Actually create a user through the form (complete workflow)
        try {
            await page.locator('button:has-text("New User")').first().click();
            await sleep(500);
            await shot(page, '05-create-user-modal.png', 'Create user modal', 0);

            await page.locator('form[action="/admin/users"] input[name="name"]').fill('Workflow Test User');
            await page.locator('form[action="/admin/users"] input[name="email"]').fill('workflow@phoenix.dev');
            await page.locator('form[action="/admin/users"] input[name="password"]').fill('secret123');
            await page.locator('form[action="/admin/users"] button[type="submit"]').click();
            await page.waitForLoadState('networkidle');
            await sleep(600);
            await shot(page, '05b-user-created.png', 'User created via workflow', 0);
        } catch (e) {
            console.log(`    (create-user flow skipped: ${e.message.split('\n')[0]})`);
        }

        try {
            await page.goto(`${BASE_URL}/admin/users/1`);
            await page.waitForLoadState('networkidle');
            await shot(page, '06-user-profile.png', 'User profile');

            await page.locator('#editModal button[type="button"]:has-text("Edit User"), button:has-text("Edit User") >> nth=0').click();
            await sleep(500);
            await shot(page, '07-edit-user-modal.png', 'Edit user modal', 0);
            await page.locator('#editModal button:has-text("Cancel")').click();
            await sleep(300);
        } catch (e) {
            console.log(`    (profile/edit skipped: ${e.message.split('\n')[0]})`);
        }

        console.log('\n-- Payments hub --');
        await page.goto(`${BASE_URL}/admin/payments`);
        await page.waitForLoadState('networkidle');
        await shot(page, '09-payments.png', 'Payments hub - Stripe + PayPal cards');

        await page.evaluate(() => window.scrollTo(0, 400));
        await sleep(400);
        await shot(page, '09b-payments-setup-guide.png', 'Payments hub - setup guide', 0);
        await page.evaluate(() => window.scrollTo(0, 0));

        console.log('\n-- Stripe checkout flow --');
        await page.goto(`${BASE_URL}/admin/payments/stripe/cancel`);
        await page.waitForLoadState('networkidle');
        await shot(page, '10-stripe-cancel.png', 'Stripe checkout cancelled');

        await page.goto(`${BASE_URL}/admin/payments/stripe/success?session_id=cs_test_demo_session_123`);
        await page.waitForLoadState('networkidle');
        await shot(page, '11-stripe-success.png', 'Stripe checkout success');

        console.log('\n-- PayPal checkout flow --');
        await page.goto(`${BASE_URL}/admin/payments/paypal/cancel`);
        await page.waitForLoadState('networkidle');
        await shot(page, '12-paypal-cancel.png', 'PayPal payment cancelled');

        await page.goto(`${BASE_URL}/admin/payments/paypal/success?token=PAYPAL_ORDER_DEMO_123`);
        await page.waitForLoadState('networkidle');
        await shot(page, '13-paypal-success.png', 'PayPal payment success');

        console.log('\n-- Final overview --');
        await page.goto(`${BASE_URL}/admin`);
        await page.waitForLoadState('networkidle');
        await sleep(2500);
        await shot(page, '00-dashboard-overview.png', 'Full dashboard overview', 0);

        await saveVideo(context, page, '01-complete-admin-workflow.webm');

        console.log('\n[ok] Complete workflow captured');
    } catch (err) {
        console.error('\n[error] Complete workflow failed:', err.message);
        try {
            await page.screenshot({ path: path.join(SCREENSHOT_DIR, 'error.png'), fullPage: true });
            await saveVideo(context, page, '99-complete-workflow-failure.webm');
        } catch (_) {}
        process.exitCode = 1;
    } finally {
        if (!process.exitCode) await browser.close().catch(() => {});
        else await browser.close().catch(() => {});
    }
}

/* --------------------------------------------------- */
/* 2. Focused videos: one scenario per video           */
/* --------------------------------------------------- */
async function scenarioDashboard() {
    console.log('\n=== Video: dashboard + analytics walkthrough ===');
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        recordVideo: { dir: VIDEO_DIR, size: { width: 1440, height: 900 } },
    });
    const page = await context.newPage();
    try {
        await registerAdmin(page);
        await login(page);
        await sleep(2500);

        for (const pos of [0, 200, 400, 600, 800, 1000]) {
            await page.evaluate(y => window.scrollTo(0, y), pos);
            await sleep(600);
        }
        await page.evaluate(() => window.scrollTo(0, 0));
        await sleep(400);

        await page.goto(`${BASE_URL}/admin/analytics`);
        await page.waitForLoadState('networkidle');
        await sleep(2500);
        for (const pos of [0, 300, 500, 800, 1100]) {
            await page.evaluate(y => window.scrollTo(0, y), pos);
            await sleep(600);
        }
        await page.evaluate(() => window.scrollTo(0, 0));

        await saveVideo(context, page, '02-dashboard-analytics.webm');
    } catch (err) {
        console.error('[error] dashboard video failed:', err.message);
        await saveVideo(context, page, '99-dashboard-failure.webm');
        process.exitCode = 1;
    } finally {
        await browser.close().catch(() => {});
    }
}

async function scenarioUsers() {
    console.log('\n=== Video: user management lifecycle ===');
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        recordVideo: { dir: VIDEO_DIR, size: { width: 1440, height: 900 } },
    });
    const page = await context.newPage();
    try {
        await registerAdmin(page);
        await login(page);

        await page.goto(`${BASE_URL}/admin/users`);
        await page.waitForLoadState('networkidle');
        await sleep(800);

        try {
            await page.locator('button:has-text("New User")').first().click();
            await sleep(600);
            const email = `video-${Date.now()}@phoenix.dev`;
            await page.locator('form[action="/admin/users"] input[name="name"]').fill('Video Demo User');
            await page.locator('form[action="/admin/users"] input[name="email"]').fill(email);
            await page.locator('form[action="/admin/users"] input[name="password"]').fill('secret123');
            await page.locator('form[action="/admin/users"] button[type="submit"]').click();
            await page.waitForLoadState('networkidle');
            await sleep(800);
        } catch (e) {
            console.log(`    (create flow skipped: ${e.message.split('\n')[0]})`);
        }

        await page.goto(`${BASE_URL}/admin/users`);
        await page.waitForLoadState('networkidle');
        await sleep(800);
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 3));
        await sleep(600);
        await page.evaluate(() => window.scrollTo(0, 0));

        try {
            await page.goto(`${BASE_URL}/admin/users/1`);
            await page.waitForLoadState('networkidle');
            await sleep(800);
            await page.locator('button:has-text("Edit User")').first().click();
            await sleep(600);
            await page.locator('#editModal input[name="name"]').fill('Video Demo User (edited)');
            await sleep(400);
            await page.locator('#editModal button:has-text("Cancel")').click();
            await sleep(400);
        } catch (e) {
            console.log(`    (profile/edit skipped: ${e.message.split('\n')[0]})`);
        }

        await page.goto(`${BASE_URL}/admin/users`);
        await page.waitForLoadState('networkidle');
        await sleep(800);

        await saveVideo(context, page, '03-user-management.webm');
    } catch (err) {
        console.error('[error] users video failed:', err.message);
        await saveVideo(context, page, '99-users-failure.webm');
        process.exitCode = 1;
    } finally {
        await browser.close().catch(() => {});
    }
}

async function scenarioPayments() {
    console.log('\n=== Video: payments hub (Stripe + PayPal) ===');
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        recordVideo: { dir: VIDEO_DIR, size: { width: 1440, height: 900 } },
    });
    const page = await context.newPage();
    try {
        await registerAdmin(page);
        await login(page);

        await page.goto(`${BASE_URL}/admin/payments`);
        await page.waitForLoadState('networkidle');
        await sleep(1500);
        for (const pos of [0, 250, 450]) {
            await page.evaluate(y => window.scrollTo(0, y), pos);
            await sleep(600);
        }
        await page.evaluate(() => window.scrollTo(0, 0));

        await page.goto(`${BASE_URL}/admin/payments/stripe/cancel`);
        await page.waitForLoadState('networkidle');
        await sleep(700);

        await page.goto(`${BASE_URL}/admin/payments/stripe/success?session_id=cs_test_video_session_456`);
        await page.waitForLoadState('networkidle');
        await sleep(700);

        await page.goto(`${BASE_URL}/admin/payments`);
        await page.waitForLoadState('networkidle');
        await sleep(1000);

        await page.goto(`${BASE_URL}/admin/payments/paypal/cancel`);
        await page.waitForLoadState('networkidle');
        await sleep(700);

        await page.goto(`${BASE_URL}/admin/payments/paypal/success?token=PAYPAL_VIDEO_ORDER_789`);
        await page.waitForLoadState('networkidle');
        await sleep(700);

        // Try launching real checkout (demo mode shows setup guide instead)
        await page.goto(`${BASE_URL}/admin/payments`);
        await page.waitForLoadState('networkidle');
        await sleep(800);

        await saveVideo(context, page, '04-payments-stripe-paypal.webm');
    } catch (err) {
        console.error('[error] payments video failed:', err.message);
        await saveVideo(context, page, '99-payments-failure.webm');
        process.exitCode = 1;
    } finally {
        await browser.close().catch(() => {});
    }
}

(async () => {
    console.log('Phoenix admin workflow recorder');
    console.log(`Base URL: ${BASE_URL}`);

    await completeWorkflow();
    await scenarioDashboard();
    await scenarioUsers();
    await scenarioPayments();

    console.log('\n================ Capture summary ================');
    const shots = fs.readdirSync(SCREENSHOT_DIR).filter(f => f.endsWith('.png') && f !== 'error.png').sort();
    const shotsTotal = shots.length;
    console.log(`Screenshots (${shotsTotal}):`);
    shots.forEach(f => console.log(`  ${f}`));

    const videos = fs.readFileSync ? fs.readdirSync(VIDEO_DIR).filter(f => f.endsWith('.webm')).sort() : [];
    console.log(`Videos (${videos.length}):`);
    videos.forEach(f => {
        const size = fs.statSync(path.join(VIDEO_DIR, f)).size;
        console.log(`  ${f}  (${(size / 1024 / 1024).toFixed(2)} MB)`);
    });
    console.log('=================================================\n');
})();
