<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Production\Models\ProductionBatch;
use App\Models\User;

final class ProductionBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'controller', 'plant_manager', 'plant_supervisor', 'plant_operator', 'viewer'], true);
    }

    public function view(User $user, ProductionBatch $batch): bool
    {
        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        return $user->plants()->where('plants.id', $batch->plant_id)->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'controller', 'plant_manager', 'plant_supervisor', 'plant_operator'], true);
    }

    public function update(User $user, ProductionBatch $batch): bool
    {
        // Closed batches are immutable without reopening
        if ($batch->status === 'closed') {
            return false;
        }

        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        if (in_array($user->role, ['plant_manager', 'plant_supervisor', 'plant_operator'], true)) {
            return $user->plants()->where('plants.id', $batch->plant_id)->exists();
        }

        return false;
    }

    public function delete(User $user, ProductionBatch $batch): bool
    {
        if ($batch->status === 'closed') {
            return false;
        }

        return $user->role === 'admin';
    }

    public function close(User $user, ProductionBatch $batch): bool
    {
        if ($batch->status === 'closed') {
            return false;
        }

        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        if ($user->role === 'plant_manager') {
            return $user->plants()->where('plants.id', $batch->plant_id)->exists();
        }

        return false;
    }

    public function reopen(User $user, ProductionBatch $batch): bool
    {
        if ($batch->status !== 'closed') {
            return false;
        }

        return in_array($user->role, ['admin', 'controller'], true);
    }
}
