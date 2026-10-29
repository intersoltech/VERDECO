<?php

namespace App\Filament\Support;

trait RoleAwareResourceAccess
{
    protected static function permissionKey(): string
    {
        return 'view-plant';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasPermission(static::permissionKey());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasPermission(str_replace('view-', 'create-', static::permissionKey()));
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasPermission(str_replace('view-', 'update-', static::permissionKey()));
    }
}