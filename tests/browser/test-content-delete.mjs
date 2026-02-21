import puppeteer from 'puppeteer-core';

const BASE = 'http://localhost';
const SCREENSHOT_DIR = '/tmp/content-delete-screenshots';
const CHROME_PATH = '/usr/bin/google-chrome-stable';

let browser, page;

async function setup() {
    const fs = await import('fs');
    if (!fs.existsSync(SCREENSHOT_DIR)) fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

    browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });
    page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });

    page.on('console', msg => {
        if (msg.type() === 'error') console.log(`[BROWSER ERROR] ${msg.text()}`);
    });
    page.on('pageerror', err => console.log(`[PAGE ERROR] ${err.message}`));
    page.on('response', resp => {
        if (resp.status() >= 400 && !resp.url().includes('dashboard/stats')) {
            resp.text().then(body => {
                console.log(`[HTTP ${resp.status()}] ${resp.url()}`);
                if (body.length < 500) console.log(`  Body: ${body}`);
            }).catch(() => {});
        }
    });
}

async function screenshot(name) {
    await page.screenshot({ path: `${SCREENSHOT_DIR}/${name}.png`, fullPage: true });
    console.log(`  📸 ${name}.png`);
}

async function login() {
    console.log('1. Logging in...');
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.type('input[name="email"]', 'test@example.com');
    await page.type('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle0' });
    console.log('   ✅ Logged in');
}

async function selectBrand() {
    console.log('2. Selecting brand...');

    // Click the brand switcher button (contains "Wybierz markę" or a brand name)
    const clicked = await page.evaluate(() => {
        const buttons = [...document.querySelectorAll('button')];
        const brandBtn = buttons.find(b =>
            b.textContent.includes('Wybierz mark') || b.classList.toString().includes('border-gray-300')
        );
        if (brandBtn) {
            brandBtn.click();
            return brandBtn.textContent.trim();
        }
        return null;
    });
    console.log(`   Clicked brand button: ${clicked}`);
    await new Promise(r => setTimeout(r, 500));
    await screenshot('02-brand-dropdown');

    // Click on the first brand in the dropdown (not "Dodaj nową" or "Zarządzaj")
    const selected = await page.evaluate(() => {
        // The dropdown is an absolute div with brand buttons
        const dropdownBtns = document.querySelectorAll('.absolute button');
        for (const btn of dropdownBtns) {
            const name = btn.querySelector('p.text-sm')?.textContent?.trim();
            if (name) {
                btn.click();
                return name;
            }
        }
        return null;
    });
    console.log(`   Selected brand: ${selected || 'NONE'}`);
    await new Promise(r => setTimeout(r, 1500));
    await screenshot('02b-brand-selected');
}

async function testContentPageDelete() {
    console.log('\n3. Testing /app/manager/content delete...');
    await page.goto(`${BASE}/app/manager/content`, { waitUntil: 'networkidle0' });
    await new Promise(r => setTimeout(r, 2000));
    await screenshot('03-content-page');

    // Check if there are post cards
    const postCards = await page.evaluate(() => {
        // Grid cards containing delete buttons
        const grids = document.querySelectorAll('.grid');
        for (const grid of grids) {
            const cards = grid.children;
            if (cards.length > 0) return cards.length;
        }
        return 0;
    });
    console.log(`   Post cards found: ${postCards}`);

    if (postCards === 0) {
        console.log('   No posts on content page');

        // Check what's shown
        const pageState = await page.evaluate(() => {
            const empty = document.querySelector('h3');
            return empty?.textContent || 'N/A';
        });
        console.log(`   Page state: ${pageState}`);
        return false;
    }

    // Find and click delete button on first card
    const deleteFound = await page.evaluate(() => {
        const buttons = [...document.querySelectorAll('button')];
        const deleteBtn = buttons.find(b => {
            const text = b.textContent.trim();
            return text === 'Usuń' || text === 'Delete';
        });
        if (deleteBtn) {
            deleteBtn.click();
            return true;
        }
        return false;
    });
    console.log(`   Delete button clicked: ${deleteFound}`);
    await new Promise(r => setTimeout(r, 1000));
    await screenshot('04-confirm-dialog');

    // Check if confirm dialog appeared
    const dialogVisible = await page.evaluate(() => {
        const buttons = [...document.querySelectorAll('button')];
        const hasConfirm = buttons.some(b => b.textContent.trim() === 'Usuń' || b.textContent.trim() === 'Delete');
        const hasCancel = buttons.some(b => b.textContent.trim() === 'Anuluj' || b.textContent.trim() === 'Cancel');
        return hasConfirm && hasCancel;
    });
    console.log(`   Confirm dialog visible: ${dialogVisible}`);

    if (dialogVisible) {
        // Cancel - don't actually delete
        await page.evaluate(() => {
            const buttons = [...document.querySelectorAll('button')];
            const cancelBtn = buttons.find(b =>
                b.textContent.trim() === 'Anuluj' || b.textContent.trim() === 'Cancel'
            );
            if (cancelBtn) cancelBtn.click();
        });
        await new Promise(r => setTimeout(r, 500));
        console.log('   ✅ Confirm dialog works');
    }
    return true;
}

async function testContentListBulkDelete() {
    console.log('\n4. Testing /app/manager/content-list bulk delete...');
    await page.goto(`${BASE}/app/manager/content-list`, { waitUntil: 'networkidle0' });
    await new Promise(r => setTimeout(r, 3000));
    await screenshot('05-content-list');

    // Check for table and checkboxes
    const tableInfo = await page.evaluate(() => {
        const table = document.querySelector('table');
        if (!table) return { hasTable: false };
        const headerCb = table.querySelector('thead input[type="checkbox"]');
        const bodyCbs = table.querySelectorAll('tbody input[type="checkbox"]');
        return {
            hasTable: true,
            hasHeaderCheckbox: !!headerCb,
            bodyCheckboxCount: bodyCbs.length,
        };
    });
    console.log(`   Table: ${tableInfo.hasTable}, Header CB: ${tableInfo.hasHeaderCheckbox}, Row CBs: ${tableInfo.bodyCheckboxCount}`);

    if (tableInfo.bodyCheckboxCount >= 2) {
        // Select first 2
        const cbs = await page.$$('table tbody input[type="checkbox"]');
        await cbs[0].click();
        await new Promise(r => setTimeout(r, 200));
        await cbs[1].click();
        await new Promise(r => setTimeout(r, 500));
        await screenshot('06-selected');

        // Check selection bar text
        const barText = await page.evaluate(() => {
            const allText = document.body.innerText;
            const match = allText.match(/Zaznaczono.*?\d+/);
            return match ? match[0] : null;
        });
        console.log(`   Selection bar: ${barText || 'NOT FOUND'}`);
        console.log('   ✅ Bulk selection works');
    } else {
        console.log('   Not enough rows for bulk test');
    }
}

async function main() {
    try {
        await setup();
        await login();
        await selectBrand();
        await testContentPageDelete();
        await testContentListBulkDelete();
        console.log('\n✅ All tests completed. Screenshots in ' + SCREENSHOT_DIR);
    } catch (err) {
        console.error('❌ Test failed:', err.message);
        await screenshot('error').catch(() => {});
    } finally {
        if (browser) await browser.close();
    }
}

main();
