<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

interface IngestionAdapterInterface
{
    /**
     * Parse raw incoming payload (CSV, JSON, MQTT/SCADA) into standardized reading records.
     *
     * @param mixed $payload
     * @return array<int, array<string, mixed>>
     */
    public function parse(mixed $payload): array;

    /**
     * Source identifier for the adapter (e.g. 'csv', 'scada_modbus', 'opc_ua').
     */
    public function sourceName(): string;
}
