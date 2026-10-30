<?php

declare(strict_types=1);

namespace App\Services\Costing;

use App\Domain\Production\Events\BatchClosed;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchMaterial;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ProductionBatchCostingService
{
    public function __construct(private readonly BatchCostCalculator $calculator) {}

    public function calculateAndSave(ProductionBatch $batch): ProductionBatch
    {
        return DB::transaction(function () use ($batch): ProductionBatch {
            $batch = ProductionBatch::query()
                ->with('materials')
                ->lockForUpdate()
                ->findOrFail($batch->getKey());

            if ($batch->status !== 'draft') {
                throw new LogicException('Only draft production batches can be recalculated.');
            }

            $costs = $this->calculator->calculate([
                'input_kg' => $batch->input_kg,
                'good_output_kg' => $batch->good_output_kg,
                'recovery_credit' => $batch->recovery_credit,
                'materials' => $batch->materials->map(fn (ProductionBatchMaterial $material): array => [
                    'kg' => $material->kg,
                    'unit_price' => $material->unit_price,
                ])->all(),
                'labor_regular_rate' => $batch->labor_regular_rate,
                'labor_overtime_rate' => $batch->labor_overtime_rate,
                'labor_regular_hours' => $batch->labor_regular_hours,
                'labor_overtime_hours' => $batch->labor_overtime_hours,
                'labor_overtime_multiplier' => $batch->labor_overtime_multiplier,
                'utility_kwh' => $batch->utility_kwh,
                'utility_rate' => $batch->utility_rate,
                'overhead_pool' => $batch->overhead_pool,
                'overhead_basis' => $batch->overhead_basis,
                'incident_cost' => $batch->incident_cost,
                'incident_allocation_rule' => $batch->incident_allocation_rule,
            ]);

            $batch->fill($costs);
            $batch->status = 'costed';
            $batch->calculated_at = now();
            $batch->save();

            BatchClosed::dispatch($batch);

            return $batch->refresh();
        });
    }
}
