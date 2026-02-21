import puppeteer from 'puppeteer-core';

const BASE = 'http://localhost';
const SCREENSHOT_DIR = '/tmp/bulk-delete-screenshots';
const CHROME_PATH = '/usr/bin/google-chrome-stable';

let browser, page;
let networkErrors = [];

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
    // Click brand selector dropdown
    const brandSelector = await page.$('[class*="Wybierz mark"], button:has-text("Wybierz")');
    // Try clicking the brand dropdown
    await page.evaluate(() => {
        // Find the brand selector button
        const btns = [...document.querySelectorAll('button')];
        const brandBtn = btns.find(b => b.textContent.includes('Wybierz mark'));
        if (brandBtn) { brandBtn.click(); return true; }
        // Also try looking for a dropdown trigger
        const dropdowns = document.querySelectorAll('[class*="brand"], [class*="dropdown"]');
        return false;
    });
    await new Promise(r => setTimeout(r, 500));
    await screenshot('02-brand-dropdown');

    // Click first brand option
    const selected = await page.evaluate(() => {
        // Look for dropdown options
        const options = document.querySelectorAll('[class*="dropdown"] a, [class*="dropdown"] button, [role="option"], [role="menuitem"]');
        for (const opt of options) {
            if (opt.textContent.trim() && !opt.textContent.includes('Wybierz')) {
                opt.click();
                return opt.textContent.trim();
            }
        }
        // Try li elements
        const lis = document.querySelectorAll('li');
        for (const li of lis) {
            if (li.textContent.includes('Aisello') || (!li.textContent.includes('Wybierz') && li.textContent.trim())) {
                li.click();
                return li.textContent.trim();
            }
        }
        return null;
    });
    console.log(`   Selected: ${selected || 'unknown'}`);
    await new Promise(r => setTimeout(r, 1000));
    await screenshot('02b-brand-selected');
}

async function navigateToContentList() {
    console.log('3. Navigating to Content List...');
    // Click sidebar link
    await page.evaluate(() => {
        const links = [...document.querySelectorAll('a')];
        const contentLink = links.find(l => l.textContent.includes('Lista treści') || l.textContent.includes('Content List') || l.href?.includes('content-list'));
        if (contentLink) contentLink.click();
    });
    await new Promise(r => setTimeout(r, 2000));
    await screenshot('03-content-list');

    // Check if we have slots now
    const slotsCount = await page.evaluate(() => {
        return document.querySelectorAll('table tbody input[type="checkbox"]').length;
    });
    console.log(`   Checkboxes in table: ${slotsCount}`);

    if (slotsCount === 0) {
        // Direct URL navigation
        await page.goto(`${BASE}/app/manager/content-list`, { waitUntil: 'networkidle0' });
        await new Promise(r => setTimeout(r, 3000));
        await screenshot('03b-direct-nav');

        const slotsCount2 = await page.evaluate(() => {
            return document.querySelectorAll('table tbody input[type="checkbox"]').length;
        });
        console.log(`   After direct nav - checkboxes: ${slotsCount2}`);
    }
}

async function testBulkDelete() {
    console.log('4. Testing bulk selection & delete...');

    // Check for header checkbox (select all)
    const headerCheckbox = await page.$('table thead input[type="checkbox"]');
    if (!headerCheckbox) {
        console.log('   ❌ No header checkbox found');
        // Debug: show what's on the page
        const tableHTML = await page.evaluate(() => {
            const table = document.querySelector('table');
            return table ? table.outerHTML.substring(0, 500) : 'NO TABLE';
        });
        console.log('   Table HTML (first 500 chars):', tableHTML);
        return;
    }

    // Get body checkboxes
    const bodyCheckboxes = await page.$$('table tbody input[type="checkbox"]');
    console.log(`   Body checkboxes: ${bodyCheckboxes.length}`);

    // Click first 2 checkboxes
    if (bodyCheckboxes.length >= 2) {
        await bodyCheckboxes[0].click();
        await new Promise(r => setTimeout(r, 300));
        await bodyCheckboxes[1].click();
        await new Promise(r => setTimeout(r, 500));
        await screenshot('04-two-selected');

        // Check selection bar
        const selectionText = await page.evaluate(() => {
            const el = document.querySelector('[class*="sticky"]');
            return el?.innerText || null;
        });
        console.log(`   Selection bar: ${selectionText || 'NOT FOUND'}`);

        // Find delete button in selection bar
        const deleteBtn = await page.evaluate(() => {
            const buttons = [...document.querySelectorAll('button')];
            const del = buttons.find(b =>
                (b.textContent.includes('Usuń') && b.textContent.match(/\d/)) ||
                (b.textContent.includes('Delete') && b.textContent.match(/\d/))
            );
            return del ? del.textContent.trim() : null;
        });
        console.log(`   Delete button text: ${deleteBtn || 'NOT FOUND'}`);

        if (deleteBtn) {
            // Click delete selected
            await page.evaluate(() => {
                const buttons = [...document.querySelectorAll('button')];
                const del = buttons.find(b =>
                    (b.textContent.includes('Usuń') && b.textContent.match(/\d/)) ||
                    (b.textContent.includes('Delete') && b.textContent.match(/\d/))
                );
                if (del) del.click();
            });
            await new Promise(r => setTimeout(r, 1000));
            await screenshot('05-confirm-dialog');

            // Confirm deletion
            const confirmed = await page.evaluate(() => {
                // Find the confirm button in the dialog (not the cancel one)
                const buttons = [...document.querySelectorAll('button')];
                const confirmBtn = buttons.find(b => {
                    const text = b.textContent.trim();
                    return (text === 'Usuń' || text === 'Delete') && b.classList.toString().includes('red');
                });
                if (confirmBtn) { confirmBtn.click(); return true; }
                // Try alternate: find a red/danger button
                const dangerBtn = buttons.find(b =>
                    b.className.includes('red') || b.className.includes('danger')
                );
                if (dangerBtn) { dangerBtn.click(); return true; }
                return false;
            });
            console.log(`   Confirmed: ${confirmed}`);

            await new Promise(r => setTimeout(r, 2000));
            await screenshot('06-after-delete');

            // Check remaining rows
            const remaining = await page.evaluate(() => {
                return document.querySelectorAll('table tbody input[type="checkbox"]').length;
            });
            console.log(`   Remaining checkboxes: ${remaining}`);
            console.log(`   ✅ Bulk delete flow completed`);
        }
    } else {
        // Just test select all
        console.log('   Testing select all...');
        await headerCheckbox.click();
        await new Promise(r => setTimeout(r, 500));
        await screenshot('04-select-all');
    }
}

async function main() {
    try {
        await setup();
        await login();
        await selectBrand();
        await navigateToContentList();
        await testBulkDelete();
        console.log('\n✅ Test completed. Screenshots in ' + SCREENSHOT_DIR);
    } catch (err) {
        console.error('❌ Test failed:', err.message);
        await screenshot('error').catch(() => {});
    } finally {
        if (browser) await browser.close();
    }
}

main();
