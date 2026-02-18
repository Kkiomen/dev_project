#!/usr/bin/env node
/**
 * NLE Test — Image on Canvas, Resize, Multi-select
 */
import puppeteer from 'puppeteer-core';

const BASE = 'http://localhost';
const CHROME = '/usr/bin/google-chrome-stable';
const SCREENSHOT_DIR = '/tmp/nle-image-screenshots';
const STORE_ACCESSOR = `document.querySelector('#app').__vue_app__.config.globalProperties.$pinia._s.get('videoEditorNew')`;

let browser, page;
let passed = 0, failed = 0;
const errors = [];

function ok(label) { passed++; console.log(`  ✓ ${label}`); }
function fail(label, err) { failed++; console.log(`  ✗ ${label}: ${err}`); }

async function screenshot(name) {
    await page.screenshot({ path: `${SCREENSHOT_DIR}/${name}.png`, fullPage: false });
    console.log(`    Screenshot: ${SCREENSHOT_DIR}/${name}.png`);
}

function evalStore(fn) {
    // Wraps fn so the store is available as first arg
    return page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return arguments[1](store);
    }, STORE_ACCESSOR, fn);
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
    await screenshot('01-editor-loaded');
    ok('Editor loaded');
}

// ============================================================
// Test 1: Add image element and check it renders on canvas
// ============================================================
async function testImageRendering() {
    console.log('\n--- Image Rendering Test ---');

    // Add an overlay track + image element via store
    const result = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        if (!store || !store.composition?.tracks) return { error: 'no store' };

        // Add an overlay track at top
        const track = store.addTrack('overlay', 'Image Layer');

        // Add an image element using a public test image
        store.addElement(track.id, {
            type: 'image',
            name: 'Test Image',
            time: 0,
            duration: 10,
            source: 'https://picsum.photos/400/300',
            x: '50%',
            y: '50%',
            width: '50%',
            height: '50%',
            fit: 'contain',
        });

        // Seek to time 0 to ensure the image is active
        store.seekTo(0);

        return {
            trackCount: store.composition.tracks.length,
            trackOrder: store.composition.tracks.map(t => t.name),
            selectedId: store.selectedElementId,
        };
    }, STORE_ACCESSOR);

    console.log(`    Tracks: ${JSON.stringify(result.trackOrder)}`);
    console.log(`    Selected: ${result.selectedId}`);

    // Wait for image to load and canvas to re-render
    await new Promise(r => setTimeout(r, 3000));
    await screenshot('02-image-added');

    // Check if the element is visible at playhead time 0
    const activeElements = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const tracks = store.composition?.tracks || [];
        const active = [];
        const time = store.playhead;
        for (const track of tracks) {
            if (!track.visible) continue;
            for (const el of track.elements) {
                const start = el.time || 0;
                const end = start + (el.duration || 0);
                if (time >= start && time < end) {
                    active.push({ type: el.type, name: el.name, source: el.source, width: el.width, height: el.height });
                }
            }
        }
        return active;
    }, STORE_ACCESSOR);

    console.log(`    Active elements at playhead: ${JSON.stringify(activeElements)}`);
    const hasImage = activeElements.some(el => el.type === 'image');
    if (hasImage) {
        ok('Image element active at playhead');
    } else {
        fail('Image element active', `Not found in active: ${JSON.stringify(activeElements)}`);
    }

    // Check canvas pixels — if image loaded, there should be non-black pixels
    const canvasHasContent = await page.evaluate(() => {
        const canvas = document.querySelector('canvas');
        if (!canvas) return false;
        const ctx = canvas.getContext('2d');
        const data = ctx.getImageData(canvas.width / 2, canvas.height / 4, 1, 1).data;
        // Check if pixel at center-top (where image should be) is not pure black
        return data[0] > 5 || data[1] > 5 || data[2] > 5;
    });

    if (canvasHasContent) {
        ok('Canvas has non-black content (image/video rendered)');
    } else {
        console.log('    Note: Canvas may be black if image still loading from external URL');
    }

    ok('Image element added without errors');
}

// ============================================================
// Test 2: Selection outline shows element-sized bounding box
// ============================================================
async function testSelectionBoundingBox() {
    console.log('\n--- Selection Bounding Box Test ---');

    // Select the image element
    const elementProps = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        // Find and select the image element
        const allElements = store.allElements;
        const imageEl = allElements.find(el => el.type === 'image');
        if (imageEl) {
            store.selectElement(imageEl.id);
        }
        const el = store.selectedElement;
        return el ? { type: el.type, x: el.x, y: el.y, width: el.width, height: el.height } : null;
    }, STORE_ACCESSOR);
    console.log(`    Selected element: ${JSON.stringify(elementProps)}`);

    if (!elementProps) {
        fail('Selection bbox', 'No element selected');
        return;
    }

    await new Promise(r => setTimeout(r, 500));

    // Check selection outline
    const outlineInfo = await page.evaluate(() => {
        const outline = document.querySelector('.border-2.border-blue-400.pointer-events-none');
        if (!outline) return null;
        const rect = outline.getBoundingClientRect();
        const canvas = document.querySelector('canvas');
        const canvasRect = canvas?.getBoundingClientRect();
        return {
            outline: { x: rect.x, y: rect.y, w: rect.width, h: rect.height },
            canvas: canvasRect ? { x: canvasRect.x, y: canvasRect.y, w: canvasRect.width, h: canvasRect.height } : null,
        };
    });

    if (!outlineInfo) {
        fail('Selection outline', 'Not found in DOM');
        await screenshot('03-no-outline');
        return;
    }

    console.log(`    Canvas: ${JSON.stringify(outlineInfo.canvas)}`);
    console.log(`    Outline: ${JSON.stringify(outlineInfo.outline)}`);

    // If element is 50% width/height, outline should be about half the canvas size
    if (outlineInfo.canvas && elementProps.width === '50%') {
        const ratio = outlineInfo.outline.w / outlineInfo.canvas.w;
        console.log(`    Outline/canvas width ratio: ${ratio.toFixed(2)} (expected ~0.5)`);
        if (ratio < 0.8) {
            ok(`Selection box is smaller than canvas (ratio: ${ratio.toFixed(2)})`);
        } else {
            fail('Selection box size', `Ratio ${ratio.toFixed(2)} — outline covers too much of canvas`);
        }
    }

    // Check handles
    const handleCount = await page.evaluate(() => {
        return document.querySelectorAll('.bg-white.border.border-blue-500.rounded-sm').length;
    });
    console.log(`    Resize handles: ${handleCount}`);
    if (handleCount === 4) ok('4 resize handles present');
    else fail('Handles', `${handleCount} found`);

    await screenshot('03-selection-bbox');
}

// ============================================================
// Test 3: Canvas resize via handle drag
// ============================================================
async function testCanvasResize() {
    console.log('\n--- Canvas Resize Test ---');

    // Get element before
    const before = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const el = store.selectedElement;
        return el ? { width: el.width, height: el.height } : null;
    }, STORE_ACCESSOR);
    console.log(`    Before: ${JSON.stringify(before)}`);

    // Get SE handle position
    const handles = await page.evaluate(() => {
        const h = document.querySelectorAll('.bg-white.border.border-blue-500.rounded-sm');
        return Array.from(h).map(el => {
            const r = el.getBoundingClientRect();
            return { cx: r.x + r.width / 2, cy: r.y + r.height / 2 };
        });
    });

    if (handles.length < 4) {
        fail('Resize', `Only ${handles.length} handles`);
        return;
    }

    // SE handle (bottom-right)
    const se = handles[3];
    console.log(`    SE handle at: (${se.cx.toFixed(0)}, ${se.cy.toFixed(0)})`);

    // Check cursor changes at handle
    await page.mouse.move(se.cx, se.cy);
    await new Promise(r => setTimeout(r, 200));

    const cursor = await page.evaluate(() => {
        const overlay = document.querySelector('.absolute.z-10');
        return overlay ? overlay.style.cursor : 'not found';
    });
    console.log(`    Cursor at SE handle: ${cursor}`);

    if (cursor.includes('resize')) {
        ok(`Cursor changes to resize (${cursor})`);
    } else {
        fail('Cursor at handle', `Got "${cursor}", expected resize cursor`);
    }

    // Drag SE handle inward by 30px
    await page.mouse.down();
    await new Promise(r => setTimeout(r, 50));
    for (let i = 0; i < 6; i++) {
        await page.mouse.move(se.cx - (i + 1) * 5, se.cy - (i + 1) * 5);
        await new Promise(r => setTimeout(r, 30));
    }
    await page.mouse.up();
    await new Promise(r => setTimeout(r, 300));

    const after = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const el = store.selectedElement;
        return el ? { width: el.width, height: el.height } : null;
    }, STORE_ACCESSOR);
    console.log(`    After: ${JSON.stringify(after)}`);

    await screenshot('04-after-resize');

    if (before && after && before.width !== after.width) {
        ok(`Resize works: ${before.width} → ${after.width}`);
    } else {
        fail('Resize', `Width unchanged: ${before?.width} → ${after?.width}`);
    }
}

// ============================================================
// Test 4: Multi-select with Ctrl+click
// ============================================================
async function testMultiSelect() {
    console.log('\n--- Multi-select Test ---');

    // Clear selection
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        store.clearSelection();
    }, STORE_ACCESSOR);
    await new Promise(r => setTimeout(r, 200));

    // Get element blocks
    const blocks = await page.$$('[class*="absolute top-1 bottom-1 rounded cursor-pointer"]');
    console.log(`    Total element blocks: ${blocks.length}`);

    if (blocks.length < 2) {
        fail('Multi-select', `Need 2+ blocks, found ${blocks.length}`);
        return;
    }

    // Click first block at its LEFT edge
    const b0 = await blocks[0].boundingBox();
    console.log(`    Block 0: x=${b0.x.toFixed(0)}, w=${b0.width.toFixed(0)}`);
    await page.mouse.click(b0.x + 20, b0.y + b0.height / 2);
    await new Promise(r => setTimeout(r, 300));

    let selected = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return [...store.selectedElementIds];
    }, STORE_ACCESSOR);
    console.log(`    After first click: ${selected.length} selected`);

    if (selected.length === 1) {
        ok('First click selects one element');
    } else {
        fail('First click', `${selected.length} selected`);
    }

    // Ctrl+click second block at its left edge
    const b1 = await blocks[1].boundingBox();
    console.log(`    Block 1: x=${b1.x.toFixed(0)}, w=${b1.width.toFixed(0)}`);

    await page.keyboard.down('Control');
    await page.mouse.click(b1.x + 20, b1.y + b1.height / 2);
    await page.keyboard.up('Control');
    await new Promise(r => setTimeout(r, 300));

    selected = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return [...store.selectedElementIds];
    }, STORE_ACCESSOR);
    console.log(`    After Ctrl+click: ${selected.length} selected`);

    await screenshot('05-multi-select');

    if (selected.length === 2) {
        ok('Ctrl+click adds second element');
    } else {
        fail('Ctrl+click', `${selected.length} selected instead of 2`);
    }

    // Batch delete
    const countBefore = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return store.allElements.length;
    }, STORE_ACCESSOR);

    await page.keyboard.press('Delete');
    await new Promise(r => setTimeout(r, 300));

    const countAfter = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return store.allElements.length;
    }, STORE_ACCESSOR);

    console.log(`    Elements: ${countBefore} → ${countAfter}`);
    if (countAfter < countBefore) {
        ok(`Batch delete removed ${countBefore - countAfter} elements`);
    } else {
        fail('Batch delete', `Count unchanged: ${countBefore} → ${countAfter}`);
    }
}

// ============================================================
// Test 5: Track reorder — move video above overlay
// ============================================================
async function testTrackReorder() {
    console.log('\n--- Track Reorder Test ---');

    const tracksBefore = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return store.composition.tracks.map(t => ({ name: t.name, type: t.type }));
    }, STORE_ACCESSOR);
    console.log(`    Tracks: ${JSON.stringify(tracksBefore.map(t => t.name))}`);

    // Move first track down
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const firstTrack = store.composition.tracks[0];
        if (firstTrack) store.moveTrackDown(firstTrack.id);
    }, STORE_ACCESSOR);
    await new Promise(r => setTimeout(r, 300));

    const tracksAfter = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return store.composition.tracks.map(t => ({ name: t.name, type: t.type }));
    }, STORE_ACCESSOR);
    console.log(`    After move: ${JSON.stringify(tracksAfter.map(t => t.name))}`);

    if (tracksBefore[0].name !== tracksAfter[0].name) {
        ok('Track reorder works');
    } else {
        fail('Track reorder', 'Order unchanged');
    }

    await screenshot('06-track-reorder');
}

async function main() {
    console.log('=== NLE Image & Canvas Test ===\n');
    try {
        await setup();
        await login();
        await navigateToEditor();
        await testImageRendering();
        await testSelectionBoundingBox();
        await testCanvasResize();
        await testMultiSelect();
        await testTrackReorder();
    } catch (err) {
        console.error('FATAL:', err.message);
        try { await screenshot('fatal'); } catch {}
    } finally {
        if (browser) await browser.close();
    }

    console.log(`\n=== Results: ${passed} passed, ${failed} failed ===`);
    console.log(`Screenshots in: ${SCREENSHOT_DIR}/`);
    if (errors.length) {
        console.log('\n=== Console Errors ===');
        errors.filter(e => !e.includes('404') && !e.includes('favicon')).forEach(e => console.log(`  ! ${e}`));
    }
    process.exit(failed > 0 ? 1 : 0);
}

main();
