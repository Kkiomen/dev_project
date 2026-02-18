import { defineStore } from 'pinia';
import axios from 'axios';

const generateId = (prefix = 'el') => `${prefix}_${Math.random().toString(36).substr(2, 8)}`;

export const useVideoEditorStore = defineStore('videoEditorNew', {
    state: () => ({
        projectId: null,
        project: null,
        composition: null,

        // UI state (not persisted)
        playhead: 0,
        zoom: 50,
        selectedElementIds: [],
        selectedTrackId: null,
        isPlaying: false,
        isDirty: false,
        inspectorTab: 'properties',
        snapEnabled: true,
        masterVolume: 1.0,

        // Cache
        waveformCache: {},
        thumbnailCache: {},
        uploadedMedia: [], // additional media items uploaded by user

        loading: false,
        saving: false,
        error: null,
    }),

    getters: {
        tracks: (s) => s.composition?.tracks || [],

        allElements(state) {
            if (!state.composition?.tracks) return [];
            return state.composition.tracks.flatMap((track) =>
                track.elements.map((el) => ({ ...el, trackId: track.id }))
            );
        },

        // Backward compat: first selected element id
        selectedElementId(state) {
            return state.selectedElementIds[0] || null;
        },

        selectedElement(state) {
            const id = state.selectedElementIds[0];
            if (!id || !state.composition?.tracks) return null;
            for (const track of state.composition.tracks) {
                const el = track.elements.find((e) => e.id === id);
                if (el) return el;
            }
            return null;
        },

        selectedElements(state) {
            if (!state.selectedElementIds.length || !state.composition?.tracks) return [];
            const ids = new Set(state.selectedElementIds);
            const result = [];
            for (const track of state.composition.tracks) {
                for (const el of track.elements) {
                    if (ids.has(el.id)) result.push(el);
                }
            }
            return result;
        },

        selectedTrack(state) {
            if (!state.composition?.tracks) return null;

            if (state.selectedTrackId) {
                return state.composition.tracks.find((t) => t.id === state.selectedTrackId) || null;
            }

            const firstId = state.selectedElementIds[0];
            if (firstId) {
                return (
                    state.composition.tracks.find((t) =>
                        t.elements.some((e) => e.id === firstId)
                    ) || null
                );
            }

            return null;
        },

        timelineDuration(state) {
            if (!state.composition?.tracks) return 0;
            let maxEnd = 0;
            for (const track of state.composition.tracks) {
                for (const el of track.elements) {
                    const end = (el.time || 0) + (el.duration || 0);
                    if (end > maxEnd) maxEnd = end;
                }
            }
            return Math.max(maxEnd, 10);
        },

        timelineWidth(state) {
            return this.timelineDuration * state.zoom + 200;
        },

        captions: (s) => s.composition?.captions || null,

        compositionWidth: (s) => s.composition?.width || 1080,
        compositionHeight: (s) => s.composition?.height || 1920,
    },

    actions: {
        // === Project ===

        async loadProject(publicId) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get(`/api/v1/video-projects/${publicId}`);
                this.project = data.data;
                this.projectId = publicId;

                if (data.data.composition) {
                    this.composition = data.data.composition;
                } else {
                    await this.buildComposition();
                }

                // Load waveform in background (fire-and-forget)
                this.loadWaveform(publicId);
            } catch (err) {
                this.error = err.response?.data?.message || err.message;
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async buildComposition() {
            try {
                const { data } = await axios.post(
                    `/api/v1/video-projects/${this.projectId}/build-composition`
                );
                this.project = data.project;
                this.composition = data.project.composition;
            } catch (err) {
                this.error = err.response?.data?.message || err.message;
            }
        },

        async saveComposition() {
            if (!this.composition || !this.projectId) return;
            this.saving = true;
            try {
                const { data } = await axios.put(
                    `/api/v1/video-projects/${this.projectId}/composition`,
                    { composition: this.composition }
                );
                this.isDirty = false;
                this.project = data.project;
            } catch (err) {
                this.error = err.response?.data?.message || err.message;
            } finally {
                this.saving = false;
            }
        },

        markDirty() {
            this.isDirty = true;
        },

        // === Tracks ===

        addTrack(type = 'overlay', name = null) {
            if (!this.composition) return;
            const trackNames = { video: 'Video', audio: 'Audio', overlay: 'Overlay' };
            const track = {
                id: generateId('track'),
                name: name || trackNames[type] || 'Track',
                type,
                muted: false,
                locked: false,
                visible: true,
                elements: [],
            };

            if (type === 'audio') {
                // Audio tracks always at the bottom
                this.composition.tracks.push(track);
            } else {
                // Visual tracks insert at position 0 (top of timeline = topmost visual layer)
                this.composition.tracks.unshift(track);
            }

            this.markDirty();
            return track;
        },

        removeTrack(trackId) {
            if (!this.composition) return;
            const idx = this.composition.tracks.findIndex((t) => t.id === trackId);
            if (idx !== -1) {
                this.composition.tracks.splice(idx, 1);
                if (this.selectedTrackId === trackId) {
                    this.selectedTrackId = null;
                    this.selectedElementIds = [];
                }
                this.markDirty();
            }
        },

        reorderTracks(fromIndex, toIndex) {
            if (!this.composition) return;
            const [track] = this.composition.tracks.splice(fromIndex, 1);
            this.composition.tracks.splice(toIndex, 0, track);
            this.markDirty();
        },

        moveTrackUp(trackId) {
            if (!this.composition) return;
            const idx = this.composition.tracks.findIndex(t => t.id === trackId);
            if (idx > 0) {
                this.reorderTracks(idx, idx - 1);
            }
        },

        moveTrackDown(trackId) {
            if (!this.composition) return;
            const idx = this.composition.tracks.findIndex(t => t.id === trackId);
            if (idx !== -1 && idx < this.composition.tracks.length - 1) {
                this.reorderTracks(idx, idx + 1);
            }
        },

        toggleTrackMute(trackId) {
            const track = this.composition?.tracks.find((t) => t.id === trackId);
            if (track) {
                track.muted = !track.muted;
                this.markDirty();
            }
        },

        toggleTrackLock(trackId) {
            const track = this.composition?.tracks.find((t) => t.id === trackId);
            if (track) {
                track.locked = !track.locked;
                this.markDirty();
            }
        },

        toggleTrackVisibility(trackId) {
            const track = this.composition?.tracks.find((t) => t.id === trackId);
            if (track) {
                track.visible = !track.visible;
                this.markDirty();
            }
        },

        // === Elements ===

        addElement(trackId, elementData) {
            const track = this.composition?.tracks.find((t) => t.id === trackId);
            if (!track) return null;

            // Type-specific defaults — height accounts for aspect ratio so
            // the bounding box appears visually square on screen.
            const type = elementData.type || 'video';
            let sizeDefaults;
            if (type === 'image' || type === 'text') {
                const compW = this.composition.width || 1920;
                const compH = this.composition.height || 1080;
                const widthPct = 50;
                const heightPct = Math.round((widthPct * compW / compH) * 100) / 100;
                sizeDefaults = { width: `${widthPct}%`, height: `${heightPct}%`, fit: 'contain' };
            } else {
                sizeDefaults = { width: '100%', height: '100%', fit: 'cover' };
            }

            const element = {
                id: generateId('el'),
                type: 'video',
                name: 'New Element',
                time: 0,
                duration: 5,
                source: null,
                trim_start: 0,
                trim_end: 0,
                x: '50%',
                y: '50%',
                ...sizeDefaults,
                rotation: 0,
                opacity: 1.0,
                volume: 1.0,
                fade_in: 0,
                fade_out: 0,
                effects: [],
                transition: null,
                modification_key: null,
                ...elementData,
            };

            track.elements.push(element);
            this.selectedElementIds = [element.id];
            this.selectedTrackId = trackId;
            this.markDirty();
            return element;
        },

        removeElement(elementId) {
            if (!this.composition) return;
            for (const track of this.composition.tracks) {
                const idx = track.elements.findIndex((e) => e.id === elementId);
                if (idx !== -1) {
                    track.elements.splice(idx, 1);
                    this.selectedElementIds = this.selectedElementIds.filter(id => id !== elementId);
                    this.markDirty();
                    return;
                }
            }
        },

        removeElements(ids) {
            if (!this.composition || !ids.length) return;
            const idSet = new Set(ids);
            for (const track of this.composition.tracks) {
                track.elements = track.elements.filter(e => !idSet.has(e.id));
            }
            this.selectedElementIds = this.selectedElementIds.filter(id => !idSet.has(id));
            this.markDirty();
        },

        moveElements(ids, deltaTime) {
            if (!this.composition) return;
            for (const id of ids) {
                const el = this._findElement(id);
                if (el) {
                    el.time = Math.max(0, el.time + deltaTime);
                }
            }
            this.markDirty();
        },

        moveElement(elementId, newTime) {
            const el = this._findElement(elementId);
            if (el) {
                el.time = Math.max(0, newTime);
                this.markDirty();
            }
        },

        moveElementToTrack(elementId, targetTrackId) {
            if (!this.composition) return;
            const targetTrack = this.composition.tracks.find((t) => t.id === targetTrackId);
            if (!targetTrack) return;

            for (const track of this.composition.tracks) {
                const idx = track.elements.findIndex((e) => e.id === elementId);
                if (idx !== -1) {
                    const [element] = track.elements.splice(idx, 1);
                    targetTrack.elements.push(element);
                    this.selectedTrackId = targetTrackId;
                    this.markDirty();
                    return;
                }
            }
        },

        trimElement(elementId, side, newValue) {
            const el = this._findElement(elementId);
            if (!el) return;

            if (side === 'start') {
                const delta = newValue - el.time;
                el.time = Math.max(0, newValue);
                el.duration = Math.max(0.1, el.duration - delta);
                el.trim_start = Math.max(0, (el.trim_start || 0) + delta);
            } else if (side === 'end') {
                el.duration = Math.max(0.1, newValue - el.time);
            }
            this.markDirty();
        },

        splitElementAtPlayhead() {
            if (!this.selectedElementId) return;
            const el = this._findElement(this.selectedElementId);
            if (!el) return;

            const splitTime = this.playhead;
            if (splitTime <= el.time || splitTime >= el.time + el.duration) return;

            const track = this.composition.tracks.find((t) =>
                t.elements.some((e) => e.id === el.id)
            );
            if (!track) return;

            const relativeTime = splitTime - el.time;
            const originalDuration = el.duration;

            // Shorten original
            el.duration = relativeTime;

            // Create second half
            const newElement = {
                ...JSON.parse(JSON.stringify(el)),
                id: generateId('el'),
                name: el.name + ' (split)',
                time: splitTime,
                duration: originalDuration - relativeTime,
                trim_start: (el.trim_start || 0) + relativeTime,
            };

            track.elements.push(newElement);
            this.markDirty();
        },

        updateElementProperty(elementId, key, value) {
            const el = this._findElement(elementId);
            if (el) {
                el[key] = value;
                this.markDirty();
            }
        },

        duplicateElement(elementId) {
            const el = this._findElement(elementId);
            if (!el) return;

            const track = this.composition.tracks.find((t) =>
                t.elements.some((e) => e.id === elementId)
            );
            if (!track) return;

            const copy = {
                ...JSON.parse(JSON.stringify(el)),
                id: generateId('el'),
                name: el.name + ' (copy)',
                time: el.time + el.duration,
            };

            track.elements.push(copy);
            this.selectedElementIds = [copy.id];
            this.markDirty();
            return copy;
        },

        // === Selection ===

        selectElement(elementId) {
            this.selectedElementIds = elementId ? [elementId] : [];
            if (elementId) {
                const track = this.composition?.tracks.find((t) =>
                    t.elements.some((e) => e.id === elementId)
                );
                if (track) this.selectedTrackId = track.id;
            }
        },

        toggleElementSelection(elementId) {
            const idx = this.selectedElementIds.indexOf(elementId);
            if (idx !== -1) {
                this.selectedElementIds.splice(idx, 1);
            } else {
                this.selectedElementIds.push(elementId);
            }
            if (elementId && this.selectedElementIds.includes(elementId)) {
                const track = this.composition?.tracks.find((t) =>
                    t.elements.some((e) => e.id === elementId)
                );
                if (track) this.selectedTrackId = track.id;
            }
        },

        selectAllElements() {
            if (!this.composition?.tracks) return;
            this.selectedElementIds = this.composition.tracks.flatMap(t =>
                t.elements.map(e => e.id)
            );
        },

        selectTrack(trackId) {
            this.selectedTrackId = trackId;
            this.selectedElementIds = [];
        },

        clearSelection() {
            this.selectedElementIds = [];
            this.selectedTrackId = null;
        },

        // === Composition Size ===

        setCompositionSize(width, height) {
            if (!this.composition) return;
            this.composition.width = width;
            this.composition.height = height;
            this.markDirty();
        },

        // === Playback ===

        play() {
            this.isPlaying = true;
        },

        pause() {
            this.isPlaying = false;
        },

        togglePlayback() {
            this.isPlaying = !this.isPlaying;
        },

        seekTo(time) {
            this.playhead = Math.max(0, time);
        },

        setZoom(pxPerSec) {
            this.zoom = Math.max(10, Math.min(200, pxPerSec));
        },

        setMasterVolume(vol) {
            this.masterVolume = Math.max(0, Math.min(1, vol));
        },

        // === Cache ===

        async uploadMedia(file) {
            if (!this.projectId) return null;
            const formData = new FormData();
            formData.append('file', file);
            try {
                const { data } = await axios.post(
                    `/api/v1/video-projects/${this.projectId}/upload-media`,
                    formData,
                    { headers: { 'Content-Type': 'multipart/form-data' } }
                );
                return data;
            } catch (err) {
                this.error = err.response?.data?.message || err.message;
                return null;
            }
        },

        async loadWaveform(publicId) {
            if (this.waveformCache[publicId]) return this.waveformCache[publicId];
            try {
                const { data } = await axios.get(
                    `/api/v1/video-projects/${publicId}/waveform`
                );
                this.waveformCache[publicId] = data.peaks || [];
                return this.waveformCache[publicId];
            } catch {
                return [];
            }
        },

        async loadThumbnails(publicId) {
            if (this.thumbnailCache[publicId]) return this.thumbnailCache[publicId];
            try {
                const { data } = await axios.get(
                    `/api/v1/video-projects/${publicId}/thumbnails`
                );
                this.thumbnailCache[publicId] = data.thumbnails || [];
                return this.thumbnailCache[publicId];
            } catch {
                return [];
            }
        },

        // === Internal helpers ===

        _findElement(elementId) {
            if (!this.composition?.tracks) return null;
            for (const track of this.composition.tracks) {
                const el = track.elements.find((e) => e.id === elementId);
                if (el) return el;
            }
            return null;
        },

        // === Reset ===

        $reset() {
            this.projectId = null;
            this.project = null;
            this.composition = null;
            this.playhead = 0;
            this.zoom = 50;
            this.selectedElementIds = [];
            this.selectedTrackId = null;
            this.isPlaying = false;
            this.isDirty = false;
            this.inspectorTab = 'properties';
            this.snapEnabled = true;
            this.masterVolume = 1.0;
            this.waveformCache = {};
            this.thumbnailCache = {};
            this.uploadedMedia = [];
            this.loading = false;
            this.saving = false;
            this.error = null;
        },
    },
});
