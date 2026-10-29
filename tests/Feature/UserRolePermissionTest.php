<?php

namespace Tests\Feature;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_have_global_plant_access_and_full_permissions(): void
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);

        $plantA = Plant::create([
            'tenant_id' => $organization->id,
            'name' => 'Dallas Plant',
            'code' => 'DAL',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'batch',
            'is_active' => true,
        ]);

        $plantB = Plant::create([
            'tenant_id' => $organization->id,
            'name' => 'Atlanta Plant',
            'code' => 'ATL',
            'location' => 'Atlanta, GA',
            'timezone' => 'America/New_York',
            'base_currency' => 'USD',
            'costing_mode' => 'daily',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasPermission('plants.read'));
        $this->assertTrue($user->hasPermission('plants.manage'));
        $this->assertTrue($user->canAccessPlant($plantA));
        $this->assertTrue($user->canAccessPlant($plantB));
    }
}
