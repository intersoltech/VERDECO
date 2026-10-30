<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

interface WebhookDispatcherInterface
{
    /**
     * Dispatch an outbound signed webhook event to subscribed endpoints.
     *
     * @param string $event
     * @param array<string, mixed> $payload
     * @param int|null $tenantId
     * @param int|null $plantId
     */
    public function dispatch(string $event, array $payload, ?int $tenantId = null, ?int $plantId = null): void;
}
