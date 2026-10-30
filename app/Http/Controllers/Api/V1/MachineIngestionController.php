<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organization\Models\Plant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkUploadRequest;
use App\Http\Requests\Api\V1\IngestDowntimeEventRequest;
use App\Http\Requests\Api\V1\IngestMeterReadingRequest;
use App\Http\Requests\Api\V1\IngestProductionTickRequest;
use App\Services\Integrations\Adapters\CsvIngestionAdapter;
use App\Services\Integrations\Adapters\JsonScadaIngestionAdapter;
use App\Services\Integrations\MachineDataIngestionService;
use Illuminate\Http\JsonResponse;

final class MachineIngestionController extends Controller
{
    public function __construct(
        private readonly MachineDataIngestionService $ingestionService
    ) {}

    public function ingestMeterReading(IngestMeterReadingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $plantId = (int) $request->input('plant_id');
        $tenantId = (int) Plant::findOrFail($plantId)->tenant_id;

        $result = $this->ingestionService->ingestMeterReading($validated, $tenantId, $plantId);

        $status = ($result['status'] ?? '') === 'duplicate' ? 200 : 201;

        return response()->json([
            'success' => true,
            'data' => $result,
        ], $status);
    }

    public function ingestProductionTick(IngestProductionTickRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $plantId = (int) $request->input('plant_id');
        $tenantId = (int) Plant::findOrFail($plantId)->tenant_id;

        $result = $this->ingestionService->ingestProductionTick($validated, $tenantId, $plantId);

        $status = ($result['status'] ?? '') === 'duplicate' ? 200 : 201;

        return response()->json([
            'success' => true,
            'data' => $result,
        ], $status);
    }

    public function ingestDowntimeEvent(IngestDowntimeEventRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $plantId = (int) $request->input('plant_id');
        $tenantId = (int) Plant::findOrFail($plantId)->tenant_id;

        $result = $this->ingestionService->ingestDowntimeEvent($validated, $tenantId, $plantId);

        $status = ($result['status'] ?? '') === 'duplicate' ? 200 : 201;

        return response()->json([
            'success' => true,
            'data' => $result,
        ], $status);
    }

    public function bulkUpload(BulkUploadRequest $request): JsonResponse
    {
        $plantId = (int) $request->input('plant_id');
        $tenantId = (int) Plant::findOrFail($plantId)->tenant_id;
        $format = (string) $request->input('format');

        $adapter = $format === 'csv'
            ? new CsvIngestionAdapter
            : new JsonScadaIngestionAdapter;

        if ($request->hasFile('file')) {
            $payload = file_get_contents($request->file('file')->getRealPath());
        } else {
            $payload = $request->input('payload');
        }

        $summary = $this->ingestionService->bulkIngest($adapter, $payload, $tenantId, $plantId);

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ], 200);
    }
}
