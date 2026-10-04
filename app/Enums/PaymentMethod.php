<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'nakit';
    case Transfer = 'havale';
    case CreditCard = 'kredi_karti';
    case Other = 'diger';
}
