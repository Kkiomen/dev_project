#!/usr/bin/env node
/**
 * Browser test: Timeline & playback scenarios from an editor's perspective.
 *
 * Tests REAL gainNode.gain.value from Web Audio API via the playback engine.
 * Each scenario sets up a complete composition (multiple tracks, elements,
 * sources) and verifies gain at various timeline positions.
 *
 * Scenarios:
 *   1. Basic: video + audio, same source, aligned
 *   2. B-roll overlay on top of main video (overlay has no audio)
 *   3. Split clip — same source cut into two segments with gap
 *   4. Background music — independent audio source on audio track
 *   5. Voiceover + music on two separate audio tracks
 *   6. Fade in/out on audio element
 *   7. Muted video track — audio track still plays
 *   8. Two video sources, each with dedicated audio
 *   9. Audio gap — audio element shorter than video
 *  10. Crossfade — two audio elements overlap on same track
 *  11. Video przesunięte — audio starts before video (intro audio)
 *  12. Wielu elementów — złożony timeline z 3 źródłami
 *
 * Usage: node tests/browser/test-nle-timeline-scenarios.mjs [projectPublicId]
 */

import puppeteer from 'puppeteer-core';
import fs from 'fs';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8000';
const CHROME = process.platform === 'win32'
    ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
    : '/usr/bin/google-chrome-stable';
const SCREENSHOT_DIR = '/tmp/nle-timeline-scenarios-screenshots';

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
    await page.goto(`${BASE_URL}/app/video/nle/MOCK_SCENARIO_TEST`, {
        waitUntil: 'networkidle0',
        timeout: 20000,
    });
    await new Promise(r => setTimeout(r, 2000));

    // NleCanvas won't mount if store has error and no composition.
    // Inject a minimal composition to force the editor to render.
    const ready = await page.evaluate(() => {
        const app = document.querySelector('#app')?.__vue_app__;
        if (!app) return { error: 'No Vue app' };
        const pinia = app.config.globalProperties.$pinia;
        const store = pinia._s.get('videoEditorNew');
        if (!store) return { error: 'No store' };

        store.error = null;
        store.loading = false;
        store.projectId = 'MOCK_SCENARIO_TEST';
        store.composition = {
            width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks: [],
        };
        return { ok: true };
    });
    if (ready.error) { fail('Navigate to NLE editor', ready.error); return; }

    // Wait for NleCanvas to mount and expose debug interface
    await new Promise(r => setTimeout(r, 1500));

    const hasDebug = await page.evaluate(() => !!window.__nlePlaybackDebug);
    if (!hasDebug) {
        fail('Navigate to NLE editor', '__nlePlaybackDebug not exposed after mounting');
        return;
    }

    pass('Navigate to NLE editor');
}

// ═══════════════════════════════════════════════════════════
// Composition + audio graph helpers
// ═══════════════════════════════════════════════════════════

function makeEl(overrides) {
    return {
        id: 'el_' + Math.random().toString(36).substr(2, 8),
        trim_start: 0, trim_end: 0,
        x: '50%', y: '50%', width: '100%', height: '100%',
        rotation: 0, opacity: 1.0, fit: 'cover',
        volume: 1.0,
        fade_in: 0, fade_out: 0,
        effects: [], transition: null, modification_key: null,
        ...overrides,
    };
}

function makeTrack(overrides) {
    return {
        id: 'track_' + Math.random().toString(36).substr(2, 8),
        muted: false, locked: false, visible: true,
        elements: [],
        ...overrides,
    };
}

/**
 * Load a composition into the store and wire up the audio graph for all sources.
 */
async function loadScenario(name, tracks, sources) {
    const result = await page.evaluate(({ name, tracks, sources }) => {
        const app = document.querySelector('#app')?.__vue_app__;
        if (!app) return { error: 'No Vue app' };
        const pinia = app.config.globalProperties.$pinia;
        const store = pinia._s.get('videoEditorNew');
        if (!store) return { error: 'No store' };
        const dbg = window.__nlePlaybackDebug;
        if (!dbg) return { error: 'No debug interface' };

        // Set composition
        store.projectId = 'MOCK_SCENARIO_TEST';
        store.composition = {
            width: 1920, height: 1080, fps: 30,
            background_color: '#000000',
            tracks,
        };

        // Wire up audio graph for each source
        dbg.ensureAudioContext();
        for (const src of sources) {
            dbg.getOrCreateVideoElement(src);
            dbg.connectAudio(src);
        }

        const info = dbg.getAudioDebugInfo();
        return { ok: true, audioNodes: info.audioNodeCount, ctx: info.audioContextState };
    }, { name, tracks, sources });

    if (result.error) {
        fail(`Load scenario: ${name}`, result.error);
        return false;
    }
    log(`[${name}] loaded — ${result.audioNodes} audio node(s), ctx=${result.ctx}`);
    return true;
}

/**
 * Call the real updateAudioGains and return all gain values.
 */
async function getGains(timelineTime) {
    return page.evaluate((t) => {
        const dbg = window.__nlePlaybackDebug;
        dbg.updateAudioGains(t);
        const info = dbg.getAudioDebugInfo();
        const gains = {};
        for (const [uri, data] of Object.entries(info.gains)) {
            // Shorten URI for readability
            const short = uri.replace('media://video-projects/1/', '');
            gains[short] = Math.round(data.gain * 1000) / 1000; // round to 3 decimals
        }
        return gains;
    }, timelineTime);
}

/**
 * Assert gain for a source at a given time.
 */
async function assertGain(testName, time, expectedGains) {
    const gains = await getGains(time);
    let allOk = true;
    for (const [source, expectedVal] of Object.entries(expectedGains)) {
        const actual = gains[source];
        if (actual === undefined) {
            fail(`${testName} @ t=${time}`, `Source "${source}" not found in gains. Got: ${JSON.stringify(gains)}`);
            allOk = false;
        } else if (Math.abs(actual - expectedVal) > 0.02) {
            fail(`${testName} @ t=${time}`, `"${source}" expected=${expectedVal}, got=${actual}`);
            allOk = false;
        }
    }
    if (allOk) {
        pass(`${testName} @ t=${time} → ${JSON.stringify(gains)}`);
    }
}

// ═══════════════════════════════════════════════════════════
// Source URIs
// ═══════════════════════════════════════════════════════════

const SRC = {
    MAIN:     'media://video-projects/1/main-interview.mp4',
    BROLL:    'media://video-projects/1/broll-cityscape.mp4',
    MUSIC:    'media://video-projects/1/background-music.mp3',
    VO:       'media://video-projects/1/voiceover.mp3',
    CLIP_B:   'media://video-projects/1/second-clip.mp4',
};

// ═══════════════════════════════════════════════════════════
// Scenario 1: Basic — video + audio, same source, aligned
// ═══════════════════════════════════════════════════════════

async function scenario1_basic() {
    console.log('\n  === Scenario 1: Basic video + audio ===');
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview', time: 0, duration: 30, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Interview Audio', time: 0, duration: 30, source: SRC.MAIN, volume: 0.8 }),
        ]}),
    ];
    if (!await loadScenario('Basic', tracks, [SRC.MAIN])) return;

    await assertGain('Basic: mid-clip', 15, { 'main-interview.mp4': 0.8 });
    await assertGain('Basic: start', 0, { 'main-interview.mp4': 0.8 });
    await assertGain('Basic: near end', 29.5, { 'main-interview.mp4': 0.8 });
}

// ═══════════════════════════════════════════════════════════
// Scenario 2: B-roll overlay — main continues, overlay has no audio
// ═══════════════════════════════════════════════════════════

async function scenario2_broll() {
    console.log('\n  === Scenario 2: B-roll overlay ===');
    // Editor places b-roll on overlay track at 5-15s. Main video + audio continues.
    // B-roll has volume=0 (no audio wanted from it).
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview', time: 0, duration: 30, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Overlay', type: 'overlay', elements: [
            makeEl({ type: 'video', name: 'B-roll City', time: 5, duration: 10, source: SRC.BROLL, volume: 0 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Interview Audio', time: 0, duration: 30, source: SRC.MAIN, volume: 1.0 }),
        ]}),
    ];
    if (!await loadScenario('B-roll', tracks, [SRC.MAIN, SRC.BROLL])) return;

    // Before b-roll: only main audio
    await assertGain('B-roll: before overlay', 3, { 'main-interview.mp4': 1.0, 'broll-cityscape.mp4': 0 });
    // During b-roll: main audio still plays, b-roll silent
    await assertGain('B-roll: during overlay', 8, { 'main-interview.mp4': 1.0, 'broll-cityscape.mp4': 0 });
    // After b-roll: back to only main
    await assertGain('B-roll: after overlay', 18, { 'main-interview.mp4': 1.0, 'broll-cityscape.mp4': 0 });
}

// ═══════════════════════════════════════════════════════════
// Scenario 3: Split clip — same source, two segments with gap
// ═══════════════════════════════════════════════════════════

async function scenario3_splitClip() {
    console.log('\n  === Scenario 3: Split clip with gap ===');
    // Editor cut the interview: 0-10s, then gap 10-15s, then 15-25s
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview pt1', time: 0, duration: 10, source: SRC.MAIN, volume: 0 }),
            makeEl({ type: 'video', name: 'Interview pt2', time: 15, duration: 10, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Audio pt1', time: 0, duration: 10, source: SRC.MAIN, volume: 1.0 }),
            makeEl({ type: 'audio', name: 'Audio pt2', time: 15, duration: 10, source: SRC.MAIN, volume: 1.0 }),
        ]}),
    ];
    if (!await loadScenario('Split clip', tracks, [SRC.MAIN])) return;

    await assertGain('Split: first segment', 5, { 'main-interview.mp4': 1.0 });
    await assertGain('Split: in the gap', 12, { 'main-interview.mp4': 0 });
    await assertGain('Split: second segment', 20, { 'main-interview.mp4': 1.0 });
}

// ═══════════════════════════════════════════════════════════
// Scenario 4: Background music — separate audio source
// ═══════════════════════════════════════════════════════════

async function scenario4_backgroundMusic() {
    console.log('\n  === Scenario 4: Background music ===');
    // Main interview video + its audio. Separate music track underneath at vol=0.3
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview', time: 0, duration: 30, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Interview Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Interview Audio', time: 0, duration: 30, source: SRC.MAIN, volume: 1.0 }),
        ]}),
        makeTrack({ name: 'Music', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'BG Music', time: 0, duration: 30, source: SRC.MUSIC, volume: 0.3 }),
        ]}),
    ];
    if (!await loadScenario('BG music', tracks, [SRC.MAIN, SRC.MUSIC])) return;

    await assertGain('Music: both playing', 10, {
        'main-interview.mp4': 1.0,
        'background-music.mp3': 0.3,
    });
}

// ═══════════════════════════════════════════════════════════
// Scenario 5: Voiceover + music on two audio tracks
// ═══════════════════════════════════════════════════════════

async function scenario5_voiceoverPlusMusic() {
    console.log('\n  === Scenario 5: Voiceover + music ===');
    // No video — just audio-only project. VO at 2-20s, music full length.
    const tracks = [
        makeTrack({ name: 'Voiceover', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'VO', time: 2, duration: 18, source: SRC.VO, volume: 1.0 }),
        ]}),
        makeTrack({ name: 'Music', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Music', time: 0, duration: 25, source: SRC.MUSIC, volume: 0.2 }),
        ]}),
    ];
    if (!await loadScenario('VO + Music', tracks, [SRC.VO, SRC.MUSIC])) return;

    // Before VO starts: only music
    await assertGain('VO+Music: before VO', 1, {
        'voiceover.mp3': 0,
        'background-music.mp3': 0.2,
    });
    // During VO: both play
    await assertGain('VO+Music: during VO', 10, {
        'voiceover.mp3': 1.0,
        'background-music.mp3': 0.2,
    });
    // After VO ends: only music
    await assertGain('VO+Music: after VO', 22, {
        'voiceover.mp3': 0,
        'background-music.mp3': 0.2,
    });
}

// ═══════════════════════════════════════════════════════════
// Scenario 6: Fade in/out on audio
// ═══════════════════════════════════════════════════════════

async function scenario6_fadeInOut() {
    console.log('\n  === Scenario 6: Fade in/out ===');
    // Audio with 3s fade in and 3s fade out, duration=20s
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Clip', time: 0, duration: 20, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Audio', time: 0, duration: 20, source: SRC.MAIN,
                     volume: 1.0, fade_in: 3, fade_out: 3 }),
        ]}),
    ];
    if (!await loadScenario('Fade', tracks, [SRC.MAIN])) return;

    // t=0: start of fade in → factor ≈ 0
    await assertGain('Fade: t=0 (start of fade in)', 0, { 'main-interview.mp4': 0 });
    // t=1.5: mid fade in → factor ≈ 0.5
    await assertGain('Fade: t=1.5 (mid fade in)', 1.5, { 'main-interview.mp4': 0.5 });
    // t=3: fade in complete → factor = 1.0
    await assertGain('Fade: t=3 (fade in done)', 3, { 'main-interview.mp4': 1.0 });
    // t=10: middle → full volume
    await assertGain('Fade: t=10 (middle)', 10, { 'main-interview.mp4': 1.0 });
    // t=18.5: mid fade out → factor ≈ 0.5
    await assertGain('Fade: t=18.5 (mid fade out)', 18.5, { 'main-interview.mp4': 0.5 });
    // t=19.9: near end → almost silent
    await assertGain('Fade: t=19.9 (near end)', 19.9, { 'main-interview.mp4': 0.033 });
}

// ═══════════════════════════════════════════════════════════
// Scenario 7: Muted video track — audio track still plays
// ═══════════════════════════════════════════════════════════

async function scenario7_mutedVideoTrack() {
    console.log('\n  === Scenario 7: Muted video track ===');
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', muted: true, elements: [
            makeEl({ type: 'video', name: 'Interview', time: 0, duration: 20, source: SRC.MAIN, volume: 0.5 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Audio', time: 0, duration: 20, source: SRC.MAIN, volume: 0.7 }),
        ]}),
    ];
    if (!await loadScenario('Muted video track', tracks, [SRC.MAIN])) return;

    // Video track is muted, but audio track is not → audio element should control gain
    await assertGain('Muted video track: audio plays', 10, { 'main-interview.mp4': 0.7 });
}

// ═══════════════════════════════════════════════════════════
// Scenario 8: Two video sources, each with dedicated audio
// ═══════════════════════════════════════════════════════════

async function scenario8_twoSources() {
    console.log('\n  === Scenario 8: Two video sources ===');
    // Interview 0-15s, then second clip 15-30s. Each has audio on audio track.
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview', time: 0, duration: 15, source: SRC.MAIN, volume: 0 }),
            makeEl({ type: 'video', name: 'Clip B', time: 15, duration: 15, source: SRC.CLIP_B, volume: 0 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Interview Audio', time: 0, duration: 15, source: SRC.MAIN, volume: 1.0 }),
            makeEl({ type: 'audio', name: 'Clip B Audio', time: 15, duration: 15, source: SRC.CLIP_B, volume: 0.9 }),
        ]}),
    ];
    if (!await loadScenario('Two sources', tracks, [SRC.MAIN, SRC.CLIP_B])) return;

    await assertGain('Two sources: first clip', 5, {
        'main-interview.mp4': 1.0,
        'second-clip.mp4': 0,
    });
    await assertGain('Two sources: second clip', 20, {
        'main-interview.mp4': 0,
        'second-clip.mp4': 0.9,
    });
}

// ═══════════════════════════════════════════════════════════
// Scenario 9: Audio gap — audio shorter than video
// ═══════════════════════════════════════════════════════════

async function scenario9_audioGap() {
    console.log('\n  === Scenario 9: Audio gap ===');
    // Video 0-30s, but audio only 5-20s. Silence before and after.
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Clip', time: 0, duration: 30, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Audio', time: 5, duration: 15, source: SRC.MAIN, volume: 1.0 }),
        ]}),
    ];
    if (!await loadScenario('Audio gap', tracks, [SRC.MAIN])) return;

    // Before audio starts: video active but has dedicated audio → gain=0
    await assertGain('Audio gap: before audio', 2, { 'main-interview.mp4': 0 });
    // During audio: gain=1
    await assertGain('Audio gap: during audio', 12, { 'main-interview.mp4': 1.0 });
    // After audio ends: video still active, gain=0
    await assertGain('Audio gap: after audio', 25, { 'main-interview.mp4': 0 });
}

// ═══════════════════════════════════════════════════════════
// Scenario 10: Crossfade — two audio elements overlap
// ═══════════════════════════════════════════════════════════

async function scenario10_crossfade() {
    console.log('\n  === Scenario 10: Audio crossfade ===');
    // Main audio 0-15s with 3s fade out. Music 12-30s with 3s fade in.
    // The 12-15s region has both fading.
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview', time: 0, duration: 30, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Interview Audio', time: 0, duration: 15, source: SRC.MAIN,
                     volume: 1.0, fade_out: 3 }),
        ]}),
        makeTrack({ name: 'Music', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Music', time: 12, duration: 18, source: SRC.MUSIC,
                     volume: 0.8, fade_in: 3 }),
        ]}),
    ];
    if (!await loadScenario('Crossfade', tracks, [SRC.MAIN, SRC.MUSIC])) return;

    // t=5: only interview audio
    await assertGain('Crossfade: only interview', 5, {
        'main-interview.mp4': 1.0,
        'background-music.mp3': 0,
    });
    // t=13: interview fading out (2s into 3s fade = 0.33), music fading in (1s into 3s fade ≈ 0.33 * 0.8)
    await assertGain('Crossfade: during crossfade (t=13)', 13, {
        'main-interview.mp4': 0.667,
        'background-music.mp3': 0.267,
    });
    // t=13.5: interview fade (1.5/3 remaining ≈ 0.5), music fade (1.5/3 ≈ 0.5 * 0.8)
    await assertGain('Crossfade: mid crossfade (t=13.5)', 13.5, {
        'main-interview.mp4': 0.5,
        'background-music.mp3': 0.4,
    });
    // t=16: interview done, only music (fade in complete)
    await assertGain('Crossfade: only music', 16, {
        'main-interview.mp4': 0,
        'background-music.mp3': 0.8,
    });
}

// ═══════════════════════════════════════════════════════════
// Scenario 11: Audio starts before video (intro/pre-roll)
// ═══════════════════════════════════════════════════════════

async function scenario11_audioBeforeVideo() {
    console.log('\n  === Scenario 11: Audio before video (intro) ===');
    // Audio starts at 0, video starts at 3s (audio intro before image appears)
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview', time: 3, duration: 20, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Interview Audio', time: 0, duration: 23, source: SRC.MAIN, volume: 1.0 }),
        ]}),
    ];
    if (!await loadScenario('Audio before video', tracks, [SRC.MAIN])) return;

    // t=1: only audio active (video hasn't started) → audio plays
    await assertGain('Intro: audio before video', 1, { 'main-interview.mp4': 1.0 });
    // t=5: both active → audio preferred
    await assertGain('Intro: both active', 5, { 'main-interview.mp4': 1.0 });
    // t=22.5: audio still active, video ended → audio plays
    await assertGain('Intro: video ended, audio continues', 22.5, { 'main-interview.mp4': 1.0 });
}

// ═══════════════════════════════════════════════════════════
// Scenario 12: Complex timeline — 3 sources, overlays, VO, music
// ═══════════════════════════════════════════════════════════

async function scenario12_complexTimeline() {
    console.log('\n  === Scenario 12: Complex timeline ===');
    // Interview 0-20s, B-roll overlay 8-14s, VO 3-18s, BG music 0-25s @ 0.15
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview', time: 0, duration: 20, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Overlay', type: 'overlay', elements: [
            makeEl({ type: 'video', name: 'B-roll', time: 8, duration: 6, source: SRC.BROLL, volume: 0 }),
        ]}),
        makeTrack({ name: 'Interview Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Interview Audio', time: 0, duration: 20, source: SRC.MAIN, volume: 0.6 }),
        ]}),
        makeTrack({ name: 'Voiceover', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'VO', time: 3, duration: 15, source: SRC.VO, volume: 1.0 }),
        ]}),
        makeTrack({ name: 'Music', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'BG Music', time: 0, duration: 25, source: SRC.MUSIC, volume: 0.15 }),
        ]}),
    ];
    if (!await loadScenario('Complex', tracks, [SRC.MAIN, SRC.BROLL, SRC.VO, SRC.MUSIC])) return;

    // t=1: interview + music (no VO yet)
    await assertGain('Complex: t=1 (interview + music, no VO)', 1, {
        'main-interview.mp4': 0.6,
        'broll-cityscape.mp4': 0,
        'voiceover.mp3': 0,
        'background-music.mp3': 0.15,
    });

    // t=5: interview + VO + music
    await assertGain('Complex: t=5 (all 3 audio sources)', 5, {
        'main-interview.mp4': 0.6,
        'broll-cityscape.mp4': 0,
        'voiceover.mp3': 1.0,
        'background-music.mp3': 0.15,
    });

    // t=10: interview + b-roll overlay + VO + music (b-roll has no audio)
    await assertGain('Complex: t=10 (b-roll overlaid)', 10, {
        'main-interview.mp4': 0.6,
        'broll-cityscape.mp4': 0,
        'voiceover.mp3': 1.0,
        'background-music.mp3': 0.15,
    });

    // t=19: interview audio + music (VO ended at 18)
    await assertGain('Complex: t=19 (VO ended)', 19, {
        'main-interview.mp4': 0.6,
        'broll-cityscape.mp4': 0,
        'voiceover.mp3': 0,
        'background-music.mp3': 0.15,
    });

    // t=22: interview ended, only music
    await assertGain('Complex: t=22 (only music)', 22, {
        'main-interview.mp4': 0,
        'broll-cityscape.mp4': 0,
        'voiceover.mp3': 0,
        'background-music.mp3': 0.15,
    });
}

// ═══════════════════════════════════════════════════════════
// Scenario 13: Mute audio track, video volume manually set > 0
// ═══════════════════════════════════════════════════════════

async function scenario13_muteAudioFallbackToVideo() {
    console.log('\n  === Scenario 13: Muted audio track, video vol > 0 ===');
    // User muted audio track but gave video volume=0.5. Since dedicated audio
    // exists (even though muted), it should still be preferred → gain=0, not fallback.
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview', time: 0, duration: 20, source: SRC.MAIN, volume: 0.5 }),
        ]}),
        makeTrack({ name: 'Audio', type: 'audio', muted: true, elements: [
            makeEl({ type: 'audio', name: 'Audio', time: 0, duration: 20, source: SRC.MAIN, volume: 1.0 }),
        ]}),
    ];
    if (!await loadScenario('Muted audio fallback', tracks, [SRC.MAIN])) return;

    // Audio track muted → audio element chosen → trackMuted=true → gain=0
    // Should NOT fall back to video element's volume=0.5
    await assertGain('Muted audio: no fallback to video', 10, { 'main-interview.mp4': 0 });
}

// ═══════════════════════════════════════════════════════════
// Scenario 14: Standalone video (no audio element) — volume from video
// ═══════════════════════════════════════════════════════════

async function scenario14_standaloneVideo() {
    console.log('\n  === Scenario 14: Standalone video (no audio element) ===');
    // Just a video clip with its own volume, no dedicated audio element
    const tracks = [
        makeTrack({ name: 'Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Quick clip', time: 0, duration: 10, source: SRC.BROLL, volume: 0.7 }),
        ]}),
    ];
    if (!await loadScenario('Standalone video', tracks, [SRC.BROLL])) return;

    // No dedicated audio → falls back to video element → volume=0.7
    await assertGain('Standalone: video controls audio', 5, { 'broll-cityscape.mp4': 0.7 });
}

// ═══════════════════════════════════════════════════════════
// Scenario 15: Montażysta dodaje muzyczną intro i outro
// ═══════════════════════════════════════════════════════════

async function scenario15_introOutro() {
    console.log('\n  === Scenario 15: Music intro + outro ===');
    // Music 0-5s (intro), interview 3-23s, music 21-28s (outro)
    // Music has fade in/out, interview audio in the middle
    const tracks = [
        makeTrack({ name: 'Main Video', type: 'video', elements: [
            makeEl({ type: 'video', name: 'Interview', time: 3, duration: 20, source: SRC.MAIN, volume: 0 }),
        ]}),
        makeTrack({ name: 'Interview Audio', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Interview Audio', time: 3, duration: 20, source: SRC.MAIN, volume: 1.0,
                     fade_in: 1, fade_out: 1 }),
        ]}),
        makeTrack({ name: 'Music', type: 'audio', elements: [
            makeEl({ type: 'audio', name: 'Intro Music', time: 0, duration: 5, source: SRC.MUSIC,
                     volume: 0.8, fade_out: 2 }),
            makeEl({ type: 'audio', name: 'Outro Music', time: 21, duration: 7, source: SRC.MUSIC,
                     volume: 0.8, fade_in: 2 }),
        ]}),
    ];
    if (!await loadScenario('Intro+Outro', tracks, [SRC.MAIN, SRC.MUSIC])) return;

    // t=1: only intro music
    await assertGain('Intro: only music', 1, {
        'main-interview.mp4': 0,
        'background-music.mp3': 0.8,
    });
    // t=3.5: interview fading in (0.5/1 = 0.5), music fading out (1.5s into 2s fade → (5-3.5)/2 = 0.75)
    // Wait — music fade_out=2, so fade starts at t=3 (duration 5, 5-2=3). At t=3.5: (5-3.5)/2 = 0.75
    await assertGain('Intro: crossfade zone', 3.5, {
        'main-interview.mp4': 0.5,
        'background-music.mp3': 0.6,  // 0.8 * 0.75
    });
    // t=10: only interview
    await assertGain('Middle: only interview', 10, {
        'main-interview.mp4': 1.0,
        'background-music.mp3': 0,
    });
    // t=22: interview fading out (1s from end=23, so 22/23... localTime=19, dur=20, fade_out=1, factor=(20-19)/1=1.0)
    // Actually: localTime = 22 - 3 = 19, dur=20, fade_out=1. 19 > 20-1=19 → factor = (20-19)/1 = 1.0. So just barely full.
    // Outro: localTime = 22-21 = 1, fade_in=2, so factor=1/2=0.5, vol=0.8*0.5=0.4
    await assertGain('Outro: crossfade zone', 22, {
        'main-interview.mp4': 1.0,
        'background-music.mp3': 0.4,
    });
    // t=24: interview done (ended at 23), outro still playing (fade_in done at 23)
    await assertGain('Outro: only music', 24, {
        'main-interview.mp4': 0,
        'background-music.mp3': 0.8,
    });
}

// ═══════════════════════════════════════════════════════════
// Main
// ═══════════════════════════════════════════════════════════

async function main() {
    console.log('\n🎬 NLE Timeline Scenario Tests (Real Player Engine)\n');

    await setup();

    try {
        await login();
        await navigateToEditor();
    } catch (e) {
        fail('Setup', e.message);
        await browser.close();
        process.exit(1);
    }

    await scenario1_basic();
    await scenario2_broll();
    await scenario3_splitClip();
    await scenario4_backgroundMusic();
    await scenario5_voiceoverPlusMusic();
    await scenario6_fadeInOut();
    await scenario7_mutedVideoTrack();
    await scenario8_twoSources();
    await scenario9_audioGap();
    await scenario10_crossfade();
    await scenario11_audioBeforeVideo();
    await scenario12_complexTimeline();
    await scenario13_muteAudioFallbackToVideo();
    await scenario14_standaloneVideo();
    await scenario15_introOutro();

    await screenshot('final');

    console.log('\n═══════════════════════════════════════');
    console.log(`Results: ${passed} passed, ${failed} failed`);
    console.log('═══════════════════════════════════════');
    for (const r of results) {
        const icon = r.status === 'PASS' ? '✅' : '❌';
        console.log(`  ${icon} ${r.name}${r.error ? ': ' + r.error : ''}`);
    }
    console.log('');

    await browser.close();
    process.exit(failed > 0 ? 1 : 0);
}

main();
