<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VideoProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VideoProjectResource;
use App\Jobs\ExportTimelineJob;
use App\Jobs\RenderCaptionsJob;
use App\Jobs\RemoveSilenceJob;
use App\Jobs\TranscribeVideoJob;
use App\Models\Brand;
use App\Models\VideoProject;
use App\Services\CompositionService;
use App\Services\TranscriberService;
use App\Services\VideoEditorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class VideoProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = VideoProject::forUser($request->user())
            ->latest();

        if ($request->filled('brand_id')) {
            $brandId = Brand::where('public_id', $request->input('brand_id'))->value('id');
            $query->where('brand_id', $brandId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $projects = $query->paginate($request->input('per_page', 20));

        return VideoProjectResource::collection($projects);
    }

    public function store(Request $request): VideoProjectResource
    {
        $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-matroska', 'max:512000'],
            'title' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:10'],
            'brand_id' => ['nullable', 'exists:brands,public_id'],
            'caption_style' => ['nullable', 'string', 'in:hormozi,mrbeast,clean,bold,neon'],
        ]);

        $file = $request->file('video');
        $filename = time() . '_' . $file->hashName();
        $videoPath = 'video-projects/' . $request->user()->id . '/' . $filename;
        Storage::putFileAs(
            'video-projects/' . $request->user()->id,
            $file,
            $filename
        );

        $brandId = null;
        if ($request->filled('brand_id')) {
            $brandId = Brand::where('public_id', $request->input('brand_id'))->value('id');
        }

        $project = VideoProject::create([
            'user_id' => $request->user()->id,
            'brand_id' => $brandId,
            'title' => $request->input('title', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            'original_filename' => $file->getClientOriginalName(),
            'video_path' => $videoPath,
            'language' => $request->input('language'),
            'caption_style' => $request->input('caption_style', 'clean'),
            'caption_settings' => [
                'highlight_keywords' => false,
                'position' => 'bottom',
            ],
            'status' => VideoProjectStatus::Pending,
        ]);

        // Start transcription pipeline
        TranscribeVideoJob::dispatch($project);

        return new VideoProjectResource($project);
    }

    public function show(Request $request, string $publicId): VideoProjectResource
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        return new VideoProjectResource($project);
    }

    public function update(Request $request, string $publicId): VideoProjectResource
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if (!$project->canEdit()) {
            return response()->json(['message' => 'Project cannot be edited in current status'], 422);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'caption_style' => ['sometimes', 'string', 'in:hormozi,mrbeast,clean,bold,neon'],
            'caption_settings' => ['sometimes', 'array'],
            'caption_settings.highlight_keywords' => ['sometimes', 'boolean'],
            'caption_settings.position' => ['sometimes', 'string', 'in:bottom,top,center'],
            'caption_settings.font_size' => ['sometimes', 'integer', 'min:16', 'max:128'],
            'transcription' => ['sometimes', 'array'],
            'transcription.segments' => ['sometimes', 'array'],
        ]);

        $project->update($validated);

        return new VideoProjectResource($project->fresh());
    }

    public function destroy(Request $request, string $publicId): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if ($project->isProcessing()) {
            return response()->json(['message' => 'Cannot delete project while processing'], 422);
        }

        // Cleanup files
        if ($project->video_path) {
            Storage::delete($project->video_path);
        }
        if ($project->output_path) {
            Storage::delete($project->output_path);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    /**
     * Trigger caption rendering for a transcribed project.
     */
    public function render(Request $request, string $publicId): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if (!$project->canExport()) {
            return response()->json(['message' => 'Project must be transcribed before rendering'], 422);
        }

        RenderCaptionsJob::dispatch($project);

        return response()->json([
            'message' => 'Rendering started',
            'project' => new VideoProjectResource($project->fresh()),
        ]);
    }

    /**
     * Stream the original video for preview.
     */
    public function stream(Request $request, string $publicId)
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if (!$project->video_path || !Storage::exists($project->video_path)) {
            return response()->json(['message' => 'No video file available'], 404);
        }

        $path = Storage::path($project->video_path);
        $mime = Storage::mimeType($project->video_path);
        $size = Storage::size($project->video_path);

        $headers = [
            'Content-Type' => $mime ?: 'video/mp4',
            'Accept-Ranges' => 'bytes',
        ];

        // Support range requests for video seeking
        $range = $request->header('Range');
        if ($range) {
            preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);
            $start = (int) $matches[1];
            $end = $matches[2] !== '' ? (int) $matches[2] : $size - 1;
            $length = $end - $start + 1;

            return response()->stream(function () use ($path, $start, $length) {
                $stream = fopen($path, 'rb');
                fseek($stream, $start);
                echo fread($stream, $length);
                fclose($stream);
            }, 206, array_merge($headers, [
                'Content-Range' => "bytes {$start}-{$end}/{$size}",
                'Content-Length' => $length,
            ]));
        }

        return response()->file($path, $headers);
    }

    /**
     * Download the rendered output video.
     */
    public function download(Request $request, string $publicId)
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if (!$project->output_path || !Storage::exists($project->output_path)) {
            return response()->json(['message' => 'No output file available'], 404);
        }

        $filename = pathinfo($project->original_filename, PATHINFO_FILENAME) . '_captioned.mp4';

        return Storage::download($project->output_path, $filename);
    }

    /**
     * Get available caption styles from the video-editor service.
     */
    public function captionStyles(VideoEditorService $editor): JsonResponse
    {
        try {
            $styles = $editor->getCaptionStyles();
            return response()->json(['styles' => $styles]);
        } catch (\Exception $e) {
            return response()->json(['styles' => [], 'error' => 'Could not fetch styles'], 200);
        }
    }

    /**
     * Health check for video processing services.
     */
    public function health(TranscriberService $transcriber, VideoEditorService $editor): JsonResponse
    {
        return response()->json([
            'transcriber' => $transcriber->isHealthy(),
            'video_editor' => $editor->isHealthy(),
        ]);
    }

    /**
     * Get statistics for the video manager dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $query = VideoProject::forUser($request->user());

        if ($request->filled('brand_id')) {
            $brandId = Brand::where('public_id', $request->input('brand_id'))->value('id');
            $query->where('brand_id', $brandId);
        }

        $total = (clone $query)->count();

        $byStatus = [];
        foreach (VideoProjectStatus::cases() as $status) {
            $byStatus[$status->value] = (clone $query)->where('status', $status)->count();
        }

        $processingCount = (clone $query)->whereIn('status', [
            VideoProjectStatus::Uploading,
            VideoProjectStatus::Transcribing,
            VideoProjectStatus::Editing,
            VideoProjectStatus::Rendering,
        ])->count();

        $completedToday = (clone $query)
            ->where('status', VideoProjectStatus::Completed)
            ->whereDate('completed_at', today())
            ->count();

        $totalDuration = (clone $query)->sum('duration');

        return response()->json([
            'total' => $total,
            'by_status' => $byStatus,
            'processing_count' => $processingCount,
            'completed_today' => $completedToday,
            'total_duration' => round($totalDuration, 1),
        ]);
    }

    /**
     * Bulk delete video projects.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string'],
        ]);

        $projects = VideoProject::whereIn('public_id', $request->input('ids'))
            ->forUser($request->user())
            ->get();

        $deleted = 0;
        $skipped = 0;

        foreach ($projects as $project) {
            if ($project->isProcessing()) {
                $skipped++;
                continue;
            }

            if ($project->video_path) {
                Storage::delete($project->video_path);
            }
            if ($project->output_path) {
                Storage::delete($project->output_path);
            }

            $project->delete();
            $deleted++;
        }

        return response()->json([
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Bulk render video projects.
     */
    public function bulkRender(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string'],
        ]);

        $projects = VideoProject::whereIn('public_id', $request->input('ids'))
            ->forUser($request->user())
            ->get();

        $dispatched = 0;
        $skipped = 0;

        foreach ($projects as $project) {
            if (!$project->canExport()) {
                $skipped++;
                continue;
            }

            RenderCaptionsJob::dispatch($project);
            $dispatched++;
        }

        return response()->json([
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Get waveform peaks for a video project (proxy to Flask service).
     */
    public function waveform(Request $request, string $publicId, VideoEditorService $editor): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if (!$project->video_path || !Storage::exists($project->video_path)) {
            return response()->json(['message' => 'No video file available'], 404);
        }

        $cacheKey = "waveform_{$project->id}";
        $cached = cache($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        try {
            $data = $editor->getWaveformPeaks($project->video_path);
            cache([$cacheKey => $data], now()->addHours(24));
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Waveform extraction failed', 'peaks' => []], 200);
        }
    }

    /**
     * Get filmstrip thumbnails for a video project (proxy to Flask service).
     */
    public function thumbnails(Request $request, string $publicId, VideoEditorService $editor): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if (!$project->video_path || !Storage::exists($project->video_path)) {
            return response()->json(['message' => 'No video file available'], 404);
        }

        $cacheKey = "thumbnails_{$project->id}";
        $cached = cache($cacheKey);
        if ($cached) {
            return response()->json($cached);
        }

        try {
            $data = $editor->getThumbnails($project->video_path);
            cache([$cacheKey => $data], now()->addHours(24));
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Thumbnail generation failed', 'thumbnails' => []], 200);
        }
    }

    /**
     * Render composition: build render plan and dispatch export job.
     */
    public function renderComposition(Request $request, string $publicId, CompositionService $compositionService): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if ($project->isProcessing()) {
            return response()->json(['message' => 'Project is currently processing'], 422);
        }

        if (!$project->video_path || !Storage::exists($project->video_path)) {
            return response()->json(['message' => 'No video file available'], 422);
        }

        // Save composition if provided in request
        if ($request->has('composition')) {
            $composition = $request->input('composition');

            if (!$compositionService->validateComposition($composition)) {
                return response()->json(['message' => 'Invalid composition structure'], 422);
            }

            $project->update(['composition' => $composition]);
        }

        $composition = $project->composition;

        if (!$composition) {
            return response()->json(['message' => 'No composition to render'], 422);
        }

        $renderPlan = $compositionService->buildRenderPlan($composition);

        // Resolve media:// URIs to storage paths for image sources
        $mediaFiles = $this->resolveMediaSources($renderPlan['media_sources'] ?? [], $project);

        ExportTimelineJob::dispatch(
            $project,
            [], // edl not used in render plan mode
            $renderPlan,
            $mediaFiles,
        );

        return response()->json([
            'message' => 'Render started',
            'project' => new VideoProjectResource($project->fresh()),
        ]);
    }

    /**
     * Resolve media:// URIs to storage paths.
     */
    protected function resolveMediaSources(array $sources, VideoProject $project): array
    {
        $resolved = [];
        $allowedPrefix = "video-projects/{$project->user_id}/";

        foreach ($sources as $source) {
            if (!is_string($source) || !str_starts_with($source, 'media://')) {
                continue;
            }

            $storagePath = str_replace('media://', '', $source);

            if (!str_starts_with($storagePath, $allowedPrefix)) {
                continue;
            }

            if (Storage::exists($storagePath)) {
                $resolved[$source] = $storagePath;
            }
        }

        return $resolved;
    }

    /**
     * Export timeline using an Edit Decision List.
     */
    public function exportTimeline(Request $request, string $publicId): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        $request->validate([
            'edl' => ['required', 'array'],
            'edl.tracks' => ['required', 'array'],
        ]);

        if ($project->isProcessing()) {
            return response()->json(['message' => 'Project is currently processing'], 422);
        }

        ExportTimelineJob::dispatch($project, $request->input('edl'));

        return response()->json([
            'message' => 'Timeline export started',
            'project' => new VideoProjectResource($project->fresh()),
        ]);
    }

    /**
     * Save composition JSON for a video project.
     */
    public function saveComposition(Request $request, string $publicId, CompositionService $compositionService): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        $request->validate([
            'composition' => ['required', 'array'],
            'composition.version' => ['required', 'integer'],
            'composition.width' => ['required', 'integer', 'min:1'],
            'composition.height' => ['required', 'integer', 'min:1'],
            'composition.tracks' => ['required', 'array'],
        ]);

        $composition = $request->input('composition');

        if (!$compositionService->validateComposition($composition)) {
            return response()->json(['message' => 'Invalid composition structure'], 422);
        }

        $project->update(['composition' => $composition]);

        return response()->json([
            'message' => 'Composition saved',
            'project' => new VideoProjectResource($project->fresh()),
        ]);
    }

    /**
     * Build default composition from existing project data.
     */
    public function buildComposition(Request $request, string $publicId, CompositionService $compositionService): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        $composition = $compositionService->buildDefaultComposition($project);
        $project->update(['composition' => $composition]);

        return response()->json([
            'message' => 'Composition built',
            'project' => new VideoProjectResource($project->fresh()),
        ]);
    }

    /**
     * Detect silence regions in a video project's audio (returns JSON, no re-encoding).
     */
    public function detectSilence(Request $request, string $publicId, VideoEditorService $editor): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if (!$project->video_path || !Storage::exists($project->video_path)) {
            return response()->json(['message' => 'No video file available'], 404);
        }

        $request->validate([
            'min_silence' => ['sometimes', 'numeric', 'min:0.1', 'max:5.0'],
            'noise_db' => ['sometimes', 'integer', 'min:-50', 'max:-10'],
        ]);

        try {
            $result = $editor->detectSilence(
                $project->video_path,
                $request->input('min_silence', 0.5),
                $request->input('noise_db', -30),
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Silence detection failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove silence from a video project.
     */
    public function removeSilence(Request $request, string $publicId): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        if (!$project->hasTranscription()) {
            return response()->json(['message' => 'Project must have transcription before silence removal'], 422);
        }

        if ($project->isProcessing()) {
            return response()->json(['message' => 'Project is currently processing'], 422);
        }

        $request->validate([
            'min_silence' => ['sometimes', 'numeric', 'min:0.1', 'max:5.0'],
            'padding' => ['sometimes', 'numeric', 'min:0', 'max:1.0'],
        ]);

        RemoveSilenceJob::dispatch(
            $project,
            $request->input('min_silence', 0.5),
            $request->input('padding', 0.1),
        );

        return response()->json([
            'message' => 'Silence removal started',
            'project' => new VideoProjectResource($project->fresh()),
        ]);
    }

    /**
     * Serve an uploaded media file (image/audio) from a video project.
     */
    public function serveMedia(Request $request, string $publicId, string $filename)
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        $path = "video-projects/{$project->user_id}/media/{$filename}";

        if (!Storage::exists($path)) {
            return response()->json(['message' => 'Media file not found'], 404);
        }

        $fullPath = Storage::path($path);
        $mime = Storage::mimeType($path);

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Upload a media file (image/audio) to a video project for use in the NLE timeline.
     */
    public function uploadMedia(Request $request, string $publicId): JsonResponse
    {
        $project = VideoProject::where('public_id', $publicId)
            ->forUser($request->user())
            ->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,svg,mp3,wav,ogg,aac'],
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $directory = "video-projects/{$project->user_id}/media";
        $path = $file->storeAs($directory, $filename);

        $isImage = str_starts_with($file->getMimeType(), 'image/');

        return response()->json([
            'name' => $file->getClientOriginalName(),
            'type' => $isImage ? 'image' : 'audio',
            'source' => "media://{$path}",
            'size' => $file->getSize(),
        ]);
    }
}
