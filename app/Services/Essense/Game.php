<?php

namespace App\Services\Essense;

/**
 * A game as stored by the Essense API.
 */
final readonly class Game
{
    public function __construct(
        public int $id,
        public string $name,
        public int $votes,
    ) {}

    /**
     * @param  array<string, mixed>  $data  One game object from the API.
     */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            // The API stores names HTML-escaped, and escaping isn't idempotent, so decode
            // exactly once to get back what the user typed (docs/api-notes.md, finding 12).
            name: html_entity_decode((string) $data['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            votes: (int) $data['votes'],
        );
    }
}
