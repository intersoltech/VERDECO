<?php

namespace App\Filament\Resources\CostIncidentResource\Pages;

use App\Filament\Resources\CostIncidentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCostIncidents extends ListRecords
{
    protected static string $resource = CostIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
