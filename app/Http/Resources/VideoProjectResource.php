<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'original_filename' => $this->original_filename,
            'language' => $this->language,
            'duration' => $this->duration,
            'width' => $this->width,
            'height' => $this->height,
            'caption_style' => $this->caption_style,
            'caption_settings' => $this->caption_settings,
            'transcription' => $this->transcription,
            'video_metadata' => $this->video_metadata,
            'is_processing' => $this->isProcessing(),
            'can_edit' => $this->canEdit(),
            'can_export' => $this->canExport(),
            'has_transcription' => $this->hasTranscription(),
            'error_message' => $this->error_message,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
