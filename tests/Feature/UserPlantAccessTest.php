<?php

namespace Tests\Feature;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPlantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_only_access_assigned_plants(): void
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);

        $allowedPlant = Plant::create([
            'tenant_id' => $organization->id,
            'name' => 'Dallas Plant',
            'code' => 'DAL',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'batch',
            'is_active' => true,
        ]);

        $blockedPlant = Plant::create([
            'tenant_id' => $organization->id,
            'name' => 'Atlanta Plant',
            'code' => 'ATL',
            'location' => 'Atlanta, GA',
            'timezone' => 'America/New_York',
            'base_currency' => 'USD',
            'costing_mode' => 'daily',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->plants()->attach($allowedPlant->id, ['tenant_id' => $organization->id]);

        $this->assertTrue($user->canAccessPlant($allowedPlant));
        $this->assertFalse($user->canAccessPlant($blockedPlant));
        $this->assertSame([$allowedPlant->id], $user->accessiblePlantIds()->all());
    }
}
