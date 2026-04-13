<?php

namespace App\Enums;

enum ListCategory: string
{
    case Supermercado = 'supermercado';
    case Mercado = 'mercado';
    case Online = 'online';
    case Farmacia = 'farmacia';
    case Otro = 'otro';
}
