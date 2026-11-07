<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case DEAN = 'dean';
    case CHAIR = 'chair';
    case FACULTY = 'faculty';
    case STUDENT = 'student';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'System Administrator / Registrar',
            self::DEAN => 'College Dean',
            self::CHAIR => 'Department Chair',
            self::FACULTY => 'Faculty Member',
            self::STUDENT => 'Student',
        };
    }

    public function canManageRosters(): bool
    {
        return $this === self::ADMIN;
    }

    public function canToggleRegistrarLock(): bool
    {
        return $this === self::ADMIN;
    }

    public function canSubmitEvaluation(): bool
    {
        return $this === self::STUDENT;
    }

    public function canViewWatchlist(): bool
    {
        return $this === self::DEAN || $this === self::CHAIR;
    }

    public function canViewDepartmentAnalytics(): bool
    {
        return $this === self::ADMIN || $this === self::DEAN || $this === self::CHAIR;
    }
}
