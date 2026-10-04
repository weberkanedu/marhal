<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Operations = 'operasyon';
    case Guide = 'rehber';

    /**
     * Kimlik / pasaport numarasını maskesiz görebilir mi?
     */
    public function canRevealSensitiveData(): bool
    {
        return $this === self::Admin;
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function canManagePayments(): bool
    {
        return in_array($this, [self::Admin, self::Operations], true);
    }
}
