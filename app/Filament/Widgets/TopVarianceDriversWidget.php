<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Models\FactMonthlyVariance;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopVarianceDriversWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top Cost Variance Drivers (Month-to-Date)';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $plantIds = $user?->hasRole('admin') ? null : ($user?->accessiblePlantIds() ?? collect());

        $currentMonth = Carbon::now()->startOfMonth()->toDateString();

        $query = FactMonthlyVariance::query()
            ->with('plant')
            ->where('period_month', $currentMonth);

        if ($plantIds !== null) {
            $query->whereIn('plant_id', $plantIds);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('plant.name')
                    ->label('Plant')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('total_variance')
                    ->label('Total Variance')
                    ->money('USD')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => (float) $state <= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('material_price_variance')
                    ->label('Mat. Price Var.')
                    ->money('USD')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => (float) $state <= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('material_usage_variance')
                    ->label('Mat. Usage Var.')
                    ->money('USD')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => (float) $state <= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('labor_efficiency_variance')
                    ->label('Labor Eff. Var.')
                    ->money('USD')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => (float) $state <= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('utility_efficiency_variance')
                    ->label('Utility Eff. Var.')
                    ->money('USD')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => (float) $state <= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('incident_variance')
                    ->label('Incident Unplanned')
                    ->money('USD')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => (float) $state > 0 ? 'danger' : 'gray'),
            ])
            ->defaultSort('total_variance', 'desc')
            ->paginated([5, 10]);
    }
}
