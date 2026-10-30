<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Costing\Models\Budget;
use App\Domain\Organization\Models\Plant;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Domain\Reporting\Models\FactMonthlyVariance;
use App\Services\Reporting\DashboardDisplayHelper;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class GroupBenchmarkTableWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Plant Performance Benchmark (Side-by-Side)';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $query = Plant::query()->where('is_active', true);

        if (! $user?->hasRole('admin')) {
            $query->whereKey($user?->accessiblePlantIds() ?? []);
        }

        $currentMonth = Carbon::now()->startOfMonth()->toDateString();
        $daysInMonth = Carbon::now()->daysInMonth;
        $dayOfMonth = max(1, Carbon::now()->day);
        $displayUnit = DashboardDisplayHelper::currentUnit();

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Plant')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('costing_mode')
                    ->label('Cost Model')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'batch' => 'info',
                        'daily' => 'warning',
                        'both' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('mtd_output')
                    ->label('MTD Output ('.strtoupper($displayUnit).')')
                    ->state(function (Plant $plant) use ($currentMonth, $displayUnit): string {
                        $monthly = FactMonthlyVariance::where('plant_id', $plant->id)
                            ->where('period_month', $currentMonth)
                            ->first();
                        $outputKg = (float) ($monthly?->output_kg ?? 0);
                        $converted = DashboardDisplayHelper::convertWeight($outputKg, $displayUnit);

                        return number_format($converted, 0).' '.$displayUnit;
                    }),

                Tables\Columns\TextColumn::make('cost_per_unit')
                    ->label('Cost / '.strtoupper($displayUnit))
                    ->state(function (Plant $plant) use ($currentMonth, $displayUnit): string {
                        $monthly = FactMonthlyVariance::where('plant_id', $plant->id)
                            ->where('period_month', $currentMonth)
                            ->first();
                        $outputKg = (float) ($monthly?->output_kg ?? 0);
                        $cost = (float) ($monthly?->actual_cost ?? 0);
                        $costPerKg = $outputKg > 0 ? ($cost / $outputKg) : 0.0;
                        $converted = DashboardDisplayHelper::convertUnitCost($costPerKg, $displayUnit);

                        return '$'.number_format($converted, 4);
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        $val = (float) filter_var($state, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

                        return $val <= 1.00 ? 'success' : 'danger';
                    }),

                Tables\Columns\TextColumn::make('yield_percent')
                    ->label('Yield %')
                    ->state(function (Plant $plant) use ($currentMonth): string {
                        $kpis = FactDailyPlantKpi::where('plant_id', $plant->id)
                            ->whereDate('production_date', '>=', $currentMonth);
                        $in = (float) (clone $kpis)->sum('input_kg');
                        $out = (float) (clone $kpis)->sum('good_output_kg');
                        $yield = $in > 0 ? ($out / $in) * 100 : 92.5;

                        return number_format($yield, 2).' %';
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        $val = (float) rtrim($state, ' %');

                        return $val >= 92.0 ? 'success' : ($val >= 88.0 ? 'warning' : 'danger');
                    }),

                Tables\Columns\TextColumn::make('mtd_budget_status')
                    ->label('MTD vs Budget')
                    ->state(function (Plant $plant) use ($currentMonth): string {
                        $monthly = FactMonthlyVariance::where('plant_id', $plant->id)
                            ->where('period_month', $currentMonth)
                            ->first();
                        $actual = (float) ($monthly?->actual_cost ?? 0);
                        $budget = (float) Budget::where('plant_id', $plant->id)
                            ->where('period_month', $currentMonth)
                            ->value('budgeted_cost');

                        if ($budget <= 0) {
                            return 'No Budget';
                        }

                        $pct = ($actual / $budget) * 100;

                        return number_format($pct, 1).'% ($'.number_format($actual, 0).')';
                    }),

                Tables\Columns\TextColumn::make('forecast_month_end')
                    ->label('Forecast Month-End')
                    ->state(function (Plant $plant) use ($currentMonth, $daysInMonth, $dayOfMonth): string {
                        $monthly = FactMonthlyVariance::where('plant_id', $plant->id)
                            ->where('period_month', $currentMonth)
                            ->first();
                        $actual = (float) ($monthly?->actual_cost ?? 0);
                        $forecast = ($actual / $dayOfMonth) * $daysInMonth;

                        return '$'.number_format($forecast, 0);
                    }),

                Tables\Columns\TextColumn::make('open_incidents')
                    ->label('Open Incidents')
                    ->state(fn (Plant $plant) => $plant->costIncidents()->where('status', '!=', 'closed')->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
            ])
            ->defaultSort('name')
            ->paginated([10, 25]);
    }
}
