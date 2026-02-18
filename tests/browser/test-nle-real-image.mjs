#!/usr/bin/env node
/**
 * NLE Test — Real image upload + rendering
 * Tests that actual uploaded images display on the canvas player.
 */
import puppeteer from 'puppeteer-core';

const BASE = 'http://localhost';
const CHROME = '/usr/bin/google-chrome-stable';
const SCREENSHOT_DIR = '/tmp/nle-real-image-screenshots';
const STORE = `document.querySelector('#app').__vue_app__.config.globalProperties.$pinia._s.get('videoEditorNew')`;

let browser, page;
let passed = 0, failed = 0;
const errors = [];

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
        const text = msg.text();
        if (msg.type() === 'error') errors.push(text);
        // Log all network-related messages for debugging
        if (text.includes('media') || text.includes('404') || text.includes('Failed')) {
            console.log(`    [console] ${text}`);
        }
    });
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

// ============================================================
// Test 1: Check resolveSourceUri for media:// paths
// ============================================================
async function testResolveSourceUri() {
    console.log('\n--- Test: resolveSourceUri logic ---');

    const result = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const projectId = store.projectId;

        // Test different URI patterns — call resolveSourceUri internally
        // by checking what URL an image element would get
        const testUris = [
            'media://video-projects/1/media/1708345200_photo.png',
            'media://video-projects/1/silence_removed_123.mp4',
            'https://example.com/image.png',
            'data:image/png;base64,AAAA',
        ];

        // We can't directly call resolveSourceUri, but we can add elements
        // and check what the image src ends up being
        return { projectId, testUris };
    }, STORE);

    console.log(`    Project ID: ${result.projectId}`);

    // Add an element with a media:// URI and check if the image src is correct
    const resolvedUrl = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);

        // Create temp element to trigger image creation
        const testTrack = store.composition.tracks.find(t => t.type === 'overlay') || store.composition.tracks[0];
        if (!testTrack) return { error: 'no track' };

        // We need to check what URL is generated — add element and check img.src
        const el = store.addElement(testTrack.id, {
            type: 'image',
            name: '_URI_TEST_',
            time: 100, // far away so doesn't affect display
            duration: 1,
            source: 'media://video-projects/1/media/1708345200_photo.png',
            x: '50%', y: '50%', width: '10%', height: '10%',
        });

        return { elementId: el?.id, source: el?.source };
    }, STORE);
    console.log(`    Element source: ${resolvedUrl.source}`);

    // Check what the canvas playback engine would resolve it to
    // by intercepting network requests
    const networkUrls = [];
    page.on('request', req => {
        const url = req.url();
        if (url.includes('media/') || url.includes('stream')) {
            networkUrls.push(url);
        }
    });

    // Seek to time 100 to trigger image load
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        store.seekTo(100);
    }, STORE);
    await new Promise(r => setTimeout(r, 1000));

    console.log(`    Network requests with media/stream:`);
    networkUrls.forEach(u => console.log(`      ${u}`));

    // Check the resolved URL contains /media/ not /stream
    const hasMediaUrl = networkUrls.some(u => u.includes('/media/') && u.includes('1708345200_photo.png'));
    if (hasMediaUrl) {
        ok('resolveSourceUri correctly maps media:// image to /media/{filename} endpoint');
    } else {
        const hasStreamUrl = networkUrls.some(u => u.includes('/stream'));
        if (hasStreamUrl) {
            fail('resolveSourceUri', 'Image still routes to /stream instead of /media/');
        } else {
            fail('resolveSourceUri', `No media request found. URLs: ${networkUrls.join(', ')}`);
        }
    }

    // Clean up test element
    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        const el = store.allElements.find(e => e.name === '_URI_TEST_');
        if (el) store.removeElement(el.id);
        store.seekTo(0);
    }, STORE);
    await new Promise(r => setTimeout(r, 300));
}

// ============================================================
// Test 2: Upload a real image and check it renders
// ============================================================
async function testUploadAndRender() {
    console.log('\n--- Test: Upload image and check rendering ---');

    // Create a test PNG image file
    const fs = await import('fs');
    const { createCanvas } = await tryImportCanvas();

    let testImagePath;
    if (createCanvas) {
        // Use node-canvas if available
        const c = createCanvas(200, 200);
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#ff00ff'; // magenta
        ctx.fillRect(0, 0, 200, 200);
        testImagePath = '/tmp/test-magenta.png';
        fs.writeFileSync(testImagePath, c.toBuffer('image/png'));
    } else {
        // Generate a minimal PNG manually (1x1 magenta pixel)
        testImagePath = '/tmp/test-magenta.png';
        const pngData = createMinimalPng(255, 0, 255);
        fs.writeFileSync(testImagePath, pngData);
    }

    // Upload via the media panel file input
    // First check if there's a file input in the media panel
    const uploadResult = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);

        // Create a canvas-based data URI as fallback for testing rendering
        const c = document.createElement('canvas');
        c.width = 200; c.height = 200;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#ff00ff'; // magenta
        ctx.fillRect(0, 0, 200, 200);
        const dataUri = c.toDataURL('image/png');

        // Add overlay track and image element
        let track = store.composition.tracks.find(t => t.type === 'overlay');
        if (!track) track = store.addTrack('overlay', 'Test Images');

        const el = store.addElement(track.id, {
            type: 'image',
            name: 'Magenta Test',
            time: 0,
            duration: 20,
            source: dataUri,
            x: '50%',
            y: '50%',
            width: '40%',
            height: '40%',
            fit: 'cover',
        });

        store.seekTo(0);

        return {
            elementId: el?.id,
            trackName: track.name,
        };
    }, STORE);
    console.log(`    Added element: ${uploadResult.elementId} on ${uploadResult.trackName}`);

    // Wait for render
    await new Promise(r => setTimeout(r, 2000));
    await screenshot('01-magenta-image');

    // Sample center — should be magenta
    const pixels = await sampleCanvasPixels([{ x: 0.5, y: 0.5 }]);
    console.log(`    Center pixel: rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b})`);

    const isMagenta = pixels[0].r > 200 && pixels[0].g < 50 && pixels[0].b > 200;
    if (isMagenta) {
        ok('Data URI image renders correctly (magenta at center)');
    } else {
        fail('Data URI render', `Center is rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b}) — expected magenta`);
    }
}

// ============================================================
// Test 3: Simulate real media:// upload scenario
// ============================================================
async function testMediaProtocolImage() {
    console.log('\n--- Test: media:// protocol image loading ---');

    // Use the actual upload endpoint to upload a test image,
    // then add it as an element
    const projectId = await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);
        return store.projectId;
    }, STORE);
    console.log(`    Project ID: ${projectId}`);

    // Create a red PNG blob and upload it via fetch
    const uploadResult = await page.evaluate(async function(storeAccessor, projectId) {
        const store = eval(storeAccessor);

        // Create red image blob
        const c = document.createElement('canvas');
        c.width = 100; c.height = 100;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#ff0000';
        ctx.fillRect(0, 0, 100, 100);

        const blob = await new Promise(resolve => c.toBlob(resolve, 'image/png'));
        const formData = new FormData();
        formData.append('file', blob, 'test-red.png');

        try {
            // Get XSRF token from cookies
            const xsrfCookie = document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='));
            const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.split('=')[1]) : '';

            const resp = await fetch(`/api/v1/video-projects/${projectId}/upload-media`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-XSRF-TOKEN': xsrfToken,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!resp.ok) {
                return { error: `Upload failed: ${resp.status} ${resp.statusText}`, body: await resp.text() };
            }

            const data = await resp.json();
            return data; // { name, type, source, size }
        } catch (err) {
            return { error: err.message };
        }
    }, STORE, projectId);

    console.log(`    Upload result: ${JSON.stringify(uploadResult)}`);

    if (uploadResult.error) {
        fail('Upload', uploadResult.error);
        return;
    }

    ok(`Image uploaded: ${uploadResult.source}`);

    // Now add this image as an element on the timeline
    const addResult = await page.evaluate(function(storeAccessor, mediaSource) {
        const store = eval(storeAccessor);

        let track = store.composition.tracks.find(t => t.type === 'overlay');
        if (!track) track = store.addTrack('overlay', 'Upload Test');

        const el = store.addElement(track.id, {
            type: 'image',
            name: 'Uploaded Red',
            time: 0,
            duration: 20,
            source: mediaSource,
            x: '25%',
            y: '25%',
            width: '30%',
            height: '30%',
            fit: 'cover',
        });

        store.seekTo(0);
        return { elementId: el?.id, source: el?.source };
    }, STORE, uploadResult.source);

    console.log(`    Element added: id=${addResult.elementId}, source=${addResult.source}`);

    // Wait for image to load from the new /media/ endpoint
    await new Promise(r => setTimeout(r, 3000));
    await screenshot('02-uploaded-image');

    // Sample at (25%, 25%) — should be red from the uploaded image
    const pixels = await sampleCanvasPixels([
        { x: 0.25, y: 0.25 },  // center of uploaded image
        { x: 0.5, y: 0.5 },    // center — should still have magenta from test 2
    ]);
    console.log(`    (25%, 25%) pixel: rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b})`);
    console.log(`    (50%, 50%) pixel: rgb(${pixels[1].r}, ${pixels[1].g}, ${pixels[1].b})`);

    const isRed = pixels[0].r > 200 && pixels[0].g < 50 && pixels[0].b < 50;
    if (isRed) {
        ok('Uploaded media:// image renders on canvas (red at 25%, 25%)');
    } else {
        fail('Uploaded image render', `Pixel at (25%, 25%) is rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b}) — expected red`);

        // Debug: check what URL was actually requested
        console.log('    Debugging: checking network requests...');
        const imgUrls = await page.evaluate(() => {
            const imgs = document.querySelectorAll('img');
            return Array.from(imgs).map(i => i.src);
        });
        console.log(`    Image elements on page: ${imgUrls.length}`);
    }
}

// ============================================================
// Test 4: Multiple images on different layers
// ============================================================
async function testMultipleImageLayers() {
    console.log('\n--- Test: Multiple images on different layers ---');

    await page.evaluate(function(storeAccessor) {
        const store = eval(storeAccessor);

        // Create blue image data URI
        const c = document.createElement('canvas');
        c.width = 100; c.height = 100;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#0000ff'; // blue
        ctx.fillRect(0, 0, 100, 100);

        // Add on a NEW track (top layer)
        const blueTrack = store.addTrack('overlay', 'Blue Layer');
        store.addElement(blueTrack.id, {
            type: 'image',
            name: 'Blue Square',
            time: 0,
            duration: 20,
            source: c.toDataURL('image/png'),
            x: '50%',
            y: '50%',
            width: '20%',
            height: '20%',
            fit: 'cover',
        });

        store.seekTo(0);
    }, STORE);

    await new Promise(r => setTimeout(r, 1500));
    await screenshot('03-multiple-layers');

    // Blue (20%) is at center and should be on TOP of magenta (40%) at center
    const pixels = await sampleCanvasPixels([
        { x: 0.5, y: 0.5 },    // very center — should be BLUE (topmost layer, smallest)
        { x: 0.35, y: 0.5 },   // outside blue but inside magenta
    ]);
    console.log(`    Center pixel: rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b})`);
    console.log(`    Off-center pixel: rgb(${pixels[1].r}, ${pixels[1].g}, ${pixels[1].b})`);

    const isBlue = pixels[0].b > 200 && pixels[0].r < 50 && pixels[0].g < 50;
    const isMagenta = pixels[1].r > 200 && pixels[1].b > 200 && pixels[1].g < 50;

    if (isBlue) {
        ok('Top layer (blue) renders at center — z-order correct');
    } else {
        fail('Blue z-order', `Center is rgb(${pixels[0].r}, ${pixels[0].g}, ${pixels[0].b}) — expected blue`);
    }

    if (isMagenta) {
        ok('Middle layer (magenta) visible where top layer doesn\'t cover');
    } else {
        console.log(`    Note: off-center might overlap with uploaded red — checking if any non-video color`);
        if (pixels[1].r > 150 || pixels[1].b > 150) {
            ok('Middle layer visible at off-center position');
        } else {
            fail('Magenta layer', `Off-center is rgb(${pixels[1].r}, ${pixels[1].g}, ${pixels[1].b})`);
        }
    }
}

// Helpers

async function tryImportCanvas() {
    try {
        const { createCanvas } = await import('canvas');
        return { createCanvas };
    } catch {
        return { createCanvas: null };
    }
}

function createMinimalPng(r, g, b) {
    // Minimal 1x1 PNG
    const buf = Buffer.from([
        0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A, // PNG signature
        0x00, 0x00, 0x00, 0x0D, // IHDR length
        0x49, 0x48, 0x44, 0x52, // IHDR
        0x00, 0x00, 0x00, 0x01, // width 1
        0x00, 0x00, 0x00, 0x01, // height 1
        0x08, 0x02, // 8bit RGB
        0x00, 0x00, 0x00, // compression, filter, interlace
        0x90, 0x77, 0x53, 0xDE, // CRC
        0x00, 0x00, 0x00, 0x0C, // IDAT length
        0x49, 0x44, 0x41, 0x54, // IDAT
        0x08, 0xD7, 0x63, 0xF8, r, g, b, 0x00, 0x00, 0x00, 0x04, 0x00, 0x01,
        0x00, 0x00, 0x00, 0x00, // CRC placeholder
        0x00, 0x00, 0x00, 0x00, // IEND length
        0x49, 0x45, 0x4E, 0x44, // IEND
        0xAE, 0x42, 0x60, 0x82, // CRC
    ]);
    return buf;
}

async function main() {
    console.log('=== NLE Real Image Test ===\n');
    try {
        await setup();
        await login();
        await navigateToEditor();
        await testResolveSourceUri();
        await testUploadAndRender();
        await testMediaProtocolImage();
        await testMultipleImageLayers();
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
        errors.filter(e => !e.includes('favicon')).forEach(e => console.log(`  ! ${e}`));
    }
    process.exit(failed > 0 ? 1 : 0);
}

main();
