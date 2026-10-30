<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductGradeResource\Pages;

use App\Domain\Organization\Models\Plant;
use App\Filament\Resources\ProductGradeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductGrade extends CreateRecord
{
    protected static string $resource = ProductGradeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $plant = Plant::find($data['plant_id']);
        $data['tenant_id'] = $plant?->tenant_id ?? 1;

        return $data;
    }
}
