<?php

namespace App\Services\Essense\Exceptions;

/**
 * Our side is wrong, not the user's: the API key is missing or rejected, or we called a path
 * or method the API doesn't have. Retrying won't help. Users only see a generic error.
 */
final class EssenseMisconfigured extends EssenseException
{
    /**
     * @param  string  $reason  Machine-readable cause, e.g. MISSING_KEY, INVALID_KEY, HTTP_404.
     */
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function missingKey(): self
    {
        return new self('MISSING_KEY', 'ESSENSE_API_KEY is not set.');
    }

    public static function rejected(string $path, int $status, string $reason): self
    {
        return new self($reason, "The Essense API rejected {$path} with HTTP {$status} ({$reason}).");
    }
}
