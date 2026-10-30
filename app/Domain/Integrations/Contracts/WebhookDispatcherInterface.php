<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

interface WebhookDispatcherInterface
{
    /**
     * Dispatch an outbound signed webhook event to subscribed endpoints.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload, ?int $tenantId = null, ?int $plantId = null): void;
}
