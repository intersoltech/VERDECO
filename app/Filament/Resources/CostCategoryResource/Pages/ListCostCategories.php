<?php

declare(strict_types=1);

namespace App\Filament\Resources\CostCategoryResource\Pages;

use App\Filament\Resources\CostCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCostCategories extends ListRecords
{
    protected static string $resource = CostCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
