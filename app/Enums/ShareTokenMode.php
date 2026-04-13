<?php

namespace App\Enums;

enum ShareTokenMode: string
{
    case Edit = 'edit';
    case ReadOnly = 'read_only';

    public function allowsWrite(): bool
    {
        return $this === self::Edit;
    }
}
