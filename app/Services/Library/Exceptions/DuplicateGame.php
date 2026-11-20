<?php

namespace App\Services\Library\Exceptions;

final class DuplicateGame extends LibraryRuleViolation
{
    /**
     * @param  string  $existingName  The matching title already in the library, as stored (spaces collapsed).
     */
    public function __construct(public readonly string $existingName)
    {
        parent::__construct("\"{$existingName}\" is already in the library.");
    }
}
