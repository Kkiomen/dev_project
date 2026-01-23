<?php

namespace App\Http\Requests\Api;

use App\Enums\FilterOperator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterRowsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Pagination
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],

            // Filters
            'filters' => ['nullable', 'array'],
            'filters.conjunction' => ['nullable', 'string', Rule::in(['and', 'or'])],
            'filters.conditions' => ['nullable', 'array'],
            'filters.conditions.*.field_id' => ['required_with:filters.conditions', 'string'],
            'filters.conditions.*.operator' => [
                'required_with:filters.conditions',
                'string',
                Rule::in(array_column(FilterOperator::cases(), 'value')),
            ],
            'filters.conditions.*.value' => ['nullable'],

            // Sort
            'sort' => ['nullable', 'array'],
            'sort.*.field_id' => ['required_with:sort', 'string'],
            'sort.*.direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Prepare the data for validation.
     * Handle JSON strings from query parameters.
     */
    protected function prepareForValidation(): void
    {
        // Parse filters from JSON string if needed
        if ($this->has('filters') && is_string($this->filters)) {
            $filters = json_decode($this->filters, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['filters' => $filters]);
            } else {
                // Remove invalid filter parameter so validation doesn't fail
                $this->request->remove('filters');
                $this->query->remove('filters');
            }
        }

        // Parse sort from JSON string if needed
        if ($this->has('sort') && is_string($this->sort)) {
            $sort = json_decode($this->sort, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['sort' => $sort]);
            } else {
                // Remove invalid sort parameter so validation doesn't fail
                $this->request->remove('sort');
                $this->query->remove('sort');
            }
        }
    }

    /**
     * Get validated filters data.
     */
    public function getFilters(): array
    {
        return $this->validated('filters', []);
    }

    /**
     * Get validated sort data.
     */
    public function getSort(): array
    {
        return $this->validated('sort', []);
    }

    /**
     * Get per page value with default.
     */
    public function getPerPage(): int
    {
        return $this->validated('per_page', 50);
    }
}
