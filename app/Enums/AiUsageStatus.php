<?php

namespace App\Enums;

enum AiUsageStatus: string
{
    case Success = 'success';
    case Error = 'error';
    case BudgetCapped = 'budget_capped';
    case UserCapped = 'user_capped';
    case CircuitOpen = 'circuit_open';
}
