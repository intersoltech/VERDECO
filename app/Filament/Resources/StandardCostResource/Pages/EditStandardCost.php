<?php

declare(strict_types=1);

namespace App\Filament\Resources\StandardCostResource\Pages;

use App\Filament\Resources\StandardCostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStandardCost extends EditRecord
{
    protected static string $resource = StandardCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
