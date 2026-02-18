#!/usr/bin/env node
/**
 * NLE Test — Video element resize on canvas
 */
import puppeteer from 'puppeteer-core';

const BASE = 'http://localhost';
const CHROME = '/usr/bin/google-chrome-stable';
const SCREENSHOT_DIR = '/tmp/nle-videoresize-screenshots';
const STORE = `document.querySelector('#app').__vue_app__.config.globalProperties.$pinia._s.get('videoEditorNew')`;

let browser, page;
let passed = 0, failed = 0;

function ok(label) { passed++; console.log(`  ✓ ${label}`); }
function fail(label, err) { failed++; console.log(`  ✗ ${label}: ${err}`); }

async function screenshot(name) {
    await page.screenshot({ path: `${SCREENSHOT_DIR}/${name}.png`, fullPage: false });
    console.log(`    📸 ${SCREENSHOT_DIR}/${name}.png`);
}

async function setup() {
    const fs = await import('fs');
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    browser = await puppeteer.launch({
        executablePath: CHROME,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1920,1080'],
    });
    page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });
    page.on('console', msg => {
        if (msg.type() === 'error') console.log(`    [ERR] ${msg.text()}`);
    });
    page.on('pageerror', err => console.log(`    [PAGE ERR] ${err.message}`));
}

async function login() {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.type('input[name="email"]', 'test@example.com');
    await page.type('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle0' });
    ok('Login');
}

async function navigateToEditor() {
    await page.goto(`${BASE}/app/video/nle/01KHNSQN597Q08ENYADEZ9KJEZ`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('canvas', { timeout: 10000 });
    await new Promise(r => setTimeout(r, 2000));
    ok('Editor loaded');
}

async function testVideoResize() {
    console.log('\n--- Debug: Video element resize ---');

    // Step 1: Get video element info
    const videoEl = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const videoEl = store.allElements.find(e => e.type === 'video');
        if (!videoEl) return null;
        return {
            id: videoEl.id,
            x: videoEl.x,
            y: videoEl.y,
            width: videoEl.width,
            height: videoEl.height,
            fit: videoEl.fit,
            type: videoEl.type,
        };
    }, STORE);
    console.log(`    Video element: ${JSON.stringify(videoEl)}`);

    if (!videoEl) {
        fail('Video element', 'Not found');
        return;
    }

    // Step 2: Select video element
    await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        store.selectElement(elId);
        store.seekTo(0);
    }, STORE, videoEl.id);
    await new Promise(r => setTimeout(r, 500));

    const selectedIds = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return [...store.selectedElementIds];
    }, STORE);
    console.log(`    Selected: ${JSON.stringify(selectedIds)}`);

    // Step 3: Check canvas and overlay geometry
    const geometry = await page.evaluate(() => {
        const canvas = document.querySelector('canvas');
        const overlay = document.querySelector('.absolute.z-10');
        const outlines = document.querySelectorAll('.border-2.border-blue-400.pointer-events-none');
        const handles = document.querySelectorAll('.bg-white.border.border-blue-500.rounded-sm');

        const result = {};
        if (canvas) {
            const r = canvas.getBoundingClientRect();
            result.canvas = { x: r.x, y: r.y, w: r.width, h: r.height };
        }
        if (overlay) {
            const r = overlay.getBoundingClientRect();
            result.overlay = { x: r.x, y: r.y, w: r.width, h: r.height };
        }
        result.outlineCount = outlines.length;
        result.handleCount = handles.length;

        if (handles.length > 0) {
            result.handles = Array.from(handles).map(h => {
                const r = h.getBoundingClientRect();
                return { cx: r.x + r.width / 2, cy: r.y + r.height / 2, w: r.width, h: r.height };
            });
        }
        if (outlines.length > 0) {
            const r = outlines[0].getBoundingClientRect();
            result.outline = { x: r.x, y: r.y, w: r.width, h: r.height };
        }
        return result;
    });

    console.log(`    Canvas: ${JSON.stringify(geometry.canvas)}`);
    console.log(`    Overlay: ${JSON.stringify(geometry.overlay)}`);
    console.log(`    Outlines: ${geometry.outlineCount}, Handles: ${geometry.handleCount}`);
    if (geometry.outline) console.log(`    Outline: ${JSON.stringify(geometry.outline)}`);
    if (geometry.handles) {
        geometry.handles.forEach((h, i) => console.log(`    Handle ${i}: cx=${h.cx.toFixed(1)}, cy=${h.cy.toFixed(1)}`));
    }

    await screenshot('01-video-selected');

    if (geometry.handleCount < 4) {
        fail('Video handles', `Only ${geometry.handleCount} handles found`);
        return;
    }

    // Step 4: Check if SE handle is within viewport
    const seHandle = geometry.handles[3]; // se = bottom-right
    console.log(`\n    SE handle position: (${seHandle.cx.toFixed(1)}, ${seHandle.cy.toFixed(1)})`);
    console.log(`    Viewport: 1920x1080`);

    const handleInView = seHandle.cx >= 0 && seHandle.cx <= 1920 && seHandle.cy >= 0 && seHandle.cy <= 1080;
    if (handleInView) {
        ok('SE handle is within viewport');
    } else {
        fail('SE handle position', 'Outside viewport');
    }

    // Step 5: Move mouse to SE handle, check cursor
    await page.mouse.move(seHandle.cx, seHandle.cy);
    await new Promise(r => setTimeout(r, 300));

    const cursorAtHandle = await page.evaluate(() => {
        const overlay = document.querySelector('.absolute.z-10');
        return overlay ? overlay.style.cursor : 'not found';
    });
    console.log(`    Cursor at SE handle: "${cursorAtHandle}"`);

    if (cursorAtHandle.includes('resize')) {
        ok(`Cursor at handle: ${cursorAtHandle}`);
    } else {
        // Debug: what does the composable think is happening?
        const debugInfo = await page.evaluate(function(storeAccessor) {
            const store = eval(storeAccessor);
            const compW = store.compositionWidth;
            const compH = store.compositionHeight;
            const el = store.selectedElement;
            if (!el) return { error: 'no selected' };

            // Calculate where handles should be in composition space
            const elW = parseFloat(el.width) / 100 * compW;
            const elH = parseFloat(el.height) / 100 * compH;
            const elX = parseFloat(el.x) / 100 * compW;
            const elY = parseFloat(el.y) / 100 * compH;

            return {
                compSize: `${compW}x${compH}`,
                elBounds: {
                    left: elX - elW / 2,
                    top: elY - elH / 2,
                    right: elX + elW / 2,
                    bottom: elY + elH / 2,
                    width: elW,
                    height: elH,
                },
                seCorner: { x: elX + elW / 2, y: elY + elH / 2 },
            };
        }, STORE);
        console.log(`    Debug — comp SE corner: ${JSON.stringify(debugInfo.seCorner)}`);
        console.log(`    Debug — comp size: ${debugInfo.compSize}`);
        console.log(`    Debug — el bounds: ${JSON.stringify(debugInfo.elBounds)}`);

        fail('Cursor at handle', `Got "${cursorAtHandle}" — no resize cursor`);
    }

    // Step 6: Drag SE handle inward
    console.log(`\n    Dragging SE handle from (${seHandle.cx.toFixed(0)}, ${seHandle.cy.toFixed(0)}) inward by 50px`);

    const before = { width: videoEl.width, height: videoEl.height };

    await page.mouse.move(seHandle.cx, seHandle.cy);
    await new Promise(r => setTimeout(r, 100));
    await page.mouse.down();
    await new Promise(r => setTimeout(r, 50));

    for (let i = 0; i < 10; i++) {
        await page.mouse.move(seHandle.cx - (i + 1) * 5, seHandle.cy - (i + 1) * 5);
        await new Promise(r => setTimeout(r, 30));
    }

    await page.mouse.up();
    await new Promise(r => setTimeout(r, 500));

    const after = await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        const el = store.allElements.find(e => e.id === elId);
        return el ? { width: el.width, height: el.height, x: el.x, y: el.y } : null;
    }, STORE, videoEl.id);

    console.log(`    Before: ${before.width} x ${before.height}`);
    console.log(`    After: ${after?.width} x ${after?.height}`);

    await screenshot('02-after-video-resize');

    if (before.width !== after?.width) {
        ok(`Video resize works: ${before.width} → ${after.width}`);
    } else {
        fail('Video resize', `Width unchanged: ${before.width} → ${after?.width}`);
    }

    // Step 7: Now try NW handle (top-left) — resize from opposite corner
    console.log('\n    --- Trying NW handle ---');

    // Re-select since dimensions changed
    await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        store.selectElement(elId);
    }, STORE, videoEl.id);
    await new Promise(r => setTimeout(r, 300));

    const updatedHandles = await page.evaluate(() => {
        const handles = document.querySelectorAll('.bg-white.border.border-blue-500.rounded-sm');
        return Array.from(handles).map(h => {
            const r = h.getBoundingClientRect();
            return { cx: r.x + r.width / 2, cy: r.y + r.height / 2 };
        });
    });

    if (updatedHandles.length >= 4) {
        const nwHandle = updatedHandles[0]; // nw = top-left
        console.log(`    NW handle: (${nwHandle.cx.toFixed(1)}, ${nwHandle.cy.toFixed(1)})`);

        await page.mouse.move(nwHandle.cx, nwHandle.cy);
        await new Promise(r => setTimeout(r, 200));

        const nwCursor = await page.evaluate(() => {
            const overlay = document.querySelector('.absolute.z-10');
            return overlay ? overlay.style.cursor : 'unknown';
        });
        console.log(`    NW cursor: ${nwCursor}`);

        if (nwCursor.includes('resize')) {
            ok(`NW handle cursor: ${nwCursor}`);
        } else {
            fail('NW cursor', `Got "${nwCursor}"`);
        }

        // Drag NW inward
        const beforeNW = await page.evaluate(function(storeAccessor, elId) {
            const store = eval(storeAccessor);
            const el = store.allElements.find(e => e.id === elId);
            return el ? { width: el.width, height: el.height } : null;
        }, STORE, videoEl.id);

        await page.mouse.down();
        await new Promise(r => setTimeout(r, 50));
        for (let i = 0; i < 10; i++) {
            await page.mouse.move(nwHandle.cx + (i + 1) * 5, nwHandle.cy + (i + 1) * 5);
            await new Promise(r => setTimeout(r, 30));
        }
        await page.mouse.up();
        await new Promise(r => setTimeout(r, 300));

        const afterNW = await page.evaluate(function(storeAccessor, elId) {
            const store = eval(storeAccessor);
            const el = store.allElements.find(e => e.id === elId);
            return el ? { width: el.width, height: el.height } : null;
        }, STORE, videoEl.id);

        console.log(`    NW Before: ${beforeNW?.width} x ${beforeNW?.height}`);
        console.log(`    NW After: ${afterNW?.width} x ${afterNW?.height}`);

        if (beforeNW?.width !== afterNW?.width) {
            ok(`NW resize works: ${beforeNW?.width} → ${afterNW?.width}`);
        } else {
            fail('NW resize', 'Width unchanged');
        }
    }

    await screenshot('03-final');
}

async function main() {
    console.log('=== NLE Video Resize Test ===\n');
    try {
        await setup();
        await login();
        await navigateToEditor();
        await testVideoResize();
    } catch (err) {
        console.error('FATAL:', err.message);
        try { await screenshot('fatal'); } catch {}
    } finally {
        if (browser) await browser.close();
    }

    console.log(`\n=== Results: ${passed} passed, ${failed} failed ===`);
    console.log(`Screenshots in: ${SCREENSHOT_DIR}/`);
    process.exit(failed > 0 ? 1 : 0);
}

main();
