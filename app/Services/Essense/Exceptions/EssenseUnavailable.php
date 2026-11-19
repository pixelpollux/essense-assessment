<?php

namespace App\Services\Essense\Exceptions;

use Throwable;

/**
 * The API couldn't be reached or answered with something unusable (timeout, 5xx, non-JSON).
 * Usually temporary: the user should be told to try again.
 */
final class EssenseUnavailable extends EssenseException
{
    public static function connectionFailed(string $path, Throwable $previous): self
    {
        return new self("Could not reach the Essense API ({$path}).", previous: $previous);
    }

    public static function serverError(string $path, int $status): self
    {
        return new self("The Essense API returned HTTP {$status} for {$path}.");
    }

    public static function unexpectedResponse(string $path): self
    {
        return new self("The Essense API returned an unexpected response for {$path}.");
    }
}
