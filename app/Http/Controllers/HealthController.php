<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbHealthy = false;
        $redisHealthy = false;

        try {
            DB::connection()->getPdo();
            $dbHealthy = true;
        } catch (\Throwable) {
            $dbHealthy = false;
        }

        try {
            Redis::ping();
            $redisHealthy = true;
        } catch (\Throwable) {
            $redisHealthy = false;
        }

        return response()->json([
            'status' => ($dbHealthy && $redisHealthy) || $dbHealthy ? 'ok' : 'degraded',
            'checks' => [
                'database' => $dbHealthy,
                'redis' => $redisHealthy,
            ],
            'timestamp' => now()->toISOString(),
        ], $dbHealthy ? 200 : 503);
    }
}
