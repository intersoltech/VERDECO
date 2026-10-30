<?php

declare(strict_types=1);

namespace App\Filament\Resources\StandardCostResource\Pages;

use App\Filament\Resources\StandardCostResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStandardCost extends CreateRecord
{
    protected static string $resource = StandardCostResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Resolve tenant_id from the selected plant
        if (empty($data['tenant_id']) && ! empty($data['plant_id'])) {
            $plant = \App\Domain\Organization\Models\Plant::find($data['plant_id']);
            $data['tenant_id'] = $plant?->tenant_id ?? auth()->user()?->tenant_id;
        }

        return parent::handleRecordCreation($data);
    }
}
