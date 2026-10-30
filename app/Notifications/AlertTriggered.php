<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Alerts\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertTriggered extends Notification
{
    use Queueable;

    public function __construct(public Alert $alert) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(sprintf('[%s] Plant %s alert: %s', strtoupper($this->alert->severity), $this->alert->plant?->code ?? 'Unknown', $this->alert->rule))
            ->line($this->alert->message)
            ->action('Review alert', url('/admin/alerts'))
            ->line('This alert was generated automatically by the Verdeco monitoring system.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'plant_id' => $this->alert->plant_id,
            'plant_code' => $this->alert->plant?->code,
            'rule' => $this->alert->rule,
            'severity' => $this->alert->severity,
            'message' => $this->alert->message,
            'triggered_at' => $this->alert->triggered_at?->toIso8601String(),
        ];
    }
}
