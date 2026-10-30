<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Domain\Costing\Models\DailyProductionSummary;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Production\Models\ProductionBatch;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DailyProductionCostingService
{
    public function __construct(private readonly DailyCostCalculator $calculator) {}

    public function calculateAndSave(DailyProductionSummary $summary): DailyProductionSummary
    {
        return DB::transaction(function () use ($summary): DailyProductionSummary {
            $summary = DailyProductionSummary::query()
                ->with('expenses')
                ->lockForUpdate()
                ->findOrFail($summary->getKey());

            if (! in_array($summary->status, ['draft', 'calculated'], true)) {
                throw new LogicException('Only draft or calculated daily summaries can be recalculated.');
            }

            $batches = ProductionBatch::query()
                ->where('tenant_id', $summary->tenant_id)
                ->where('plant_id', $summary->plant_id)
                ->whereDate('batch_date', $summary->production_date)
                ->whereIn('status', ['costed', 'closed'])
                ->get();

            $incidents = CostIncident::query()
                ->where('tenant_id', $summary->tenant_id)
                ->where('plant_id', $summary->plant_id)
                ->whereDate('occurred_at', $summary->production_date)
                ->whereNotIn('status', ['closed', 'resolved'])
                ->get();

            $costs = $this->calculator->calculate(
                $batches->map(fn (ProductionBatch $batch): array => ['net_cost' => $batch->net_cost])->all(),
                $summary->expenses->map(fn ($expense): array => ['amount' => $expense->amount])->all(),
                $incidents->map(fn (CostIncident $incident): array => ['cash_cost' => $incident->cash_cost])->all(),
                $batches->reduce(
                    fn (string $outputKg, ProductionBatch $batch): string => DecimalMath::add($outputKg, $batch->good_output_kg),
                    '0',
                ),
            );

            $summary->fill($costs);
            $summary->status = 'calculated';
            $summary->calculated_at = now();
            $summary->save();
            $summary->batches()->sync($batches->mapWithKeys(fn (ProductionBatch $batch): array => [
                $batch->id => [
                    'tenant_id' => $summary->tenant_id,
                    'plant_id' => $summary->plant_id,
                ],
            ])->all());

            return $summary->refresh();
        });
    }
}
