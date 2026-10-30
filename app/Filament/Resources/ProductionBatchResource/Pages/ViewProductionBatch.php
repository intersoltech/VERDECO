<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionBatchResource\Pages;

use App\Domain\Production\Models\ProductionBatch;
use App\Filament\Resources\ProductionBatchResource;
use App\Services\Costing\ProductionBatchCostingService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionBatch extends ViewRecord
{
    protected static string $resource = ProductionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calculate_cost')
                ->label('Calculate Cost & Close')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === 'draft' && (auth()->user()?->hasPermission('production-batches.manage') ?? false))
                ->action(function (): void {
                    /** @var ProductionBatch $record */
                    $record = $this->record;
                    app(ProductionBatchCostingService::class)->calculateAndSave($record);
                    $this->refreshFormData(['status', 'net_cost', 'cost_per_kg', 'yield_percent', 'calculated_at']);
                }),

            Actions\Action::make('pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->url(fn (): string => route('reports.batch.pdf', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('excel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->url(fn (): string => route('reports.batch.excel', $this->record))
                ->openUrlInNewTab(),

            Actions\EditAction::make()
                ->visible(fn (): bool => $this->record->status === 'draft'),
        ];
    }
}
