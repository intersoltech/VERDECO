<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Domain\Integrations\Contracts\WebhookDispatcherInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class WebhookDispatcher implements WebhookDispatcherInterface
{
    public function dispatch(string $event, array $payload, ?int $tenantId = null, ?int $plantId = null): void
    {
        $timestamp = (string) time();
        $secret = (string) config('services.verdeco.webhook_secret', 'verdeco-webhook-secret-key');

        $envelope = [
            'event' => $event,
            'timestamp' => $timestamp,
            'tenant_id' => $tenantId,
            'plant_id' => $plantId,
            'data' => $payload,
        ];

        $jsonPayload = json_encode($envelope, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$jsonPayload, $secret);

        $url = config('services.verdeco.webhook_url');

        if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            try {
                Http::timeout(5)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Verdeco-Signature' => 'sha256='.$signature,
                        'X-Verdeco-Event' => $event,
                        'X-Verdeco-Timestamp' => $timestamp,
                    ])
                    ->post($url, $envelope);
            } catch (\Throwable $e) {
                Log::warning('Webhook delivery failed', [
                    'event' => $event,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Signed webhook dispatched', [
            'event' => $event,
            'plant_id' => $plantId,
            'signature' => 'sha256='.$signature,
        ]);
    }
}
