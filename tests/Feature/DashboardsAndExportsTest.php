<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use App\Models\User;
use App\Services\Reporting\DashboardDisplayHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardsAndExportsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Plant $plant;

    private ProductionLine $line;

    private User $admin;

    private User $operator;

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

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->plants()->attach($this->plant->id, ['tenant_id' => $this->organization->id]);

        $this->operator = User::factory()->create(['role' => 'plant_operator']);
        $this->operator->plants()->attach($this->plant->id, ['tenant_id' => $this->organization->id]);
    }

    public function test_group_overview_dashboard_is_accessible_to_admin(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/group-overview');
        $response->assertOk();
    }

    public function test_plant_view_dashboard_is_accessible(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/plant-view');
        $response->assertOk();
    }

    public function test_incident_center_dashboard_is_accessible(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/incident-center');
        $response->assertOk();
    }

    public function test_unit_conversion_display_helper(): void
    {
        DashboardDisplayHelper::setUnit('kg');
        $this->assertSame('kg', DashboardDisplayHelper::currentUnit());
        $this->assertEquals(1000.0, DashboardDisplayHelper::convertWeight(1000.0));
        $this->assertEquals(1.05, DashboardDisplayHelper::convertUnitCost(1.05));

        DashboardDisplayHelper::setUnit('lb');
        $this->assertSame('lb', DashboardDisplayHelper::currentUnit());
        $this->assertEquals('$/lb', DashboardDisplayHelper::unitCostLabel());

        // 1000 kg -> lb approx 2204.62
        $lb = DashboardDisplayHelper::convertWeight(1000.0);
        $this->assertGreaterThan(2200, $lb);
        $this->assertLessThan(2210, $lb);

        // $1.00/kg -> approx $0.4536/lb
        $costPerLb = DashboardDisplayHelper::convertUnitCost(1.00);
        $this->assertEquals(0.453592, $costPerLb);
    }

    public function test_batch_drill_down_view_page_loads_with_full_cost_sheet(): void
    {
        $batch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'batch_number' => 'VDC-BATCH-202610-VIEW',
            'batch_date' => '2026-10-30',
            'status' => 'costed',
            'input_kg' => 10000.0,
            'good_output_kg' => 9300.0,
            'recovery_credit' => 200.0,
            'material_cost' => 6800.0,
            'labor_cost' => 784.0,
            'utility_cost' => 456.0,
            'overhead_cost' => 1500.0,
            'incident_cost' => 100.0,
            'total_cost' => 9640.0,
            'net_cost' => 9440.0,
            'yield_percent' => 93.0,
            'cost_per_kg' => 1.015054,
            'calculated_at' => now(),
        ]);

        $this->actingAs($this->admin);
        $response = $this->get("/admin/production-batches/{$batch->id}");
        $response->assertOk();
        $response->assertSee('VDC-BATCH-202610-VIEW');
        $response->assertSee('9,440.00'); // Financials visible to admin
    }

    public function test_batch_pdf_cost_sheet_download(): void
    {
        $batch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'batch_number' => 'VDC-PDF-001',
            'batch_date' => '2026-10-30',
            'status' => 'costed',
            'input_kg' => 5000.0,
            'good_output_kg' => 4650.0,
            'net_cost' => 4800.0,
            'cost_per_kg' => 1.032258,
            'yield_percent' => 93.0,
        ]);

        $this->actingAs($this->admin);
        $response = $this->get("/reports/batch/{$batch->id}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_batch_excel_cost_sheet_download(): void
    {
        $batch = ProductionBatch::create([
            'tenant_id' => $this->organization->id,
            'plant_id' => $this->plant->id,
            'line_id' => $this->line->id,
            'batch_number' => 'VDC-XLS-001',
            'batch_date' => '2026-10-30',
            'status' => 'costed',
            'input_kg' => 5000.0,
            'good_output_kg' => 4650.0,
            'net_cost' => 4800.0,
            'cost_per_kg' => 1.032258,
            'yield_percent' => 93.0,
        ]);

        $this->actingAs($this->admin);
        $response = $this->get("/reports/batch/{$batch->id}/excel");

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $response->headers->get('content-type'));
    }

    public function test_monthly_variance_excel_report_download(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get("/reports/monthly-variance/excel?plant_id={$this->plant->id}");

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $response->headers->get('content-type'));
    }
}
