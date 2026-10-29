<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\VerdecoDemoSeeder;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_admin_account_can_authenticate_and_access_the_admin_panel(): void
    {
        $this->seed(VerdecoDemoSeeder::class);

        $admin = User::query()->where('email', 'admin@verdeco.local')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue(Auth::attempt([
            'email' => 'admin@verdeco.local',
            'password' => 'password',
        ]));
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_admin_login_page_is_available_to_guests(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_filament_login_form_authenticates_the_seeded_admin(): void
    {
        $this->seed(VerdecoDemoSeeder::class);
        config(['app.env' => 'production']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@verdeco.local',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertRedirect();

        $this->assertAuthenticatedAs(User::query()->where('email', 'admin@verdeco.local')->firstOrFail());
    }
}
