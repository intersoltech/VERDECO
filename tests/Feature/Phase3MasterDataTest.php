<?php

declare(strict_types=1);

use App\Domain\Costing\Models\CostCategory;
use App\Domain\Costing\Models\StandardCost;
use App\Domain\MasterData\Models\Material;
use App\Domain\MasterData\Models\ProductGrade;
use App\Domain\MasterData\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// 1. StandardCost model relationships
// ---------------------------------------------------------------------------

it('StandardCost model has plant, line, and productGrade relationships', function (): void {
    $sc = new StandardCost;

    expect($sc->plant())->toBeInstanceOf(BelongsTo::class);
    expect($sc->line())->toBeInstanceOf(BelongsTo::class);
    expect($sc->productGrade())->toBeInstanceOf(BelongsTo::class);
});

// ---------------------------------------------------------------------------
// 2. User rolePermissions — admin gets all areas
// ---------------------------------------------------------------------------

it('admin user has cost-categories manage permission', function (): void {
    $user = new User(['role' => 'admin']);

    expect($user->hasPermission('cost-categories.manage'))->toBeTrue()
        ->and($user->hasPermission('cost-categories.read'))->toBeTrue()
        ->and($user->hasPermission('units.manage'))->toBeTrue()
        ->and($user->hasPermission('standard-costs.manage'))->toBeTrue();
});

it('plant_manager has cost-categories and standard-costs manage permission', function (): void {
    $user = new User(['role' => 'plant_manager']);

    expect($user->hasPermission('cost-categories.manage'))->toBeTrue()
        ->and($user->hasPermission('standard-costs.manage'))->toBeTrue()
        ->and($user->hasPermission('units.read'))->toBeTrue();
});

it('plant_supervisor has read-only access to cost-categories and units', function (): void {
    $user = new User(['role' => 'plant_supervisor']);

    expect($user->hasPermission('cost-categories.read'))->toBeTrue()
        ->and($user->hasPermission('cost-categories.manage'))->toBeFalse()
        ->and($user->hasPermission('units.read'))->toBeTrue()
        ->and($user->hasPermission('units.manage'))->toBeFalse();
});

it('plant_operator cannot manage cost-categories or units', function (): void {
    $user = new User(['role' => 'plant_operator']);

    expect($user->hasPermission('cost-categories.manage'))->toBeFalse()
        ->and($user->hasPermission('cost-categories.read'))->toBeFalse()
        ->and($user->hasPermission('units.manage'))->toBeFalse()
        ->and($user->hasPermission('standard-costs.read'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// 3. canViewFinancials — operator vs manager
// ---------------------------------------------------------------------------

it('plant_manager can view financials', function (): void {
    $user = new User(['role' => 'plant_manager']);
    expect($user->canViewFinancials())->toBeTrue();
});

it('plant_operator cannot view financials', function (): void {
    $user = new User(['role' => 'plant_operator']);
    expect($user->canViewFinancials())->toBeFalse();
});

it('plant_supervisor cannot view financials', function (): void {
    $user = new User(['role' => 'plant_supervisor']);
    expect($user->canViewFinancials())->toBeFalse();
});

// ---------------------------------------------------------------------------
// 4. Unit model: convert fast-path (kg <-> lb)
// ---------------------------------------------------------------------------

it('Unit::convert handles kg to lb fast-path without DB', function (): void {
    $result = Unit::convert('100', 'kg', 'lb');
    // 100 / 0.45359237 ≈ 220.462
    expect((float) $result)->toBeGreaterThan(220.0)->toBeLessThan(221.0);
});

it('Unit::convert handles lb to kg fast-path without DB', function (): void {
    $result = Unit::convert('1', 'lb', 'kg');
    // 1 * 0.45359237 = 0.45359237
    expect((float) $result)->toBeBetween(0.4535, 0.4537);
});

it('Unit::convert returns same value for identical units', function (): void {
    $result = Unit::convert('500', 'kg', 'kg');
    expect((float) $result)->toEqual(500.0);
});

// ---------------------------------------------------------------------------
// 5. CostCategory fillable fields
// ---------------------------------------------------------------------------

it('CostCategory has all required fillable fields', function (): void {
    $cat = new CostCategory;
    $fillable = $cat->getFillable();

    expect($fillable)->toContain('tenant_id')
        ->toContain('plant_id')
        ->toContain('name')
        ->toContain('code')
        ->toContain('kind')
        ->toContain('type')
        ->toContain('allocation_basis')
        ->toContain('is_active');
});

// ---------------------------------------------------------------------------
// 6. StandardCost fillable fields
// ---------------------------------------------------------------------------

it('StandardCost has all required fillable fields', function (): void {
    $sc = new StandardCost;
    $fillable = $sc->getFillable();

    expect($fillable)->toContain('plant_id')
        ->toContain('line_id')
        ->toContain('product_grade_id')
        ->toContain('effective_from')
        ->toContain('standard_yield_percent')
        ->toContain('kg_per_hour')
        ->toContain('kwh_per_kg')
        ->toContain('labor_hours_per_ton')
        ->toContain('material_usage_kg_per_kg')
        ->toContain('overhead_rate');
});

// ---------------------------------------------------------------------------
// 7. Material model: prices() HasMany
// ---------------------------------------------------------------------------

it('Material has prices HasMany relationship', function (): void {
    $material = new Material;
    expect($material->prices())->toBeInstanceOf(HasMany::class);
});

// ---------------------------------------------------------------------------
// 8. ProductGrade fillable
// ---------------------------------------------------------------------------

it('ProductGrade has quality spec fillable fields', function (): void {
    $grade = new ProductGrade;
    $fillable = $grade->getFillable();

    // Must carry the grade and spec fields
    expect($fillable)->toContain('name')
        ->toContain('grade_type');
});
