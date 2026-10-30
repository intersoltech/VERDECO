<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Costing\Models\Budget;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Integrations\Contracts\WebhookDispatcherInterface;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use App\Domain\Quality\Models\BatchQualityResult;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Domain\Reporting\Models\FactMonthlyVariance;
use App\Models\User;
use App\Notifications\AlertTriggered;
use App\Services\Alerts\AlertEvaluator;
use App\Services\Alerts\AlertStore;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertEngineAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Plant $plant;

    private ProductionLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Verdeco Group Global',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);

        $this->plant = Plant::create([
            'tenant_id' => $this->organization->id,
            'name' => 'Verdeco Dallas',
            'code' => 'VDC',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'both',
            'is_active' => true,
        ]);

        $this->line = ProductionLine::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'name' => 'Line 01 Extruder',
            'code' => 'VDC-L01',
            'type' => 'extruder',
            'rated_kg_per_hr' => 2200,
            'rated_kw' => 440,
            'is_active' => true,
        ]);
    }

    public function test_eight_default_operational_rules_trigger_alerts(): void
    {
        $today = Carbon::today()->toDateString();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();

        // 1. Cost per kg drift: actual $1.15 vs std $1.00 (15% drift > 10% threshold)
        FactMonthlyVariance::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'period_month' => $startOfMonth,
            'actual_cost' => 11500.0,
            'standard_cost' => 10000.0,
            'total_variance' => 1500.0,
            'output_kg' => 10000.0,
        ]);
        $this->createRule('cost_per_kg_drift', 10.0, 'gt');

        // 3. Overtime hours > 20
        $batch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'batch_number' => 'VDC-OT-001',
            'batch_date' => $today,
            'labor_overtime_hours' => 26.5,
        ]);
        $this->createRule('overtime_hours_threshold', 20.0, 'gt');

        // 4. Unplanned downtime > 60 min
        DowntimeEvent::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'downtime_type' => 'unplanned',
            'reason_code' => 'screen_pack_change',
            'start_at' => now()->subHours(2),
            'duration_minutes' => 95,
        ]);
        $this->createRule('unplanned_downtime_threshold', 60.0, 'gt');

        // 2 & 5. Yield below threshold and energy intensity spike
        FactDailyPlantKpi::updateOrCreate([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'production_date' => $today,
        ], [
            'input_kg' => 10000.0,
            'good_output_kg' => 8850.0,
            'yield_percent' => 88.5,
            'actual_cost' => 1000.0,
            'standard_cost' => 950.0,
            'kwh_per_kg' => 0.44, // Energy intensity spike > 0.40
        ]);
        $this->createRule('yield_below_threshold', 91.0, 'lt');
        $this->createRule('energy_intensity_spike', 0.40, 'gt');

        // 5. Batch on quality hold > 0
        BatchQualityResult::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'production_batch_id' => $batch->id,
            'tested_at' => now(),
            'status' => 'hold',
            'inspector_notes' => 'Excess fines and black specks',
        ]);
        $this->createRule('quality_hold_batch', 0, 'gt');

        // 6. Incident cost > $2500
        CostIncident::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'incident_type' => 'breakdown',
            'severity' => 'critical',
            'root_cause_category' => 'electrical',
            'description' => 'Main drive inverter burnt out',
            'status' => 'open',
            'occurred_at' => now(),
            'cash_cost' => 4500.00,
        ]);
        $this->createRule('incident_cost_threshold', 2500.0, 'gt');

        // 7. Budget consumed > 90%
        Budget::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'period_month' => $startOfMonth,
            'budgeted_cost' => 12000.0, // actual 11500 / 12000 = 95.8% > 90%
        ]);
        $this->createRule('budget_consumed_threshold', 90.0, 'gt');

        // Evaluate all rules
        $evaluator = app(AlertEvaluator::class);
        $alerts = $evaluator->evaluate();

        $rulesTriggered = collect($alerts)->pluck('rule')->all();

        $this->assertContains('cost_per_kg_drift', $rulesTriggered);
        $this->assertContains('yield_below_threshold', $rulesTriggered);
        $this->assertContains('overtime_hours_threshold', $rulesTriggered);
        $this->assertContains('unplanned_downtime_threshold', $rulesTriggered);
        $this->assertContains('energy_intensity_spike', $rulesTriggered);
        $this->assertContains('quality_hold_batch', $rulesTriggered);
        $this->assertContains('incident_cost_threshold', $rulesTriggered);
        $this->assertContains('budget_consumed_threshold', $rulesTriggered);

        // Store and sync
        $store = app(AlertStore::class);
        $persisted = $store->sync($alerts);

        $this->assertCount(8, $persisted);
        $this->assertSame(8, Alert::where('plant_id', $this->plant->id)->where('status', 'open')->count());
    }

    public function test_alert_lifecycle_and_auto_resolution(): void
    {
        $today = Carbon::today()->toDateString();

        $incident = CostIncident::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'incident_type' => 'rework',
            'severity' => 'high',
            'root_cause_category' => 'off_spec',
            'description' => 'Color yellowness b* out of specification',
            'status' => 'open',
            'occurred_at' => now(),
            'cash_cost' => 3000.00,
        ]);

        $this->createRule('incident_cost_threshold', 2000.0, 'gt');

        $evaluator = app(AlertEvaluator::class);
        $store = app(AlertStore::class);

        // First evaluation: trigger alert
        $alerts = $evaluator->evaluate();
        $store->sync($alerts);

        $alert = Alert::where('rule', 'incident_cost_threshold')->first();
        $this->assertNotNull($alert);
        $this->assertSame('open', $alert->status);

        // Acknowledge alert
        $admin = User::factory()->create(['role' => 'admin']);
        $alert->acknowledge($admin);
        $this->assertSame('acknowledged', $alert->fresh()->status);
        $this->assertSame($admin->id, $alert->fresh()->acknowledged_by);

        // Close the incident -> condition clears
        $incident->close($admin);

        // Next evaluation run: incident cost is now 0, alert should auto-resolve!
        $alertsAfter = $evaluator->evaluate();
        $store->sync($alertsAfter);

        $this->assertSame('resolved', $alert->fresh()->status);
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_signed_webhook_dispatcher(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Signed webhook dispatched', \Mockery::on(function (array $context) {
                return $context['event'] === 'AlertTriggered'
                    && str_starts_with((string) $context['signature'], 'sha256=');
            }));

        $dispatcher = app(WebhookDispatcherInterface::class);
        $dispatcher->dispatch('AlertTriggered', [
            'alert_id' => 99,
            'rule' => 'cost_per_kg_drift',
            'severity' => 'high',
        ], $this->organization->id, $this->plant->id);
    }

    public function test_alert_notification_stored_in_database_and_mail(): void
    {
        Notification::fake();

        $manager = User::factory()->create(['role' => 'plant_manager']);
        $manager->plants()->attach($this->plant->id, ['tenant_id' => $this->organization->id]);

        $store = app(AlertStore::class);
        $store->sync([[
            'plant_id' => $this->plant->id,
            'rule' => 'yield_below_threshold',
            'severity' => 'high',
            'message' => 'VDC yield fell below 91%',
        ]]);

        Notification::assertSentTo($manager, AlertTriggered::class, function (AlertTriggered $notification) {
            $channels = $notification->via($this->plant);

            return in_array('mail', $channels, true) && in_array('database', $channels, true);
        });
    }

    private function createRule(string $code, float $threshold, string $operator): AlertRule
    {
        return AlertRule::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'code' => $code,
            'name' => ucfirst(str_replace('_', ' ', $code)),
            'severity' => 'high',
            'enabled' => true,
            'threshold_value' => $threshold,
            'comparison_operator' => $operator,
            'message_template' => '{plant_code} {rule_name} triggered with value {value}.',
        ]);
    }
}
