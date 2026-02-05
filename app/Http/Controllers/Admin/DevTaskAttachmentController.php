<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DevTaskAttachmentResource;
use App\Models\DevTask;
use App\Models\DevTaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class DevTaskAttachmentController extends Controller
{
    public function index(DevTask $task)
    {
        $attachments = $task->attachments()
            ->with('uploader')
            ->ordered()
            ->get();

        return DevTaskAttachmentResource::collection($attachments);
    }

    public function store(Request $request, DevTask $task)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:20480', // 20MB max
        ]);

        $file = $validated['file'];
        $filename = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        $path = $file->store('dev-task-attachments/' . $task->public_id, 'public');

        $width = null;
        $height = null;
        $thumbnailPath = null;

        if (str_starts_with($mimeType, 'image/')) {
            try {
                $image = Image::read($file->getRealPath());
                $width = $image->width();
                $height = $image->height();

                if ($width > 200 || $height > 200) {
                    $thumbnail = $image->scaleDown(200, 200);
                    $thumbnailPath = 'dev-task-attachments/' . $task->public_id . '/thumbs/' . basename($path);
                    Storage::disk('public')->put($thumbnailPath, $thumbnail->toJpeg(80));
                }
            } catch (\Exception $e) {
                // Ignore image processing errors
            }
        }

        $maxPosition = $task->attachments()->max('position') ?? -1;

        $attachment = $task->attachments()->create([
            'filename' => $filename,
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $mimeType,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'thumbnail_path' => $thumbnailPath,
            'uploaded_by' => auth()->id(),
            'position' => $maxPosition + 1,
        ]);

        $task->logs()->create([
            'type' => 'attachment_added',
            'content' => "Attachment added: {$filename}",
            'user_id' => auth()->id(),
            'metadata' => [
                'attachment_id' => $attachment->public_id,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size' => $size,
            ],
        ]);

        $attachment->load('uploader');

        return new DevTaskAttachmentResource($attachment);
    }

    public function destroy(DevTask $task, DevTaskAttachment $attachment)
    {
        $this->ensureAttachmentBelongsToTask($task, $attachment);

        $filename = $attachment->filename;
        $attachment->delete();

        $task->logs()->create([
            'type' => 'attachment_deleted',
            'content' => "Attachment deleted: {$filename}",
            'user_id' => auth()->id(),
            'metadata' => [
                'filename' => $filename,
            ],
        ]);

        return response()->json(['message' => 'Attachment deleted']);
    }

    public function reorder(Request $request, DevTask $task)
    {
        $validated = $request->validate([
            'attachment_ids' => 'required|array',
            'attachment_ids.*' => 'required|string',
        ]);

        foreach ($validated['attachment_ids'] as $position => $publicId) {
            DevTaskAttachment::where('dev_task_id', $task->id)
                ->where('public_id', $publicId)
                ->update(['position' => $position]);
        }

        return DevTaskAttachmentResource::collection(
            $task->attachments()->with('uploader')->ordered()->get()
        );
    }

    protected function ensureAttachmentBelongsToTask(DevTask $task, DevTaskAttachment $attachment): void
    {
        if ($attachment->dev_task_id !== $task->id) {
            abort(404, 'Attachment not found for this task');
        }
    }
}
