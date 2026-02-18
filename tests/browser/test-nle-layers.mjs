#!/usr/bin/env node
/**
 * NLE Test — Layer rendering verification
 *
 * Tests that image/text/shape elements on overlay tracks ACTUALLY render
 * on the canvas player, not just exist in the store.
 */
import puppeteer from 'puppeteer-core';

const BASE = 'http://localhost';
const CHROME = '/usr/bin/google-chrome-stable';
const SCREENSHOT_DIR = '/tmp/nle-layers-screenshots';
const STORE = `document.querySelector('#app').__vue_app__.config.globalProperties.$pinia._s.get('videoEditorNew')`;

let browser, page;
let passed = 0, failed = 0;
const errors = [];

function ok(label) { passed++; console.log(`  ✓ ${label}`); }
function fail(label, err) { failed++; console.log(`  ✗ ${label}: ${err}`); errors.push(`FAIL: ${label}: ${err}`); }

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
    await screenshot('00-editor-loaded');
    ok('Editor loaded');
}

/**
 * Helper: sample pixels from canvas at given positions.
 * positions = [{x, y}, ...] — relative to canvas (0-1 range)
 * Returns array of {r, g, b, a} values.
 */
async function sampleCanvasPixels(positions) {
    return page.evaluate(function(positions) {
        const canvas = document.querySelector('canvas');
        if (!canvas) return null;
        const ctx = canvas.getContext('2d');
        const w = canvas.width;
        const h = canvas.height;
        return positions.map(p => {
            const px = Math.floor(p.x * w);
            const py = Math.floor(p.y * h);
            const data = ctx.getImageData(px, py, 1, 1).data;
            return { r: data[0], g: data[1], b: data[2], a: data[3], px, py };
        });
    }, positions);
}

function isNotBlack(pixel) {
    return pixel.r > 10 || pixel.g > 10 || pixel.b > 10;
}

function isRedish(pixel) {
    return pixel.r > 150 && pixel.g < 80 && pixel.b < 80;
}

function isWhitish(pixel) {
    return pixel.r > 200 && pixel.g > 200 && pixel.b > 200;
}

// ============================================================
// Test 1: Baseline — video element renders on canvas
// ============================================================
async function testVideoRenders() {
    console.log('\n--- Test: Video renders on canvas ---');

    // Video should be visible at time 0
    const pixels = await sampleCanvasPixels([
        { x: 0.5, y: 0.5 },  // center
        { x: 0.25, y: 0.25 }, // quarter
    ]);
    console.log(`    Center pixel: rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b})`);
    console.log(`    Quarter pixel: rgb(${pixels[1].r}, ${pixels[1].g}, ${pixels[1].b})`);

    if (isNotBlack(pixels[0])) {
        ok('Video renders — center pixel is not black');
    } else {
        fail('Video renders', 'Center pixel is black');
    }
    await screenshot('01-video-baseline');
}

// ============================================================
// Test 2: Add a RED image element using data URI (loads instantly)
// ============================================================
async function testImageLayerRenders() {
    console.log('\n--- Test: Image layer renders above video ---');

    // Create a 100x100 red PNG as data URI
    const addResult = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        if (!store) return { error: 'no store' };

        // Create a 100x100 solid red canvas and convert to data URI
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = 100;
        tempCanvas.height = 100;
        const tCtx = tempCanvas.getContext('2d');
        tCtx.fillStyle = '#ff0000';
        tCtx.fillRect(0, 0, 100, 100);
        const dataUri = tempCanvas.toDataURL('image/png');

        // Add overlay track
        const track = store.addTrack('overlay', 'Red Image Layer');

        // Add image element at center, 30% size
        store.addElement(track.id, {
            type: 'image',
            name: 'Red Square',
            time: 0,
            duration: 30,
            source: dataUri,
            x: '50%',
            y: '50%',
            width: '30%',
            height: '30%',
            fit: 'cover',
        });

        store.seekTo(0);

        return {
            trackCount: store.composition.tracks.length,
            tracks: store.composition.tracks.map(t => t.name),
            elementCount: store.allElements.length,
        };
    }, STORE);

    console.log(`    Tracks: ${JSON.stringify(addResult.tracks)}`);
    console.log(`    Total elements: ${addResult.elementCount}`);

    // Wait for data URI image to load + re-render
    await new Promise(r => setTimeout(r, 1500));
    await screenshot('02-red-image-added');

    // Sample center pixel — should be RED (the image is at 50%, 50%)
    const pixels = await sampleCanvasPixels([
        { x: 0.5, y: 0.5 },   // center — should be RED (image)
        { x: 0.1, y: 0.1 },   // corner — should NOT be red (outside image)
    ]);
    console.log(`    Center pixel: rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b})`);
    console.log(`    Corner pixel: rgb(${pixels[1].r}, ${pixels[1].g}, ${pixels[1].b})`);

    if (isRedish(pixels[0])) {
        ok('RED image renders at center (image layer visible above video)');
    } else {
        fail('Image layer render', `Center is rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b}) — expected red`);
    }

    // Corner should NOT be red (image only covers 30% area)
    if (!isRedish(pixels[1])) {
        ok('Corner pixel is not red (image covers only 30% of canvas)');
    } else {
        fail('Image bounds', 'Corner pixel is also red — image might be covering entire canvas');
    }
}

// ============================================================
// Test 3: Text element renders on canvas
// ============================================================
async function testTextLayerRenders() {
    console.log('\n--- Test: Text layer renders ---');

    // Add a white text element at top
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        if (!store) return;

        // Find or create overlay track
        let textTrack = store.composition.tracks.find(t => t.name === 'Red Image Layer');
        if (!textTrack) textTrack = store.addTrack('overlay', 'Text Layer');

        store.addElement(textTrack.id, {
            type: 'text',
            name: 'Test Title',
            time: 0,
            duration: 30,
            text: 'HELLO WORLD',
            x: '50%',
            y: '15%',
            font_size: 72,
            font_weight: 'bold',
            color: '#ffffff',
        });

        store.seekTo(0);
    }, STORE);

    await new Promise(r => setTimeout(r, 500));
    await screenshot('03-text-added');

    // Sample at y=15% (where text should be)
    const pixels = await sampleCanvasPixels([
        { x: 0.5, y: 0.15 },  // where text should render
    ]);
    console.log(`    Text area pixel: rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b})`);

    if (isWhitish(pixels[0])) {
        ok('Text renders at expected position (white pixels found)');
    } else {
        // Text rendering depends on exact font metrics — log but don't hard fail
        console.log('    Note: Text pixel might miss exact glyph area. Checking nearby...');

        // Sample a wider area around y=15%
        const widerPixels = await sampleCanvasPixels([
            { x: 0.45, y: 0.14 }, { x: 0.5, y: 0.14 }, { x: 0.55, y: 0.14 },
            { x: 0.45, y: 0.15 }, { x: 0.55, y: 0.15 },
            { x: 0.45, y: 0.16 }, { x: 0.5, y: 0.16 }, { x: 0.55, y: 0.16 },
        ]);
        const anyWhite = widerPixels.some(p => isWhitish(p));
        if (anyWhite) {
            ok('Text renders (white pixels found in nearby area)');
        } else {
            fail('Text render', `No white pixels near text position — text may not be rendering`);
        }
    }
}

// ============================================================
// Test 4: Image track ABOVE video — z-order check
// ============================================================
async function testLayerZOrder() {
    console.log('\n--- Test: Layer z-order (image above video) ---');

    // The red image was added on an overlay track which should be above the video track.
    // At center (50%, 50%), we should see RED, not the video.
    const info = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return {
            tracks: store.composition.tracks.map((t, i) => ({
                index: i,
                name: t.name,
                type: t.type,
                visible: t.visible,
                elementCount: t.elements.length,
            })),
        };
    }, STORE);
    console.log(`    Track order (index 0 = top visual layer):`);
    info.tracks.forEach(t => console.log(`      [${t.index}] ${t.name} (${t.type}, ${t.elementCount} elements, visible=${t.visible})`));

    // Force a re-render at time 0
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        store.seekTo(0);
    }, STORE);
    await new Promise(r => setTimeout(r, 1000));

    const pixels = await sampleCanvasPixels([
        { x: 0.5, y: 0.5 },  // center — should be RED (overlay on top)
    ]);
    console.log(`    Center pixel: rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b})`);

    if (isRedish(pixels[0])) {
        ok('Z-order correct: image layer renders ON TOP of video');
    } else {
        fail('Z-order', `Center pixel is rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b}) — expected red from overlay`);
    }
    await screenshot('04-z-order');
}

// ============================================================
// Test 5: Toggle track visibility hides/shows layer
// ============================================================
async function testTrackVisibility() {
    console.log('\n--- Test: Track visibility toggle ---');

    // Hide the image track
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const imageTrack = store.composition.tracks.find(t => t.name === 'Red Image Layer');
        if (imageTrack) {
            imageTrack.visible = false;
            store.markDirty();
        }
    }, STORE);
    await new Promise(r => setTimeout(r, 500));

    // Force re-render
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        store.seekTo(0);
    }, STORE);
    await new Promise(r => setTimeout(r, 1000));
    await screenshot('05-image-hidden');

    const pixelsHidden = await sampleCanvasPixels([{ x: 0.5, y: 0.5 }]);
    console.log(`    Center after hide: rgb(${pixelsHidden[0].r}, ${pixelsHidden[0].g}, ${pixelsHidden[0].b})`);

    if (!isRedish(pixelsHidden[0])) {
        ok('Hiding track removes image from canvas');
    } else {
        fail('Track hide', 'Center is still red after hiding image track');
    }

    // Show it again
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const imageTrack = store.composition.tracks.find(t => t.name === 'Red Image Layer');
        if (imageTrack) {
            imageTrack.visible = true;
            store.markDirty();
        }
    }, STORE);
    await new Promise(r => setTimeout(r, 500));
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        store.seekTo(0);
    }, STORE);
    await new Promise(r => setTimeout(r, 1000));
    await screenshot('05b-image-shown');

    const pixelsShown = await sampleCanvasPixels([{ x: 0.5, y: 0.5 }]);
    console.log(`    Center after show: rgb(${pixelsShown[0].r}, ${pixelsShown[0].g}, ${pixelsShown[0].b})`);

    if (isRedish(pixelsShown[0])) {
        ok('Showing track brings image back');
    } else {
        fail('Track show', `Center is rgb(${pixelsShown[0].r}, ${pixelsShown[0].g}, ${pixelsShown[0].b}) — expected red`);
    }
}

// ============================================================
// Test 6: Seek to time where image is NOT active
// ============================================================
async function testTimeBasedVisibility() {
    console.log('\n--- Test: Time-based element visibility ---');

    // Add a short-duration green element at time 5-8
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const track = store.composition.tracks.find(t => t.name === 'Red Image Layer');
        if (!track) return;

        // Create green data URI
        const c = document.createElement('canvas');
        c.width = 50; c.height = 50;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#00ff00';
        ctx.fillRect(0, 0, 50, 50);

        store.addElement(track.id, {
            type: 'image',
            name: 'Green Square',
            time: 5,
            duration: 3,
            source: c.toDataURL(),
            x: '80%',
            y: '80%',
            width: '20%',
            height: '20%',
            fit: 'cover',
        });
    }, STORE);
    await new Promise(r => setTimeout(r, 500));

    // At time 0: red image visible, green NOT visible
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        store.seekTo(0);
    }, STORE);
    await new Promise(r => setTimeout(r, 1000));

    const atTime0 = await sampleCanvasPixels([
        { x: 0.8, y: 0.8 },  // green should NOT be here at time 0
    ]);
    console.log(`    At t=0, (80%,80%): rgb(${atTime0[0].r}, ${atTime0[0].g}, ${atTime0[0].b})`);
    if (atTime0[0].g < 200) {
        ok('At t=0: green element not visible (correct — starts at t=5)');
    } else {
        fail('Time visibility', 'Green visible at t=0 when it should start at t=5');
    }

    // At time 6: green should be visible
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        store.seekTo(6);
    }, STORE);
    await new Promise(r => setTimeout(r, 1000));
    await screenshot('06-green-at-t6');

    const atTime6 = await sampleCanvasPixels([
        { x: 0.8, y: 0.8 },  // green SHOULD be here at time 6
    ]);
    console.log(`    At t=6, (80%,80%): rgb(${atTime6[0].r}, ${atTime6[0].g}, ${atTime6[0].b})`);
    if (atTime6[0].g > 150) {
        ok('At t=6: green element visible (correct — within t=5-8 range)');
    } else {
        fail('Time visibility t=6', `Green not visible: rgb(${atTime6[0].r}, ${atTime6[0].g}, ${atTime6[0].b})`);
    }
}

// ============================================================
// Test 7: Selection box matches element (not entire canvas)
// ============================================================
async function testSelectionMatchesElement() {
    console.log('\n--- Test: Selection box matches element size ---');

    // Select the red image element
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        store.seekTo(0);
        const redEl = store.allElements.find(el => el.name === 'Red Square');
        if (redEl) store.selectElement(redEl.id);
    }, STORE);
    await new Promise(r => setTimeout(r, 500));

    const info = await page.evaluate(() => {
        const canvas = document.querySelector('canvas');
        const outline = document.querySelector('.border-2.border-blue-400.pointer-events-none');
        if (!canvas || !outline) return null;
        const cRect = canvas.getBoundingClientRect();
        const oRect = outline.getBoundingClientRect();
        return {
            canvas: { w: cRect.width, h: cRect.height },
            outline: { w: oRect.width, h: oRect.height },
            ratioW: oRect.width / cRect.width,
            ratioH: oRect.height / cRect.height,
        };
    });

    if (!info) {
        fail('Selection box', 'No outline found');
        return;
    }

    console.log(`    Canvas: ${info.canvas.w.toFixed(0)}x${info.canvas.h.toFixed(0)}`);
    console.log(`    Outline: ${info.outline.w.toFixed(0)}x${info.outline.h.toFixed(0)}`);
    console.log(`    Ratios: w=${info.ratioW.toFixed(2)}, h=${info.ratioH.toFixed(2)}`);

    // Element is 30% width/height, so outline should be ~30% of canvas
    if (info.ratioW < 0.5 && info.ratioH < 0.5) {
        ok(`Selection box is ~${(info.ratioW * 100).toFixed(0)}% of canvas (element is 30%)`);
    } else {
        fail('Selection box size', `Ratio w=${info.ratioW.toFixed(2)} h=${info.ratioH.toFixed(2)} — expected ~0.3`);
    }

    await screenshot('07-selection-box');
}

// ============================================================
// Test 8: Resize handle actually changes element size on canvas
// ============================================================
async function testResizeChangesPixels() {
    console.log('\n--- Test: Resize changes visible element size ---');

    // Element should still be selected (Red Square)
    // Sample the edge of the red area BEFORE resize
    const beforePixels = await sampleCanvasPixels([
        { x: 0.35, y: 0.5 },  // left edge of 30% element at center = 50-15 = 35%
    ]);
    const wasRed = isRedish(beforePixels[0]);
    console.log(`    Before resize (35%, 50%): rgb(${beforePixels[0].r}, ${beforePixels[0].g}, ${beforePixels[0].b}) — red=${wasRed}`);

    // Resize the element to be larger using store
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const el = store.selectedElement;
        if (el) {
            el.width = '60%';
            el.height = '60%';
            store.markDirty();
        }
    }, STORE);
    await new Promise(r => setTimeout(r, 500));
    // Force re-render
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        store.seekTo(0);
    }, STORE);
    await new Promise(r => setTimeout(r, 1000));
    await screenshot('08-after-resize-big');

    // Now sample at same position — should be red after making element 60%
    const afterPixels = await sampleCanvasPixels([
        { x: 0.35, y: 0.5 },  // was outside 30%, now inside 60%
    ]);
    const isNowRed = isRedish(afterPixels[0]);
    console.log(`    After resize (35%, 50%): rgb(${afterPixels[0].r}, ${afterPixels[0].g}, ${afterPixels[0].b}) — red=${isNowRed}`);

    if (isNowRed) {
        ok('Resize expanded: pixel at 35% is now red (was outside 30% element, now inside 60%)');
    } else {
        fail('Resize render', `Pixel at 35% is still not red after expanding to 60%`);
    }
}

async function main() {
    console.log('=== NLE Layer Rendering Test ===\n');
    try {
        await setup();
        await login();
        await navigateToEditor();
        await testVideoRenders();
        await testImageLayerRenders();
        await testTextLayerRenders();
        await testLayerZOrder();
        await testTrackVisibility();
        await testTimeBasedVisibility();
        await testSelectionMatchesElement();
        await testResizeChangesPixels();
    } catch (err) {
        console.error('FATAL:', err.message);
        try { await screenshot('fatal'); } catch {}
    } finally {
        if (browser) await browser.close();
    }

    console.log(`\n=== Results: ${passed} passed, ${failed} failed ===`);
    console.log(`Screenshots in: ${SCREENSHOT_DIR}/`);
    if (errors.length) {
        console.log('\n=== Errors ===');
        errors.filter(e => !e.includes('404') && !e.includes('favicon')).forEach(e => console.log(`  ! ${e}`));
    }
    process.exit(failed > 0 ? 1 : 0);
}

main();
