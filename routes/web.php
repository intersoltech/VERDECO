<?php

use App\Domain\Alerts\Models\Alert;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Http\Controllers\HealthController;
use App\Services\Costing\PlantCostSummaryService;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    $user = auth()->user();
    $tablesPresent = Schema::hasTable('plants') && Schema::hasTable('alerts') && Schema::hasTable('cost_incidents');

    if (! $user || ! $tablesPresent) {
        return view('verdeco-home', [
            'plantCount' => 0,
            'alertCount' => 0,
            'incidentCount' => 0,
            'plantSummaries' => [],
            'recentAlerts' => collect(),
        ]);
    }

    $plantIds = $user->hasRole('admin') ? null : $user->accessiblePlantIds();
    $plantQuery = Plant::query();
    $alertQuery = Alert::query()->where('status', 'open');
    $incidentQuery = CostIncident::query()->where('status', '!=', 'closed');

    if ($plantIds !== null) {
        $plantQuery->whereKey($plantIds);
        $alertQuery->whereIn('plant_id', $plantIds);
        $incidentQuery->whereIn('plant_id', $plantIds);
    }

    $plantCount = $plantQuery->count();
    $alertCount = $alertQuery->count();
    $incidentCount = $incidentQuery->count();
    $plantSummaries = (new PlantCostSummaryService)->forOrganization($user);
    $recentAlerts = (clone $alertQuery)
        ->with('plant')
        ->orderByDesc('triggered_at')
        ->limit(4)
        ->get();

    return view('verdeco-home', [
        'plantCount' => $plantCount,
        'alertCount' => $alertCount,
        'incidentCount' => $incidentCount,
        'plantSummaries' => $plantSummaries,
        'recentAlerts' => $recentAlerts,
    ]);
});

Route::get('/health', HealthController::class);

Route::get('/api/ops-summary', function () {
    if (! Schema::hasTable('plants')) {
        return response()->json([
            'plants' => [],
            'totals' => [
                'plants' => 0,
                'active_alerts' => 0,
                'open_incidents' => 0,
            ],
        ]);
    }

    $user = auth()->user();
    $plantIds = $user->hasRole('admin') ? null : $user->accessiblePlantIds();
    $plantQuery = Plant::query()
        ->withCount(['costIncidents as unresolved_incidents' => fn ($query) => $query->where('status', '!=', 'closed')]);

    $alertQuery = Alert::query()->where('status', 'open');
    $incidentQuery = CostIncident::query()->where('status', '!=', 'closed');

    if ($plantIds !== null) {
        $plantQuery->whereKey($plantIds);
        $alertQuery->whereIn('plant_id', $plantIds);
        $incidentQuery->whereIn('plant_id', $plantIds);
    }

    $plants = $plantQuery->get()
        ->map(fn (Plant $plant) => [
            'id' => $plant->id,
            'name' => $plant->name,
            'code' => $plant->code,
            'location' => $plant->location,
            'costing_mode' => $plant->costing_mode,
            'status' => $plant->is_active ? 'active' : 'inactive',
            'unresolved_incidents' => (int) $plant->unresolved_incidents,
        ])
        ->all();

    return response()->json([
        'plants' => $plants,
        'totals' => [
            'plants' => count($plants),
            'active_alerts' => Schema::hasTable('alerts') ? $alertQuery->count() : 0,
            'open_incidents' => Schema::hasTable('cost_incidents') ? $incidentQuery->count() : 0,
        ],
    ]);
})->middleware('auth');

Route::get('/api/alerts', function () {
    if (! Schema::hasTable('alerts')) {
        return response()->json([
            'count' => 0,
            'alerts' => [],
        ]);
    }

    $user = auth()->user();
    $alertQuery = Alert::query()
        ->with(['plant', 'acknowledgedBy', 'resolvedBy'])
        ->where('status', 'open');

    if (! $user->hasRole('admin')) {
        $alertQuery->whereIn('plant_id', $user->accessiblePlantIds());
    }

    $alerts = $alertQuery->orderByDesc('triggered_at')->get()
        ->map(fn (Alert $alert) => [
            'id' => $alert->id,
            'plant' => $alert->plant?->code ?? 'Unknown plant',
            'rule' => $alert->rule,
            'severity' => $alert->severity,
            'message' => $alert->message,
            'triggered_at' => $alert->triggered_at?->toISOString(),
            'acknowledged_at' => $alert->acknowledged_at?->toISOString(),
            'acknowledged_by' => $alert->acknowledgedBy?->name,
            'resolved_at' => $alert->resolved_at?->toISOString(),
            'resolved_by' => $alert->resolvedBy?->name,
        ])
        ->all();

    return response()->json([
        'count' => count($alerts),
        'alerts' => $alerts,
    ]);
})->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/reports/batch/{batch}/pdf', function (ProductionBatch $batch, ExportService $exportService) {
        return $exportService->downloadBatchPdf($batch);
    })->name('reports.batch.pdf');

    Route::get('/reports/batch/{batch}/excel', function (ProductionBatch $batch, ExportService $exportService) {
        return $exportService->downloadBatchExcel($batch);
    })->name('reports.batch.excel');

    Route::get('/reports/monthly-variance/excel', function (Request $request, ExportService $exportService) {
        $plantId = $request->query('plant_id') ? (int) $request->query('plant_id') : null;
        $month = $request->query('month');

        return $exportService->downloadMonthlyVarianceExcel($plantId, $month);
    })->name('reports.variance.excel');
});
