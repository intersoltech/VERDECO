<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Reporting\Models\FactDailyPlantKpi;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperationalSummaryController extends Controller
{
    public function plants(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Plant::query()->where('is_active', true)->with('productionLines');

        if ($user && $user->role !== 'admin') {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        $plants = $query->get()->map(fn (Plant $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'code' => $p->code,
            'location' => $p->location,
            'timezone' => $p->timezone,
            'base_currency' => $p->base_currency,
            'costing_mode' => $p->costing_mode,
            'lines' => $p->productionLines->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'code' => $l->code,
                'type' => $l->type,
                'rated_kg_per_hr' => $l->rated_kg_per_hr,
                'rated_kw' => $l->rated_kw,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'count' => $plants->count(),
            'data' => $plants,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $plantId = $request->query('plant_id');
        $plantCode = $request->query('plant_code');

        if ($plantCode && ! $plantId) {
            $plantId = Plant::where('code', $plantCode)->value('id');
        }

        if ($plantId) {
            $plant = Plant::findOrFail((int) $plantId);
            if ($user && $user->role !== 'admin' && ! $user->plants()->where('plants.id', $plant->id)->exists()) {
                return response()->json(['message' => 'Unauthorized access to plant.'], 403);
            }
            $plants = collect([$plant]);
        } else {
            $plantsQuery = Plant::query()->where('is_active', true);
            if ($user && $user->role !== 'admin') {
                $plantsQuery->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
            }
            $plants = $plantsQuery->get();
        }

        $today = Carbon::today()->toDateString();

        $summaries = $plants->map(function (Plant $p) use ($today) {
            $kpi = FactDailyPlantKpi::query()
                ->where('plant_id', $p->id)
                ->where('production_date', $today)
                ->first();

            $runningBatches = ProductionBatch::query()
                ->where('plant_id', $p->id)
                ->where('status', 'running')
                ->get(['id', 'batch_number', 'line_id', 'batch_date', 'status', 'good_output_kg', 'utility_kwh']);

            $recentDowntime = DowntimeEvent::query()
                ->where('plant_id', $p->id)
                ->whereDate('start_at', $today)
                ->orderByDesc('start_at')
                ->limit(5)
                ->get(['id', 'line_id', 'downtime_type', 'reason_code', 'start_at', 'duration_minutes']);

            $openAlertsCount = Alert::query()
                ->where('plant_id', $p->id)
                ->whereIn('status', ['open', 'acknowledged'])
                ->count();

            return [
                'plant_id' => $p->id,
                'plant_code' => $p->code,
                'plant_name' => $p->name,
                'date' => $today,
                'kpi' => [
                    'good_output_kg' => (float) ($kpi?->good_output_kg ?? 0),
                    'yield_percent' => (float) ($kpi?->yield_percent ?? 0),
                    'cost_per_kg' => (float) ($kpi?->cost_per_kg ?? 0),
                    'kwh_per_kg' => (float) ($kpi?->kwh_per_kg ?? 0),
                    'open_incident_count' => (int) ($kpi?->open_incident_count ?? 0),
                ],
                'running_batches' => $runningBatches,
                'recent_downtime' => $recentDowntime,
                'open_alerts_count' => $openAlertsCount,
            ];
        });

        return response()->json([
            'success' => true,
            'date' => $today,
            'plants' => $summaries,
        ]);
    }
}
