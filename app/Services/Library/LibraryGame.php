<?php

namespace App\Services\Library;

/**
 * A game as shown to one user: the API's data plus whether this user has an active vote on it.
 */
final readonly class LibraryGame
{
    public function __construct(
        public int $id,
        public string $name,
        public int $votes,
        public bool $votedByMe,
    ) {}
}
