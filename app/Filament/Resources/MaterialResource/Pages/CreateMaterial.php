<?php

declare(strict_types=1);

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Resources\MaterialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterial extends CreateRecord
{
    protected static string $resource = MaterialResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $plant = \App\Domain\Organization\Models\Plant::find($data['plant_id']);
        $data['tenant_id'] = $plant?->tenant_id ?? 1;

        return $data;
    }
}
