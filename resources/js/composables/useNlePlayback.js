import { ref, watch, onUnmounted, computed } from 'vue';
import { useVideoEditorStore } from '@/stores/videoEditorNew';

/**
 * NLE Canvas Playback Engine.
 *
 * Uses video.play() + requestVideoFrameCallback (with rAF fallback)
 * for smooth, frame-accurate video rendering. Audio is routed through
 * Web Audio API gain nodes for per-element volume/fade/mute control.
 *
 * Audio graph:
 *   MediaElementSource (per video) -> gainNode (per element) -> masterGain -> destination
 *
 * Clock source:
 *   Primary video element's currentTime is the source of truth for timeline position.
 *   Falls back to performance.now() timer when no video elements are active.
 */
export function useNlePlayback(canvasRef) {
    const store = useVideoEditorStore();
    const videoElements = ref(new Map()); // sourceUri -> HTMLVideoElement
    const imageElements = ref(new Map()); // sourceUri -> HTMLImageElement

    // Sync state
    let animFrameId = null;
    let primaryElSnapshot = null; // element config snapshot of the clock master
    let primaryVideo = null;      // HTMLVideoElement driving the clock
    let useRVFC = false;

    // Audio
    let audioCtx = null;
    let masterGainNode = null;
    const audioNodes = new Map(); // sourceUri -> { sourceNode, gainNode }

    // Seek guard (generation counter prevents stale seeks from rendering)
    let seekGeneration = 0;

    // Transition seek guard — prevents RVFC from using stale mediaTime after a seek
    let transitionSeekPending = false;

    // Last good frame cache (prevents black flash when video not ready)
    let cachedFrameCanvas = null;
    let hasCachedFrame = false;

    const canvasWidth = computed(() => store.compositionWidth);
    const canvasHeight = computed(() => store.compositionHeight);

    // ═══════════════════════════════════════════════════════
    // Media element creation
    // ═══════════════════════════════════════════════════════

    function getOrCreateVideoElement(source) {
        if (videoElements.value.has(source)) {
            return videoElements.value.get(source);
        }

        const video = document.createElement('video');
        video.crossOrigin = 'anonymous';
        video.preload = 'auto';
        video.playsInline = true;
        // Audio routed via Web Audio API — do NOT set muted

        const url = resolveSourceUri(source);
        video.src = url;

        videoElements.value.set(source, video);
        return video;
    }

    function getOrCreateImageElement(source) {
        if (imageElements.value.has(source)) {
            return imageElements.value.get(source);
        }

        const img = new Image();
        img.crossOrigin = 'anonymous';

        // When image loads: auto-fit element dimensions to image aspect ratio, then re-render
        img.onload = () => {
            autoFitImageElement(source, img.naturalWidth, img.naturalHeight);
            if (!store.isPlaying) {
                renderFrame(store.playhead);
            }
        };

        img.src = resolveSourceUri(source);
        imageElements.value.set(source, img);
        return img;
    }

    function resolveSourceUri(uri) {
        if (!uri) return '';
        if (uri.startsWith('data:')) return uri;
        if (uri.startsWith('media://')) {
            const path = uri.replace('media://', '');
            if (path.startsWith('video-projects/') && store.projectId) {
                // Uploaded media files: media://video-projects/{userId}/media/{filename}
                const mediaMatch = path.match(/video-projects\/\d+\/media\/(.+)$/);
                if (mediaMatch) {
                    return `/api/v1/video-projects/${store.projectId}/media/${mediaMatch[1]}`;
                }
                // Main project video: media://video-projects/{userId}/...
                return `/api/v1/video-projects/${store.projectId}/stream`;
            }
            return `/storage/${path}`;
        }
        if (uri.startsWith('http://') || uri.startsWith('https://')) {
            return uri;
        }
        return uri;
    }

    /**
     * Auto-fit image element dimensions to match the image's natural aspect ratio.
     * Only adjusts elements that still have their default size (haven't been manually resized).
     */
    function autoFitImageElement(source, naturalW, naturalH) {
        if (!store.composition?.tracks || !naturalW || !naturalH) return;

        const compW = store.compositionWidth;
        const compH = store.compositionHeight;

        for (const track of store.composition.tracks) {
            for (const el of track.elements) {
                if (el.type !== 'image' || el.source !== source) continue;
                if (el._autoFitted) continue; // already fitted

                const imgRatio = naturalW / naturalH;

                // Target: ~50% of the shorter composition dimension
                const maxPx = Math.min(compW, compH) * 0.5;
                let w, h;
                if (imgRatio >= 1) {
                    // landscape image
                    w = maxPx;
                    h = maxPx / imgRatio;
                } else {
                    // portrait image
                    h = maxPx;
                    w = maxPx * imgRatio;
                }

                el.width = ((w / compW) * 100).toFixed(2) + '%';
                el.height = ((h / compH) * 100).toFixed(2) + '%';
                el._autoFitted = true;
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    // Active elements
    // ═══════════════════════════════════════════════════════

    function getActiveElements(time) {
        if (!store.composition?.tracks) return [];
        const active = [];

        // Iterate in reverse: track[0] (top of timeline) is drawn LAST = topmost visual layer.
        // This matches standard NLE convention (top track = top layer).
        const tracks = store.composition.tracks;
        for (let i = tracks.length - 1; i >= 0; i--) {
            const track = tracks[i];
            if (!track.visible) continue;

            for (const el of track.elements) {
                const start = el.time || 0;
                const end = start + (el.duration || 0);
                if (time >= start && time < end) {
                    active.push({ ...el, trackMuted: track.muted });
                }
            }
        }

        return active;
    }

    // ═══════════════════════════════════════════════════════
    // Web Audio API
    // ═══════════════════════════════════════════════════════

    function ensureAudioContext() {
        if (audioCtx) return;
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        masterGainNode = audioCtx.createGain();
        masterGainNode.gain.value = store.masterVolume ?? 1;
        masterGainNode.connect(audioCtx.destination);
    }

    function connectAudio(sourceUri) {
        if (!audioCtx || !masterGainNode) return;
        if (audioNodes.has(sourceUri)) return;

        const video = videoElements.value.get(sourceUri);
        if (!video) return;

        try {
            const sourceNode = audioCtx.createMediaElementSource(video);
            const gainNode = audioCtx.createGain();
            gainNode.gain.value = 0;
            sourceNode.connect(gainNode);
            gainNode.connect(masterGainNode);
            audioNodes.set(sourceUri, { sourceNode, gainNode });
        } catch {
            // MediaElementSource already created for this element
        }
    }

    /**
     * Get all media elements from all tracks (not filtered by time).
     */
    function getAllMediaElements() {
        if (!store.composition?.tracks) return [];
        const elements = [];
        for (const track of store.composition.tracks) {
            for (const el of track.elements) {
                if (el.type === 'video' || el.type === 'audio') {
                    elements.push({ ...el, trackMuted: track.muted });
                }
            }
        }
        return elements;
    }

    function updateAudioGains(timelineTime) {
        const activeEls = getActiveElements(timelineTime);
        const allEls = getAllMediaElements();

        for (const [uri, nodes] of audioNodes) {
            const hasDedicatedAudio = allEls.some(
                e => e.type === 'audio' && e.source === uri
            );

            const matchingActive = activeEls.filter(
                e => (e.type === 'video' || e.type === 'audio') && e.source === uri
            );

            let el;
            if (hasDedicatedAudio) {
                el = matchingActive.find(e => e.type === 'audio');
            } else {
                el = matchingActive.find(e => e.type === 'video');
            }

            if (el && !el.trackMuted) {
                let vol = el.volume ?? 1.0;
                vol *= computeFadeFactor(el, timelineTime);
                nodes.gainNode.gain.value = vol;
            } else {
                nodes.gainNode.gain.value = 0;
            }
        }
    }

    function computeFadeFactor(el, timelineTime) {
        const localTime = timelineTime - (el.time || 0);
        const duration = el.duration || 0;
        let factor = 1.0;

        if (el.fade_in > 0 && localTime < el.fade_in) {
            factor = localTime / el.fade_in;
        }
        if (el.fade_out > 0 && localTime > duration - el.fade_out) {
            factor = Math.min(factor, (duration - localTime) / el.fade_out);
        }

        return Math.max(0, Math.min(1, factor));
    }

    // ═══════════════════════════════════════════════════════
    // Rendering
    // ═══════════════════════════════════════════════════════

    function renderFrame(time) {
        const canvas = canvasRef.value;
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const w = canvas.width;
        const h = canvas.height;

        // Clear with background color
        ctx.fillStyle = store.composition?.background_color || '#000000';
        ctx.fillRect(0, 0, w, h);

        const elements = getActiveElements(time);

        for (const el of elements) {
            ctx.save();
            ctx.globalAlpha = el.opacity ?? 1;

            if (el.type === 'video') {
                renderVideoElement(ctx, el, w, h);
            } else if (el.type === 'image') {
                renderImageElement(ctx, el, w, h);
            } else if (el.type === 'text') {
                renderTextElement(ctx, el, w, h);
            } else if (el.type === 'shape') {
                renderShapeElement(ctx, el, w, h);
            }

            ctx.restore();
        }

        // Cache complete frame for fallback
        cacheCurrentFrame(canvas);
    }

    function renderVideoElement(ctx, el, canvasW, canvasH) {
        if (!el.source) return;
        const video = getOrCreateVideoElement(el.source);

        // Only draw if video has decoded frame data
        if (video.readyState >= 2) {
            const { dx, dy, dw, dh } = calculateFit(
                video.videoWidth, video.videoHeight,
                canvasW, canvasH, el
            );
            ctx.drawImage(video, dx, dy, dw, dh);
        } else if (hasCachedFrame && cachedFrameCanvas) {
            // Fallback: draw last good complete frame to avoid black flash
            ctx.drawImage(cachedFrameCanvas, 0, 0, canvasW, canvasH);
        }
    }

    function renderImageElement(ctx, el, canvasW, canvasH) {
        if (!el.source) return;
        const img = getOrCreateImageElement(el.source);

        if (img.complete && img.naturalWidth > 0) {
            const { dx, dy, dw, dh } = calculateFit(
                img.naturalWidth, img.naturalHeight,
                canvasW, canvasH, el
            );
            ctx.drawImage(img, dx, dy, dw, dh);
        }
    }

    function renderTextElement(ctx, el, canvasW, canvasH) {
        const fontSize = el.font_size || 48;
        const fontFamily = el.font_family || 'sans-serif';
        const color = el.color || '#ffffff';
        const align = el.text_align || 'center';

        ctx.font = `${el.font_weight || 'bold'} ${fontSize}px ${fontFamily}`;
        ctx.fillStyle = color;
        ctx.textAlign = align;
        ctx.textBaseline = 'middle';

        const x = resolvePosition(el.x, canvasW);
        const y = resolvePosition(el.y, canvasH);

        if (el.stroke_color) {
            ctx.strokeStyle = el.stroke_color;
            ctx.lineWidth = el.stroke_width || 2;
            ctx.strokeText(el.text || '', x, y);
        }

        ctx.fillText(el.text || '', x, y);
    }

    function renderShapeElement(ctx, el, canvasW, canvasH) {
        const x = resolvePosition(el.x, canvasW);
        const y = resolvePosition(el.y, canvasH);
        const w = resolveSize(el.width, canvasW);
        const h = resolveSize(el.height, canvasH);

        ctx.fillStyle = el.color || '#ffffff';
        if (el.shape === 'circle') {
            ctx.beginPath();
            ctx.arc(x, y, Math.min(w, h) / 2, 0, Math.PI * 2);
            ctx.fill();
        } else {
            ctx.fillRect(x - w / 2, y - h / 2, w, h);
        }
    }

    function cacheCurrentFrame(sourceCanvas) {
        if (!cachedFrameCanvas) {
            cachedFrameCanvas = document.createElement('canvas');
        }
        if (cachedFrameCanvas.width !== sourceCanvas.width ||
            cachedFrameCanvas.height !== sourceCanvas.height) {
            cachedFrameCanvas.width = sourceCanvas.width;
            cachedFrameCanvas.height = sourceCanvas.height;
        }
        const cacheCtx = cachedFrameCanvas.getContext('2d');
        cacheCtx.drawImage(sourceCanvas, 0, 0);
        hasCachedFrame = true;
    }

    // ═══════════════════════════════════════════════════════
    // Geometry helpers
    // ═══════════════════════════════════════════════════════

    function calculateFit(srcW, srcH, canvasW, canvasH, el) {
        const targetW = resolveSize(el.width, canvasW);
        const targetH = resolveSize(el.height, canvasH);
        const targetX = resolvePosition(el.x, canvasW);
        const targetY = resolvePosition(el.y, canvasH);
        const fit = el.fit || 'cover';

        let dw, dh;
        const srcRatio = srcW / srcH;
        const targetRatio = targetW / targetH;

        if (fit === 'cover') {
            if (srcRatio > targetRatio) {
                dh = targetH;
                dw = dh * srcRatio;
            } else {
                dw = targetW;
                dh = dw / srcRatio;
            }
        } else {
            if (srcRatio > targetRatio) {
                dw = targetW;
                dh = dw / srcRatio;
            } else {
                dh = targetH;
                dw = dh * srcRatio;
            }
        }

        const dx = targetX - dw / 2;
        const dy = targetY - dh / 2;

        return { dx, dy, dw, dh };
    }

    function resolvePosition(value, total) {
        if (typeof value === 'string' && value.endsWith('%')) {
            return (parseFloat(value) / 100) * total;
        }
        return parseFloat(value) || 0;
    }

    function resolveSize(value, total) {
        if (typeof value === 'string' && value.endsWith('%')) {
            return (parseFloat(value) / 100) * total;
        }
        return parseFloat(value) || total;
    }

    // ═══════════════════════════════════════════════════════
    // Playback engine
    // ═══════════════════════════════════════════════════════

    function startPlayback() {
        stopPlaybackEngine();

        const active = getActiveElements(store.playhead);
        const videoEl = active.find(el => el.type === 'video' && el.source);

        // Set up audio context (requires user gesture — play button click qualifies)
        ensureAudioContext();
        if (audioCtx?.state === 'suspended') {
            audioCtx.resume().catch(() => {});
        }

        // Connect audio for all active media elements
        for (const el of active) {
            if ((el.type === 'video' || el.type === 'audio') && el.source) {
                getOrCreateVideoElement(el.source);
                connectAudio(el.source);
            }
        }

        if (videoEl) {
            startVideoPlayback(videoEl);
        } else {
            // No video at current position — start audio-only sources and use timer
            syncActiveAudioSources(store.playhead);
            startTimerPlayback();
        }

        updateAudioGains(store.playhead);
    }

    function startVideoPlayback(el) {
        // Cancel any existing animation loop
        if (animFrameId) {
            cancelAnimationFrame(animFrameId);
            animFrameId = null;
        }

        primaryElSnapshot = { ...el };
        const video = getOrCreateVideoElement(el.source);
        primaryVideo = video;

        // Seek to correct local time
        const localTime = store.playhead - (el.time || 0) + (el.trim_start || 0);
        video.currentTime = Math.max(0, localTime);

        // Also play other active media elements
        playOtherActiveMedia(store.playhead, el.source);

        useRVFC = 'requestVideoFrameCallback' in video;

        video.play().then(() => {
            if (!store.isPlaying) return; // user paused before play resolved
            if (useRVFC) {
                startRVFCSync();
            } else {
                startRAFSync();
            }
        }).catch(() => {
            store.isPlaying = false;
        });
    }

    function playOtherActiveMedia(time, excludeSource) {
        const active = getActiveElements(time);
        for (const el of active) {
            if ((el.type === 'video' || el.type === 'audio') && el.source && el.source !== excludeSource) {
                const video = getOrCreateVideoElement(el.source);
                connectAudio(el.source);
                const localTime = time - (el.time || 0) + (el.trim_start || 0);
                video.currentTime = Math.max(0, localTime);
                video.play().catch(() => {});
            }
        }
    }

    /**
     * Ensure HTMLVideoElements for active audio-only elements keep playing.
     * Called when no video element is active but audio elements still need
     * their shared HTMLVideoElement to be playing for Web Audio API to work.
     */
    function syncActiveAudioSources(time) {
        const active = getActiveElements(time);
        for (const el of active) {
            if (el.type === 'audio' && el.source) {
                const video = getOrCreateVideoElement(el.source);
                connectAudio(el.source);
                const localTime = time - (el.time || 0) + (el.trim_start || 0);
                if (Math.abs(video.currentTime - localTime) > 0.1) {
                    video.currentTime = Math.max(0, localTime);
                }
                if (video.paused) {
                    video.play().catch(() => {});
                }
            }
        }
    }

    function stopPlaybackEngine() {
        if (animFrameId) {
            cancelAnimationFrame(animFrameId);
            animFrameId = null;
        }

        videoElements.value.forEach(video => video.pause());

        for (const [, nodes] of audioNodes) {
            nodes.gainNode.gain.value = 0;
        }

        primaryElSnapshot = null;
        primaryVideo = null;
        transitionSeekPending = false;
    }

    // --- RVFC sync (frame-accurate, decoded-frame callback) ---

    function startRVFCSync() {
        if (!primaryVideo || !store.isPlaying) return;

        function onVideoFrame(now, metadata) {
            if (!store.isPlaying || !primaryVideo) return;

            // Skip stale frames delivered before a pending seek completes
            if (transitionSeekPending) {
                if (store.isPlaying && primaryVideo) {
                    primaryVideo.requestVideoFrameCallback(onVideoFrame);
                }
                return;
            }

            const sourceTime = metadata.mediaTime;
            const timelineTime = sourceTime - (primaryElSnapshot.trim_start || 0) + (primaryElSnapshot.time || 0);

            onSyncTick(timelineTime);

            if (store.isPlaying && primaryVideo) {
                primaryVideo.requestVideoFrameCallback(onVideoFrame);
            }
        }

        primaryVideo.requestVideoFrameCallback(onVideoFrame);
    }

    // --- rAF sync (fallback for browsers without RVFC) ---

    function startRAFSync() {
        function tick() {
            if (!store.isPlaying || !primaryVideo) return;

            // Skip ticks while a transition seek is in progress
            if (transitionSeekPending) {
                animFrameId = requestAnimationFrame(tick);
                return;
            }

            const sourceTime = primaryVideo.currentTime;
            const timelineTime = sourceTime - (primaryElSnapshot.trim_start || 0) + (primaryElSnapshot.time || 0);

            onSyncTick(timelineTime);

            if (store.isPlaying) {
                animFrameId = requestAnimationFrame(tick);
            }
        }

        animFrameId = requestAnimationFrame(tick);
    }

    // --- Timer-based playback (no video elements on timeline) ---

    function startTimerPlayback() {
        if (animFrameId) {
            cancelAnimationFrame(animFrameId);
            animFrameId = null;
        }

        let lastTs = performance.now();
        let lastAudioSync = 0;

        function tick(ts) {
            if (!store.isPlaying) return;

            const dt = (ts - lastTs) / 1000;
            lastTs = ts;

            const newTime = store.playhead + dt;

            // Check if a video element has become active
            const active = getActiveElements(newTime);
            const videoEl = active.find(el => el.type === 'video' && el.source);
            if (videoEl) {
                store.playhead = newTime;
                startVideoPlayback(videoEl);
                return;
            }

            if (newTime >= store.timelineDuration) {
                store.isPlaying = false;
                store.playhead = 0;
                stopPlaybackEngine();
                renderFrame(0);
                return;
            }

            store.playhead = newTime;

            // Periodically sync audio-only sources (every ~500ms, not every frame)
            if (ts - lastAudioSync > 500) {
                syncActiveAudioSources(newTime);
                lastAudioSync = ts;
            }
            updateAudioGains(newTime);
            renderFrame(newTime);

            animFrameId = requestAnimationFrame(tick);
        }

        animFrameId = requestAnimationFrame(tick);
    }

    // --- Per-frame sync logic ---

    function onSyncTick(timelineTime) {
        // End of timeline
        if (timelineTime >= store.timelineDuration) {
            store.isPlaying = false;
            store.playhead = 0;
            stopPlaybackEngine();
            renderFrame(0);
            return;
        }

        store.playhead = timelineTime;

        // Check if primary element is still active
        if (primaryElSnapshot) {
            const start = primaryElSnapshot.time || 0;
            const end = start + (primaryElSnapshot.duration || 0);
            if (timelineTime < start || timelineTime >= end) {
                handlePrimaryTransition(timelineTime);
            }
        }

        updateAudioGains(timelineTime);
        renderFrame(timelineTime);
    }

    function handlePrimaryTransition(timelineTime) {
        const active = getActiveElements(timelineTime);
        const nextVideo = active.find(el => el.type === 'video' && el.source);

        if (nextVideo) {
            // Update primary snapshot
            primaryElSnapshot = { ...nextVideo };
            const video = getOrCreateVideoElement(nextVideo.source);

            if (video !== primaryVideo) {
                if (primaryVideo) primaryVideo.pause();
                primaryVideo = video;
            }

            connectAudio(nextVideo.source);

            const localTime = timelineTime - (nextVideo.time || 0) + (nextVideo.trim_start || 0);
            if (Math.abs(video.currentTime - localTime) > 0.05) {
                // Guard: block sync ticks until seek completes to prevent
                // stale mediaTime from producing wrong timeline positions
                transitionSeekPending = true;
                video.currentTime = Math.max(0, localTime);
                video.addEventListener('seeked', function onSeeked() {
                    video.removeEventListener('seeked', onSeeked);
                    transitionSeekPending = false;
                }, { once: true });
            }

            if (video.paused && store.isPlaying) {
                video.play().catch(() => {});
            }
        } else {
            // No more video elements active — switch to timer playback
            if (primaryVideo) primaryVideo.pause();
            primaryElSnapshot = null;
            primaryVideo = null;
            transitionSeekPending = false;

            // Keep audio sources playing even when no video element is on screen
            syncActiveAudioSources(timelineTime);

            startTimerPlayback();
        }
    }

    // ═══════════════════════════════════════════════════════
    // Seek (when paused)
    // ═══════════════════════════════════════════════════════

    function seekToTime(time) {
        const gen = ++seekGeneration;
        const active = getActiveElements(time);
        let remaining = 0;

        for (const el of active) {
            if ((el.type === 'video' || el.type === 'audio') && el.source) {
                remaining++;
                const video = getOrCreateVideoElement(el.source);
                const localTime = time - (el.time || 0) + (el.trim_start || 0);
                video.currentTime = Math.max(0, localTime);

                video.addEventListener('seeked', function onSeeked() {
                    video.removeEventListener('seeked', onSeeked);
                    remaining--;
                    if (remaining <= 0 && gen === seekGeneration) {
                        renderFrame(time);
                    }
                }, { once: true });
            }
        }

        if (remaining === 0) {
            renderFrame(time);
        }
    }

    // ═══════════════════════════════════════════════════════
    // Watchers
    // ═══════════════════════════════════════════════════════

    watch(() => store.isPlaying, (playing) => {
        if (playing) {
            startPlayback();
        } else {
            stopPlaybackEngine();
            renderFrame(store.playhead);
        }
    });

    // Re-render on seek when paused
    watch(() => store.playhead, (newTime) => {
        if (!store.isPlaying) {
            seekToTime(newTime);
        }
    });

    // Sync master volume to audio graph
    watch(() => store.masterVolume, (vol) => {
        if (masterGainNode) {
            masterGainNode.gain.value = vol;
        }
    });

    // ═══════════════════════════════════════════════════════
    // Cleanup
    // ═══════════════════════════════════════════════════════

    onUnmounted(() => {
        stopPlaybackEngine();

        videoElements.value.forEach(video => {
            video.pause();
            video.removeAttribute('src');
            video.load();
        });
        videoElements.value.clear();
        imageElements.value.clear();

        for (const [, nodes] of audioNodes) {
            nodes.sourceNode.disconnect();
            nodes.gainNode.disconnect();
        }
        audioNodes.clear();

        if (masterGainNode) {
            masterGainNode.disconnect();
            masterGainNode = null;
        }
        if (audioCtx) {
            audioCtx.close().catch(() => {});
            audioCtx = null;
        }

        cachedFrameCanvas = null;
        hasCachedFrame = false;
    });

    function getAudioDebugInfo() {
        const gains = {};
        for (const [uri, nodes] of audioNodes) {
            gains[uri] = {
                gain: nodes.gainNode.gain.value,
            };
        }
        return {
            gains,
            masterVolume: masterGainNode ? masterGainNode.gain.value : null,
            audioContextState: audioCtx ? audioCtx.state : null,
            videoElementCount: videoElements.value.size,
            audioNodeCount: audioNodes.size,
        };
    }

    return {
        renderFrame,
        getActiveElements,
        resolveSourceUri,
        resolvePosition,
        resolveSize,
        calculateFit,
        canvasWidth,
        canvasHeight,
        getAudioDebugInfo,
        updateAudioGains,
        ensureAudioContext,
        connectAudio,
        getOrCreateVideoElement,
    };
}
