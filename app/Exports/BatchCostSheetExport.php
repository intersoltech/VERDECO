<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Production\Models\ProductionBatch;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BatchCostSheetExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private readonly ProductionBatch $batch) {}

    public function title(): string
    {
        return 'Batch '.$this->batch->batch_number;
    }

    public function headings(): array
    {
        return [
            'Line Item / Cost Component',
            'Usage / Metric',
            'Unit',
            'Unit Rate ($)',
            'Total Amount ($)',
            'Cost / kg ($)',
            'Cost / lb ($)',
        ];
    }

    public function array(): array
    {
        $outKg = max(1.0, (float) $this->batch->good_output_kg);

        return [
            ['Batch Number', $this->batch->batch_number, '', '', '', '', ''],
            ['Plant', $this->batch->plant?->name ?? 'N/A', '', '', '', '', ''],
            ['Date', $this->batch->batch_date, '', '', '', '', ''],
            ['Status', strtoupper((string) $this->batch->status), '', '', '', '', ''],
            ['---', '---', '---', '---', '---', '---', '---'],
            ['Material Input', (float) $this->batch->input_kg, 'kg', '', '', '', ''],
            ['Good Output', (float) $this->batch->good_output_kg, 'kg', '', '', '', ''],
            ['Production Yield', (float) $this->batch->yield_percent, '%', '', '', '', ''],
            ['---', '---', '---', '---', '---', '---', '---'],
            [
                'Raw Material (Flake)',
                (float) $this->batch->input_kg,
                'kg',
                0.68,
                (float) $this->batch->material_cost,
                round((float) $this->batch->material_cost / $outKg, 4),
                round(((float) $this->batch->material_cost / $outKg) * 0.45359237, 4),
            ],
            [
                'Direct Labor',
                (float) $this->batch->labor_regular_hours + (float) $this->batch->labor_overtime_hours,
                'hours',
                (float) $this->batch->labor_regular_rate,
                (float) $this->batch->labor_cost,
                round((float) $this->batch->labor_cost / $outKg, 4),
                round(((float) $this->batch->labor_cost / $outKg) * 0.45359237, 4),
            ],
            [
                'Utilities (Electricity)',
                (float) $this->batch->utility_kwh,
                'kWh',
                (float) $this->batch->utility_rate,
                (float) $this->batch->utility_cost,
                round((float) $this->batch->utility_cost / $outKg, 4),
                round(((float) $this->batch->utility_cost / $outKg) * 0.45359237, 4),
            ],
            [
                'Factory Overhead',
                strtoupper((string) ($this->batch->overhead_basis ?? 'per_kg')),
                'basis',
                '',
                (float) $this->batch->overhead_cost,
                round((float) $this->batch->overhead_cost / $outKg, 4),
                round(((float) $this->batch->overhead_cost / $outKg) * 0.45359237, 4),
            ],
            [
                'Incident Cost Allocation',
                strtoupper((string) ($this->batch->incident_allocation_rule ?? 'direct_tag')),
                'rule',
                '',
                (float) $this->batch->incident_cost,
                round((float) $this->batch->incident_cost / $outKg, 4),
                round(((float) $this->batch->incident_cost / $outKg) * 0.45359237, 4),
            ],
            [
                'Less: Recovery Credit',
                'Scrap/Purge',
                'credit',
                '',
                -(float) $this->batch->recovery_credit,
                -round((float) $this->batch->recovery_credit / $outKg, 4),
                -round(((float) $this->batch->recovery_credit / $outKg) * 0.45359237, 4),
            ],
            ['---', '---', '---', '---', '---', '---', '---'],
            [
                'NET BATCH COST',
                '',
                '',
                '',
                (float) $this->batch->net_cost,
                (float) $this->batch->cost_per_kg,
                round((float) $this->batch->cost_per_kg * 0.45359237, 6),
            ],
        ];
    }
}
