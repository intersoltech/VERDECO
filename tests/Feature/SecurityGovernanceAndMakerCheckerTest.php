<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Costing\Models\CostPeriod;
use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Models\User;
use App\Services\Reporting\FactRollupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SecurityGovernanceAndMakerCheckerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Plant $plantDallas;

    private ProductionLine $lineDallas;

    private User $plantManager;

    private User $controller;

    private User $groupAdmin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Verdeco Group Global',
            'slug' => 'verdeco-global',
            'country_code' => 'US',
        ]);

        $this->plantDallas = Plant::create([
            'tenant_id' => $this->organization->id,
            'name' => 'Verdeco Dallas',
            'code' => 'VDC',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'both',
            'is_active' => true,
        ]);

        $this->lineDallas = ProductionLine::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'name' => 'Dallas Line 01',
            'code' => 'VDC-L01',
            'type' => 'extruder',
            'rated_kg_per_hr' => 2200,
            'rated_kw' => 450,
            'is_active' => true,
        ]);

        $this->plantManager = User::factory()->create([
            'name' => 'Dallas Plant Manager',
            'role' => 'plant_manager',
        ]);
        $this->plantManager->plants()->attach($this->plantDallas->id, ['tenant_id' => $this->organization->id]);

        $this->operator = User::factory()->create([
            'name' => 'Dallas Line Operator',
            'role' => 'plant_operator',
        ]);
        $this->operator->plants()->attach($this->plantDallas->id, ['tenant_id' => $this->organization->id]);

        $this->controller = User::factory()->create([
            'name' => 'Group Corporate Controller',
            'role' => 'controller',
        ]);

        $this->groupAdmin = User::factory()->create([
            'name' => 'Group Executive Admin',
            'role' => 'admin',
        ]);
    }

    public function test_maker_cannot_approve_own_standard_cost_revision(): void
    {
        // 1. Plant manager authors a revised standard cost (maker)
        $standardCost = StandardCost::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'status' => 'draft',
            'effective_from' => Carbon::now()->startOfMonth(),
            'standard_yield_percent' => 93.5,
            'kwh_per_kg' => 0.35,
            'labor_hours_per_ton' => 2.8,
            'overhead_rate' => 0.14,
            'created_by' => $this->plantManager->id,
        ]);

        // Submit for dual-control approval
        $standardCost->submitForApproval($this->plantManager);
        $this->assertSame('pending_approval', $standardCost->fresh()->status);
        $this->assertSame($this->plantManager->id, $standardCost->fresh()->submitted_by);

        // 2. Policy test: Maker cannot approve their own standard cost revision
        $this->assertFalse(Gate::forUser($this->plantManager)->allows('approve', $standardCost));

        // 3. Domain Model enforcement: calling approve() by the maker throws LogicException
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Dual control violation');

        $standardCost->approve($this->plantManager);
    }

    public function test_independent_checker_approves_standard_cost_revision(): void
    {
        $standardCost = StandardCost::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'status' => 'pending_approval',
            'effective_from' => Carbon::now()->startOfMonth(),
            'standard_yield_percent' => 94.0,
            'kwh_per_kg' => 0.34,
            'labor_hours_per_ton' => 2.7,
            'overhead_rate' => 0.13,
            'created_by' => $this->plantManager->id,
            'submitted_by' => $this->plantManager->id,
        ]);

        // Policy allows independent corporate controller to approve
        $this->assertTrue(Gate::forUser($this->controller)->allows('approve', $standardCost));

        // Controller approves
        $standardCost->approve($this->controller);

        $fresh = $standardCost->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($this->controller->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);

        // Activity log records the approval
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => StandardCost::class,
            'subject_id' => $standardCost->id,
        ]);
    }

    public function test_costing_services_ignore_unapproved_standard_costs(): void
    {
        $date = Carbon::today()->toDateString();

        // Approved active standard: yield 90%
        StandardCost::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'status' => 'approved',
            'effective_from' => Carbon::today()->subMonths(2),
            'standard_yield_percent' => 90.0,
            'kwh_per_kg' => 0.40,
            'labor_hours_per_ton' => 3.5,
            'overhead_rate' => 0.18,
        ]);

        // Unapproved draft revision: yield 98% (should be ignored by costing pipeline)
        StandardCost::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'status' => 'draft',
            'effective_from' => Carbon::today()->subDays(5),
            'standard_yield_percent' => 98.0,
            'kwh_per_kg' => 0.20,
            'labor_hours_per_ton' => 1.5,
            'overhead_rate' => 0.05,
            'created_by' => $this->plantManager->id,
        ]);

        // Create a closed production batch for today
        ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'batch_number' => 'VDC-STD-TEST-01',
            'batch_date' => $date,
            'status' => 'closed',
            'input_kg' => 10000.0,
            'good_output_kg' => 9000.0,
            'total_cost' => 9000.0,
            'net_cost' => 9000.0,
            'yield_percent' => 90.0,
            'cost_per_kg' => 1.00,
            'utility_kwh' => 3600.0,
        ]);

        $rollupService = app(FactRollupService::class);
        $rollupService->updateDailyPlantKpi($this->organization->id, $this->plantDallas->id, $date);
        $dailyKpi = FactDailyPlantKpi::where('plant_id', $this->plantDallas->id)
            ->where('production_date', $date)
            ->first();

        // Standard cost resolved from the approved record (yield 90%), not the draft record (yield 98%)
        $this->assertNotNull($dailyKpi);
        $this->assertGreaterThan(0, (float) $dailyKpi->standard_cost);
    }

    public function test_incident_reporter_cannot_approve_own_incident(): void
    {
        $incident = CostIncident::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'incident_type' => 'breakdown',
            'severity' => 'critical',
            'root_cause_category' => 'mechanical',
            'description' => 'Melt pump gearbox teeth stripped',
            'status' => 'open',
            'occurred_at' => now(),
            'cash_cost' => 4500.00,
            'reported_by_user_id' => $this->operator->id,
        ]);

        // Policy: Reporting operator cannot approve
        $this->assertFalse(Gate::forUser($this->operator)->allows('approve', $incident));

        // Model dual-control exception
        try {
            $incident->approve($this->operator);
            $this->fail('Expected dual-control LogicException');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Dual control violation', $e->getMessage());
        }

        // Plant Manager (different user) approves
        $this->assertTrue(Gate::forUser($this->plantManager)->allows('approve', $incident));
        $incident->approve($this->plantManager);

        $this->assertSame('approved', $incident->fresh()->status);
        $this->assertSame($this->plantManager->id, $incident->fresh()->approved_by);
    }

    public function test_closed_batches_are_immutable_without_authorized_reopening(): void
    {
        $batch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'line_id' => $this->lineDallas->id,
            'batch_number' => 'VDC-IMMUTABLE-01',
            'batch_date' => Carbon::today()->toDateString(),
            'status' => 'running',
            'input_kg' => 5000.0,
            'good_output_kg' => 4600.0,
            'total_cost' => 4600.0,
            'net_cost' => 4600.0,
        ]);

        // Close the batch
        $batch->close($this->plantManager);
        $this->assertSame('closed', $batch->fresh()->status);
        $this->assertSame($this->plantManager->id, $batch->fresh()->closed_by);

        // Policy: Closed batch cannot be edited
        $this->assertFalse(Gate::forUser($this->plantManager)->allows('update', $batch));

        // Model Immutability: directly modifying closed quantities throws exception
        try {
            $batch->update(['good_output_kg' => 4900.0]);
            $this->fail('Expected immutability exception');
        } catch (LogicException $e) {
            $this->assertStringContainsString('Closed production batch is immutable', $e->getMessage());
        }

        // Reopen by corporate controller with audit adjustment reason
        $this->assertTrue(Gate::forUser($this->controller)->allows('reopen', $batch));
        $batch->reopen($this->controller, 'Scale calibration correction after certified re-weigh');

        $this->assertSame('running', $batch->fresh()->status);
        $this->assertSame($this->controller->id, $batch->fresh()->reopened_by);
        $this->assertSame('Scale calibration correction after certified re-weigh', $batch->fresh()->adjustment_reason);

        // Now updates are permitted
        $batch->update(['good_output_kg' => 4650.0]);
        $this->assertSame('4650.0000', (string) $batch->fresh()->good_output_kg);
    }

    public function test_cost_period_approver_cannot_close_same_period(): void
    {
        $period = CostPeriod::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plantDallas->id,
            'period_start' => Carbon::now()->subMonth()->startOfMonth(),
            'period_end' => Carbon::now()->subMonth()->endOfMonth(),
            'status' => 'open',
            'actual_cost' => 50000.0,
            'standard_cost' => 48000.0,
            'variance_cost' => 2000.0,
            'output_kg' => 50000.0,
        ]);

        // Controller approves the period
        $period->approve($this->controller);
        $this->assertSame('approved', $period->fresh()->status);

        // Dual control in Policy: Controller who approved cannot close the same period
        $this->assertFalse(Gate::forUser($this->controller)->allows('close', $period));

        // Group Admin can close
        $this->assertTrue(Gate::forUser($this->groupAdmin)->allows('close', $period));
        $period->close($this->groupAdmin);

        $this->assertSame('closed', $period->fresh()->status);
        $this->assertSame($this->groupAdmin->id, $period->fresh()->closed_by);
    }
}
