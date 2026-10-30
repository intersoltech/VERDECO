<?php

declare(strict_types=1);

namespace App\Services\Costing;

final class BatchCostCalculator
{
    public function __construct(
        private readonly OverheadAllocator $overheadAllocator = new OverheadAllocator,
        private readonly IncidentCostAllocator $incidentAllocator = new IncidentCostAllocator,
    ) {}

    /**
     * @param  array<string, mixed>  $batch
     * @return array<string, string>
     */
    public function calculate(array $batch): array
    {
        $inputKg = DecimalMath::toDecimal($batch['input_kg'] ?? '0');
        $goodOutputKg = DecimalMath::toDecimal($batch['good_output_kg'] ?? '0');
        $recoveryCredit = DecimalMath::toDecimal($batch['recovery_credit'] ?? '0');

        $materialCost = '0';
        foreach ($batch['materials'] ?? [] as $material) {
            $kg = DecimalMath::toDecimal($material['kg'] ?? '0');
            $unitPrice = DecimalMath::toDecimal($material['unit_price'] ?? '0');
            $materialCost = bcadd($materialCost, bcmul($kg, $unitPrice, 6), 6);
        }

        $regularRate = DecimalMath::toDecimal($batch['labor_regular_rate'] ?? '0');
        $overtimeRate = DecimalMath::toDecimal($batch['labor_overtime_rate'] ?? '0');
        $regularHours = DecimalMath::toDecimal($batch['labor_regular_hours'] ?? '0');
        $overtimeHours = DecimalMath::toDecimal($batch['labor_overtime_hours'] ?? '0');
        $overtimeMultiplier = DecimalMath::toDecimal($batch['labor_overtime_multiplier'] ?? '1');

        $regularLaborCost = DecimalMath::multiply($regularHours, $regularRate);
        $overtimeLaborCost = DecimalMath::multiply(
            DecimalMath::multiply($overtimeHours, $overtimeRate),
            $overtimeMultiplier,
        );
        $laborCost = DecimalMath::add($regularLaborCost, $overtimeLaborCost);

        $utilityKwh = DecimalMath::toDecimal($batch['utility_kwh'] ?? '0');
        $utilityRate = DecimalMath::toDecimal($batch['utility_rate'] ?? '0');
        $utilityCost = DecimalMath::multiply($utilityKwh, $utilityRate);

        $overheadPool = DecimalMath::toDecimal($batch['overhead_pool'] ?? '0');
        $overheadBasis = (string) ($batch['overhead_basis'] ?? 'per_kg');
        $overheadContext = $batch['overhead_context'] ?? ['kg' => $goodOutputKg];
        $overheadCost = $this->overheadAllocator->allocateForBatch($overheadPool, $overheadBasis, $overheadContext);

        $incidentCost = $this->incidentAllocator->allocate(
            DecimalMath::toDecimal($batch['incident_cost'] ?? '0'),
            (string) ($batch['incident_allocation_rule'] ?? 'direct_tag'),
            $batch['incident_context'] ?? [
                'kg' => $goodOutputKg,
                'matched' => true,
                'total_kg' => $goodOutputKg,
            ],
        );

        $totalCost = DecimalMath::add($materialCost, $laborCost, $utilityCost, $overheadCost, $incidentCost);
        $netCost = DecimalMath::subtract($totalCost, $recoveryCredit);

        $yieldPercent = '0';
        if (bccomp($inputKg, '0', 6) > 0) {
            $yieldPercent = DecimalMath::multiply(DecimalMath::divide($goodOutputKg, $inputKg), '100');
        }

        $costPerKg = '0';
        if (bccomp($goodOutputKg, '0', 6) > 0) {
            $costPerKg = DecimalMath::divide($netCost, $goodOutputKg);
        }

        return [
            'material_cost' => DecimalMath::normalize($materialCost),
            'labor_cost' => DecimalMath::normalize($laborCost),
            'utility_cost' => DecimalMath::normalize($utilityCost),
            'overhead_cost' => DecimalMath::normalize($overheadCost),
            'incident_cost' => DecimalMath::normalize($incidentCost),
            'recovery_credit' => DecimalMath::normalize($recoveryCredit),
            'total_cost' => DecimalMath::normalize($totalCost),
            'net_cost' => DecimalMath::normalize($netCost),
            'yield_percent' => DecimalMath::normalize($yieldPercent),
            'cost_per_kg' => DecimalMath::normalize($costPerKg),
        ];
    }
}
