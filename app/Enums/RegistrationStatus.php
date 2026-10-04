<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Pending = 'on_kayit';
    case Confirmed = 'kesin_kayit';
    case Cancelled = 'iptal';
}
