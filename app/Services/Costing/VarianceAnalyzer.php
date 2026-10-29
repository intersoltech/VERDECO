<?php

declare(strict_types=1);

namespace App\Services\Costing;

final class VarianceAnalyzer
{
    /**
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $standard
     * @return array<string, string>
     */
    public function analyze(array $actual, array $standard): array
    {
        $actualMaterialKg = DecimalMath::toDecimal($actual['material_kg'] ?? '0');
        $standardMaterialKg = DecimalMath::toDecimal($standard['material_kg'] ?? '0');
        $actualMaterialRate = DecimalMath::toDecimal($actual['material_unit_price'] ?? '0');
        $standardMaterialRate = DecimalMath::toDecimal($standard['material_unit_price'] ?? '0');

        $materialPriceVariance = DecimalMath::multiply(
            DecimalMath::subtract($actualMaterialRate, $standardMaterialRate),
            $actualMaterialKg,
        );

        $materialUsageVariance = DecimalMath::multiply(
            DecimalMath::subtract($actualMaterialKg, $standardMaterialKg),
            $standardMaterialRate,
        );

        $actualLaborHours = DecimalMath::toDecimal($actual['labor_hours'] ?? '0');
        $standardLaborHours = DecimalMath::toDecimal($standard['labor_hours'] ?? '0');
        $actualLaborRate = DecimalMath::toDecimal($actual['labor_rate'] ?? '0');
        $standardLaborRate = DecimalMath::toDecimal($standard['labor_rate'] ?? '0');

        $laborRateVariance = DecimalMath::multiply(
            DecimalMath::subtract($actualLaborRate, $standardLaborRate),
            $actualLaborHours,
        );

        $laborEfficiencyVariance = DecimalMath::multiply(
            DecimalMath::subtract($actualLaborHours, $standardLaborHours),
            $standardLaborRate,
        );

        $actualKwhPerKg = DecimalMath::toDecimal($actual['kwh_per_kg'] ?? '0');
        $standardKwhPerKg = DecimalMath::toDecimal($standard['kwh_per_kg'] ?? '0');
        $outputKg = DecimalMath::toDecimal($actual['good_output_kg'] ?? '0');
        $utilityRate = DecimalMath::toDecimal($actual['utility_rate'] ?? '0');

        $utilityEfficiencyVariance = DecimalMath::multiply(
            DecimalMath::subtract($actualKwhPerKg, $standardKwhPerKg),
            DecimalMath::multiply($outputKg, $utilityRate)
        );

        $utilityEfficiencyVariance = DecimalMath::multiply(
            $utilityEfficiencyVariance,
            '10'
        );

        $actualOverhead = DecimalMath::toDecimal($actual['overhead_cost'] ?? '0');
        $standardOverhead = DecimalMath::toDecimal($standard['overhead_cost'] ?? '0');
        $overheadVariance = DecimalMath::subtract($actualOverhead, $standardOverhead);

        $actualIncident = DecimalMath::toDecimal($actual['incident_cost'] ?? '0');
        $standardIncident = DecimalMath::toDecimal($standard['incident_cost'] ?? '0');
        $incidentVariance = DecimalMath::subtract($actualIncident, $standardIncident);

        $actualTotal = DecimalMath::toDecimal($actual['total_cost'] ?? '0');
        $standardTotal = DecimalMath::toDecimal($standard['total_cost'] ?? '0');
        $totalVariance = DecimalMath::subtract($actualTotal, $standardTotal);

        return [
            'material_price_variance' => $materialPriceVariance,
            'material_usage_variance' => $materialUsageVariance,
            'labor_rate_variance' => $laborRateVariance,
            'labor_efficiency_variance' => $laborEfficiencyVariance,
            'utility_efficiency_variance' => $utilityEfficiencyVariance,
            'overhead_variance' => $overheadVariance,
            'incident_variance' => $incidentVariance,
            'total_variance' => $totalVariance,
        ];
    }
}
