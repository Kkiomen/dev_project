#!/usr/bin/env node
/**
 * NLE Test — Image auto-fit aspect ratio
 * Checks that added images get proportional bounding boxes, not distorted ones.
 */
import puppeteer from 'puppeteer-core';

const BASE = 'http://localhost';
const CHROME = '/usr/bin/google-chrome-stable';
const SCREENSHOT_DIR = '/tmp/nle-aspect-screenshots';
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

// ============================================================
// Test 1: Landscape image gets proper proportions on portrait canvas
// ============================================================
async function testLandscapeImage() {
    console.log('\n--- Test: Landscape image (800x400) on portrait canvas ---');

    const result = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const compW = store.compositionWidth;
        const compH = store.compositionHeight;

        // Create a landscape image (800x400 = 2:1 ratio)
        const c = document.createElement('canvas');
        c.width = 800; c.height = 400;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#ff0000';
        ctx.fillRect(0, 0, 800, 400);

        let track = store.composition.tracks.find(t => t.type === 'overlay');
        if (!track) track = store.addTrack('overlay', 'Test Layer');

        const el = store.addElement(track.id, {
            type: 'image',
            name: 'Landscape',
            time: 0,
            duration: 20,
            source: c.toDataURL('image/png'),
            x: '50%',
            y: '50%',
        });

        return {
            compSize: `${compW}x${compH}`,
            initialWidth: el.width,
            initialHeight: el.height,
            elementId: el.id,
        };
    }, STORE);

    console.log(`    Composition: ${result.compSize}`);
    console.log(`    Initial size: ${result.initialWidth} x ${result.initialHeight}`);

    // Wait for image onload + autoFit
    await new Promise(r => setTimeout(r, 1500));

    const afterFit = await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        const el = store.allElements.find(e => e.id === elId);
        if (!el) return null;

        const compW = store.compositionWidth;
        const compH = store.compositionHeight;

        // Calculate pixel sizes
        const wPct = parseFloat(el.width);
        const hPct = parseFloat(el.height);
        const wPx = (wPct / 100) * compW;
        const hPx = (hPct / 100) * compH;
        const pixelRatio = wPx / hPx;

        return {
            width: el.width,
            height: el.height,
            widthPx: Math.round(wPx),
            heightPx: Math.round(hPx),
            pixelRatio: pixelRatio.toFixed(2),
            autoFitted: el._autoFitted,
        };
    }, STORE, result.elementId);

    console.log(`    After auto-fit: ${afterFit.width} x ${afterFit.height}`);
    console.log(`    Pixel size: ${afterFit.widthPx}x${afterFit.heightPx}`);
    console.log(`    Pixel ratio (w/h): ${afterFit.pixelRatio} (image is 2:1)`);
    console.log(`    Auto-fitted: ${afterFit.autoFitted}`);

    // The bounding box should be roughly 2:1 ratio (landscape image)
    const ratio = parseFloat(afterFit.pixelRatio);
    if (ratio >= 1.5 && ratio <= 2.5) {
        ok(`Landscape image: bounding box pixel ratio is ${afterFit.pixelRatio} (~2:1)`);
    } else {
        fail('Landscape ratio', `Pixel ratio is ${afterFit.pixelRatio} — expected ~2.0`);
    }

    // Height should NOT be equal to width percentage
    const wPct = parseFloat(afterFit.width);
    const hPct = parseFloat(afterFit.height);
    if (Math.abs(wPct - hPct) > 5) {
        ok(`Width (${wPct}%) ≠ height (${hPct}%) — not distorted`);
    } else {
        fail('Proportions', `Width ${wPct}% ≈ height ${hPct}% — still square percentages`);
    }

    // Select and check selection box
    await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        store.selectElement(elId);
        store.seekTo(0);
    }, STORE, result.elementId);
    await new Promise(r => setTimeout(r, 500));
    await screenshot('01-landscape');

    const selInfo = await page.evaluate(() => {
        const outline = document.querySelector('.border-2.border-blue-400.pointer-events-none');
        if (!outline) return null;
        const r = outline.getBoundingClientRect();
        return { w: r.width, h: r.height, ratio: (r.width / r.height).toFixed(2) };
    });

    if (selInfo) {
        console.log(`    Selection box: ${selInfo.w.toFixed(0)}x${selInfo.h.toFixed(0)}, ratio ${selInfo.ratio}`);
        const selRatio = parseFloat(selInfo.ratio);
        if (selRatio > 1.3) {
            ok(`Selection box is landscape (ratio ${selInfo.ratio})`);
        } else {
            fail('Selection box shape', `Ratio ${selInfo.ratio} — expected landscape (>1.3)`);
        }
    }
}

// ============================================================
// Test 2: Portrait image gets proper proportions
// ============================================================
async function testPortraitImage() {
    console.log('\n--- Test: Portrait image (400x800) on portrait canvas ---');

    const result = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);

        // Create portrait image (400x800 = 1:2 ratio)
        const c = document.createElement('canvas');
        c.width = 400; c.height = 800;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#00ff00';
        ctx.fillRect(0, 0, 400, 800);

        let track = store.composition.tracks.find(t => t.type === 'overlay');
        const el = store.addElement(track.id, {
            type: 'image',
            name: 'Portrait',
            time: 0,
            duration: 20,
            source: c.toDataURL('image/png'),
            x: '50%',
            y: '50%',
        });

        return { elementId: el.id };
    }, STORE);

    await new Promise(r => setTimeout(r, 1500));

    const afterFit = await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        const el = store.allElements.find(e => e.id === elId);
        if (!el) return null;

        const compW = store.compositionWidth;
        const compH = store.compositionHeight;
        const wPx = (parseFloat(el.width) / 100) * compW;
        const hPx = (parseFloat(el.height) / 100) * compH;

        return {
            width: el.width,
            height: el.height,
            widthPx: Math.round(wPx),
            heightPx: Math.round(hPx),
            pixelRatio: (wPx / hPx).toFixed(2),
        };
    }, STORE, result.elementId);

    console.log(`    After auto-fit: ${afterFit.width} x ${afterFit.height}`);
    console.log(`    Pixel size: ${afterFit.widthPx}x${afterFit.heightPx}`);
    console.log(`    Pixel ratio (w/h): ${afterFit.pixelRatio} (image is 0.5:1)`);

    const ratio = parseFloat(afterFit.pixelRatio);
    if (ratio >= 0.3 && ratio <= 0.7) {
        ok(`Portrait image: pixel ratio is ${afterFit.pixelRatio} (~0.5)`);
    } else {
        fail('Portrait ratio', `Pixel ratio is ${afterFit.pixelRatio} — expected ~0.5`);
    }

    await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        store.selectElement(elId);
        store.seekTo(0);
    }, STORE, result.elementId);
    await new Promise(r => setTimeout(r, 500));
    await screenshot('02-portrait');
}

// ============================================================
// Test 3: Square image gets square bounding box
// ============================================================
async function testSquareImage() {
    console.log('\n--- Test: Square image (500x500) on portrait canvas ---');

    const result = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);

        const c = document.createElement('canvas');
        c.width = 500; c.height = 500;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#0000ff';
        ctx.fillRect(0, 0, 500, 500);

        let track = store.composition.tracks.find(t => t.type === 'overlay');
        const el = store.addElement(track.id, {
            type: 'image',
            name: 'Square',
            time: 0,
            duration: 20,
            source: c.toDataURL('image/png'),
            x: '50%',
            y: '50%',
        });

        return { elementId: el.id };
    }, STORE);

    await new Promise(r => setTimeout(r, 1500));

    const afterFit = await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        const el = store.allElements.find(e => e.id === elId);
        if (!el) return null;

        const compW = store.compositionWidth;
        const compH = store.compositionHeight;
        const wPx = (parseFloat(el.width) / 100) * compW;
        const hPx = (parseFloat(el.height) / 100) * compH;

        return {
            width: el.width,
            height: el.height,
            widthPx: Math.round(wPx),
            heightPx: Math.round(hPx),
            pixelRatio: (wPx / hPx).toFixed(2),
        };
    }, STORE, result.elementId);

    console.log(`    After auto-fit: ${afterFit.width} x ${afterFit.height}`);
    console.log(`    Pixel size: ${afterFit.widthPx}x${afterFit.heightPx}`);
    console.log(`    Pixel ratio (w/h): ${afterFit.pixelRatio} (image is 1:1)`);

    const ratio = parseFloat(afterFit.pixelRatio);
    if (ratio >= 0.8 && ratio <= 1.2) {
        ok(`Square image: pixel ratio is ${afterFit.pixelRatio} (~1.0)`);
    } else {
        fail('Square ratio', `Pixel ratio is ${afterFit.pixelRatio} — expected ~1.0`);
    }

    await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        store.selectElement(elId);
        store.seekTo(0);
    }, STORE, result.elementId);
    await new Promise(r => setTimeout(r, 500));
    await screenshot('03-square');
}

// ============================================================
// Test 4: Uploaded real image via media:// protocol
// ============================================================
async function testUploadedImageProportions() {
    console.log('\n--- Test: Uploaded media:// image proportions ---');

    const projectId = await page.evaluate(function(storeAccessor) {
        return eval(storeAccessor).projectId;
    }, STORE);

    // Upload a landscape red image (300x150)
    const uploadResult = await page.evaluate(async function(storeAccessor, projectId) {
        const c = document.createElement('canvas');
        c.width = 300; c.height = 150;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#ff6600';
        ctx.fillRect(0, 0, 300, 150);

        const blob = await new Promise(resolve => c.toBlob(resolve, 'image/png'));
        const formData = new FormData();
        formData.append('file', blob, 'test-landscape.png');

        const xsrfCookie = document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='));
        const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.split('=')[1]) : '';

        const resp = await fetch(`/api/v1/video-projects/${projectId}/upload-media`, {
            method: 'POST',
            body: formData,
            headers: { 'X-XSRF-TOKEN': xsrfToken, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });

        return resp.ok ? await resp.json() : { error: resp.status };
    }, STORE, projectId);

    if (uploadResult.error) {
        fail('Upload', `${uploadResult.error}`);
        return;
    }
    console.log(`    Uploaded: ${uploadResult.source}`);

    // Add to timeline
    const result = await page.evaluate(function(storeAccessor, source) {
        const store = eval(storeAccessor);
        let track = store.composition.tracks.find(t => t.type === 'overlay');
        const el = store.addElement(track.id, {
            type: 'image',
            name: 'Uploaded Landscape',
            time: 0,
            duration: 20,
            source: source,
            x: '50%',
            y: '30%',
        });
        store.seekTo(0);
        return { elementId: el.id };
    }, STORE, uploadResult.source);

    // Wait for image to load via /media/ endpoint + auto-fit
    await new Promise(r => setTimeout(r, 3000));

    const afterFit = await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        const el = store.allElements.find(e => e.id === elId);
        if (!el) return null;

        const compW = store.compositionWidth;
        const compH = store.compositionHeight;
        const wPx = (parseFloat(el.width) / 100) * compW;
        const hPx = (parseFloat(el.height) / 100) * compH;

        return {
            width: el.width,
            height: el.height,
            widthPx: Math.round(wPx),
            heightPx: Math.round(hPx),
            pixelRatio: (wPx / hPx).toFixed(2),
            autoFitted: el._autoFitted,
        };
    }, STORE, result.elementId);

    console.log(`    After auto-fit: ${afterFit.width} x ${afterFit.height}`);
    console.log(`    Pixel size: ${afterFit.widthPx}x${afterFit.heightPx}`);
    console.log(`    Pixel ratio: ${afterFit.pixelRatio} (original image 2:1)`);

    const ratio = parseFloat(afterFit.pixelRatio);
    if (ratio >= 1.5 && ratio <= 2.5 && afterFit.autoFitted) {
        ok(`Uploaded image auto-fitted: ratio ${afterFit.pixelRatio}`);
    } else {
        fail('Uploaded auto-fit', `Ratio ${afterFit.pixelRatio}, autoFitted=${afterFit.autoFitted}`);
    }

    await page.evaluate(function(storeAccessor, elId) {
        const store = eval(storeAccessor);
        store.selectElement(elId);
    }, STORE, result.elementId);
    await new Promise(r => setTimeout(r, 500));
    await screenshot('04-uploaded-proportions');
}

async function main() {
    console.log('=== NLE Aspect Ratio Test ===\n');
    try {
        await setup();
        await login();
        await navigateToEditor();
        await testLandscapeImage();
        await testPortraitImage();
        await testSquareImage();
        await testUploadedImageProportions();
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
