<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base for domain exceptions that carry a user-safe message.
 *
 * These are mapped to friendly flash/422 responses in bootstrap/app.php — they must
 * never surface as a raw 500 or leak internals (ARCHITECTURE §7).
 */
abstract class DomainException extends RuntimeException
{
    /**
     * A message safe to show the end user (no paths, SQL, or credentials).
     */
    public function userMessage(): string
    {
        return $this->getMessage();
    }
}
