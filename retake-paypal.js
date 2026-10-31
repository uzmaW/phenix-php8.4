/**
 * Retake only the PayPal success screenshot (after fix).
 * Run: node retake-paypal.js
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:7073';
const SCREENSHOT_DIR = './screenshots';

async function run() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page    = await context.newPage();

    try {
        // Register + login
        await page.goto(`${BASE_URL}/admin/register`);
        await page.waitForLoadState('networkidle');
        try {
            await page.fill('input[name="name"]',                  'Phoenix Admin');
            await page.fill('input[name="email"]',                 'admin@phoenix.dev');
            await page.fill('input[name="password"]',              'secret123');
            await page.fill('input[name="password_confirmation"]', 'secret123');
            await page.click('button[type="submit"]');
            await page.waitForURL('**/admin/login', { timeout: 4000 });
        } catch (_) {}

        await page.goto(`${BASE_URL}/admin/login`);
        await page.waitForLoadState('networkidle');
        await page.fill('input[name="email"]',    'admin@phoenix.dev');
        await page.fill('input[name="password"]', 'secret123');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/admin', { timeout: 6000 });
        console.log('Logged in ✓');

        // PayPal success with demo token
        await page.goto(`${BASE_URL}/admin/payments/paypal/success?token=PAYPAL_DEMO_ORDER_123`);
        await page.waitForLoadState('networkidle', { timeout: 10000 });
        await new Promise(r => setTimeout(r, 800));

        const file = path.join(SCREENSHOT_DIR, '13-paypal-success.png');
        await page.screenshot({ path: file, fullPage: true });
        console.log(`  ✓ 13-paypal-success.png`);

        // Also retake PayPal cancel for consistency
        await page.goto(`${BASE_URL}/admin/payments/paypal/cancel`);
        await page.waitForLoadState('networkidle');
        await new Promise(r => setTimeout(r, 600));
        await page.screenshot({ path: path.join(SCREENSHOT_DIR, '12-paypal-cancel.png'), fullPage: true });
        console.log(`  ✓ 12-paypal-cancel.png`);

    } finally {
        await browser.close();
    }
}

run().then(() => console.log('Done.')).catch(e => { console.error(e); process.exit(1); });
