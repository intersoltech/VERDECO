<?php

declare(strict_types=1);

namespace App\Services\Costing\Contracts;

interface VarianceStrategyInterface
{
    /**
     * Unique identifier code for the variance driver (e.g. material_price, material_usage, labor_rate, labor_efficiency, utility_efficiency, overhead_volume).
     */
    public function key(): string;

    /**
     * Human-readable label for the variance driver.
     */
    public function label(): string;

    /**
     * Calculate variance between actual and standard cost parameters.
     * Favorable variance is negative (cost below standard); unfavorable is positive (cost above standard).
     *
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $standard
     */
    public function calculate(array $actual, array $standard): string;
}
