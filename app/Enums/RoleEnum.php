<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SUPERADMIN = 'superadmin';
    case GERENTE = 'gerente';
    case AREA_MANAGER = 'area_manager';
    case DIRECTOR = 'director';
    case LEADER = 'leader';
    case COORDINATOR = 'coordinator';
    case WORKER = 'worker';
    case ANALYST = 'analyst';

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Super Administrador',
            self::GERENTE => 'Gerente',
            self::AREA_MANAGER => 'Encargado de Área',
            self::DIRECTOR => 'Director',
            self::LEADER => 'Líder',
            self::COORDINATOR => 'Coordinador',
            self::WORKER => 'Trabajador',
            self::ANALYST => 'Analista',
        };
    }

    /**
     * Roles with the same scope as superadmin (minus settings & user deletion).
     */
    public static function adminLevel(): array
    {
        return [self::SUPERADMIN, self::GERENTE];
    }

    /**
     * Roles with area-manager scope.
     */
    public static function managerLevel(): array
    {
        return [self::AREA_MANAGER, self::DIRECTOR, self::LEADER, self::COORDINATOR];
    }

    /**
     * Roles with worker scope.
     */
    public static function workerLevel(): array
    {
        return [self::WORKER, self::ANALYST];
    }

    public function isAdminLevel(): bool
    {
        return in_array($this, self::adminLevel());
    }

    public function isManagerLevel(): bool
    {
        return in_array($this, self::managerLevel());
    }

    public function isWorkerLevel(): bool
    {
        return in_array($this, self::workerLevel());
    }

    /**
     * Roles that can be toggled on/off from the admin panel.
     */
    public static function configurable(): array
    {
        return [self::GERENTE, self::DIRECTOR, self::LEADER, self::COORDINATOR, self::WORKER, self::ANALYST];
    }
}
