<?php

namespace Tests\Feature;

use App\Domain\Costing\Models\CostPeriod;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostPeriodLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_period_can_be_approved_closed_and_reopened(): void
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);

        $plant = Plant::create([
            'tenant_id' => $organization->id,
            'name' => 'Verdeco Dallas',
            'code' => 'VDC',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'batch',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $period = CostPeriod::create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'period_start' => now()->subDays(7),
            'period_end' => now(),
            'status' => 'open',
            'actual_cost' => 1200.00,
            'standard_cost' => 1000.00,
            'variance_cost' => 200.00,
            'output_kg' => 5000,
            'comments' => 'Initial period',
        ]);

        $period->approve($user);
        $this->assertSame('approved', $period->fresh()->status);
        $this->assertNotNull($period->fresh()->approved_at);
        $this->assertSame($user->id, $period->fresh()->approved_by);

        $period->close($user);
        $this->assertSame('closed', $period->fresh()->status);
        $this->assertNotNull($period->fresh()->closed_at);
        $this->assertSame($user->id, $period->fresh()->closed_by);

        $period->reopen($user);
        $this->assertSame('open', $period->fresh()->status);
        $this->assertNull($period->fresh()->closed_at);
        $this->assertNull($period->fresh()->closed_by);
    }
}
