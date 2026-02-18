# Code Quality Analyzer Memory

## Project Architecture (confirmed)
- Laravel 12 + Vue 3 (Composition API) + Pinia + vue-i18n + Tailwind CSS 4
- All user-facing text via `t()` / `__()` — enforced strictly
- Stores use Pinia options API; composables use Vue Composition API
- AI keys: per-brand via `BrandAiKey::getKeyForProvider()`, never global

## NLE Video Editor (added 2026-02-17)
- Store: `resources/js/stores/videoEditor.js` — Pinia options API
- NLE clip model: `{ sourceIn, sourceOut, timelineStart }` — NO `start`, `end`, `trimStart`, `trimEnd`
- Composables in `resources/js/components/videoEditor/composables/`
- Key composables: `usePlayback.js`, `useEditorHistory.js`, `useTimeline.js`, `useClipInteraction.js`, `useWaveform.js`
- `useClipInteraction.js` is STALE — still uses old model fields (`start`, `trimStart`, `trimEnd`) and is not used by any active component (VideoClip/AudioWaveform handle interaction inline)
- `buildEDL()` in store has a misleading JSDoc comment — says it uses `trimStart/trimEnd` but the actual output correctly uses `start: clip.sourceIn, end: clip.sourceOut`

## Known Issues Found (2026-02-17 review — useCanvasPlayback era)
- `useClipInteraction.js`, `usePlayback.js`, `useWaveform.js` REMOVED (confirmed as of 2026-02-17 second review)
- `useCanvasPlayback.js` introduced — replaces direct video element approach
- `drawInitialFrame()` registers a `seeked` event listener inline but does NOT guard against hiddenVideo being destroyed before the seeked event fires — null-deref risk
- `seekTo()` registers a one-shot `seeked` listener but rapid back-to-back seeks will accumulate stale listeners that all fire (though self-removing, they may call `drawFrame()` after the video source changed)
- `findNextClip()` finds the FIRST clip with `timelineStart >= timelineTime` but clips array from getter is already sorted, so this is correct; however the function's name implies "next AFTER current" but it also returns the clip AT timelineTime — subtle edge case if gap-snapping lands exactly on clip start
- `onPause` and `onPlayEvent` handlers exist but are empty — unnecessary event listener registrations (2 extra events on every createHiddenVideo)
- `setVolume()` modifies `hiddenVideo.volume` not the gainNode — volume and audio-clip gain are two separate axes, comment acknowledges but it's a confusing half-implementation
- `stepFrame()` hardcodes 30fps — no FPS metadata from project
- `fullscreenchange` event NOT listened to in VideoPlayerPanel — `isFullscreen` ref goes stale if user exits fullscreen with Escape key
- `handleSeek` in VideoManagerEditorPage now correctly calls only `playback.seekTo()` (fixed since previous review); store sync happens via watch
- `trimClip` right-side now correctly clamps to `this.duration` (confirmed fixed)
- `buildEDL()` JSDoc still has misleading comment about trimStart/trimEnd

## Translation Keys
- `videoEditor.inspector.start` and `videoEditor.inspector.end` exist in en.json but are NOT used in InspectorPanel.vue (replaced by `timelineStart` and `sourceRange`) — stale keys, harmless but should be cleaned
- All active component strings are properly translated via `t()`

## Patterns
- Clip components (VideoClip, AudioWaveform, CaptionClip) handle drag/trim interaction inline via pointer events, emitting `move`/`trim` events up to TimelineContainer, which re-emits to VideoManagerEditorPage, which calls store actions — clean unidirectional data flow
- History: `useEditorHistory` uses command pattern; keyboard Ctrl+Z/Shift+Ctrl+Z handled in composable; Space/Delete/S/Arrows handled in page — no conflict
