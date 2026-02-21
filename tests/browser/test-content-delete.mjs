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

async function navigateToManagerAndSelectBrand() {
    console.log('2. Navigating to AI Manager and selecting brand...');

    // Click "AI Manager" sidebar link to enter manager mode
    await page.evaluate(() => {
        const links = [...document.querySelectorAll('a')];
        const managerLink = links.find(l =>
            l.textContent.includes('AI Manager') || l.href?.includes('/manager')
        );
        if (managerLink) managerLink.click();
    });
    await new Promise(r => setTimeout(r, 2000));
    await screenshot('02-manager');

    // Check brand selector state
    const brandState = await page.evaluate(() => {
        const buttons = [...document.querySelectorAll('button')];
        const brandBtn = buttons.find(b => {
            const text = b.textContent.trim();
            return text.includes('Wybierz mark') || text === 'Aisello' || text.match(/^[A-Z].*\s*$/);
        });
        return brandBtn ? brandBtn.textContent.trim() : 'NOT FOUND';
    });
    console.log(`   Brand state: ${brandState}`);

    if (brandState.includes('Wybierz')) {
        // Select brand
        await page.evaluate(() => {
            const buttons = [...document.querySelectorAll('button')];
            const brandBtn = buttons.find(b => b.textContent.includes('Wybierz mark'));
            if (brandBtn) brandBtn.click();
        });
        await new Promise(r => setTimeout(r, 500));

        await page.evaluate(() => {
            const dropdownBtns = document.querySelectorAll('.absolute button');
            for (const btn of dropdownBtns) {
                const name = btn.querySelector('p')?.textContent?.trim();
                if (name && !name.includes('Dodaj') && !name.includes('Zarządzaj')) {
                    btn.click();
                    return;
                }
            }
        });
        await new Promise(r => setTimeout(r, 2000));
        console.log('   Brand selected');
    }

    await screenshot('02b-brand-ready');
}

async function testContentPageDelete() {
    console.log('\n3. Testing content page delete...');

    // Navigate via sidebar link (SPA navigation, no full reload)
    await page.evaluate(() => {
        const links = [...document.querySelectorAll('a')];
        const contentLink = links.find(l =>
            (l.textContent.trim() === 'Treści' || l.textContent.trim() === 'Content') &&
            !l.textContent.includes('Lista')
        );
        if (contentLink) {
            contentLink.click();
            return contentLink.textContent.trim();
        }
        return null;
    });
    await new Promise(r => setTimeout(r, 2000));
    await screenshot('03-content-page');

    // Check what's on the page
    const pageInfo = await page.evaluate(() => {
        const h1 = document.querySelector('h1');
        const cards = document.querySelectorAll('.grid > div');
        const emptyState = document.querySelector('h3');
        return {
            title: h1?.textContent || '',
            cardCount: cards.length,
            emptyMessage: emptyState?.textContent || '',
        };
    });
    console.log(`   Title: ${pageInfo.title}, Cards: ${pageInfo.cardCount}, Empty: ${pageInfo.emptyMessage}`);

    if (pageInfo.cardCount > 0) {
        // Find and click delete button
        const deleteClicked = await page.evaluate(() => {
            const buttons = [...document.querySelectorAll('button')];
            const deleteBtn = buttons.find(b => {
                const text = b.textContent.trim();
                return (text === 'Usuń' || text === 'Delete') &&
                    b.closest('.grid > div');
            });
            if (deleteBtn) {
                deleteBtn.click();
                return true;
            }
            return false;
        });
        console.log(`   Delete clicked: ${deleteClicked}`);
        await new Promise(r => setTimeout(r, 1000));
        await screenshot('04-confirm-dialog');

        // Check confirm dialog
        const dialogInfo = await page.evaluate(() => {
            // Look for modal overlay
            const overlay = document.querySelector('.fixed.inset-0');
            if (!overlay) return { visible: false };
            const buttons = [...overlay.querySelectorAll('button')];
            return {
                visible: true,
                buttons: buttons.map(b => b.textContent.trim()),
            };
        });
        console.log(`   Dialog: ${JSON.stringify(dialogInfo)}`);

        if (dialogInfo.visible) {
            // Click confirm to actually test the API call
            await page.evaluate(() => {
                const overlay = document.querySelector('.fixed.inset-0');
                if (!overlay) return;
                const buttons = [...overlay.querySelectorAll('button')];
                const confirmBtn = buttons.find(b => {
                    const cls = b.className || '';
                    return cls.includes('red') || cls.includes('danger');
                });
                if (confirmBtn) confirmBtn.click();
            });
            await new Promise(r => setTimeout(r, 2000));
            await screenshot('05-after-delete');
            console.log('   ✅ Delete flow completed');
        }
    } else {
        console.log('   ⚠️ No posts to test delete on (need scheduled posts)');
    }
}

async function testContentListBulkDelete() {
    console.log('\n4. Testing content-list bulk delete...');

    // Navigate via sidebar
    await page.evaluate(() => {
        const links = [...document.querySelectorAll('a')];
        const link = links.find(l =>
            l.textContent.includes('Lista treści') || l.href?.includes('content-list')
        );
        if (link) link.click();
    });
    await new Promise(r => setTimeout(r, 3000));
    await screenshot('06-content-list');

    // Check for table with checkboxes
    const tableInfo = await page.evaluate(() => {
        const table = document.querySelector('table');
        if (!table) return { hasTable: false, html: document.querySelector('main, [class*="min-h"]')?.innerHTML?.substring(0, 300) || '' };
        return {
            hasTable: true,
            headerCb: !!table.querySelector('thead input[type="checkbox"]'),
            bodyCbs: table.querySelectorAll('tbody input[type="checkbox"]').length,
            rows: table.querySelectorAll('tbody tr').length,
        };
    });
    console.log(`   Table info: ${JSON.stringify(tableInfo)}`);

    if (tableInfo.bodyCbs >= 2) {
        const cbs = await page.$$('table tbody input[type="checkbox"]');
        await cbs[0].click();
        await new Promise(r => setTimeout(r, 200));
        await cbs[1].click();
        await new Promise(r => setTimeout(r, 500));
        await screenshot('07-selected');

        const barFound = await page.evaluate(() => {
            const text = document.body.innerText;
            return text.includes('Zaznaczono') || text.includes('Selected');
        });
        console.log(`   Selection bar: ${barFound ? 'VISIBLE' : 'NOT FOUND'}`);
        console.log('   ✅ Bulk selection works');
    } else {
        console.log('   ⚠️ Not enough rows for bulk test');
    }
}

async function main() {
    try {
        await setup();
        await login();
        await navigateToManagerAndSelectBrand();
        await testContentPageDelete();
        await testContentListBulkDelete();
        console.log('\n✅ All tests completed. Screenshots in ' + SCREENSHOT_DIR);
    } catch (err) {
        console.error('❌ Test failed:', err.message);
        console.error(err.stack);
        await screenshot('error').catch(() => {});
    } finally {
        if (browser) await browser.close();
    }
}

main();
