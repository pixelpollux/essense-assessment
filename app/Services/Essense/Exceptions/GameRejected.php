<?php

namespace App\Services\Essense\Exceptions;

/**
 * /games/add answered {"id": -1}. Our own validation is meant to make this unreachable,
 * so if it happens, something got past that validation.
 */
final class GameRejected extends EssenseException
{
    public function __construct()
    {
        parent::__construct('The Essense API rejected the game name.');
    }
}
