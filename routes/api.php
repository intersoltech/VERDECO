<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MachineIngestionController;
use App\Http\Controllers\Api\V1\OperationalSummaryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        // SCADA & Machine Ingestion Endpoints
        Route::post('ingest/meter-readings', [MachineIngestionController::class, 'ingestMeterReading']);
        Route::post('ingest/production-ticks', [MachineIngestionController::class, 'ingestProductionTick']);
        Route::post('ingest/downtime-events', [MachineIngestionController::class, 'ingestDowntimeEvent']);
        Route::post('ingest/bulk-upload', [MachineIngestionController::class, 'bulkUpload']);

        // Operational Read Endpoints
        Route::get('plants', [OperationalSummaryController::class, 'plants']);
        Route::get('operations/summary', [OperationalSummaryController::class, 'summary']);
    });
});
