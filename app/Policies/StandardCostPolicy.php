<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Costing\Models\StandardCost;
use App\Models\User;

final class StandardCostPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'controller', 'plant_manager', 'plant_supervisor', 'viewer'], true);
    }

    public function view(User $user, StandardCost $standardCost): bool
    {
        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        return $user->plants()->where('plants.id', $standardCost->plant_id)->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'controller', 'plant_manager'], true);
    }

    public function update(User $user, StandardCost $standardCost): bool
    {
        // Approved standard costs are immutable and cannot be updated in-place (must create new version)
        if ($standardCost->status === 'approved') {
            return false;
        }

        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        if ($user->role === 'plant_manager') {
            return $user->plants()->where('plants.id', $standardCost->plant_id)->exists();
        }

        return false;
    }

    public function delete(User $user, StandardCost $standardCost): bool
    {
        // Approved or pending standard costs cannot be deleted
        if ($standardCost->status === 'approved' || $standardCost->status === 'pending_approval') {
            return false;
        }

        return in_array($user->role, ['admin', 'controller'], true);
    }

    public function submitForApproval(User $user, StandardCost $standardCost): bool
    {
        if (! in_array($standardCost->status, ['draft', 'rejected'], true)) {
            return false;
        }

        if ($user->role === 'admin' || $user->role === 'controller') {
            return true;
        }

        if ($user->role === 'plant_manager') {
            return $user->plants()->where('plants.id', $standardCost->plant_id)->exists();
        }

        return false;
    }

    public function approve(User $user, StandardCost $standardCost): bool
    {
        // Dual control: maker cannot be checker
        if ($standardCost->created_by && $standardCost->created_by === $user->id) {
            return false;
        }

        if ($standardCost->submitted_by && $standardCost->submitted_by === $user->id) {
            return false;
        }

        // Only controller or admin can approve standard cost revisions
        return in_array($user->role, ['admin', 'controller'], true);
    }

    public function reject(User $user, StandardCost $standardCost): bool
    {
        return in_array($user->role, ['admin', 'controller'], true);
    }
}
