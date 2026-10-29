<?php

declare(strict_types=1);

namespace App\Services\Costing;

final class DailyCostCalculator
{
    /**
     * @param  list<array<string, mixed>>  $batches
     * @param  list<array<string, mixed>>  $dailyExpenses
     * @return array<string, string>
     */
    public function calculate(array $batches, array $dailyExpenses = [], array $incidents = [], string $outputKg = '0'): array
    {
        $batchTotal = '0';
        foreach ($batches as $batch) {
            $batchTotal = bcadd($batchTotal, DecimalMath::toDecimal($batch['net_cost'] ?? '0'), 6);
        }

        $expenseTotal = '0';
        foreach ($dailyExpenses as $expense) {
            $expenseTotal = bcadd($expenseTotal, DecimalMath::toDecimal($expense['amount'] ?? '0'), 6);
        }

        $incidentTotal = '0';
        foreach ($incidents as $incident) {
            $incidentTotal = bcadd($incidentTotal, DecimalMath::toDecimal($incident['cash_cost'] ?? '0'), 6);
        }

        $actualTotal = DecimalMath::add($batchTotal, $expenseTotal, $incidentTotal);
        $costPerKg = '0';
        if (bccomp(DecimalMath::toDecimal($outputKg), '0', 6) > 0) {
            $costPerKg = DecimalMath::divide($actualTotal, $outputKg);
        }

        return [
            'batch_total_cost' => DecimalMath::normalize($batchTotal),
            'daily_expense_total' => DecimalMath::normalize($expenseTotal),
            'incident_total_cost' => DecimalMath::normalize($incidentTotal),
            'actual_total_cost' => DecimalMath::normalize($actualTotal),
            'output_kg' => DecimalMath::normalize(DecimalMath::toDecimal($outputKg)),
            'cost_per_kg' => DecimalMath::normalize($costPerKg),
        ];
    }
}
