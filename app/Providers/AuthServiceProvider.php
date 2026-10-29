<?php

namespace App\Providers;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Organization\Models\Plant;
use App\Policies\AlertPolicy;
use App\Policies\AlertRulePolicy;
use App\Policies\PlantPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Plant::class => PlantPolicy::class,
        Alert::class => AlertPolicy::class,
        AlertRule::class => AlertRulePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
