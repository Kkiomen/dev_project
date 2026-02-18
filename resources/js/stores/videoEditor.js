import { defineStore } from 'pinia';
import axios from 'axios';
import { useVideoManagerStore } from '@/stores/videoManager';

let nextClipId = 1;

function generateClipId() {
    return `clip_${nextClipId++}`;
}

export const useVideoEditorStore = defineStore('videoEditor', {
    state: () => ({
        project: null,
        loading: false,

        tracks: [
            { id: 'video', type: 'video', label: 'Video', muted: false, locked: false, clips: [] },
            { id: 'audio', type: 'audio', label: 'Audio', muted: false, locked: false, clips: [] },
            { id: 'captions', type: 'captions', label: 'Captions', muted: false, locked: false, clips: [] },
        ],

        playhead: 0,
        zoom: 50, // pixelsPerSecond
        selectedClipId: null,
        isPlaying: false,
        duration: 0,
        inspectorTab: 'properties',
        snapEnabled: true,

        // Waveform & thumbnails cache
        waveformPeaks: [],
        thumbnails: [],
        thumbnailsLoading: false,
        waveformLoading: false,

        error: null,
    }),

    getters: {
        selectedClip(state) {
            for (const track of state.tracks) {
                const clip = track.clips.find(c => c.id === state.selectedClipId);
                if (clip) return clip;
            }
            return null;
        },

        selectedTrack(state) {
            for (const track of state.tracks) {
                if (track.clips.find(c => c.id === state.selectedClipId)) return track;
            }
            return null;
        },

        videoTrack(state) {
            return state.tracks.find(t => t.id === 'video');
        },

        audioTrack(state) {
            return state.tracks.find(t => t.id === 'audio');
        },

        captionsTrack(state) {
            return state.tracks.find(t => t.id === 'captions');
        },

        /**
         * Visible ranges on the timeline — derived from clip timelineStart + duration.
         */
        visibleRanges(state) {
            const videoTrack = state.tracks.find(t => t.id === 'video');
            if (!videoTrack) return [];
            return videoTrack.clips
                .map(clip => ({
                    start: clip.timelineStart,
                    end: clip.timelineStart + (clip.sourceOut - clip.sourceIn),
                }))
                .filter(r => r.end > r.start)
                .sort((a, b) => a.start - b.start);
        },

        /**
         * Video clips sorted by timelineStart, for use by useCanvasPlayback.
         */
        sortedVideoClips(state) {
            const videoTrack = state.tracks.find(t => t.id === 'video');
            if (!videoTrack) return [];
            return [...videoTrack.clips].sort((a, b) => a.timelineStart - b.timelineStart);
        },

        /**
         * Audio clips sorted by timelineStart, for use by useCanvasPlayback.
         */
        sortedAudioClips(state) {
            const audioTrack = state.tracks.find(t => t.id === 'audio');
            if (!audioTrack) return [];
            return [...audioTrack.clips].sort((a, b) => a.timelineStart - b.timelineStart);
        },

        /**
         * Total timeline duration — max of all clip ends and source duration.
         */
        timelineDuration(state) {
            let maxEnd = state.duration;
            for (const track of state.tracks) {
                for (const clip of track.clips) {
                    const clipEnd = clip.timelineStart + (clip.sourceOut - clip.sourceIn);
                    if (clipEnd > maxEnd) maxEnd = clipEnd;
                }
            }
            return maxEnd;
        },

        timelineWidth(state) {
            return Math.ceil(this.timelineDuration * state.zoom) + 200;
        },
    },

    actions: {
        /**
         * Load project and initialize timeline tracks.
         */
        async loadProject(publicId) {
            this.loading = true;
            this.error = null;
            try {
                const videoManagerStore = useVideoManagerStore();
                await videoManagerStore.fetchProject(publicId);
                this.project = videoManagerStore.currentProject;

                if (this.project) {
                    this.duration = this.project.duration || 0;
                    this.initializeTracks();
                }
            } catch (e) {
                this.error = e.response?.data?.message || 'Failed to load project';
                throw e;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Build initial clips from project data using NLE model.
         * Each clip has: sourceIn, sourceOut (source file time), timelineStart (position on timeline).
         */
        initializeTracks() {
            nextClipId = 1;

            // Video clip — full duration
            this.tracks.find(t => t.id === 'video').clips = [{
                id: generateClipId(),
                type: 'video',
                sourceIn: 0,
                sourceOut: this.duration,
                timelineStart: 0,
                label: this.project?.title || 'Video',
            }];

            // Audio clip — mirrors video
            this.tracks.find(t => t.id === 'audio').clips = [{
                id: generateClipId(),
                type: 'audio',
                sourceIn: 0,
                sourceOut: this.duration,
                timelineStart: 0,
                label: 'Audio',
            }];

            // Caption clips — from transcription segments
            const segments = this.project?.transcription?.segments || [];
            this.tracks.find(t => t.id === 'captions').clips = segments.map(seg => ({
                id: generateClipId(),
                type: 'caption',
                sourceIn: seg.start,
                sourceOut: seg.end,
                timelineStart: seg.start,
                label: seg.text || '',
                text: seg.text || '',
            }));
        },

        /**
         * Select a clip by ID.
         */
        selectClip(clipId) {
            this.selectedClipId = clipId;
        },

        /**
         * Deselect current clip.
         */
        deselectClip() {
            this.selectedClipId = null;
        },

        /**
         * Split the selected clip at playhead position.
         * Correctly divides source range at the split point.
         */
        splitClipAtPlayhead() {
            if (!this.selectedClipId) return;

            const track = this.selectedTrack;
            const clip = this.selectedClip;
            if (!track || !clip) return;

            const splitTime = this.playhead;
            const clipEnd = clip.timelineStart + (clip.sourceOut - clip.sourceIn);

            // Playhead must be inside this clip's timeline range
            if (splitTime <= clip.timelineStart || splitTime >= clipEnd) return;

            const clipIndex = track.clips.findIndex(c => c.id === clip.id);

            // Calculate the source time at the split point
            const sourceTimeAtSplit = clip.sourceIn + (splitTime - clip.timelineStart);

            // Left part: same timelineStart, sourceIn→sourceTimeAtSplit
            const leftClip = {
                ...clip,
                sourceOut: sourceTimeAtSplit,
            };

            // Right part: starts at splitTime on timeline, sourceTimeAtSplit→sourceOut
            const rightClip = {
                ...clip,
                id: generateClipId(),
                sourceIn: sourceTimeAtSplit,
                timelineStart: splitTime,
            };

            track.clips.splice(clipIndex, 1, leftClip, rightClip);
            this.selectedClipId = rightClip.id;
        },

        /**
         * Trim a clip's edge.
         * side='left': adjust sourceIn + timelineStart (right edge stays).
         * side='right': adjust sourceOut (left edge stays).
         */
        trimClip(clipId, side, newEdgeTime) {
            for (const track of this.tracks) {
                const clip = track.clips.find(c => c.id === clipId);
                if (!clip) continue;

                const clipDuration = clip.sourceOut - clip.sourceIn;

                if (side === 'left') {
                    // newEdgeTime is the new timeline left edge position
                    const dt = newEdgeTime - clip.timelineStart;
                    const newSourceIn = clip.sourceIn + dt;
                    // Clamp: sourceIn can't go below 0, can't exceed sourceOut - 0.1
                    if (newSourceIn < 0 || newSourceIn >= clip.sourceOut - 0.1) break;
                    clip.sourceIn = newSourceIn;
                    clip.timelineStart = newEdgeTime;
                } else if (side === 'right') {
                    // newEdgeTime is the new timeline right edge position
                    const newSourceOut = clip.sourceIn + (newEdgeTime - clip.timelineStart);
                    // Clamp: sourceOut can't go below sourceIn + 0.1 or above source duration
                    if (newSourceOut <= clip.sourceIn + 0.1) break;
                    clip.sourceOut = Math.min(newSourceOut, this.duration);
                }
                break;
            }
        },

        /**
         * Move a clip to a new timeline position.
         * Only timelineStart changes — sourceIn/sourceOut stay intact.
         */
        moveClip(clipId, newTimelineStart) {
            for (const track of this.tracks) {
                const clip = track.clips.find(c => c.id === clipId);
                if (!clip) continue;

                clip.timelineStart = Math.max(0, newTimelineStart);
                break;
            }
        },

        /**
         * Delete a clip.
         */
        deleteClip(clipId) {
            for (const track of this.tracks) {
                const index = track.clips.findIndex(c => c.id === clipId);
                if (index !== -1) {
                    track.clips.splice(index, 1);
                    if (this.selectedClipId === clipId) {
                        this.selectedClipId = null;
                    }
                    break;
                }
            }
        },

        /**
         * Toggle mute on a track.
         */
        toggleTrackMute(trackId) {
            const track = this.tracks.find(t => t.id === trackId);
            if (track) track.muted = !track.muted;
        },

        /**
         * Toggle lock on a track.
         */
        toggleTrackLock(trackId) {
            const track = this.tracks.find(t => t.id === trackId);
            if (track) track.locked = !track.locked;
        },

        /**
         * Set zoom level (pixels per second).
         */
        setZoom(pxPerSec) {
            this.zoom = Math.max(10, Math.min(300, pxPerSec));
        },

        /**
         * Move playhead to a specific time, clamped to timelineDuration.
         */
        seekTo(time) {
            this.playhead = Math.max(0, Math.min(time, this.timelineDuration));
        },

        /**
         * Capture state snapshot for undo/redo.
         */
        captureState() {
            return JSON.parse(JSON.stringify({
                tracks: this.tracks,
                selectedClipId: this.selectedClipId,
                playhead: this.playhead,
            }));
        },

        /**
         * Apply a state snapshot (for undo/redo).
         */
        applyState(snapshot) {
            this.tracks = snapshot.tracks;
            this.selectedClipId = snapshot.selectedClipId;
            this.playhead = snapshot.playhead;
        },

        /**
         * Load waveform peaks from API.
         */
        async loadWaveform(publicId) {
            this.waveformLoading = true;
            try {
                const response = await axios.get(`/api/v1/video-projects/${publicId}/waveform`);
                this.waveformPeaks = response.data.peaks || [];
            } catch {
                this.waveformPeaks = [];
            } finally {
                this.waveformLoading = false;
            }
        },

        /**
         * Load thumbnails from API.
         */
        async loadThumbnails(publicId) {
            this.thumbnailsLoading = true;
            try {
                const response = await axios.get(`/api/v1/video-projects/${publicId}/thumbnails`);
                this.thumbnails = response.data.thumbnails || [];
            } catch {
                this.thumbnails = [];
            } finally {
                this.thumbnailsLoading = false;
            }
        },

        /**
         * Build an EDL (Edit Decision List) from current timeline state.
         * Maps NLE model to backend-compatible format: { start: sourceIn, end: sourceOut, trimStart: 0, trimEnd: 0 }
         * sorted by timelineStart so backend processes clips in timeline order.
         */
        buildEDL() {
            const edl = { tracks: [] };
            for (const track of this.tracks) {
                const sortedClips = [...track.clips].sort((a, b) => a.timelineStart - b.timelineStart);
                edl.tracks.push({
                    id: track.id,
                    type: track.type,
                    muted: track.muted,
                    clips: sortedClips.map(clip => ({
                        id: clip.id,
                        type: clip.type,
                        start: clip.sourceIn,
                        end: clip.sourceOut,
                        trimStart: 0,
                        trimEnd: 0,
                        text: clip.text || undefined,
                    })),
                });
            }
            return edl;
        },

        /**
         * Export timeline via API.
         */
        async exportTimeline(publicId) {
            const edl = this.buildEDL();
            const response = await axios.post(`/api/v1/video-projects/${publicId}/export-timeline`, { edl });
            return response.data;
        },

        /**
         * Reset store state.
         */
        $reset() {
            this.project = null;
            this.loading = false;
            this.tracks = [
                { id: 'video', type: 'video', label: 'Video', muted: false, locked: false, clips: [] },
                { id: 'audio', type: 'audio', label: 'Audio', muted: false, locked: false, clips: [] },
                { id: 'captions', type: 'captions', label: 'Captions', muted: false, locked: false, clips: [] },
            ];
            this.playhead = 0;
            this.zoom = 50;
            this.selectedClipId = null;
            this.isPlaying = false;
            this.duration = 0;
            this.inspectorTab = 'properties';
            this.snapEnabled = true;
            this.waveformPeaks = [];
            this.thumbnails = [];
            this.error = null;
        },
    },
});
