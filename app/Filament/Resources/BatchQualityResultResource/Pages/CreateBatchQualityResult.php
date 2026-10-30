<?php

namespace App\Filament\Resources\BatchQualityResultResource\Pages;

use App\Filament\Resources\BatchQualityResultResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBatchQualityResult extends CreateRecord
{
    protected static string $resource = BatchQualityResultResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()?->tenant_id ?? 1;
        $data['inspected_by'] = auth()->id();

        return $data;
    }
}
