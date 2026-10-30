<?php

declare(strict_types=1);

namespace App\Filament\Resources\CostCategoryResource\Pages;

use App\Domain\Organization\Models\Plant;
use App\Filament\Resources\CostCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCostCategory extends CreateRecord
{
    protected static string $resource = CostCategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (empty($data['tenant_id']) && ! empty($data['plant_id'])) {
            $plant = Plant::find($data['plant_id']);
            $data['tenant_id'] = $plant?->tenant_id ?? auth()->user()?->tenant_id;
        }

        return parent::handleRecordCreation($data);
    }
}
