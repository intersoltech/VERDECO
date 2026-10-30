<?php

declare(strict_types=1);

namespace App\Filament\Resources\StandardCostResource\Pages;

use App\Filament\Resources\StandardCostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStandardCosts extends ListRecords
{
    protected static string $resource = StandardCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
