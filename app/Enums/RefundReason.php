<?php

namespace App\Enums;

/**
 * Why today's action was given back.
 */
enum RefundReason: string
{
    /** The user undid their own action: removed their vote, or removed the game they added. */
    case Withdrawn = 'withdrawn';

    /** Someone else removed the game the user voted for or added. */
    case GameRemoved = 'game_removed';
}
