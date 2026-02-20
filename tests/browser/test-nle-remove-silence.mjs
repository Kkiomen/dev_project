#!/usr/bin/env node
/**
 * Browser test: NLE Remove Silence feature
 *
 * Tests the applySilenceRemoval store action by injecting compositions
 * and verifying that elements are split correctly based on speech regions.
 *
 * Scenarios:
 *   1. Basic silence removal — video+audio split at speech boundaries
 *   2. Trim_start offset — element starts at non-zero media position
 *   3. No silence — all speech, composition unchanged
 *   4. Multiple sources — only matching source elements affected
 *   5. Gap closing — segments shifted to close timeline gaps
 *   6. Audio-video sync — paired elements get identical timing
 *
 * Usage: node tests/browser/test-nle-remove-silence.mjs [projectPublicId]
 */

import puppeteer from 'puppeteer-core';
import fs from 'fs';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8000';
const CHROME = process.platform === 'win32'
    ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
    : '/usr/bin/google-chrome-stable';
const SCREENSHOT_DIR = '/tmp/nle-remove-silence-screenshots';

let browser, page;
let passed = 0, failed = 0;
const results = [];

function log(msg) { console.log(`  ${msg}`); }
function pass(name) { passed++; results.push({ name, status: 'PASS' }); console.log(`  \u2705 ${name}`); }
function fail(name, error) { failed++; results.push({ name, status: 'FAIL', error }); console.log(`  \u274c ${name}: ${error}`); }

async function screenshot(name) {
    if (!fs.existsSync(SCREENSHOT_DIR)) fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    const path = `${SCREENSHOT_DIR}/${name}.png`;
    await page.screenshot({ path, fullPage: false });
}

// ═══════════════════════════════════════════════════════════
// Setup
// ═══════════════════════════════════════════════════════════

async function setup() {
    browser = await puppeteer.launch({
        executablePath: CHROME,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu',
               '--autoplay-policy=no-user-gesture-required'],
    });
    page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });
    page.on('pageerror', err => log(`[PAGE ERROR] ${err.message}`));
}

async function login() {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle0', timeout: 15000 });
    await page.type('input[name="email"]', 'test@example.com');
    await page.type('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 15000 });
    pass('Login');
}

async function navigateToEditor() {
    await page.goto(`${BASE_URL}/app/video/nle/MOCK_SILENCE_TEST`, {
        waitUntil: 'networkidle0',
        timeout: 20000,
    });
    await new Promise(r => setTimeout(r, 2000));

    const ready = await page.evaluate(() => {
        const app = document.querySelector('#app')?.__vue_app__;
        if (!app) return { error: 'No Vue app' };
        const pinia = app.config.globalProperties.$pinia;
        const store = pinia._s.get('videoEditorNew');
        if (!store) return { error: 'No store' };

        store.error = null;
        store.loading = false;
        store.projectId = 'MOCK_SILENCE_TEST';
        store.composition = {
            version: 1,
            width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks: [],
        };
        return { ok: true };
    });
    if (ready.error) { fail('Navigate to NLE editor', ready.error); return; }
    pass('Navigate to NLE editor');
}

// ═══════════════════════════════════════════════════════════
// Helper: inject composition and run applySilenceRemoval
// ═══════════════════════════════════════════════════════════

async function runSilenceRemoval(composition, speechRegions, padding = 0) {
    return page.evaluate(({ comp, regions, pad }) => {
        const app = document.querySelector('#app').__vue_app__;
        const store = app.config.globalProperties.$pinia._s.get('videoEditorNew');
        store.composition = JSON.parse(JSON.stringify(comp));
        store.isDirty = false;
        const removed = store.applySilenceRemoval(regions, pad);
        return {
            removed,
            tracks: JSON.parse(JSON.stringify(store.composition.tracks)),
            isDirty: store.isDirty,
        };
    }, { comp: composition, regions: speechRegions, pad: padding });
}

// ═══════════════════════════════════════════════════════════
// Tests
// ═══════════════════════════════════════════════════════════

async function testBasicSilenceRemoval() {
    const testName = 'Basic silence removal';
    try {
        // Video 10s, speech at 0-3s and 6-10s (silence 3-6s)
        const composition = {
            version: 1, width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks: [
                {
                    id: 'track_video', name: 'Video', type: 'video',
                    muted: false, locked: false, visible: true,
                    elements: [{
                        id: 'el_vid1', type: 'video', name: 'Main Video',
                        time: 0, duration: 10, trim_start: 0, source: 'video://main.mp4',
                        volume: 1.0, opacity: 1.0,
                    }],
                },
                {
                    id: 'track_audio', name: 'Audio', type: 'audio',
                    muted: false, locked: false, visible: true,
                    elements: [{
                        id: 'el_aud1', type: 'audio', name: 'Main Audio',
                        time: 0, duration: 10, trim_start: 0, source: 'video://main.mp4',
                        volume: 1.0,
                    }],
                },
            ],
        };

        const speechRegions = [
            { start: 0, end: 3 },
            { start: 6, end: 10 },
        ];

        const result = await runSilenceRemoval(composition, speechRegions);

        // Video track should have 2 elements
        const videoTrack = result.tracks.find(t => t.id === 'track_video');
        const audioTrack = result.tracks.find(t => t.id === 'track_audio');

        if (videoTrack.elements.length !== 2) {
            fail(testName, `Expected 2 video elements, got ${videoTrack.elements.length}`);
            return;
        }
        if (audioTrack.elements.length !== 2) {
            fail(testName, `Expected 2 audio elements, got ${audioTrack.elements.length}`);
            return;
        }

        // First segment: time=0, duration=3, trim_start=0
        const v1 = videoTrack.elements[0];
        if (Math.abs(v1.time - 0) > 0.01 || Math.abs(v1.duration - 3) > 0.01 || Math.abs(v1.trim_start - 0) > 0.01) {
            fail(testName, `Video segment 1 wrong: time=${v1.time}, dur=${v1.duration}, ts=${v1.trim_start}`);
            return;
        }

        // Second segment: time=3, duration=4, trim_start=6 (gap closed!)
        const v2 = videoTrack.elements[1];
        if (Math.abs(v2.time - 3) > 0.01 || Math.abs(v2.duration - 4) > 0.01 || Math.abs(v2.trim_start - 6) > 0.01) {
            fail(testName, `Video segment 2 wrong: time=${v2.time}, dur=${v2.duration}, ts=${v2.trim_start}`);
            return;
        }

        if (!result.isDirty) {
            fail(testName, 'Store should be marked dirty');
            return;
        }

        pass(testName);
    } catch (err) {
        fail(testName, err.message);
    }
}

async function testTrimStartOffset() {
    const testName = 'Trim_start offset';
    try {
        // Element shows media range [5, 15] (trim_start=5, duration=10)
        // Speech at [0,3], [7,12], [14,20] — overlapping with [5,15] are [7,12] and [14,15]
        const composition = {
            version: 1, width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks: [{
                id: 'track_v', name: 'V', type: 'video',
                muted: false, locked: false, visible: true,
                elements: [{
                    id: 'el_v', type: 'video', name: 'V',
                    time: 2, duration: 10, trim_start: 5, source: 'video://clip.mp4',
                    volume: 1.0, opacity: 1.0,
                }],
            }],
        };

        const speechRegions = [
            { start: 0, end: 3 },
            { start: 7, end: 12 },
            { start: 14, end: 20 },
        ];

        const result = await runSilenceRemoval(composition, speechRegions);
        const els = result.tracks[0].elements;

        if (els.length !== 2) {
            fail(testName, `Expected 2 elements, got ${els.length}`);
            return;
        }

        // First: media [7,12], timeline starts at time=2
        if (Math.abs(els[0].trim_start - 7) > 0.01 || Math.abs(els[0].duration - 5) > 0.01) {
            fail(testName, `Seg 1 wrong: ts=${els[0].trim_start}, dur=${els[0].duration}`);
            return;
        }
        if (Math.abs(els[0].time - 2) > 0.01) {
            fail(testName, `Seg 1 time wrong: ${els[0].time}, expected 2`);
            return;
        }

        // Second: media [14,15], timeline starts at 2+5=7
        if (Math.abs(els[1].trim_start - 14) > 0.01 || Math.abs(els[1].duration - 1) > 0.01) {
            fail(testName, `Seg 2 wrong: ts=${els[1].trim_start}, dur=${els[1].duration}`);
            return;
        }
        if (Math.abs(els[1].time - 7) > 0.01) {
            fail(testName, `Seg 2 time wrong: ${els[1].time}, expected 7`);
            return;
        }

        pass(testName);
    } catch (err) {
        fail(testName, err.message);
    }
}

async function testNoSilence() {
    const testName = 'No silence — all speech';
    try {
        const composition = {
            version: 1, width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks: [{
                id: 'track_v', name: 'V', type: 'video',
                muted: false, locked: false, visible: true,
                elements: [{
                    id: 'el_v', type: 'video', name: 'V',
                    time: 0, duration: 10, trim_start: 0, source: 'video://clip.mp4',
                    volume: 1.0, opacity: 1.0,
                }],
            }],
        };

        // Speech covers entire duration
        const speechRegions = [{ start: 0, end: 10 }];

        const result = await runSilenceRemoval(composition, speechRegions);
        const els = result.tracks[0].elements;

        // Should remain 1 element, unchanged
        if (els.length !== 1) {
            fail(testName, `Expected 1 element, got ${els.length}`);
            return;
        }
        if (result.isDirty) {
            fail(testName, 'Store should NOT be dirty when nothing changed');
            return;
        }

        pass(testName);
    } catch (err) {
        fail(testName, err.message);
    }
}

async function testMultipleSources() {
    const testName = 'Multiple sources — only matching affected';
    try {
        const composition = {
            version: 1, width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks: [
                {
                    id: 'track_v1', name: 'V1', type: 'video',
                    muted: false, locked: false, visible: true,
                    elements: [{
                        id: 'el_main', type: 'video', name: 'Main',
                        time: 0, duration: 10, trim_start: 0, source: 'video://main.mp4',
                        volume: 1.0, opacity: 1.0,
                    }],
                },
                {
                    id: 'track_overlay', name: 'Overlay', type: 'overlay',
                    muted: false, locked: false, visible: true,
                    elements: [{
                        id: 'el_overlay', type: 'image', name: 'Logo',
                        time: 0, duration: 10, trim_start: 0, source: 'media://logo.png',
                        volume: 1.0, opacity: 1.0,
                    }],
                },
            ],
        };

        // Speech regions only apply to video sources
        const speechRegions = [
            { start: 0, end: 3 },
            { start: 7, end: 10 },
        ];

        const result = await runSilenceRemoval(composition, speechRegions);

        // Main video should be split
        const mainTrack = result.tracks.find(t => t.id === 'track_v1');
        if (mainTrack.elements.length !== 2) {
            fail(testName, `Expected 2 main elements, got ${mainTrack.elements.length}`);
            return;
        }

        // Overlay should be unchanged (different source, not grouped with main)
        const overlayTrack = result.tracks.find(t => t.id === 'track_overlay');
        if (overlayTrack.elements.length !== 1) {
            fail(testName, `Expected 1 overlay element, got ${overlayTrack.elements.length}`);
            return;
        }
        if (overlayTrack.elements[0].id !== 'el_overlay') {
            fail(testName, 'Overlay element ID changed');
            return;
        }

        pass(testName);
    } catch (err) {
        fail(testName, err.message);
    }
}

async function testGapClosing() {
    const testName = 'Gap closing';
    try {
        // 20s video, speech at [2,5], [10,13], [17,20]
        // Silent gaps: [0,2], [5,10], [13,17]
        const composition = {
            version: 1, width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks: [{
                id: 'track_v', name: 'V', type: 'video',
                muted: false, locked: false, visible: true,
                elements: [{
                    id: 'el_v', type: 'video', name: 'V',
                    time: 0, duration: 20, trim_start: 0, source: 'video://long.mp4',
                    volume: 1.0, opacity: 1.0,
                }],
            }],
        };

        const speechRegions = [
            { start: 2, end: 5 },
            { start: 10, end: 13 },
            { start: 17, end: 20 },
        ];

        const result = await runSilenceRemoval(composition, speechRegions);
        const els = result.tracks[0].elements;

        if (els.length !== 3) {
            fail(testName, `Expected 3 segments, got ${els.length}`);
            return;
        }

        // Segments should be contiguous on timeline: [0,3], [3,6], [6,9]
        const expected = [
            { time: 0, duration: 3, trim_start: 2 },
            { time: 3, duration: 3, trim_start: 10 },
            { time: 6, duration: 3, trim_start: 17 },
        ];

        for (let i = 0; i < 3; i++) {
            const el = els[i];
            const exp = expected[i];
            if (Math.abs(el.time - exp.time) > 0.01 ||
                Math.abs(el.duration - exp.duration) > 0.01 ||
                Math.abs(el.trim_start - exp.trim_start) > 0.01) {
                fail(testName, `Segment ${i}: time=${el.time}(${exp.time}), dur=${el.duration}(${exp.duration}), ts=${el.trim_start}(${exp.trim_start})`);
                return;
            }
        }

        // Total timeline duration should be 9s (from 20s original)
        const totalDuration = els.reduce((max, el) => Math.max(max, el.time + el.duration), 0);
        if (Math.abs(totalDuration - 9) > 0.01) {
            fail(testName, `Total duration ${totalDuration}, expected 9`);
            return;
        }

        pass(testName);
    } catch (err) {
        fail(testName, err.message);
    }
}

async function testAudioVideoSync() {
    const testName = 'Audio-video sync';
    try {
        const composition = {
            version: 1, width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks: [
                {
                    id: 'track_video', name: 'Video', type: 'video',
                    muted: false, locked: false, visible: true,
                    elements: [{
                        id: 'el_vid', type: 'video', name: 'Video',
                        time: 0, duration: 10, trim_start: 0, source: 'video://sync.mp4',
                        volume: 1.0, opacity: 1.0,
                    }],
                },
                {
                    id: 'track_audio', name: 'Audio', type: 'audio',
                    muted: false, locked: false, visible: true,
                    elements: [{
                        id: 'el_aud', type: 'audio', name: 'Audio',
                        time: 0, duration: 10, trim_start: 0, source: 'video://sync.mp4',
                        volume: 1.0,
                    }],
                },
            ],
        };

        const speechRegions = [
            { start: 1, end: 4 },
            { start: 7, end: 9 },
        ];

        const result = await runSilenceRemoval(composition, speechRegions);

        const videoEls = result.tracks.find(t => t.id === 'track_video').elements;
        const audioEls = result.tracks.find(t => t.id === 'track_audio').elements;

        if (videoEls.length !== audioEls.length) {
            fail(testName, `Video has ${videoEls.length} els, audio has ${audioEls.length}`);
            return;
        }

        for (let i = 0; i < videoEls.length; i++) {
            const v = videoEls[i];
            const a = audioEls[i];
            if (Math.abs(v.time - a.time) > 0.01 ||
                Math.abs(v.duration - a.duration) > 0.01 ||
                Math.abs(v.trim_start - a.trim_start) > 0.01) {
                fail(testName, `Segment ${i} out of sync: V(t=${v.time},d=${v.duration},ts=${v.trim_start}) vs A(t=${a.time},d=${a.duration},ts=${a.trim_start})`);
                return;
            }
        }

        pass(testName);
    } catch (err) {
        fail(testName, err.message);
    }
}

async function testPaddingExpandsSpeech() {
    const testName = 'Padding expands speech regions';
    try {
        // 10s video, speech at [2,4] and [6,8]
        // With padding=0.5: padded to [1.5,4.5] and [5.5,8.5]
        const composition = {
            version: 1, width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks: [{
                id: 'track_v', name: 'V', type: 'video',
                muted: false, locked: false, visible: true,
                elements: [{
                    id: 'el_v', type: 'video', name: 'V',
                    time: 0, duration: 10, trim_start: 0, source: 'video://pad.mp4',
                    volume: 1.0, opacity: 1.0,
                }],
            }],
        };

        const speechRegions = [
            { start: 2, end: 4 },
            { start: 6, end: 8 },
        ];

        const result = await runSilenceRemoval(composition, speechRegions, 0.5);
        const els = result.tracks[0].elements;

        if (els.length !== 2) {
            fail(testName, `Expected 2 segments, got ${els.length}`);
            return;
        }

        // First segment: padded to [1.5, 4.5], duration=3
        if (Math.abs(els[0].trim_start - 1.5) > 0.01 || Math.abs(els[0].duration - 3) > 0.01) {
            fail(testName, `Seg 1: ts=${els[0].trim_start}(exp 1.5), dur=${els[0].duration}(exp 3)`);
            return;
        }

        // Second segment: padded to [5.5, 8.5], duration=3
        if (Math.abs(els[1].trim_start - 5.5) > 0.01 || Math.abs(els[1].duration - 3) > 0.01) {
            fail(testName, `Seg 2: ts=${els[1].trim_start}(exp 5.5), dur=${els[1].duration}(exp 3)`);
            return;
        }

        // Segments should be contiguous on timeline: [0,3] and [3,6]
        if (Math.abs(els[1].time - 3) > 0.01) {
            fail(testName, `Seg 2 time=${els[1].time}, expected 3`);
            return;
        }

        pass(testName);
    } catch (err) {
        fail(testName, err.message);
    }
}

// ═══════════════════════════════════════════════════════════
// Main
// ═══════════════════════════════════════════════════════════

async function main() {
    console.log('\n=== NLE Remove Silence Tests ===\n');

    await setup();

    try {
        await login();
        await navigateToEditor();
        await screenshot('01-editor-ready');

        await testBasicSilenceRemoval();
        await testTrimStartOffset();
        await testNoSilence();
        await testMultipleSources();
        await testGapClosing();
        await testAudioVideoSync();
        await testPaddingExpandsSpeech();

    } catch (err) {
        fail('Unexpected error', err.message);
    } finally {
        await screenshot('99-final');
        if (browser) await browser.close();
    }

    console.log(`\n=== Results: ${passed} passed, ${failed} failed ===\n`);
    process.exit(failed > 0 ? 1 : 0);
}

main();
