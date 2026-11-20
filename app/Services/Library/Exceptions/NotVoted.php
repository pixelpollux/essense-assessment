<?php

namespace App\Services\Library\Exceptions;

final class NotVoted extends LibraryRuleViolation
{
    public function __construct(public readonly int $gameId)
    {
        parent::__construct("You don't have a vote on game {$gameId}.");
    }
}
