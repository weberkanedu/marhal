<?php

namespace App\Enums;

enum PaymentType: string
{
    case Collection = 'tahsilat';
    case Refund = 'iade';
}
