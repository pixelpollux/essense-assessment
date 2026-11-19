<?php

namespace App\Services\Library\Exceptions;

use RuntimeException;

/**
 * Base class for "you can't do that" outcomes of the library's rules: expected, user-facing,
 * and never caused by a fault in the API or our config.
 */
abstract class LibraryRuleViolation extends RuntimeException {}
