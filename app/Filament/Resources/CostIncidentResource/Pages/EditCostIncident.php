<?php

namespace App\Filament\Resources\CostIncidentResource\Pages;

use App\Filament\Resources\CostIncidentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCostIncident extends EditRecord
{
    protected static string $resource = CostIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
