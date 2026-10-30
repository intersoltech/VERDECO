<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductGradeResource\Pages;

use App\Filament\Resources\ProductGradeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductGrades extends ListRecords
{
    protected static string $resource = ProductGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
