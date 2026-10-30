<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

use App\Domain\Organization\Models\Plant;
use Illuminate\Support\Collection;

interface PlantAccessibleUserInterface
{
    /**
     * Determine if user has access to a specific plant.
     */
    public function canAccessPlant(Plant $plant): bool;

    /**
     * Return list of accessible plant IDs for scoping queries.
     *
     * @return Collection<int, int>
     */
    public function accessiblePlantIds(): Collection;

    /**
     * Check if user is allowed to view financials/pricing.
     */
    public function canViewFinancials(): bool;
}
