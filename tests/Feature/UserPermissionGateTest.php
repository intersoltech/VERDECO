<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permissions_are_exposed_via_laravel_can_method(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $operator = User::factory()->create([
            'role' => 'plant_operator',
        ]);

        $this->assertTrue($admin->can('view-any-plant'));
        $this->assertTrue($admin->can('create-plant'));
        $this->assertTrue($operator->can('view-plant'));
        $this->assertFalse($operator->can('create-plant'));
    }
}
