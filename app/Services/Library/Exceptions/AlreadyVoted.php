<?php

namespace App\Services\Library\Exceptions;

final class AlreadyVoted extends LibraryRuleViolation
{
    public function __construct(public readonly int $gameId)
    {
        parent::__construct("You already have a vote on game {$gameId}.");
    }
}
