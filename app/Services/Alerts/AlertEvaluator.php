<?php

declare(strict_types=1);

namespace App\Services\Alerts;

final class AlertEvaluator
{
    public function __construct(private readonly AlertRuleEngine $ruleEngine) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function evaluate(): array
    {
        return $this->ruleEngine->evaluate();
    }
}
