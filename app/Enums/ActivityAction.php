<?php

namespace App\Enums;

enum ActivityAction: string
{
    case ItemAdded = 'item_added';
    case ItemChecked = 'item_checked';
    case ItemUnchecked = 'item_unchecked';
    case ItemEdited = 'item_edited';
    case ItemDeleted = 'item_deleted';
    case ListCleared = 'list_cleared';
}
