<?php

namespace App\Services\Essense\Exceptions;

use RuntimeException;

/**
 * Base class for every failure the Essense client reports, so callers can catch them all at once.
 */
abstract class EssenseException extends RuntimeException {}
