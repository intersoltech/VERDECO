<?php

namespace Tests\Feature;

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AlertEvaluationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_alerts_creates_open_alert_for_threshold_hit(): void
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'verdeco',
            'country_code' => 'US',
        ]);

        $plant = Plant::create([
            'tenant_id' => $organization->id,
            'name' => 'Dallas Plant',
            'code' => 'DAL',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'batch',
            'is_active' => true,
        ]);

        CostIncident::create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'line_id' => null,
            'incident_type' => 'breakdown',
            'severity' => 'high',
            'root_cause_category' => 'mechanical',
            'description' => 'Unexpected outage',
            'owner' => 'maintenance',
            'status' => 'approved',
            'occurred_at' => now()->subDay(),
            'cash_cost' => 1200.00,
            'opportunity_cost' => 300.00,
            'allocation_rule' => 'by_kg',
        ]);

        AlertRule::create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'code' => 'incident_cost_threshold',
            'name' => 'Incident cost threshold',
            'description' => 'Open incident cash cost exceeds the threshold.',
            'severity' => 'high',
            'enabled' => true,
            'threshold_value' => 1000,
            'comparison_operator' => 'gt',
            'cooldown_minutes' => 60,
            'message_template' => '{plant_code} incident cost is {value} against a threshold of {threshold}.',
        ]);

        $exitCode = Artisan::call('app:evaluate-alerts');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('alerts', [
            'plant_id' => $plant->id,
            'rule' => 'incident_cost_threshold',
            'status' => 'open',
        ]);
    }
}
