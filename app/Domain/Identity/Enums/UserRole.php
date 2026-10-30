<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Controller = 'controller';
    case PlantManager = 'plant_manager';
    case Supervisor = 'supervisor';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Group Admin / Executive',
            self::Controller => 'Controller / Accountant',
            self::PlantManager => 'Plant Manager',
            self::Supervisor => 'Shift Supervisor',
            self::Operator => 'Line Operator',
            self::Viewer => 'Read-only Viewer',
        };
    }

    public function canViewFinancials(): bool
    {
        return in_array($this, [self::Admin, self::Controller, self::PlantManager, self::Viewer], true);
    }
}
