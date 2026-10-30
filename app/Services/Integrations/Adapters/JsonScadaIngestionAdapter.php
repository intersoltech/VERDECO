<?php

declare(strict_types=1);

namespace App\Services\Integrations\Adapters;

use App\Domain\Integrations\Contracts\IngestionAdapterInterface;
use Carbon\Carbon;
use InvalidArgumentException;

final class JsonScadaIngestionAdapter implements IngestionAdapterInterface
{
    public function sourceName(): string
    {
        return 'scada_json';
    }

    /**
     * @param  string|array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function parse(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw new InvalidArgumentException('Malformed JSON SCADA payload: '.json_last_error_msg());
            }
        } elseif (is_array($payload)) {
            $decoded = $payload;
        } else {
            throw new InvalidArgumentException('Expected JSON string or decoded array.');
        }

        // Support single record or list of readings in "readings" / "events" wrapper
        $items = isset($decoded['readings']) && is_array($decoded['readings'])
            ? $decoded['readings']
            : (isset($decoded[0]) ? $decoded : [$decoded]);

        $records = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $timestampStr = $item['read_at'] ?? $item['timestamp'] ?? null;
            $readAt = $timestampStr ? Carbon::parse($timestampStr) : Carbon::now();

            $records[] = [
                'external_id' => $item['external_id'] ?? $item['event_id'] ?? $item['message_id'] ?? null,
                'line_code' => $item['line_code'] ?? $item['line'] ?? null,
                'batch_number' => $item['batch_number'] ?? null,
                'reading_type' => strtolower((string) ($item['reading_type'] ?? 'meter_reading')),
                'meter_type' => isset($item['meter_type']) ? strtolower((string) $item['meter_type']) : null,
                'reading_value' => isset($item['reading_value']) && is_numeric($item['reading_value']) ? (float) $item['reading_value'] : null,
                'unit' => isset($item['unit']) ? strtolower((string) $item['unit']) : null,
                'downtime_reason' => $item['downtime_reason'] ?? $item['reason_code'] ?? null,
                'duration_minutes' => isset($item['duration_minutes']) && is_numeric($item['duration_minutes']) ? (int) $item['duration_minutes'] : null,
                'read_at' => $readAt->toDateTimeString(),
                'raw' => $item,
            ];
        }

        return $records;
    }
}
