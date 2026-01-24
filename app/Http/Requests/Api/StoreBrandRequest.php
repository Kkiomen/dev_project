<?php

namespace App\Http\Requests\Api;

use App\Enums\BrandTone;
use App\Enums\EmojiUsage;
use App\Enums\Industry;
use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', Rule::in(Industry::values())],
            'description' => ['nullable', 'string', 'max:5000'],

            // Target audience
            'target_audience' => ['nullable', 'array'],
            'target_audience.age_range' => ['nullable', 'string', 'max:50'],
            'target_audience.gender' => ['nullable', 'string', Rule::in(['all', 'male', 'female'])],
            'target_audience.interests' => ['nullable', 'array'],
            'target_audience.interests.*' => ['string', 'max:100'],
            'target_audience.pain_points' => ['nullable', 'array'],
            'target_audience.pain_points.*' => ['string', 'max:255'],

            // Voice settings
            'voice' => ['nullable', 'array'],
            'voice.tone' => ['nullable', 'string', Rule::in(BrandTone::values())],
            'voice.personality' => ['nullable', 'array'],
            'voice.personality.*' => ['string', 'max:50'],
            'voice.language' => ['nullable', 'string', 'size:2'],
            'voice.emoji_usage' => ['nullable', 'string', Rule::in(EmojiUsage::values())],

            // Content pillars
            'content_pillars' => ['nullable', 'array'],
            'content_pillars.*.name' => ['required', 'string', 'max:100'],
            'content_pillars.*.description' => ['nullable', 'string', 'max:500'],
            'content_pillars.*.percentage' => ['required', 'integer', 'min:0', 'max:100'],

            // Posting preferences
            'posting_preferences' => ['nullable', 'array'],
            'posting_preferences.frequency' => ['nullable', 'array'],
            'posting_preferences.frequency.*' => ['integer', 'min:0', 'max:28'],
            'posting_preferences.best_times' => ['nullable', 'array'],
            'posting_preferences.best_times.*' => ['array'],
            'posting_preferences.auto_schedule' => ['nullable', 'boolean'],

            // Platforms
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['array'],
            'platforms.*.enabled' => ['nullable', 'boolean'],
            'platforms.*.page_id' => ['nullable', 'string'],
            'platforms.*.account_id' => ['nullable', 'string'],
            'platforms.*.channel_id' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'content_pillars.*.percentage.max' => __('validation.custom.content_pillars.percentage_max'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure content pillars percentages sum to 100 if provided
        if ($this->has('content_pillars') && is_array($this->content_pillars)) {
            $pillars = $this->content_pillars;
            // Filter out any invalid entries
            $pillars = array_filter($pillars, fn($p) => isset($p['name']));
            $this->merge(['content_pillars' => array_values($pillars)]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that content pillars percentages sum to 100
            if ($this->has('content_pillars') && !empty($this->content_pillars)) {
                $totalPercentage = collect($this->content_pillars)->sum('percentage');
                if ($totalPercentage > 0 && $totalPercentage !== 100) {
                    $validator->errors()->add(
                        'content_pillars',
                        __('validation.custom.content_pillars.percentage_sum')
                    );
                }
            }
        });
    }
}
