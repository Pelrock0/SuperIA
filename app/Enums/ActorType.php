<?php

namespace App\Enums;

enum ActorType: string
{
    case Owner = 'owner';
    case Anonymous = 'anonymous';
}
