<?php

namespace App\Providers;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Costing\Models\CostPeriod;
use App\Domain\Costing\Models\StandardCost;
use App\Domain\Incidents\Models\CostIncident;
use App\Domain\Organization\Models\Plant;
use App\Domain\Production\Models\ProductionBatch;
use App\Policies\AlertPolicy;
use App\Policies\AlertRulePolicy;
use App\Policies\CostIncidentPolicy;
use App\Policies\CostPeriodPolicy;
use App\Policies\PlantPolicy;
use App\Policies\ProductionBatchPolicy;
use App\Policies\StandardCostPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Plant::class => PlantPolicy::class,
        Alert::class => AlertPolicy::class,
        AlertRule::class => AlertRulePolicy::class,
        StandardCost::class => StandardCostPolicy::class,
        CostIncident::class => CostIncidentPolicy::class,
        ProductionBatch::class => ProductionBatchPolicy::class,
        CostPeriod::class => CostPeriodPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
