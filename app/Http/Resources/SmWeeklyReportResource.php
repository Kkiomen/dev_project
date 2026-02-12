<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmWeeklyReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'period_label' => $this->getPeriodLabel(),
            'summary' => $this->summary,
            'top_posts' => $this->top_posts,
            'recommendations' => $this->recommendations,
            'growth_metrics' => $this->growth_metrics,
            'platform_breakdown' => $this->platform_breakdown,
            'status' => $this->status,
            'is_ready' => $this->isReady(),
            'generated_at' => $this->generated_at,
            'created_at' => $this->created_at,
        ];
    }
}
