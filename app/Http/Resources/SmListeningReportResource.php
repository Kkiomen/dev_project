<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmListeningReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'period_label' => $this->getPeriodLabel(),
            'share_of_voice' => $this->share_of_voice,
            'sentiment_breakdown' => $this->sentiment_breakdown,
            'top_mentions' => $this->top_mentions,
            'trending_keywords' => $this->trending_keywords,
            'ai_summary' => $this->ai_summary,
            'status' => $this->status,
            'is_ready' => $this->isReady(),
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
        ];
    }
}
