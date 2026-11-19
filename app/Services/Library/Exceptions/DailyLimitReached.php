<?php

namespace App\Services\Library\Exceptions;

final class DailyLimitReached extends LibraryRuleViolation
{
    public function __construct()
    {
        parent::__construct("Today's action has already been used.");
    }
}
