<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Organization\Models\Plant;
use App\Domain\Reporting\Models\FactMonthlyVariance;
use App\Services\Reporting\DashboardDisplayHelper;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PlantWaterfallWidget extends BaseWidget
{
    public ?int $plantId = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Standard-to-Actual Cost Waterfall Bridge';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $plantQuery = Plant::query()->where('is_active', true);
        if (! $user?->hasRole('admin')) {
            $plantQuery->whereKey($user?->accessiblePlantIds() ?? []);
        }

        $plant = $this->plantId ? Plant::find($this->plantId) : $plantQuery->first();
        $currentMonth = Carbon::now()->startOfMonth()->toDateString();
        $displayUnit = DashboardDisplayHelper::currentUnit();

        $variance = $plant ? FactMonthlyVariance::where('plant_id', $plant->id)
            ->where('period_month', $currentMonth)
            ->first() : null;

        $outputKg = max(1.0, (float) ($variance?->output_kg ?? 1000.0));
        $stdCost = (float) ($variance?->standard_cost ?? ($outputKg * 0.95));
        $actCost = (float) ($variance?->actual_cost ?? ($outputKg * 1.02));

        $matPrice = (float) ($variance?->material_price_variance ?? 0.0);
        $matUsage = (float) ($variance?->material_usage_variance ?? 0.0);
        $laborRate = (float) ($variance?->labor_rate_variance ?? 0.0);
        $laborEff = (float) ($variance?->labor_efficiency_variance ?? 0.0);
        $utilEff = (float) ($variance?->utility_efficiency_variance ?? 0.0);
        $overheadVol = (float) ($variance?->overhead_volume_variance ?? 0.0);
        $incident = (float) ($variance?->incident_variance ?? 0.0);

        // Convert per-unit impact to display unit (kg or lb)
        $items = collect([
            [
                'step' => '1. Standard Baseline',
                'description' => 'Target production cost standard as-of period start',
                'total_impact' => $stdCost,
                'unit_impact' => DashboardDisplayHelper::convertUnitCost($stdCost / $outputKg, $displayUnit),
                'type' => 'baseline',
            ],
            [
                'step' => '2. Material Price Variance',
                'description' => 'Difference between purchase price and standard flake price',
                'total_impact' => $matPrice,
                'unit_impact' => DashboardDisplayHelper::convertUnitCost($matPrice / $outputKg, $displayUnit),
                'type' => $matPrice > 0 ? 'unfavorable' : 'favorable',
            ],
            [
                'step' => '3. Material Usage & Yield Variance',
                'description' => 'Yield loss from startup, screen changes, fines & contamination',
                'total_impact' => $matUsage,
                'unit_impact' => DashboardDisplayHelper::convertUnitCost($matUsage / $outputKg, $displayUnit),
                'type' => $matUsage > 0 ? 'unfavorable' : 'favorable',
            ],
            [
                'step' => '4. Labor Rate & Overtime Variance',
                'description' => 'Wage differentials and overtime multiplier premiums',
                'total_impact' => $laborRate,
                'unit_impact' => DashboardDisplayHelper::convertUnitCost($laborRate / $outputKg, $displayUnit),
                'type' => $laborRate > 0 ? 'unfavorable' : 'favorable',
            ],
            [
                'step' => '5. Labor Efficiency Variance',
                'description' => 'Labor hours utilized vs standard hours allowed per ton',
                'total_impact' => $laborEff,
                'unit_impact' => DashboardDisplayHelper::convertUnitCost($laborEff / $outputKg, $displayUnit),
                'type' => $laborEff > 0 ? 'unfavorable' : 'favorable',
            ],
            [
                'step' => '6. Utility Efficiency Variance',
                'description' => 'Energy intensity deviation (kWh/kg vs baseline)',
                'total_impact' => $utilEff,
                'unit_impact' => DashboardDisplayHelper::convertUnitCost($utilEff / $outputKg, $displayUnit),
                'type' => $utilEff > 0 ? 'unfavorable' : 'favorable',
            ],
            [
                'step' => '7. Overhead Volume Under-Absorption',
                'description' => 'Fixed indirect expense spread over fewer kilograms',
                'total_impact' => $overheadVol,
                'unit_impact' => DashboardDisplayHelper::convertUnitCost($overheadVol / $outputKg, $displayUnit),
                'type' => $overheadVol > 0 ? 'unfavorable' : 'favorable',
            ],
            [
                'step' => '8. Unplanned Incident Costs',
                'description' => 'Breakdowns, contamination, emergency maintenance & rush freight',
                'total_impact' => $incident,
                'unit_impact' => DashboardDisplayHelper::convertUnitCost($incident / $outputKg, $displayUnit),
                'type' => $incident > 0 ? 'unfavorable' : 'favorable',
            ],
            [
                'step' => '9. Actual Net Cost',
                'description' => 'Final realized manufacturing cost for the period',
                'total_impact' => $actCost,
                'unit_impact' => DashboardDisplayHelper::convertUnitCost($actCost / $outputKg, $displayUnit),
                'type' => 'final',
            ],
        ]);

        return $table
            ->records($items)
            ->columns([
                Tables\Columns\TextColumn::make('step')
                    ->label('Waterfall Driver')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Cost Accounting Explanation'),

                Tables\Columns\TextColumn::make('total_impact')
                    ->label('Total Impact ($)')
                    ->state(fn (array $record) => ($record['total_impact'] >= 0 ? '$' : '-$').number_format(abs($record['total_impact']), 2))
                    ->badge()
                    ->color(fn (array $record): string => match ($record['type']) {
                        'favorable' => 'success',
                        'unfavorable' => 'danger',
                        'baseline' => 'info',
                        default => 'primary',
                    }),

                Tables\Columns\TextColumn::make('unit_impact')
                    ->label('Unit Impact ('.DashboardDisplayHelper::unitCostLabel($displayUnit).')')
                    ->state(fn (array $record) => ($record['unit_impact'] >= 0 ? '$' : '-$').number_format(abs($record['unit_impact']), 4))
                    ->badge()
                    ->color(fn (array $record): string => match ($record['type']) {
                        'favorable' => 'success',
                        'unfavorable' => 'danger',
                        'baseline' => 'info',
                        default => 'primary',
                    }),
            ])
            ->paginated(false);
    }
}
