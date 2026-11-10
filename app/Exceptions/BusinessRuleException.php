<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an action is not allowed by a business rule (e.g. deactivating the last Super Administrator).
 *
 * Controllers catch it and show the message to the user; it is safe to display.
 */
class BusinessRuleException extends RuntimeException {}
