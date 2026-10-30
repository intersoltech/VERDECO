<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $alerts
     */
    public function __construct(private readonly array $alerts) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Verdeco alert digest')
            ->line(sprintf('There are %d open alert(s) requiring attention.', count($this->alerts)));

        foreach ($this->alerts as $alert) {
            $mail->line(sprintf(
                '[%s] %s | %s',
                strtoupper((string) ($alert['severity'] ?? 'info')),
                $alert['plant_code'] ?? 'Unknown plant',
                $alert['message'] ?? 'No message provided.'
            ));
        }

        return $mail->action('Open dashboard', url('/admin'));
    }
}
