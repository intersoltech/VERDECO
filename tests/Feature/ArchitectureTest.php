<?php

declare(strict_types=1);

test('costing services do not use debug functions')
    ->expect('App\Services\Costing')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

test('domain models extend eloquent model')
    ->expect([
        'App\Domain\Alerts\Models',
        'App\Domain\Costing\Models',
        'App\Domain\Incidents\Models',
        'App\Domain\MasterData\Models',
        'App\Domain\Organization\Models',
        'App\Domain\Production\Models',
        'App\Domain\Quality\Models',
        'App\Domain\Reporting\Models',
    ])
    ->toExtend('Illuminate\Database\Eloquent\Model');

test('controllers extend base controller')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller')
    ->ignoring('App\Http\Controllers\Controller');
