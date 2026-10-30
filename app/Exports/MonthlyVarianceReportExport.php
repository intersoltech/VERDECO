<?php

declare(strict_types=1);

namespace App\Exports;

use App\Domain\Reporting\Models\FactMonthlyVariance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MonthlyVarianceReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private readonly ?int $plantId = null, private readonly ?string $month = null) {}

    public function title(): string
    {
        return 'Monthly Cost Variances';
    }

    public function headings(): array
    {
        return [
            'Plant Name',
            'Period Month',
            'Output (kg)',
            'Standard Cost ($)',
            'Actual Cost ($)',
            'Total Variance ($)',
            'Material Price Var ($)',
            'Material Usage Var ($)',
            'Labor Rate Var ($)',
            'Labor Efficiency Var ($)',
            'Utility Efficiency Var ($)',
            'Overhead Volume Var ($)',
            'Incident Var ($)',
        ];
    }

    public function collection()
    {
        $query = FactMonthlyVariance::query()->with('plant');

        if ($this->plantId) {
            $query->where('plant_id', $this->plantId);
        }
        if ($this->month) {
            $query->where('period_month', $this->month);
        }

        return $query->get()->map(function (FactMonthlyVariance $v) {
            return [
                $v->plant?->name ?? 'All Plants',
                $v->period_month,
                (float) $v->output_kg,
                (float) $v->standard_cost,
                (float) $v->actual_cost,
                (float) $v->total_variance,
                (float) $v->material_price_variance,
                (float) $v->material_usage_variance,
                (float) $v->labor_rate_variance,
                (float) $v->labor_efficiency_variance,
                (float) $v->utility_efficiency_variance,
                (float) $v->overhead_volume_variance,
                (float) $v->incident_variance,
            ];
        });
    }
}
