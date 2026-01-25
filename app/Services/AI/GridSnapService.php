<?php

namespace App\Services\AI;

/**
 * Grid Snap Service - 8pt Grid System.
 *
 * Ensures all coordinates and dimensions are multiples of 8
 * for a cleaner, more professional look.
 *
 * Mathematical formula: P(x, y) = (n × 8, m × 8) where n, m ∈ ℤ
 */
class GridSnapService
{
    /**
     * Grid unit size in pixels.
     */
    protected int $gridUnit = 8;

    /**
     * Snap a single value to the grid.
     */
    public function snapToGrid(int|float $value): int
    {
        return (int) round($value / $this->gridUnit) * $this->gridUnit;
    }

    /**
     * Snap layer coordinates and dimensions to the grid.
     */
    public function snapLayer(array $layer): array
    {
        // Snap position
        if (isset($layer['x'])) {
            $layer['x'] = $this->snapToGrid($layer['x']);
        }
        if (isset($layer['y'])) {
            $layer['y'] = $this->snapToGrid($layer['y']);
        }

        // Snap dimensions (ensure minimum of 8)
        if (isset($layer['width'])) {
            $layer['width'] = max($this->gridUnit, $this->snapToGrid($layer['width']));
        }
        if (isset($layer['height'])) {
            $layer['height'] = max($this->gridUnit, $this->snapToGrid($layer['height']));
        }

        // Snap corner radius if present
        if (isset($layer['properties']['cornerRadius'])) {
            $layer['properties']['cornerRadius'] = $this->snapToGrid($layer['properties']['cornerRadius']);
        }

        // Snap padding if present
        if (isset($layer['properties']['padding'])) {
            $layer['properties']['padding'] = $this->snapToGrid($layer['properties']['padding']);
        }

        return $layer;
    }

    /**
     * Snap all layers to the grid.
     */
    public function snapAllLayers(array $layers): array
    {
        return array_map([$this, 'snapLayer'], $layers);
    }

    /**
     * Get the grid unit size.
     */
    public function getGridUnit(): int
    {
        return $this->gridUnit;
    }

    /**
     * Get valid grid values for a range.
     */
    public function getGridValues(int $min, int $max): array
    {
        $values = [];
        $current = $this->snapToGrid($min);

        while ($current <= $max) {
            $values[] = $current;
            $current += $this->gridUnit;
        }

        return $values;
    }

    /**
     * Check if a value is on the grid.
     */
    public function isOnGrid(int|float $value): bool
    {
        return $value % $this->gridUnit === 0;
    }
}
