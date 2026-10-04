<?php

namespace App\Enums;

enum Relation: string
{
    case Spouse = 'es';
    case Mother = 'anne';
    case Father = 'baba';
    case Child = 'cocuk';
    case Sibling = 'kardes';
    case Other = 'diger';
}
