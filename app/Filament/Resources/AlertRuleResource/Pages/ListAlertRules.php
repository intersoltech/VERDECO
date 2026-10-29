<?php

namespace App\Filament\Resources\AlertRuleResource\Pages;

use App\Filament\Resources\AlertRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAlertRules extends ListRecords
{
    protected static string $resource = AlertRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
