<?php

namespace Tests\Feature;

use App\Filament\Resources\PlantResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_plant_resource_but_operator_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'plant_operator']);

        $this->actingAs($admin);
        $this->assertTrue(PlantResource::canViewAny());
        $this->assertTrue(PlantResource::canCreate());

        $this->actingAs($operator);
        $this->assertTrue(PlantResource::canViewAny());
        $this->assertFalse(PlantResource::canCreate());
    }
}
