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

## Full Composition Render Feature (2026-02-18 review)
- Files: `docker/video-editor/server.py` (new `/render-composition`), `app/Services/CompositionService.php` (`buildRenderPlan`, `buildVisualSegments`, `mergeAdjacentSegments`), `app/Services/VideoEditorService.php` (`renderComposition`), `app/Jobs/ExportTimelineJob.php` (dual-mode), `app/Http/Controllers/Api/V1/VideoProjectController.php` (`renderComposition`, `resolveMediaSources`)
- **Critical Bug (server.py L879):** Double-render logic error — when image source is missing, `seg["type"]` is mutated to `"black"` (L866), but then the fallback `if` at L879 checks the original `seg_type` variable (captured before the branch), so it evaluates `seg_type == "image" and not image_files.get(...)` which IS TRUE — both the mutation-to-black AND the fallback fire, but the fallback actually renders correctly. However the seg_file is written twice to concat list. Actually wait: the `seg_file` write to list is at L890 after both. The real issue: the fallback check at L879 is redundant and confusing — black segments ARE caught by `seg_type == "black"` initially but only if type was originally "black". If type was "image" with missing source, `seg_type` still == "image" at L879, so the condition `seg_type == "image" and not image_files.get(...)` correctly catches it. So functionally it works but there's dead code: `seg["type"] = "black"` mutation at L866 is never read again.
- **Bug (server.py L879):** `seg_file` appended to `segment_files` BEFORE the `continue` branch at L843-844 — if `seg_duration <= 0` we skip rendering but still append the seg_file path, so the cleanup will try to unlink a non-existent file (harmless due to `_cleanup` ignoring errors) and the path is never written to concat list (because L890 checks `os.path.exists(seg_file)`). So functionally safe but wastes list space.
- **Bug (ExportTimelineJob):** `$this->project->video_path` is used without null check — if project has no video_path, the VideoEditorService throws "Video file not found" which is caught and sets status to Failed. Acceptable but could be cleaner.
- **Security (controller resolveMediaSources):** `str_replace('media://', '', $source)` can produce paths like `../../../etc/passwd` if a user crafts a `media://` URI with traversal. The Storage::exists() call goes through Laravel's filesystem which is rooted to storage root, so traversal is blocked. But there is NO ownership check — any authenticated user could reference another user's file if they know the path. Missing: verify that `$storagePath` starts with `video-projects/{$project->user_id}/`.
- **SOLID (ExportTimelineJob):** Dual-mode flag (`$renderPlan !== null`) violates SRP — the job has two different responsibilities. Should be two separate job classes: `ExportTimelineJob` and `RenderCompositionJob`.
- **Controller:** `renderComposition` action is not guarded by `canExport()` / `canEdit()` — only checks `isProcessing()`. A project in `Pending` or `Transcribing` state with a manually-set composition could be rendered. Recommend adding a state guard.
- **CompositionService `buildRenderPlan`:** No PHPDoc on `buildVisualSegments` or `mergeAdjacentSegments` despite being non-trivial protected methods.
- **Missing class PHPDoc** on `CompositionService` and `VideoEditorService`.
- Route: POST `/api/v1/video-projects/{publicId}/render-composition` at api.php L752

## App Settings Feature (2026-02-20 review)
- Files: `database/migrations/2026_02_20_000001_create_app_settings_table.php`, `app/Models/AppSetting.php`, `app/Http/Middleware/CheckRegistrationEnabled.php`, `app/Http/Controllers/Admin/AppSettingsController.php`, `resources/js/stores/adminSettings.js`, `resources/js/pages/AdminSettingsPage.vue`
- **Critical Bug (AppSetting::getValue):** `Cache::remember` caches `null` when DB returns null AND default is null. The `$default` in the closure is applied before caching, so if DB row doesn't exist and no default is passed, `null` is cached and subsequent calls with different defaults still return cached `null`. `getBool()` calls `getValue($key)` with NO default, so if row doesn't exist, `null` is cached, then `getBool()` returns its `$default`. But this only matters if the DB row was deleted — seeded rows always exist. Low-risk but architecturally fragile.
- **Critical Bug (AppSetting::getValue):** `Cache::remember` caches the RESULT of the closure, which is `static::where('key', $key)->value('value') ?? $default`. If `$default` is non-null (e.g. passed as `true`), its stringified form gets cached. But `getBool()` calls `getValue($key)` WITHOUT a default, so `$default` is `null` in the closure — the `?? $default` part returns `null` — which IS cached. This means the cache never stores the `$default` of `getBool()` — only `getBool()` handles its own default. Behavior is correct but the two-level default system is confusing.
- **Bug (router/index.js):** `requiresAdmin` meta is set on admin routes but the `beforeEach` guard NEVER checks it — any logged-in user can navigate to `/admin/settings`. Backend is still protected, but the UI routes are not guarded.
- **Critical (Navigation.vue L289):** "Admin" section header label is hardcoded as the string `Admin` — not translated via `t()`. Minor in practice (it's a proper noun) but violates the project's strict no-hardcode rule.
- **API URL pattern:** Store correctly uses `/api/admin/settings` which maps through bootstrap.js interceptor to `/api/panel/admin/` for SPA — correct pattern.
- **DRY (AppSettingsController::update L33):** After `setValue()`, re-reads from DB (via `getBool()`) to build the response. Since `setValue()` already invalidates cache and `getBool()` re-queries, this is one extra query per updated key. Could return the validated input directly since we just wrote it.
- **Missing PHPDoc:** `AppSettingsController`, `AppSetting` model, `CheckRegistrationEnabled` — no class or method docblocks.
- **Translation:** All `adminSettings.*` keys exist in both `en.json` and `pl.json` — fully covered.
- **Backend translation:** `CheckRegistrationEnabled` uses `__('Registration is currently disabled.')` inline — not in any `lang/en/` or `lang/pl/` PHP file. Works because Laravel falls back to the string itself as the key, but it should be in `lang/en/auth.php` or `lang/en/app.php`.

## NLE Remove Silence Feature (2026-02-18 review)
- **Critical Issue:** Missing translation keys: `nle.timeline.removeSilence`, `nle.timeline.detectingSilence`, `nle.timeline.noSilenceDetected` — MUST be added to all locale files before deployment
- **Major Issues:**
  - `applySilenceRemoval()` algorithm returns 0/1 but semantics unclear (no composition vs. no applicable speech)
  - Error handling in `detectSilence()` returns null without distinguishing error types
  - Floating-point tolerance (0.01s) hardcoded without explanation
- **Minor Issues:**
  - Uses `JSON.parse(JSON.stringify())` for cloning (inefficient, should use structuredClone)
  - Missing error state clear on success
  - PHPDoc incomplete on controller methods
- **Implementation Quality:** Algorithm is fundamentally sound, grouping by source and computing timeline positions correctly. Needs refinement for edge cases and better error communication.
- Routes: POST `/api/v1/video-projects/{publicId}/detect-silence` and `{publicId}/remove-silence` defined in api.php line 745-746

## Auto-Discover Competitors Feature (2026-02-20 review)
- Files: `app/Services/Apify/CompetitorAnalysisService.php` (new methods), `app/Http/Controllers/Api/V1/CiCompetitorController.php` (new action), `routes/api.php` (POST `/discover-competitors` at L738), `resources/js/stores/competitiveIntelligence.js` (new state/action), `resources/js/components/ci/DiscoverCompetitorsModal.vue` (new), `resources/js/components/ci/CompetitorList.vue` (modified), `resources/js/components/ci/CiDashboard.vue` (modified)
- **Code Quality: EXCELLENT** — All SOLID principles followed, translations complete in en/pl, responsive design, error handling proper
- **Best Practices:** Service uses correct AI key pattern, controller properly guards with `authorize('update')`, store actions correct, Vue components use Composition API properly, all text translated via `t()`
- **Architecture:** Follows existing patterns — strategy in service, error code handling, modal component reuse, toast integration
- **Translation:** All `ci.discover.*` keys (14 keys) exist in both en.json and pl.json — fully covered
- **Zero Critical/Major Issues:** Implementation is production-ready
- Route: POST `/api/v1/brands/{brand}/ci/discover-competitors` with optional `platforms[]` param
