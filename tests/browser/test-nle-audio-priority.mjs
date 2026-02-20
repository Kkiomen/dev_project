#!/usr/bin/env node
/**
 * Browser test: Audio priority — audio track controls sound, video track is muted.
 *
 * Tests the REAL updateAudioGains function via the playback engine's debug interface.
 * Creates a mock composition, wires up the Web Audio API graph, then verifies
 * actual gainNode.gain.value under different scenarios.
 *
 * Usage: node tests/browser/test-nle-audio-priority.mjs [projectPublicId]
 */

import puppeteer from 'puppeteer-core';
import fs from 'fs';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8000';
const CHROME = process.platform === 'win32'
    ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
    : '/usr/bin/google-chrome-stable';
const SCREENSHOT_DIR = '/tmp/nle-audio-priority-screenshots';
const PROJECT_ID = process.argv[2] || null;

let browser, page;
let passed = 0, failed = 0;
const results = [];

function log(msg) { console.log(`  ${msg}`); }
function pass(name) { passed++; results.push({ name, status: 'PASS' }); console.log(`  ✅ ${name}`); }
function fail(name, error) { failed++; results.push({ name, status: 'FAIL', error }); console.log(`  ❌ ${name}: ${error}`); }

async function screenshot(name) {
    if (!fs.existsSync(SCREENSHOT_DIR)) fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    const path = `${SCREENSHOT_DIR}/${name}.png`;
    await page.screenshot({ path, fullPage: false });
    log(`Screenshot: ${path}`);
}

// ═══════════════════════════════════════════════════════════
// Setup & Auth
// ═══════════════════════════════════════════════════════════

async function setup() {
    browser = await puppeteer.launch({
        executablePath: CHROME,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu', '--autoplay-policy=no-user-gesture-required'],
    });
    page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });
    page.on('console', msg => {
        if (msg.type() === 'error' && !msg.text().includes('404')) {
            log(`[CONSOLE ERROR] ${msg.text()}`);
        }
    });
    page.on('pageerror', err => log(`[PAGE ERROR] ${err.message}`));
}

async function testLogin() {
    try {
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle0', timeout: 15000 });
        await page.type('input[name="email"]', 'test@example.com');
        await page.type('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 15000 });
        pass('Login');
        return true;
    } catch (e) {
        fail('Login', e.message);
        return false;
    }
}

async function navigateToEditor() {
    try {
        let pid = PROJECT_ID;

        if (!pid) {
            pid = await page.evaluate(async () => {
                const resp = await fetch('/api/v1/video-projects', { headers: { Accept: 'application/json' } });
                if (resp.status !== 200) return null;
                const data = await resp.json();
                const project = (data.data || []).find(p => p.video_path);
                return project ? project.public_id : null;
            });
        }

        const targetPid = pid || 'MOCK_TEST_PROJECT';
        log(`Navigating to NLE editor: /app/video/nle/${targetPid}`);
        await page.goto(`${BASE_URL}/app/video/nle/${targetPid}`, {
            waitUntil: 'networkidle0',
            timeout: 20000,
        });
        await new Promise(r => setTimeout(r, 2000));

        const mode = pid ? 'real' : 'mock';
        pass(`Navigate to editor (${mode} mode)`);
        return mode;
    } catch (e) {
        fail('Navigate to editor', e.message);
        return null;
    }
}

// ═══════════════════════════════════════════════════════════
// Mock composition + audio graph setup
// ═══════════════════════════════════════════════════════════

const MOCK_SOURCE = 'media://video-projects/1/test.mp4';

async function injectMockComposition() {
    const result = await page.evaluate((source) => {
        const app = document.querySelector('#app')?.__vue_app__;
        if (!app) return { error: 'No Vue app found' };
        const pinia = app.config.globalProperties.$pinia;
        const store = pinia._s.get('videoEditorNew');
        if (!store) return { error: 'Store videoEditorNew not found' };

        store.projectId = 'MOCK_TEST_PROJECT';
        store.composition = {
            width: 1920,
            height: 1080,
            fps: 30,
            background_color: '#000000',
            tracks: [
                {
                    id: 'track_video',
                    name: 'Main Video',
                    type: 'video',
                    muted: false,
                    locked: false,
                    visible: true,
                    elements: [{
                        id: 'el_video_1',
                        type: 'video',
                        name: 'Test Video',
                        time: 0.0,
                        duration: 10.0,
                        source: source,
                        trim_start: 0.0, trim_end: 0.0,
                        x: '50%', y: '50%', width: '100%', height: '100%',
                        rotation: 0, opacity: 1.0, fit: 'cover',
                        volume: 0,
                        fade_in: 0, fade_out: 0,
                        effects: [], transition: null, modification_key: null,
                    }],
                },
                {
                    id: 'track_overlay',
                    name: 'Overlay',
                    type: 'overlay',
                    muted: false, locked: false, visible: true,
                    elements: [],
                },
                {
                    id: 'track_audio',
                    name: 'Audio',
                    type: 'audio',
                    muted: false, locked: false, visible: true,
                    elements: [{
                        id: 'el_audio_1',
                        type: 'audio',
                        name: 'Test Audio',
                        time: 0.0,
                        duration: 10.0,
                        source: source,
                        trim_start: 0.0, trim_end: 0.0,
                        x: '50%', y: '50%', width: '100%', height: '100%',
                        rotation: 0, opacity: 1.0, fit: 'cover',
                        volume: 1.0,
                        fade_in: 0, fade_out: 0,
                        effects: [], transition: null, modification_key: null,
                    }],
                },
            ],
        };

        return { ok: true, trackCount: store.composition.tracks.length };
    }, MOCK_SOURCE);

    if (result.error) { fail('Inject mock composition', result.error); return false; }
    log(`Mock composition injected: ${result.trackCount} tracks`);
    pass('Inject mock composition');
    return true;
}

/**
 * Set up Web Audio graph via the REAL playback engine.
 * Creates a dummy HTMLVideoElement (with silent audio) and connects it
 * through ensureAudioContext + connectAudio — exactly as the player does.
 */
async function setupAudioGraph() {
    const result = await page.evaluate((source) => {
        const dbg = window.__nlePlaybackDebug;
        if (!dbg) return { error: '__nlePlaybackDebug not available (DEV mode required)' };

        // Create a video element via the real engine
        const video = dbg.getOrCreateVideoElement(source);

        // Initialize audio context (normally triggered by user gesture)
        dbg.ensureAudioContext();

        // Connect this video element to audio graph (creates MediaElementSource + GainNode)
        dbg.connectAudio(source);

        const info = dbg.getAudioDebugInfo();
        return {
            ok: true,
            audioContextState: info.audioContextState,
            audioNodeCount: info.audioNodeCount,
            videoElementCount: info.videoElementCount,
            gains: info.gains,
        };
    }, MOCK_SOURCE);

    if (result.error) { fail('Setup audio graph', result.error); return false; }
    log(`Audio graph: ctx=${result.audioContextState}, nodes=${result.audioNodeCount}, videos=${result.videoElementCount}`);
    pass('Setup real audio graph');
    return true;
}

// ═══════════════════════════════════════════════════════════
// Helper: call real updateAudioGains and read real gain values
// ═══════════════════════════════════════════════════════════

async function callRealUpdateAndGetGain(testTime, mutations = {}) {
    return page.evaluate(({ testTime, mutations, source }) => {
        const dbg = window.__nlePlaybackDebug;
        if (!dbg) return { error: 'No debug interface' };

        const app = document.querySelector('#app').__vue_app__;
        const pinia = app.config.globalProperties.$pinia;
        const store = pinia._s.get('videoEditorNew');

        // Apply mutations
        const originals = {};
        for (const [key, value] of Object.entries(mutations)) {
            for (const t of store.composition.tracks) {
                if (key === 'videoTime') {
                    for (const el of t.elements) {
                        if (el.type === 'video') { originals.videoTime = el.time; el.time = value; }
                    }
                } else if (key === 'audioTime') {
                    for (const el of t.elements) {
                        if (el.type === 'audio') { originals.audioTime = el.time; el.time = value; }
                    }
                } else if (key === 'audioTrackMuted') {
                    if (t.type === 'audio') { originals.audioTrackMuted = t.muted; t.muted = value; }
                } else if (key === 'videoVolume') {
                    for (const el of t.elements) {
                        if (el.type === 'video') { originals.videoVolume = el.volume; el.volume = value; }
                    }
                }
            }
        }

        // Call the REAL updateAudioGains
        dbg.updateAudioGains(testTime);

        // Read REAL gain values from Web Audio API
        const info = dbg.getAudioDebugInfo();
        const gainValue = info.gains[source]?.gain ?? null;

        // Restore originals
        for (const [key, value] of Object.entries(originals)) {
            for (const t of store.composition.tracks) {
                if (key === 'videoTime') {
                    for (const el of t.elements) if (el.type === 'video') el.time = value;
                } else if (key === 'audioTime') {
                    for (const el of t.elements) if (el.type === 'audio') el.time = value;
                } else if (key === 'audioTrackMuted') {
                    if (t.type === 'audio') t.muted = value;
                } else if (key === 'videoVolume') {
                    for (const el of t.elements) if (el.type === 'video') el.volume = value;
                }
            }
        }

        // Restore gain to 0 after test
        dbg.updateAudioGains(testTime);

        return { gainValue, allGains: info.gains };
    }, { testTime, mutations, source: MOCK_SOURCE });
}

// ═══════════════════════════════════════════════════════════
// Tests
// ═══════════════════════════════════════════════════════════

async function testCompositionVolumes() {
    const data = await page.evaluate(() => {
        const app = document.querySelector('#app')?.__vue_app__;
        const pinia = app.config.globalProperties.$pinia;
        const store = pinia._s.get('videoEditorNew');
        if (!store?.composition?.tracks) return null;

        let videoVol = null, audioVol = null, videoSource = null, audioSource = null;
        for (const track of store.composition.tracks) {
            for (const el of track.elements) {
                if (el.type === 'video' && el.source && videoVol === null) {
                    videoVol = 'volume' in el ? el.volume : '__missing__';
                    videoSource = el.source;
                }
                if (el.type === 'audio' && el.source && audioVol === null) {
                    audioVol = 'volume' in el ? el.volume : '__missing__';
                    audioSource = el.source;
                }
            }
        }
        return { videoVol, audioVol, videoSource, audioSource };
    });

    if (!data || data.videoVol === null) { fail('Composition volumes', 'No elements'); return; }

    log(`Video volume=${data.videoVol}, Audio volume=${data.audioVol}`);

    if (data.videoVol === 0) pass('Video element has volume=0');
    else fail('Video element has volume=0', `Got ${data.videoVol}`);

    if (data.audioVol === 1) pass('Audio element has volume=1');
    else fail('Audio element has volume=1', `Got ${data.audioVol}`);

    if (data.videoSource === data.audioSource) pass('Video and audio share same source');
    else fail('Video and audio share same source', 'Sources differ');
}

async function testRealGainBothActive() {
    const r = await callRealUpdateAndGetGain(5.0);
    if (r.error) { fail('Real gain — both active', r.error); return; }

    log(`Both active at t=5.0 → real gainNode.gain.value = ${r.gainValue}`);

    // Audio element volume=1, video volume=0. Audio element should be chosen → gain=1
    if (r.gainValue === 1) {
        pass('Real gain=1 when both active (audio element preferred)');
    } else {
        fail('Real gain=1 when both active', `Got ${r.gainValue}`);
    }
}

async function testRealGainAudioShifted() {
    // Shift audio to t=5, test at t=2 → only video active, but dedicated audio exists → gain=0
    const r = await callRealUpdateAndGetGain(2.0, { audioTime: 5.0 });
    if (r.error) { fail('Real gain — audio shifted', r.error); return; }

    log(`Audio shifted +5s, t=2.0 → real gainNode.gain.value = ${r.gainValue}`);

    if (r.gainValue === 0) {
        pass('Real gain=0 when only video active (no audio leak)');
    } else {
        fail('Real gain=0 when only video active (no audio leak)', `Got ${r.gainValue}`);
    }
}

async function testRealGainVideoShifted() {
    // Shift video to t=5, test at t=2 → only audio active → gain=1
    const r = await callRealUpdateAndGetGain(2.0, { videoTime: 5.0 });
    if (r.error) { fail('Real gain — video shifted', r.error); return; }

    log(`Video shifted +5s, t=2.0 → real gainNode.gain.value = ${r.gainValue}`);

    if (r.gainValue === 1) {
        pass('Real gain=1 when only audio active');
    } else {
        fail('Real gain=1 when only audio active', `Got ${r.gainValue}`);
    }
}

async function testRealGainAudioTrackMuted() {
    // Mute audio track → gain=0
    const r = await callRealUpdateAndGetGain(5.0, { audioTrackMuted: true });
    if (r.error) { fail('Real gain — audio muted', r.error); return; }

    log(`Audio track muted, t=5.0 → real gainNode.gain.value = ${r.gainValue}`);

    if (r.gainValue === 0) {
        pass('Real gain=0 when audio track muted');
    } else {
        fail('Real gain=0 when audio track muted', `Got ${r.gainValue}`);
    }
}

async function testRealGainVideoVolumeIgnored() {
    // Set video volume=1 → audio still preferred → gain=1 from audio
    const r = await callRealUpdateAndGetGain(5.0, { videoVolume: 1.0 });
    if (r.error) { fail('Real gain — video vol ignored', r.error); return; }

    log(`Video volume=1, t=5.0 → real gainNode.gain.value = ${r.gainValue}`);

    if (r.gainValue === 1) {
        pass('Real gain=1 (video volume ignored, audio element preferred)');
    } else {
        fail('Real gain=1 (video volume ignored)', `Got ${r.gainValue}`);
    }
}

async function testRealGainSmallOffset() {
    // Realistic scenario: video shifted 0.3s forward (user's original complaint)
    // At t=0.1: only audio active → gain=1
    // At t=0.5: both active → gain=1 (from audio)
    const r1 = await callRealUpdateAndGetGain(0.1, { videoTime: 0.3 });
    const r2 = await callRealUpdateAndGetGain(0.5, { videoTime: 0.3 });

    if (r1.error || r2.error) { fail('Real gain — small offset', r1.error || r2.error); return; }

    log(`Video +0.3s: t=0.1 → gain=${r1.gainValue}, t=0.5 → gain=${r2.gainValue}`);

    if (r1.gainValue === 1 && r2.gainValue === 1) {
        pass('Small offset: audio plays consistently at both times');
    } else {
        fail('Small offset: audio plays consistently', `t=0.1: ${r1.gainValue}, t=0.5: ${r2.gainValue}`);
    }
}

// ═══════════════════════════════════════════════════════════
// Main
// ═══════════════════════════════════════════════════════════

async function main() {
    console.log('\n🎬 NLE Audio Priority Test (Real Player Engine)\n');

    await setup();

    const loggedIn = await testLogin();
    if (!loggedIn) { await browser.close(); process.exit(1); }

    const mode = await navigateToEditor();
    if (!mode) { await browser.close(); process.exit(1); }

    if (mode === 'mock') {
        const injected = await injectMockComposition();
        if (!injected) { await browser.close(); process.exit(1); }
    }

    const graphReady = await setupAudioGraph();
    if (!graphReady) { await browser.close(); process.exit(1); }

    await screenshot('01-setup-done');

    console.log('\n  --- Composition Structure ---');
    await testCompositionVolumes();

    console.log('\n  --- Real Player Gain Tests ---');
    await testRealGainBothActive();
    await testRealGainAudioShifted();
    await testRealGainVideoShifted();
    await testRealGainAudioTrackMuted();
    await testRealGainVideoVolumeIgnored();
    await testRealGainSmallOffset();

    await screenshot('02-tests-done');

    console.log('\n═══════════════════════════════════════');
    console.log(`Results: ${passed} passed, ${failed} failed`);
    console.log('═══════════════════════════════════════');
    for (const r of results) {
        console.log(`  ${r.status === 'PASS' ? '✅' : '❌'} ${r.name}${r.error ? ': ' + r.error : ''}`);
    }
    console.log('');

    await browser.close();
    process.exit(failed > 0 ? 1 : 0);
}

main();
