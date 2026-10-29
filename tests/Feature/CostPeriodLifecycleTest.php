<?php

namespace Tests\Feature;

use App\Domain\Costing\Models\CostPeriod;
use App\Domain\Costing\Models\CostPeriodEvent;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Filament\Resources\CostPeriodResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
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

        $approver = User::factory()->create();
        $closer = User::factory()->create();
        $reopener = User::factory()->create();

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

        $period->approve($approver);
        $this->assertSame('approved', $period->fresh()->status);
        $this->assertNotNull($period->fresh()->approved_at);
        $this->assertSame($approver->id, $period->fresh()->approved_by);

        $period->close($closer);
        $this->assertSame('closed', $period->fresh()->status);
        $this->assertNotNull($period->fresh()->closed_at);
        $this->assertSame($closer->id, $period->fresh()->closed_by);

        $period->reopen($reopener);
        $this->assertSame('open', $period->fresh()->status);
        $this->assertNull($period->fresh()->closed_at);
        $this->assertNull($period->fresh()->closed_by);
        $this->assertSame($reopener->id, $period->fresh()->reopened_by);
        $this->assertNotNull($period->fresh()->reopened_at);

        $events = $period->events()->orderBy('id')->get();
        $this->assertSame(['approved', 'closed', 'reopened'], $events->pluck('event')->all());
        $this->assertSame([$approver->id, $closer->id, $reopener->id], $events->pluck('actor_id')->all());
        $this->assertSame([$approver->name, $closer->name, $reopener->name], $events->pluck('actor_name')->all());
        $this->view('filament.cost-period-history', ['events' => $events])
            ->assertSee('approved')
            ->assertSee($closer->email);
    }

    public function test_approver_cannot_close_the_same_cost_period(): void
    {
        $period = $this->createOpenPeriod();
        $approver = User::factory()->create();
        $period->approve($approver);

        $this->expectException(LogicException::class);
        $period->close($approver);
    }

    public function test_period_cannot_be_closed_before_approval(): void
    {
        $period = $this->createOpenPeriod();

        $this->expectException(LogicException::class);
        $period->close();
    }

    public function test_closed_period_accounting_values_cannot_be_changed_without_reopening(): void
    {
        $period = $this->createOpenPeriod();
        $manager = User::factory()->create(['role' => 'plant_manager']);
        $closer = User::factory()->create(['role' => 'plant_manager']);
        $manager->plants()->attach($period->plant_id, ['tenant_id' => $period->tenant_id]);
        $closer->plants()->attach($period->plant_id, ['tenant_id' => $period->tenant_id]);
        $period->approve($manager);
        $period->close($closer);

        $this->actingAs($manager);
        $this->assertFalse(CostPeriodResource::canEdit($period->fresh()));

        $period->reopen($manager);
        $this->assertTrue(CostPeriodResource::canEdit($period->fresh()));
        $period->approve($manager);
        $period->close($closer);

        $period->actual_cost = 9999;

        $this->expectException(LogicException::class);
        $period->save();
    }

    public function test_cost_period_events_are_immutable(): void
    {
        $period = $this->createOpenPeriod();
        $period->approve(User::factory()->create());
        $event = CostPeriodEvent::query()->firstOrFail();
        $event->event = 'reopened';

        $this->expectException(LogicException::class);
        $event->save();
    }

    private function createOpenPeriod(): CostPeriod
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'period-'.uniqid(),
            'country_code' => 'US',
        ]);

        $plant = Plant::create([
            'tenant_id' => $organization->id,
            'name' => 'Verdeco Dallas',
            'code' => 'VDC-'.uniqid(),
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'batch',
            'is_active' => true,
        ]);

        return CostPeriod::create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'period_start' => now()->subDays(7),
            'period_end' => now(),
            'status' => 'open',
            'actual_cost' => 1200,
            'standard_cost' => 1000,
            'variance_cost' => 200,
            'output_kg' => 5000,
        ]);
    }
}
