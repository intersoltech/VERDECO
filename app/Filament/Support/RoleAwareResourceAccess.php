<?php

namespace App\Filament\Support;

use App\Domain\Organization\Models\Plant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait RoleAwareResourceAccess
{
    protected static function permissionArea(): string
    {
        return 'plants';
    }

    protected static function isRecordEditable(Model $record): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('admin')) {
            return $query;
        }

        $model = $query->getModel();
        $plantIds = $user->accessiblePlantIds();

        if ($model instanceof Plant) {
            return $query->whereKey($plantIds);
        }

        return $query->whereIn($model->qualifyColumn('plant_id'), $plantIds);
    }

    protected static function scopePlantOptions(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        return $query->whereKey($user?->accessiblePlantIds() ?? []);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasPermission(static::permissionArea().'.read');
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin') || (
            $user->hasPermission(static::permissionArea().'.manage')
            && $user->accessiblePlantIds()->isNotEmpty()
        );
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if ($user === null || ! static::isRecordEditable($record)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasPermission(static::permissionArea().'.manage')
            && ($record instanceof Plant
                ? $user->canAccessPlant($record)
                : $user->canAccessPlant($record->plant));
    }
}
