<?php

use App\Domain\Alerts\Models\Alert;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Http\Controllers\HealthController;
use App\Services\Costing\PlantCostSummaryService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    $tablesPresent = Schema::hasTable('plants') && Schema::hasTable('alerts') && Schema::hasTable('cost_incidents');

    if (! $tablesPresent) {
        return view('verdeco-home', [
            'plantCount' => 0,
            'alertCount' => 0,
            'incidentCount' => 0,
            'plantSummaries' => [],
            'recentAlerts' => collect(),
        ]);
    }

    $plantCount = Plant::query()->count();
    $alertCount = Alert::query()->where('status', 'open')->count();
    $incidentCount = CostIncident::query()->where('status', '!=', 'closed')->count();
    $plantSummaries = (new PlantCostSummaryService())->forOrganization();
    $recentAlerts = Alert::query()
        ->with('plant')
        ->where('status', 'open')
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

    $plants = Plant::query()
        ->withCount(['costIncidents as unresolved_incidents' => fn ($query) => $query->where('status', '!=', 'closed')])
        ->get()
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
            'active_alerts' => Schema::hasTable('alerts') ? Alert::query()->where('status', 'open')->count() : 0,
            'open_incidents' => Schema::hasTable('cost_incidents') ? CostIncident::query()->where('status', '!=', 'closed')->count() : 0,
        ],
    ]);
});

Route::get('/api/alerts', function () {
    if (! Schema::hasTable('alerts')) {
        return response()->json([
            'count' => 0,
            'alerts' => [],
        ]);
    }

    $alerts = Alert::query()
        ->with(['plant', 'acknowledgedBy', 'resolvedBy'])
        ->where('status', 'open')
        ->orderByDesc('triggered_at')
        ->get()
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
});
