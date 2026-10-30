<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

class IncidentParetoWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Incident Pareto Analysis (Root Cause & Severity)';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $plantQuery = Plant::query()->where('is_active', true);
        if (! $user?->hasRole('admin')) {
            $plantQuery->whereKey($user?->accessiblePlantIds() ?? []);
        }
        $targetPlantIds = $plantQuery->pluck('id');

        $incidents = CostIncident::query()
            ->whereIn('plant_id', $targetPlantIds)
            ->get();

        $totalIncidents = max(1, $incidents->count());

        $grouped = $incidents->groupBy(function ($inc) {
            return $inc->root_cause_category.' / '.$inc->incident_type;
        })->map(function (Collection $group, string $key) use ($totalIncidents) {
            $first = $group->first();
            $count = $group->count();
            $cash = $group->sum('cash_cost');
            $opp = $group->sum('opportunity_cost');

            return [
                'key' => $key,
                'root_cause' => $first?->root_cause_category ?? 'Unclassified',
                'incident_type' => $first?->incident_type ?? 'Other',
                'count' => $count,
                'share_pct' => ($count / $totalIncidents) * 100,
                'cash_cost' => $cash,
                'opportunity_cost' => $opp,
                'severity' => $first?->severity ?? 'medium',
            ];
        })->sortByDesc('cash_cost')->values();

        // Calculate cumulative %
        $cumulative = 0.0;
        $items = $grouped->map(function ($item) use (&$cumulative) {
            $cumulative += $item['share_pct'];
            $item['cumulative_pct'] = min(100.0, $cumulative);

            return $item;
        });

        return $table
            ->records($items)
            ->columns([
                Tables\Columns\TextColumn::make('root_cause')
                    ->label('Root Cause Category')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('incident_type')
                    ->label('Incident Type')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('count')
                    ->label('Frequency')
                    ->numeric()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('share_pct')
                    ->label('Share %')
                    ->state(fn (array $record) => number_format($record['share_pct'], 1).' %'),

                Tables\Columns\TextColumn::make('cumulative_pct')
                    ->label('Pareto Cumulative %')
                    ->state(fn (array $record) => number_format($record['cumulative_pct'], 1).' %')
                    ->badge()
                    ->color(fn (array $record): string => $record['cumulative_pct'] <= 80 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('cash_cost')
                    ->label('Cash Impact ($)')
                    ->state(fn (array $record) => '$'.number_format((float) $record['cash_cost'], 2))
                    ->weight('bold')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('opportunity_cost')
                    ->label('Opportunity Impact ($)')
                    ->state(fn (array $record) => '$'.number_format((float) $record['opportunity_cost'], 2))
                    ->color('warning'),
            ])
            ->paginated(false);
    }
}
