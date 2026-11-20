<?php

namespace App\Enums;

/**
 * What a user spent their one daily action on.
 */
enum ActionType: string
{
    case Vote = 'vote';
    case Add = 'add';
}
