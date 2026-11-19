<?php

namespace App\Services\Essense\Exceptions;

/**
 * The API answered {"success": false}: the game doesn't exist (or no longer does).
 * A normal outcome the user can be told about, e.g. someone else just removed it.
 */
final class GameNotFound extends EssenseException
{
    public function __construct(public readonly int $gameId)
    {
        parent::__construct("Game {$gameId} was not found by the Essense API.");
    }
}
