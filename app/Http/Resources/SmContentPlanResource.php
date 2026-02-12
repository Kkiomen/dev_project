<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmContentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'month' => $this->month,
            'year' => $this->year,
            'period_label' => $this->getPeriodLabel(),
            'status' => $this->status,
            'summary' => $this->summary,
            'total_slots' => $this->total_slots,
            'completed_slots' => $this->completed_slots,
            'completion_percentage' => $this->getCompletionPercentage(),
            'generated_at' => $this->generated_at,
            'strategy' => $this->when(
                $this->relationLoaded('strategy') && $this->strategy,
                fn () => new SmStrategyResource($this->strategy)
            ),
            'slots' => $this->when(
                $this->relationLoaded('slots'),
                fn () => SmContentPlanSlotResource::collection($this->slots)
            ),
            'created_at' => $this->created_at,
        ];
    }
}
