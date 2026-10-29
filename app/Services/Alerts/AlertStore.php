<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Organization\Models\Plant;
use App\Notifications\AlertTriggered;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class AlertStore
{
    /**
     * @param array<int, array<string, mixed>> $alerts
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
                ->where('status', 'open')
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

                Notification::route('mail', 'admin@verdeco.local')
                    ->notify(new AlertTriggered($alert));
            }

            $persisted[] = $alert;
        }

        $staleAlerts = Alert::query()->where('status', 'open')->get();

        foreach ($staleAlerts as $staleAlert) {
            $key = sprintf('%d:%s', $staleAlert->plant_id, $staleAlert->rule);

            if (!isset($activeKeys[$key])) {
                $staleAlert->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                ]);
            }
        }

        return $persisted;
    }

    private function notify(Alert $alert): void
    {
        Log::info('Plant alert triggered', [
            'alert_id' => $alert->id,
            'plant_id' => $alert->plant_id,
            'rule' => $alert->rule,
            'severity' => $alert->severity,
            'message' => $alert->message,
        ]);
    }
}
