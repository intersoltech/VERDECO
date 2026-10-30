<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Domain\Integrations\Contracts\IngestionAdapterInterface;
use App\Domain\Integrations\Models\StagedMachineReading;
use App\Domain\Production\Models\DowntimeEvent;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionLine;
use App\Services\Costing\DecimalMath;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class MachineDataIngestionService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function ingestMeterReading(array $data, int $tenantId, int $plantId): array
    {
        $externalId = isset($data['external_id']) && trim((string) $data['external_id']) !== ''
            ? trim((string) $data['external_id'])
            : null;

        if ($externalId !== null) {
            $existing = StagedMachineReading::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $externalId)
                ->first();

            if ($existing) {
                return [
                    'status' => 'duplicate',
                    'reading_id' => $existing->id,
                    'message' => 'Duplicate meter reading ignored (idempotent replay).',
                    'reading' => $existing,
                ];
            }
        }

        $line = $this->resolveLine($plantId, $data['line_id'] ?? null, $data['line_code'] ?? null);
        $batch = $this->resolveBatch($plantId, $line?->id, $data['production_batch_id'] ?? null, $data['batch_number'] ?? null);

        $meterType = (string) ($data['meter_type'] ?? 'electricity_kwh');
        $readingValue = (float) ($data['reading_value'] ?? 0.0);
        $unit = (string) ($data['unit'] ?? 'kwh');
        $readAt = isset($data['read_at']) ? Carbon::parse($data['read_at']) : Carbon::now();

        return DB::transaction(function () use ($tenantId, $plantId, $line, $batch, $data, $externalId, $meterType, $readingValue, $unit, $readAt) {
            $staged = StagedMachineReading::query()->create([
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'line_id' => $line?->id,
                'production_batch_id' => $batch?->id,
                'source' => (string) ($data['source'] ?? 'scada_meter'),
                'external_id' => $externalId,
                'reading_type' => 'meter_reading',
                'meter_type' => $meterType,
                'reading_value' => $readingValue,
                'unit' => $unit,
                'status' => 'processed',
                'payload' => $data,
                'read_at' => $readAt,
                'processed_at' => Carbon::now(),
            ]);

            // If an active batch exists and this is electricity kWh, accumulate usage
            if ($batch && $meterType === 'electricity_kwh' && $readingValue > 0) {
                $isDelta = (bool) ($data['is_delta'] ?? true);
                if ($isDelta) {
                    $newKwh = DecimalMath::add((string) $batch->utility_kwh, (string) $readingValue);
                    $batch->update(['utility_kwh' => $newKwh]);
                }
            }

            return [
                'status' => 'processed',
                'reading_id' => $staged->id,
                'reading_type' => 'meter_reading',
                'meter_type' => $meterType,
                'reading_value' => $readingValue,
                'unit' => $unit,
                'line_id' => $line?->id,
                'batch_id' => $batch?->id,
                'read_at' => $staged->read_at->toIso8601String(),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function ingestProductionTick(array $data, int $tenantId, int $plantId): array
    {
        $externalId = isset($data['external_id']) && trim((string) $data['external_id']) !== ''
            ? trim((string) $data['external_id'])
            : null;

        if ($externalId !== null) {
            $existing = StagedMachineReading::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $externalId)
                ->first();

            if ($existing) {
                return [
                    'status' => 'duplicate',
                    'reading_id' => $existing->id,
                    'message' => 'Duplicate production tick ignored (idempotent replay).',
                    'reading' => $existing,
                ];
            }
        }

        $line = $this->resolveLine($plantId, $data['line_id'] ?? null, $data['line_code'] ?? null);
        $batch = $this->resolveBatch($plantId, $line?->id, $data['production_batch_id'] ?? null, $data['batch_number'] ?? null);

        $unit = strtolower((string) ($data['unit'] ?? 'kg'));
        $rawWeight = (float) ($data['weight'] ?? $data['reading_value'] ?? 0.0);

        // Standardize weight internally to Kilograms
        $weightInKg = ($unit === 'lb' || $unit === 'lbs')
            ? (float) DecimalMath::divide((string) $rawWeight, '2.20462')
            : $rawWeight;

        $readAt = isset($data['read_at']) ? Carbon::parse($data['read_at']) : Carbon::now();

        return DB::transaction(function () use ($tenantId, $plantId, $line, $batch, $data, $externalId, $rawWeight, $weightInKg, $unit, $readAt) {
            $staged = StagedMachineReading::query()->create([
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'line_id' => $line?->id,
                'production_batch_id' => $batch?->id,
                'source' => (string) ($data['source'] ?? 'scale_counter'),
                'external_id' => $externalId,
                'reading_type' => 'production_tick',
                'meter_type' => 'weight_counter',
                'reading_value' => $weightInKg,
                'unit' => 'kg',
                'status' => 'processed',
                'payload' => array_merge($data, ['original_weight' => $rawWeight, 'original_unit' => $unit]),
                'read_at' => $readAt,
                'processed_at' => Carbon::now(),
            ]);

            // Accumulate output to running batch if present
            if ($batch && $weightInKg > 0) {
                $newOutput = DecimalMath::add((string) $batch->good_output_kg, (string) $weightInKg);
                $batch->update(['good_output_kg' => $newOutput]);
            }

            return [
                'status' => 'processed',
                'reading_id' => $staged->id,
                'reading_type' => 'production_tick',
                'weight_kg' => $weightInKg,
                'original_weight' => $rawWeight,
                'original_unit' => $unit,
                'line_id' => $line?->id,
                'batch_id' => $batch?->id,
                'read_at' => $staged->read_at->toIso8601String(),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function ingestDowntimeEvent(array $data, int $tenantId, int $plantId): array
    {
        $externalId = isset($data['external_id']) && trim((string) $data['external_id']) !== ''
            ? trim((string) $data['external_id'])
            : null;

        if ($externalId !== null) {
            $existing = StagedMachineReading::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $externalId)
                ->first();

            if ($existing) {
                return [
                    'status' => 'duplicate',
                    'reading_id' => $existing->id,
                    'message' => 'Duplicate downtime event ignored (idempotent replay).',
                    'reading' => $existing,
                ];
            }
        }

        $line = $this->resolveLine($plantId, $data['line_id'] ?? null, $data['line_code'] ?? null);
        if (! $line) {
            throw new InvalidArgumentException('Line could not be identified for plant ID '.$plantId);
        }

        $startAt = isset($data['start_at']) ? Carbon::parse($data['start_at']) : Carbon::now()->subMinutes(15);
        $endAt = isset($data['end_at']) ? Carbon::parse($data['end_at']) : null;

        $durationMinutes = isset($data['duration_minutes']) && is_numeric($data['duration_minutes'])
            ? (int) $data['duration_minutes']
            : ($endAt ? (int) $startAt->diffInMinutes($endAt) : 15);

        $downtimeType = in_array(strtolower((string) ($data['downtime_type'] ?? 'unplanned')), ['planned', 'unplanned'], true)
            ? strtolower((string) ($data['downtime_type'] ?? 'unplanned'))
            : 'unplanned';

        $reasonCode = (string) ($data['reason_code'] ?? $data['downtime_reason'] ?? 'scada_auto_fault');
        $notes = (string) ($data['notes'] ?? 'Auto-logged by SCADA integration');

        return DB::transaction(function () use ($tenantId, $plantId, $line, $data, $externalId, $downtimeType, $reasonCode, $startAt, $endAt, $durationMinutes, $notes) {
            // Create the operational downtime event
            $event = DowntimeEvent::query()->create([
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'line_id' => $line->id,
                'downtime_type' => $downtimeType,
                'reason_code' => $reasonCode,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'duration_minutes' => $durationMinutes,
                'notes' => $notes,
            ]);

            // Create staged reading audit record
            $staged = StagedMachineReading::query()->create([
                'tenant_id' => $tenantId,
                'plant_id' => $plantId,
                'line_id' => $line->id,
                'source' => (string) ($data['source'] ?? 'scada_plc'),
                'external_id' => $externalId,
                'reading_type' => 'downtime_event',
                'reading_value' => $durationMinutes,
                'unit' => 'minutes',
                'status' => 'processed',
                'payload' => array_merge($data, ['downtime_event_id' => $event->id]),
                'read_at' => $startAt,
                'processed_at' => Carbon::now(),
            ]);

            return [
                'status' => 'processed',
                'reading_id' => $staged->id,
                'downtime_event_id' => $event->id,
                'line_id' => $line->id,
                'line_code' => $line->code,
                'duration_minutes' => $durationMinutes,
                'reason_code' => $reasonCode,
                'downtime_type' => $downtimeType,
                'start_at' => $startAt->toIso8601String(),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function bulkIngest(IngestionAdapterInterface $adapter, mixed $payload, int $tenantId, int $plantId): array
    {
        $records = $adapter->parse($payload);
        $results = [
            'total' => count($records),
            'processed' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($records as $index => $record) {
            try {
                $type = $record['reading_type'] ?? 'meter_reading';
                $record['source'] = $adapter->sourceName();

                $res = match ($type) {
                    'production_tick', 'weight', 'counter' => $this->ingestProductionTick($record, $tenantId, $plantId),
                    'downtime_event', 'downtime' => $this->ingestDowntimeEvent($record, $tenantId, $plantId),
                    default => $this->ingestMeterReading($record, $tenantId, $plantId),
                };

                if (($res['status'] ?? '') === 'duplicate') {
                    $results['duplicates']++;
                } else {
                    $results['processed']++;
                }

                $results['details'][] = $res;
            } catch (\Throwable $e) {
                Log::warning('Bulk ingestion record failure', [
                    'index' => $index,
                    'record' => $record,
                    'error' => $e->getMessage(),
                ]);
                $results['errors']++;
                $results['details'][] = [
                    'status' => 'error',
                    'index' => $index,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function resolveLine(int $plantId, mixed $lineId, mixed $lineCode): ?ProductionLine
    {
        if ($lineId) {
            return ProductionLine::query()
                ->where('plant_id', $plantId)
                ->where('id', (int) $lineId)
                ->first();
        }

        if ($lineCode) {
            return ProductionLine::query()
                ->where('plant_id', $plantId)
                ->where('code', (string) $lineCode)
                ->first();
        }

        return null;
    }

    private function resolveBatch(int $plantId, ?int $lineId, mixed $batchId, mixed $batchNumber): ?ProductionBatch
    {
        if ($batchId) {
            return ProductionBatch::query()
                ->where('plant_id', $plantId)
                ->where('id', (int) $batchId)
                ->first();
        }

        if ($batchNumber) {
            return ProductionBatch::query()
                ->where('plant_id', $plantId)
                ->where('batch_number', (string) $batchNumber)
                ->first();
        }

        // Fallback: locate running batch on the line
        if ($lineId) {
            return ProductionBatch::query()
                ->where('plant_id', $plantId)
                ->where('line_id', $lineId)
                ->where('status', 'running')
                ->latest('batch_date')
                ->first();
        }

        return null;
    }
}
