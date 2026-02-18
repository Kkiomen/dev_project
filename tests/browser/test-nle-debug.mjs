#!/usr/bin/env node
/**
 * NLE Debug Test — Focused on multi-select and canvas interaction bugs
 */
import puppeteer from 'puppeteer-core';

const BASE = 'http://localhost';
const CHROME = '/usr/bin/google-chrome-stable';
const SCREENSHOT_DIR = '/tmp/nle-debug-screenshots';

let browser, page;
let passed = 0, failed = 0;
const errors = [];

function ok(label) { passed++; console.log(`  ✓ ${label}`); }
function fail(label, err) { failed++; console.log(`  ✗ ${label}: ${err}`); }

async function screenshot(name) {
    await page.screenshot({ path: `${SCREENSHOT_DIR}/${name}.png`, fullPage: false });
    console.log(`    Screenshot: ${SCREENSHOT_DIR}/${name}.png`);
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
    page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
    page.on('pageerror', err => errors.push(err.message));
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
// DEBUG: Multi-select — add element at different time, test ctrl+click
// ============================================================
async function debugMultiSelect() {
    console.log('\n--- Debug Multi-select ---');

    // Add a text element on a DIFFERENT track at a DIFFERENT time
    const addResult = await page.evaluate(() => {
        const app = document.querySelector('#app').__vue_app__;
        const pinia = app.config.globalProperties.$pinia;
        const store = pinia._s.get('videoEditorNew');
        if (!store || !store.composition?.tracks) return { error: 'no store' };

        // Find or create an overlay track
        let overlayTrack = store.composition.tracks.find(t => t.type === 'overlay');
        if (!overlayTrack) {
            overlayTrack = store.addTrack('overlay', 'Test Overlay');
        }

        // Add a text element at time=10 (won't overlap with video at time=0)
        store.addElement(overlayTrack.id, {
            type: 'text',
            name: 'Test Text',
            time: 10,
            duration: 5,
            text: 'Hello',
            x: '50%',
            y: '30%',
            font_size: 48,
            color: '#ffffff',
        });

        // Clear selection so we start fresh
        store.clearSelection();

        return {
            trackCount: store.composition.tracks.length,
            elementCount: store.allElements.length,
            selectedIds: [...store.selectedElementIds],
        };
    });
    await new Promise(r => setTimeout(r, 500));
    console.log(`    Setup: ${JSON.stringify(addResult)}`);

    // Get all element blocks on the timeline
    const allBlocks = await page.$$('[class*="absolute top-1 bottom-1 rounded cursor-pointer"]');
    console.log(`    Element blocks on timeline: ${allBlocks.length}`);

    if (allBlocks.length < 2) {
        fail('Multi-select setup', `Need 2+ blocks, got ${allBlocks.length}`);
        return;
    }

    // Click first block (should be the video element)
    const firstBox = await allBlocks[0].boundingBox();
    console.log(`    Block 0 bounds: x=${firstBox.x}, y=${firstBox.y}, w=${firstBox.width}, h=${firstBox.height}`);
    await page.mouse.click(firstBox.x + firstBox.width / 2, firstBox.y + firstBox.height / 2);
    await new Promise(r => setTimeout(r, 300));

    const afterFirst = await page.evaluate(() => {
        const app = document.querySelector('#app').__vue_app__;
        const store = app.config.globalProperties.$pinia._s.get('videoEditorNew');
        return { selectedIds: [...store.selectedElementIds] };
    });
    console.log(`    After click first: selectedIds=${JSON.stringify(afterFirst.selectedIds)}`);

    const firstSelected = await allBlocks[0].evaluate(el => el.classList.contains('ring-2'));
    if (firstSelected) {
        ok('First element selected');
    } else {
        fail('First element selected', 'No ring-2');
    }

    // Now Ctrl+click the SECOND block
    const secondBox = await allBlocks[1].boundingBox();
    console.log(`    Block 1 bounds: x=${secondBox.x}, y=${secondBox.y}, w=${secondBox.width}, h=${secondBox.height}`);

    await page.keyboard.down('Control');
    await page.mouse.click(secondBox.x + secondBox.width / 2, secondBox.y + secondBox.height / 2);
    await page.keyboard.up('Control');
    await new Promise(r => setTimeout(r, 300));

    const afterCtrl = await page.evaluate(() => {
        const app = document.querySelector('#app').__vue_app__;
        const store = app.config.globalProperties.$pinia._s.get('videoEditorNew');
        return { selectedIds: [...store.selectedElementIds] };
    });
    console.log(`    After Ctrl+click: selectedIds=${JSON.stringify(afterCtrl.selectedIds)}`);

    await screenshot('01-multi-select-ctrl');

    if (afterCtrl.selectedIds.length === 2) {
        ok('Ctrl+click multi-selects 2 elements');
    } else if (afterCtrl.selectedIds.length === 1) {
        fail('Multi-select', `Only 1 selected: ${JSON.stringify(afterCtrl.selectedIds)}`);
    } else {
        fail('Multi-select', `${afterCtrl.selectedIds.length} selected: ${JSON.stringify(afterCtrl.selectedIds)}`);
    }

    // Check inspector shows multi-select message
    const inspectorText = await page.evaluate(() => {
        const panels = document.querySelectorAll('[class*="flex-1 overflow-y-auto p-3"]');
        for (const p of panels) {
            const text = p.innerText.trim();
            if (text.includes('element') || text.includes('zaznacz')) return text;
        }
        return '';
    });
    console.log(`    Inspector text: "${inspectorText.substring(0, 80)}"`);

    // Ctrl+A
    await page.keyboard.down('Control');
    await page.keyboard.press('a');
    await page.keyboard.up('Control');
    await new Promise(r => setTimeout(r, 300));

    const afterSelectAll = await page.evaluate(() => {
        const app = document.querySelector('#app').__vue_app__;
        const store = app.config.globalProperties.$pinia._s.get('videoEditorNew');
        return { selectedIds: [...store.selectedElementIds], allCount: store.allElements.length };
    });
    console.log(`    After Ctrl+A: ${afterSelectAll.selectedIds.length}/${afterSelectAll.allCount} selected`);

    if (afterSelectAll.selectedIds.length === afterSelectAll.allCount) {
        ok(`Ctrl+A selects all (${afterSelectAll.selectedIds.length})`);
    } else {
        fail('Ctrl+A', `${afterSelectAll.selectedIds.length}/${afterSelectAll.allCount}`);
    }

    // Escape clears
    await page.keyboard.press('Escape');
    await new Promise(r => setTimeout(r, 200));
    const afterEscape = await page.evaluate(() => {
        const app = document.querySelector('#app').__vue_app__;
        const store = app.config.globalProperties.$pinia._s.get('videoEditorNew');
        return store.selectedElementIds.length;
    });
    if (afterEscape === 0) ok('Escape clears'); else fail('Escape', `${afterEscape} still selected`);
}

// ============================================================
// DEBUG: Canvas resize — drag a corner handle
// ============================================================
async function debugCanvasResize() {
    console.log('\n--- Debug Canvas Resize ---');

    // Select the video element first
    const allBlocks = await page.$$('[class*="absolute top-1 bottom-1 rounded cursor-pointer"]');
    if (allBlocks.length === 0) { fail('Canvas resize', 'No elements'); return; }

    await allBlocks[0].click();
    await new Promise(r => setTimeout(r, 500));

    // Get initial element properties from store
    const before = await page.evaluate(() => {
        const app = document.querySelector('#app').__vue_app__;
        const store = app.config.globalProperties.$pinia._s.get('videoEditorNew');
        const el = store.selectedElement;
        if (!el) return null;
        return { id: el.id, x: el.x, y: el.y, width: el.width, height: el.height, type: el.type };
    });
    console.log(`    Selected element: ${JSON.stringify(before)}`);

    if (!before) { fail('Canvas resize', 'No element selected'); return; }

    // Find canvas and overlay
    const canvas = await page.$('canvas');
    const overlay = await page.$('[class*="absolute inset-0 z-10"]');

    if (!canvas || !overlay) {
        fail('Canvas resize', `canvas=${!!canvas}, overlay=${!!overlay}`);
        return;
    }

    const canvasBox = await canvas.boundingBox();
    const overlayBox = await overlay.boundingBox();
    console.log(`    Canvas: x=${canvasBox.x}, y=${canvasBox.y}, w=${canvasBox.width}, h=${canvasBox.height}`);
    console.log(`    Overlay: x=${overlayBox.x}, y=${overlayBox.y}, w=${overlayBox.width}, h=${overlayBox.height}`);

    // Check selection overlays are rendered
    const overlayCount = await page.evaluate(() => {
        const outlines = document.querySelectorAll('[class*="border-2 border-blue-400"]');
        return outlines.length;
    });
    console.log(`    Selection outlines: ${overlayCount}`);

    // Get the selection outline bounds and handle positions
    const handleInfo = await page.evaluate(() => {
        const handles = document.querySelectorAll('[class*="bg-white border border-blue-500 rounded-sm"]');
        return Array.from(handles).map(h => {
            const rect = h.getBoundingClientRect();
            return { x: rect.x, y: rect.y, w: rect.width, h: rect.height, cx: rect.x + rect.width/2, cy: rect.y + rect.height/2 };
        });
    });
    console.log(`    Handles: ${handleInfo.length}`);
    handleInfo.forEach((h, i) => console.log(`      Handle ${i}: cx=${h.cx.toFixed(0)}, cy=${h.cy.toFixed(0)}`));

    await screenshot('02-before-resize');

    if (handleInfo.length < 4) {
        fail('Resize handles', `Found ${handleInfo.length}, expected 4`);
        return;
    }

    // Try to drag the SE (bottom-right) handle — last handle
    const seHandle = handleInfo[3]; // se should be bottom-right
    console.log(`    Dragging SE handle from (${seHandle.cx.toFixed(0)}, ${seHandle.cy.toFixed(0)})`);

    // Simulate drag: mousedown on handle, move 50px left/up, mouseup
    await page.mouse.move(seHandle.cx, seHandle.cy);
    await new Promise(r => setTimeout(r, 100));

    // Check cursor
    const cursorBefore = await page.evaluate(() => {
        const overlay = document.querySelector('[class*="absolute inset-0 z-10"]');
        return overlay ? getComputedStyle(overlay).cursor : 'unknown';
    });
    console.log(`    Cursor at handle: ${cursorBefore}`);

    await page.mouse.down();
    await new Promise(r => setTimeout(r, 50));

    // Move 50px left and 50px up (shrink)
    for (let i = 0; i < 10; i++) {
        await page.mouse.move(seHandle.cx - (i + 1) * 5, seHandle.cy - (i + 1) * 5);
        await new Promise(r => setTimeout(r, 20));
    }

    await page.mouse.up();
    await new Promise(r => setTimeout(r, 300));

    await screenshot('03-after-resize');

    // Check if size changed
    const after = await page.evaluate(() => {
        const app = document.querySelector('#app').__vue_app__;
        const store = app.config.globalProperties.$pinia._s.get('videoEditorNew');
        const el = store.selectedElement;
        if (!el) return null;
        return { id: el.id, x: el.x, y: el.y, width: el.width, height: el.height };
    });
    console.log(`    After resize: ${JSON.stringify(after)}`);

    if (before && after && before.width !== after.width) {
        ok(`Resize works: ${before.width} → ${after.width}`);
    } else {
        fail('Canvas resize', `Size unchanged: ${before?.width} → ${after?.width}`);
    }
}

// ============================================================
// DEBUG: Canvas move — drag element to move
// ============================================================
async function debugCanvasMove() {
    console.log('\n--- Debug Canvas Move ---');

    const allBlocks = await page.$$('[class*="absolute top-1 bottom-1 rounded cursor-pointer"]');
    if (allBlocks.length === 0) { fail('Canvas move', 'No elements'); return; }

    await allBlocks[0].click();
    await new Promise(r => setTimeout(r, 500));

    const before = await page.evaluate(() => {
        const app = document.querySelector('#app').__vue_app__;
        const store = app.config.globalProperties.$pinia._s.get('videoEditorNew');
        const el = store.selectedElement;
        return el ? { x: el.x, y: el.y } : null;
    });
    console.log(`    Before move: ${JSON.stringify(before)}`);

    const overlay = await page.$('[class*="absolute inset-0 z-10"]');
    const overlayBox = await overlay.boundingBox();

    // Click center of canvas overlay (where the video element should be)
    const centerX = overlayBox.x + overlayBox.width / 2;
    const centerY = overlayBox.y + overlayBox.height / 2;

    console.log(`    Dragging from center (${centerX.toFixed(0)}, ${centerY.toFixed(0)}) → +100px right`);

    await page.mouse.move(centerX, centerY);
    await new Promise(r => setTimeout(r, 100));
    await page.mouse.down();
    await new Promise(r => setTimeout(r, 50));

    for (let i = 0; i < 10; i++) {
        await page.mouse.move(centerX + (i + 1) * 10, centerY);
        await new Promise(r => setTimeout(r, 20));
    }

    await page.mouse.up();
    await new Promise(r => setTimeout(r, 300));

    const after = await page.evaluate(() => {
        const app = document.querySelector('#app').__vue_app__;
        const store = app.config.globalProperties.$pinia._s.get('videoEditorNew');
        const el = store.selectedElement;
        return el ? { x: el.x, y: el.y } : null;
    });
    console.log(`    After move: ${JSON.stringify(after)}`);

    await screenshot('04-after-move');

    if (before && after && before.x !== after.x) {
        ok(`Canvas move works: x ${before.x} → ${after.x}`);
    } else {
        fail('Canvas move', `Position unchanged: x ${before?.x} → ${after?.x}`);
    }
}

async function main() {
    console.log('=== NLE Debug Test ===\n');
    try {
        await setup();
        await login();
        await navigateToEditor();
        await debugMultiSelect();
        await debugCanvasResize();
        await debugCanvasMove();
    } catch (err) {
        console.error('FATAL:', err.message);
        try { await screenshot('fatal'); } catch {}
    } finally {
        if (browser) await browser.close();
    }

    console.log(`\n=== Results: ${passed} passed, ${failed} failed ===`);
    if (errors.length) {
        console.log('\n=== Console Errors ===');
        errors.forEach(e => console.log(`  ! ${e}`));
    }
    process.exit(failed > 0 ? 1 : 0);
}

main();
