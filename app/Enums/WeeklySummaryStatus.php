<?php

namespace App\Enums;

enum WeeklySummaryStatus: string
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Failed = 'failed';
}
