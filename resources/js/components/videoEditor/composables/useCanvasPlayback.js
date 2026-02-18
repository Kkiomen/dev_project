import { ref, watch, onUnmounted, toValue } from 'vue';

const ASSUMED_FPS = 30;

/**
 * Canvas + Web Audio NLE playback engine.
 *
 * Uses a hidden <video> as decode source, paints frames to a <canvas>,
 * and routes audio through Web Audio API GainNode so deleted clips
 * are truly silent and invisible.
 *
 * Audio graph: MediaElementSource → clipGain (0/1) → volumeGain (0–1) → destination
 * Play state is managed entirely by this composable, not by the hidden video's events.
 *
 * @param {import('vue').Ref<HTMLCanvasElement|null>} canvasEl
 * @param {Object} options
 * @param {Function} options.getVideoClips - () => sorted video clips [{id, sourceIn, sourceOut, timelineStart}]
 * @param {Function} options.getAudioClips - () => sorted audio clips [{id, sourceIn, sourceOut, timelineStart}]
 * @param {import('vue').Ref<string|null>} options.src - reactive video source URL
 */
export function useCanvasPlayback(canvasEl, { getVideoClips, getAudioClips, src }) {
    const isPlaying = ref(false);
    const currentTime = ref(0);
    const duration = ref(0);
    const activeClipId = ref(null);

    // Internal state
    let hiddenVideo = null;
    let audioCtx = null;
    let sourceNode = null;
    let clipGainNode = null;   // per-clip on/off (0 or 1)
    let volumeGainNode = null; // master volume (0–1)
    let animFrame = null;
    let useRVFC = false;
    let pendingSeekHandler = null;
    let transitionSeeking = false; // guard: true while seeking during clip transition

    // --- Clip helpers ---

    function clipTimelineEnd(clip) {
        return clip.timelineStart + (clip.sourceOut - clip.sourceIn);
    }

    function findClipAtTime(clips, timelineTime) {
        return clips.find(c => {
            const end = clipTimelineEnd(c);
            return timelineTime >= c.timelineStart && timelineTime < end;
        }) || null;
    }

    function findNextClip(clips, timelineTime) {
        return clips.find(c => c.timelineStart >= timelineTime) || null;
    }

    function timelineToSource(timelineTime, clip) {
        return clip.sourceIn + (timelineTime - clip.timelineStart);
    }

    function sourceToTimeline(sourceTime, clip) {
        return clip.timelineStart + (sourceTime - clip.sourceIn);
    }

    // --- Canvas drawing ---

    function drawFrame() {
        const canvas = toValue(canvasEl);
        if (!canvas || !hiddenVideo) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.drawImage(hiddenVideo, 0, 0, canvas.width, canvas.height);
    }

    function drawBlack() {
        const canvas = toValue(canvasEl);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    // --- Seeked listener management (prevents stale anonymous listener leaks) ---

    function registerSeekDraw() {
        if (!hiddenVideo) return;
        clearPendingSeek();

        pendingSeekHandler = function onSeek() {
            if (!hiddenVideo) return;
            hiddenVideo.removeEventListener('seeked', pendingSeekHandler);
            pendingSeekHandler = null;
            drawFrame();
        };
        hiddenVideo.addEventListener('seeked', pendingSeekHandler);
    }

    function clearPendingSeek() {
        if (pendingSeekHandler && hiddenVideo) {
            hiddenVideo.removeEventListener('seeked', pendingSeekHandler);
        }
        pendingSeekHandler = null;
    }

    // --- Audio gain control ---

    function updateAudioGain(timelineTime) {
        if (!clipGainNode) return;
        const audioClips = getAudioClips();
        const inAudioClip = findClipAtTime(audioClips, timelineTime);
        clipGainNode.gain.value = inAudioClip ? 1 : 0;
    }

    // --- Web Audio setup ---

    function ensureAudioContext() {
        if (audioCtx) return;
        if (!hiddenVideo) return;

        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        sourceNode = audioCtx.createMediaElementSource(hiddenVideo);
        clipGainNode = audioCtx.createGain();
        clipGainNode.gain.value = 0;
        volumeGainNode = audioCtx.createGain();
        volumeGainNode.gain.value = 1;
        sourceNode.connect(clipGainNode);
        clipGainNode.connect(volumeGainNode);
        volumeGainNode.connect(audioCtx.destination);
    }

    // --- Hidden video element ---

    function createHiddenVideo(videoSrc) {
        destroyHiddenVideo();

        hiddenVideo = document.createElement('video');
        hiddenVideo.preload = 'auto';
        hiddenVideo.playsInline = true;
        hiddenVideo.crossOrigin = 'anonymous';
        hiddenVideo.style.display = 'none';
        document.body.appendChild(hiddenVideo);

        useRVFC = 'requestVideoFrameCallback' in hiddenVideo;

        hiddenVideo.addEventListener('loadedmetadata', onLoadedMetadata);
        hiddenVideo.addEventListener('durationchange', onDurationChange);
        hiddenVideo.addEventListener('ended', onEnded);

        hiddenVideo.src = videoSrc;
    }

    function destroyHiddenVideo() {
        stopSync();
        clearPendingSeek();
        transitionSeeking = false;

        if (hiddenVideo) {
            hiddenVideo.pause();
            hiddenVideo.removeEventListener('loadedmetadata', onLoadedMetadata);
            hiddenVideo.removeEventListener('durationchange', onDurationChange);
            hiddenVideo.removeEventListener('ended', onEnded);
            hiddenVideo.removeAttribute('src');
            hiddenVideo.load();

            if (hiddenVideo.parentNode) {
                hiddenVideo.parentNode.removeChild(hiddenVideo);
            }
        }

        if (sourceNode) {
            sourceNode.disconnect();
            sourceNode = null;
        }
        if (clipGainNode) {
            clipGainNode.disconnect();
            clipGainNode = null;
        }
        if (volumeGainNode) {
            volumeGainNode.disconnect();
            volumeGainNode = null;
        }
        if (audioCtx) {
            audioCtx.close().catch(() => {});
            audioCtx = null;
        }

        hiddenVideo = null;
    }

    // --- Video event handlers ---

    function onLoadedMetadata() {
        if (hiddenVideo && hiddenVideo.duration && !isNaN(hiddenVideo.duration)) {
            duration.value = hiddenVideo.duration;
            drawInitialFrame();
        }
    }

    function onDurationChange() {
        if (hiddenVideo && hiddenVideo.duration && !isNaN(hiddenVideo.duration)) {
            duration.value = hiddenVideo.duration;
        }
    }

    function onEnded() {
        isPlaying.value = false;
        stopSync();
    }

    function drawInitialFrame() {
        if (!hiddenVideo) return;
        hiddenVideo.currentTime = 0;
        registerSeekDraw();
    }

    // --- Sync loop ---

    function startSync() {
        stopSync();

        if (useRVFC) {
            startRVFCSync();
        } else {
            startRAFSync();
        }
    }

    /**
     * requestVideoFrameCallback path — frame-accurate rendering.
     * The loop terminates when isPlaying becomes false (we stop re-registering).
     */
    function startRVFCSync() {
        function onVideoFrame(now, metadata) {
            if (!isPlaying.value || !hiddenVideo) return;

            const sourceTime = metadata.mediaTime;
            syncFrame(sourceTime);

            if (isPlaying.value && hiddenVideo) {
                hiddenVideo.requestVideoFrameCallback(onVideoFrame);
            }
        }

        if (hiddenVideo) {
            hiddenVideo.requestVideoFrameCallback(onVideoFrame);
        }
    }

    /**
     * rAF fallback path for browsers without requestVideoFrameCallback.
     * The loop terminates via the isPlaying guard (cancelAnimationFrame
     * is a best-effort optimization, not the sole termination signal).
     */
    function startRAFSync() {
        const tick = () => {
            if (!isPlaying.value || !hiddenVideo) return;

            const sourceTime = hiddenVideo.currentTime;
            syncFrame(sourceTime);

            if (isPlaying.value) {
                animFrame = requestAnimationFrame(tick);
            }
        };
        animFrame = requestAnimationFrame(tick);
    }

    /**
     * Core per-frame logic: maps source time to timeline, draws or transitions clips.
     * Called once per decoded frame (via rVFC) or once per display frame (via rAF).
     */
    function syncFrame(sourceTime) {
        // While seeking to a new clip, skip frames — the source time is stale
        // and would cause false "end of clip" detection.
        if (transitionSeeking) return;

        const videoClips = getVideoClips();
        let activeClip = videoClips.find(c => c.id === activeClipId.value);

        // Recovery: if activeClip is stale (deleted via split/undo), find clip at
        // current timeline time rather than source time to avoid ambiguity when
        // multiple clips share the same source range.
        if (!activeClip) {
            activeClip = findClipAtTime(videoClips, currentTime.value);
            if (activeClip) {
                activeClipId.value = activeClip.id;
            }
        }

        if (!activeClip) {
            stopPlayback();
            return;
        }

        // Extra guard: if sourceTime is completely outside this clip's source range,
        // a seek is likely still in progress. Skip this frame.
        if (sourceTime < activeClip.sourceIn - 0.15 || sourceTime > activeClip.sourceOut + 0.15) {
            return;
        }

        const timelineTime = sourceToTimeline(sourceTime, activeClip);
        currentTime.value = timelineTime;

        updateAudioGain(timelineTime);

        if (sourceTime >= activeClip.sourceIn && sourceTime < activeClip.sourceOut - 0.04) {
            drawFrame();
        } else {
            // Reached end of clip — transition to next
            const activeEnd = clipTimelineEnd(activeClip);
            const nextClip = videoClips.find(c =>
                c.timelineStart >= activeEnd - 0.05 && c.id !== activeClip.id
            );

            if (nextClip) {
                activeClipId.value = nextClip.id;
                currentTime.value = nextClip.timelineStart;
                transitionSeeking = true;
                hiddenVideo.currentTime = nextClip.sourceIn;
                hiddenVideo.addEventListener('seeked', function onTransitionSeek() {
                    hiddenVideo.removeEventListener('seeked', onTransitionSeek);
                    transitionSeeking = false;
                    // Draw the first frame of the new clip immediately
                    if (isPlaying.value) {
                        drawFrame();
                    }
                });
            } else {
                stopPlayback();
            }
        }
    }

    function stopSync() {
        if (animFrame !== null) {
            cancelAnimationFrame(animFrame);
            animFrame = null;
        }
        // rVFC callbacks stop when we stop re-registering (guarded by isPlaying).
    }

    function stopPlayback() {
        isPlaying.value = false;
        transitionSeeking = false;
        stopSync();
        if (hiddenVideo) {
            hiddenVideo.pause();
        }
        if (clipGainNode) {
            clipGainNode.gain.value = 0;
        }
    }

    // --- Public API ---

    function play() {
        if (!hiddenVideo) return;

        const videoClips = getVideoClips();
        const time = currentTime.value;
        let clip = findClipAtTime(videoClips, time);

        // If in a gap, snap to the next clip after this position
        if (!clip) {
            clip = findNextClip(videoClips, time);
            if (!clip) {
                // No clips ahead — only restart from beginning if we're at/past the last clip's end
                const lastClip = videoClips[videoClips.length - 1];
                if (lastClip && time >= clipTimelineEnd(lastClip) - 0.1) {
                    clip = videoClips[0];
                }
                if (!clip) return; // truly in a dead zone, don't play
            }
            currentTime.value = clip.timelineStart;
        }

        activeClipId.value = clip.id;
        const sourceTime = timelineToSource(currentTime.value, clip);
        hiddenVideo.currentTime = Math.max(0, sourceTime);

        // Ensure audio context (needs user gesture)
        ensureAudioContext();
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => {});
        }

        updateAudioGain(currentTime.value);

        isPlaying.value = true;
        hiddenVideo.play().then(() => {
            startSync();
        }).catch(() => {
            isPlaying.value = false;
        });
    }

    function pause() {
        isPlaying.value = false;
        transitionSeeking = false;
        stopSync();
        if (hiddenVideo) {
            hiddenVideo.pause();
        }
        if (clipGainNode) {
            clipGainNode.gain.value = 0;
        }
    }

    function togglePlay() {
        isPlaying.value ? pause() : play();
    }

    function seekTo(timelineTime) {
        currentTime.value = timelineTime;
        if (!hiddenVideo) return;

        const videoClips = getVideoClips();
        const clip = findClipAtTime(videoClips, timelineTime);

        if (clip) {
            activeClipId.value = clip.id;
            const sourceTime = timelineToSource(timelineTime, clip);
            hiddenVideo.currentTime = Math.max(0, sourceTime);
            registerSeekDraw();
        } else {
            // In a gap — draw black
            drawBlack();
            const next = findNextClip(videoClips, timelineTime);
            if (next) {
                activeClipId.value = next.id;
            }
        }
    }

    function stepFrame(direction = 1) {
        const frameDuration = 1 / ASSUMED_FPS;
        seekTo(currentTime.value + frameDuration * direction);
    }

    function setVolume(vol) {
        if (volumeGainNode) {
            volumeGainNode.gain.value = Math.max(0, Math.min(1, vol));
        }
    }

    function toggleMute() {
        if (hiddenVideo) {
            hiddenVideo.muted = !hiddenVideo.muted;
        }
    }

    // --- Watch source URL changes ---

    watch(() => toValue(src), (newSrc) => {
        if (newSrc) {
            createHiddenVideo(newSrc);
        } else {
            destroyHiddenVideo();
        }
    }, { immediate: true });

    // --- Cleanup ---

    onUnmounted(() => {
        destroyHiddenVideo();
    });

    return {
        isPlaying,
        currentTime,
        duration,
        activeClipId,
        play,
        pause,
        togglePlay,
        seekTo,
        stepFrame,
        setVolume,
        toggleMute,
    };
}
