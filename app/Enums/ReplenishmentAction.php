<?php

namespace App\Enums;

enum ReplenishmentAction: string
{
    case Accepted = 'accepted';
    case Ignored = 'ignored';
    case Silenced = 'silenced';
}
