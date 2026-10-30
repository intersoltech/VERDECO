<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Integrations\Contracts\WebhookDispatcherInterface;
use App\Domain\Organization\Models\Plant;
use App\Models\User;
use App\Notifications\AlertTriggered;
use Illuminate\Support\Facades\Notification;

final class AlertStore
{
    /**
     * @param  array<int, array<string, mixed>>  $alerts
     * @return array<int, Alert>
     */
    public function sync(array $alerts): array
    {
        $activeKeys = [];
        $persisted = [];

        foreach ($alerts as $alertData) {
            $plantId = (int) ($alertData['plant_id'] ?? 0);
            $rule = (string) ($alertData['rule'] ?? '');

            if ($plantId <= 0 || $rule === '') {
                continue;
            }

            $activeKeys[sprintf('%d:%s', $plantId, $rule)] = true;

            $plant = Plant::query()->find($plantId);
            $tenantId = $plant?->tenant_id ?? 1;

            $alert = Alert::query()
                ->where('plant_id', $plantId)
                ->where('rule', $rule)
                ->whereIn('status', ['open', 'acknowledged'])
                ->first();

            if ($alert) {
                $alert->update([
                    'tenant_id' => $tenantId,
                    'severity' => $alertData['severity'] ?? $alert->severity,
                    'message' => $alertData['message'] ?? $alert->message,
                    'triggered_at' => $alert->triggered_at ?? now(),
                ]);
            } else {
                $alert = Alert::query()->create([
                    'tenant_id' => $tenantId,
                    'plant_id' => $plantId,
                    'rule' => $rule,
                    'severity' => $alertData['severity'] ?? 'info',
                    'status' => 'open',
                    'message' => $alertData['message'] ?? '',
                    'triggered_at' => now(),
                ]);

                // Notify plant users and admins
                $plantUsers = User::query()
                    ->where(function ($query) use ($plantId) {
                        $query->whereHas('plants', fn ($q) => $q->where('plants.id', $plantId))
                            ->orWhere('role', 'admin');
                    })
                    ->get();

                if ($plantUsers->isNotEmpty()) {
                    Notification::send($plantUsers, new AlertTriggered($alert));
                } else {
                    Notification::route('mail', 'admin@verdeco.local')
                        ->notify(new AlertTriggered($alert));
                }

                // Dispatch signed webhook
                app(WebhookDispatcherInterface::class)->dispatch('AlertTriggered', [
                    'alert_id' => $alert->id,
                    'plant_id' => $plantId,
                    'plant_code' => $plant?->code,
                    'rule' => $alert->rule,
                    'severity' => $alert->severity,
                    'message' => $alert->message,
                    'triggered_at' => $alert->triggered_at?->toIso8601String(),
                ], $tenantId, $plantId);
            }

            $persisted[] = $alert;
        }

        $staleAlerts = Alert::query()->whereIn('status', ['open', 'acknowledged'])->get();

        foreach ($staleAlerts as $staleAlert) {
            $key = sprintf('%d:%s', $staleAlert->plant_id, $staleAlert->rule);

            if (! isset($activeKeys[$key])) {
                $staleAlert->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                ]);
            }
        }

        return $persisted;
    }
}
