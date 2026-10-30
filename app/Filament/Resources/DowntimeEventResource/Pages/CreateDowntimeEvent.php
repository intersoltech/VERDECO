<?php

namespace App\Filament\Resources\DowntimeEventResource\Pages;

use App\Filament\Resources\DowntimeEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDowntimeEvent extends CreateRecord
{
    protected static string $resource = DowntimeEventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()?->tenant_id ?? 1;
        $data['reported_by'] = auth()->id();

        return $data;
    }
}
