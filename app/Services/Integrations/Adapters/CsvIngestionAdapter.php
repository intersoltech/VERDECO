<?php

declare(strict_types=1);

namespace App\Services\Integrations\Adapters;

use App\Domain\Integrations\Contracts\IngestionAdapterInterface;
use Carbon\Carbon;
use InvalidArgumentException;

final class CsvIngestionAdapter implements IngestionAdapterInterface
{
    public function sourceName(): string
    {
        return 'csv_file';
    }

    /**
     * @param  string|resource  $payload  Raw CSV content or file stream
     * @return array<int, array<string, mixed>>
     */
    public function parse(mixed $payload): array
    {
        if (is_resource($payload)) {
            $content = stream_get_contents($payload);
        } elseif (is_string($payload)) {
            $content = $payload;
        } else {
            throw new InvalidArgumentException('Expected string or stream resource for CSV ingestion.');
        }

        if ($content === false || trim($content) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if ($lines === false || count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $normalizedHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);

        $records = [];

        foreach ($lines as $lineIndex => $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line);
            if (count($row) !== count($normalizedHeaders)) {
                continue;
            }

            $mapped = array_combine($normalizedHeaders, $row);

            $timestampStr = $mapped['timestamp'] ?? $mapped['date_time'] ?? $mapped['read_at'] ?? null;
            $readAt = $timestampStr ? Carbon::parse($timestampStr) : Carbon::now();

            $records[] = [
                'external_id' => $mapped['external_id'] ?? $mapped['id'] ?? null,
                'line_code' => $mapped['line_code'] ?? $mapped['line'] ?? null,
                'batch_number' => $mapped['batch_number'] ?? $mapped['batch_no'] ?? null,
                'reading_type' => strtolower(trim($mapped['reading_type'] ?? 'meter_reading')),
                'meter_type' => isset($mapped['meter_type']) ? strtolower(trim($mapped['meter_type'])) : null,
                'reading_value' => isset($mapped['value']) && is_numeric($mapped['value']) ? (float) $mapped['value'] : null,
                'unit' => isset($mapped['unit']) ? strtolower(trim($mapped['unit'])) : null,
                'downtime_reason' => $mapped['downtime_reason'] ?? $mapped['reason_code'] ?? null,
                'duration_minutes' => isset($mapped['duration_minutes']) && is_numeric($mapped['duration_minutes']) ? (int) $mapped['duration_minutes'] : null,
                'read_at' => $readAt->toDateTimeString(),
                'raw' => $mapped,
            ];
        }

        return $records;
    }
}
