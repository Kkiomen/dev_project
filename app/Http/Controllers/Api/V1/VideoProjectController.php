<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VideoProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VideoProjectResource;
use App\Jobs\RenderCaptionsJob;
use App\Jobs\RemoveSilenceJob;
use App\Jobs\TranscribeVideoJob;
use App\Models\VideoProject;
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
            $query->where('brand_id', $request->input('brand_id'));
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
            'brand_id' => ['nullable', 'exists:brands,id'],
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

        $project = VideoProject::create([
            'user_id' => $request->user()->id,
            'brand_id' => $request->input('brand_id'),
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
            $query->where('brand_id', $request->input('brand_id'));
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
}
