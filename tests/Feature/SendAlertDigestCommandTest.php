<?php

namespace Tests\Feature;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Plant;
use App\Notifications\AlertDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendAlertDigestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_digest_fails_clearly_when_recipient_is_not_configured(): void
    {
        $this->createOpenAlert();
        config(['services.verdeco.alert_digest_email' => null]);

        $exitCode = Artisan::call('app:send-alert-digest');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('ALERT_DIGEST_EMAIL', Artisan::output());
    }

    public function test_digest_sends_when_recipient_is_configured(): void
    {
        Notification::fake();
        $this->createOpenAlert();
        config(['services.verdeco.alert_digest_email' => 'ops@example.com']);

        $exitCode = Artisan::call('app:send-alert-digest');

        $this->assertSame(0, $exitCode);
        Notification::assertSentOnDemand(AlertDigestNotification::class);
    }

    private function createOpenAlert(): void
    {
        $organization = Organization::create([
            'name' => 'Verdeco Group',
            'slug' => 'digest-test',
            'country_code' => 'US',
        ]);

        $plant = Plant::create([
            'tenant_id' => $organization->id,
            'name' => 'Dallas Plant',
            'code' => 'DAL',
            'location' => 'Dallas, TX',
            'timezone' => 'America/Chicago',
            'base_currency' => 'USD',
            'costing_mode' => 'batch',
            'is_active' => true,
        ]);

        Alert::create([
            'tenant_id' => $organization->id,
            'plant_id' => $plant->id,
            'rule' => 'test_alert',
            'severity' => 'high',
            'status' => 'open',
            'message' => 'Test alert',
            'triggered_at' => now(),
        ]);
    }
}
