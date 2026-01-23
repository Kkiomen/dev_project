<?php

namespace App\Services;

use App\Enums\FieldType;
use App\Enums\FilterOperator;
use App\Models\Field;
use App\Models\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class RowFilterService
{
    /**
     * Apply filters to a rows query.
     */
    public function apply(Builder|HasMany $query, Table $table, array $filterData): Builder|HasMany
    {
        if (empty($filterData['conditions'])) {
            return $query;
        }

        $conjunction = strtolower($filterData['conjunction'] ?? 'and');
        $conditions = $filterData['conditions'];

        // Cache fields for lookup
        $fields = $table->fields->keyBy('public_id');

        $method = $conjunction === 'or' ? 'orWhere' : 'where';

        $query->where(function (Builder $q) use ($conditions, $fields, $method) {
            foreach ($conditions as $condition) {
                $fieldId = $condition['field_id'] ?? null;
                $operatorValue = $condition['operator'] ?? null;
                $value = $condition['value'] ?? null;

                if (!$fieldId || !$operatorValue) {
                    continue;
                }

                $field = $fields->get($fieldId);
                if (!$field) {
                    continue;
                }

                $operator = FilterOperator::tryFrom($operatorValue);
                if (!$operator) {
                    continue;
                }

                // Validate operator is allowed for field type
                $allowedOperators = FilterOperator::forFieldType($field->type);
                if (!in_array($operator, $allowedOperators)) {
                    continue;
                }

                $q->{$method}(function (Builder $subQuery) use ($field, $operator, $value) {
                    $this->applyCondition($subQuery, $field, $operator, $value);
                });
            }
        });

        return $query;
    }

    /**
     * Apply a single filter condition using EXISTS.
     */
    protected function applyCondition(Builder $query, Field $field, FilterOperator $operator, mixed $value): void
    {
        $valueColumn = $field->type->valueColumn();

        // Handle empty/not empty operators
        if ($operator === FilterOperator::IS_EMPTY) {
            $query->whereNotExists(function ($q) use ($field, $valueColumn) {
                $q->select(DB::raw(1))
                    ->from('cells')
                    ->whereColumn('cells.row_id', 'rows.id')
                    ->where('cells.field_id', $field->id)
                    ->whereNotNull("cells.{$valueColumn}");
            });
            return;
        }

        if ($operator === FilterOperator::IS_NOT_EMPTY) {
            $query->whereExists(function ($q) use ($field, $valueColumn) {
                $q->select(DB::raw(1))
                    ->from('cells')
                    ->whereColumn('cells.row_id', 'rows.id')
                    ->where('cells.field_id', $field->id)
                    ->whereNotNull("cells.{$valueColumn}");
            });
            return;
        }

        // Handle checkbox operators
        if ($operator === FilterOperator::IS_TRUE) {
            $query->whereExists(function ($q) use ($field) {
                $q->select(DB::raw(1))
                    ->from('cells')
                    ->whereColumn('cells.row_id', 'rows.id')
                    ->where('cells.field_id', $field->id)
                    ->where('cells.value_boolean', true);
            });
            return;
        }

        if ($operator === FilterOperator::IS_FALSE) {
            $query->where(function (Builder $subQuery) use ($field) {
                // Either cell doesn't exist or value is false
                $subQuery->whereNotExists(function ($q) use ($field) {
                    $q->select(DB::raw(1))
                        ->from('cells')
                        ->whereColumn('cells.row_id', 'rows.id')
                        ->where('cells.field_id', $field->id)
                        ->where('cells.value_boolean', true);
                });
            });
            return;
        }

        // All other operators use EXISTS with specific conditions
        $query->whereExists(function ($q) use ($field, $operator, $value, $valueColumn) {
            $q->select(DB::raw(1))
                ->from('cells')
                ->whereColumn('cells.row_id', 'rows.id')
                ->where('cells.field_id', $field->id);

            $this->applyOperatorCondition($q, $valueColumn, $operator, $value, $field);
        });
    }

    /**
     * Apply the specific operator condition to the query.
     */
    protected function applyOperatorCondition(
        \Illuminate\Database\Query\Builder $query,
        string $column,
        FilterOperator $operator,
        mixed $value,
        Field $field
    ): void {
        $fullColumn = "cells.{$column}";

        switch ($operator) {
            case FilterOperator::EQUALS:
                if ($field->type === FieldType::SELECT) {
                    // For select, value is stored in JSON
                    $query->whereRaw("JSON_EXTRACT({$fullColumn}, '$.id') = ?", [$value]);
                } else {
                    $query->where($fullColumn, '=', $value);
                }
                break;

            case FilterOperator::NOT_EQUALS:
                if ($field->type === FieldType::SELECT) {
                    $query->whereRaw("JSON_EXTRACT({$fullColumn}, '$.id') != ?", [$value]);
                } else {
                    $query->where($fullColumn, '!=', $value);
                }
                break;

            case FilterOperator::CONTAINS:
                if ($field->type === FieldType::JSON) {
                    $query->whereRaw("{$fullColumn} LIKE ?", ['%' . $value . '%']);
                } else {
                    $query->where($fullColumn, 'LIKE', '%' . $value . '%');
                }
                break;

            case FilterOperator::NOT_CONTAINS:
                $query->where($fullColumn, 'NOT LIKE', '%' . $value . '%');
                break;

            case FilterOperator::STARTS_WITH:
                $query->where($fullColumn, 'LIKE', $value . '%');
                break;

            case FilterOperator::ENDS_WITH:
                $query->where($fullColumn, 'LIKE', '%' . $value);
                break;

            case FilterOperator::GREATER_THAN:
                $query->where($fullColumn, '>', $value);
                break;

            case FilterOperator::LESS_THAN:
                $query->where($fullColumn, '<', $value);
                break;

            case FilterOperator::GREATER_OR_EQUAL:
                $query->where($fullColumn, '>=', $value);
                break;

            case FilterOperator::LESS_OR_EQUAL:
                $query->where($fullColumn, '<=', $value);
                break;

            case FilterOperator::BETWEEN:
                if (is_array($value) && count($value) >= 2) {
                    $query->whereBetween($fullColumn, [$value[0], $value[1]]);
                }
                break;

            case FilterOperator::BEFORE:
                $query->where($fullColumn, '<', $value);
                break;

            case FilterOperator::AFTER:
                $query->where($fullColumn, '>', $value);
                break;

            case FilterOperator::ON_OR_BEFORE:
                $query->where($fullColumn, '<=', $value);
                break;

            case FilterOperator::ON_OR_AFTER:
                $query->where($fullColumn, '>=', $value);
                break;

            case FilterOperator::IS_ANY_OF:
                if (is_array($value)) {
                    if ($field->type === FieldType::SELECT) {
                        $placeholders = implode(',', array_fill(0, count($value), '?'));
                        $query->whereRaw("JSON_EXTRACT({$fullColumn}, '$.id') IN ({$placeholders})", $value);
                    } else {
                        $query->whereIn($fullColumn, $value);
                    }
                }
                break;

            case FilterOperator::IS_NONE_OF:
                if (is_array($value)) {
                    if ($field->type === FieldType::SELECT) {
                        $placeholders = implode(',', array_fill(0, count($value), '?'));
                        $query->whereRaw("JSON_EXTRACT({$fullColumn}, '$.id') NOT IN ({$placeholders})", $value);
                    } else {
                        $query->whereNotIn($fullColumn, $value);
                    }
                }
                break;

            case FilterOperator::CONTAINS_ANY:
                // For multi-select: check if any of the values are in the JSON array
                if (is_array($value)) {
                    $query->where(function ($q) use ($fullColumn, $value) {
                        foreach ($value as $v) {
                            $q->orWhereRaw("JSON_CONTAINS({$fullColumn}, ?)", [json_encode(['id' => $v])]);
                        }
                    });
                }
                break;

            case FilterOperator::CONTAINS_ALL:
                // For multi-select: check if all values are in the JSON array
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $query->whereRaw("JSON_CONTAINS({$fullColumn}, ?)", [json_encode(['id' => $v])]);
                    }
                }
                break;
        }
    }
}
