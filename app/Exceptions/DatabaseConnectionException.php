<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when the tool cannot reach a target project's database
 * (requirement #2: "should have error if there is no connection").
 *
 * The underlying driver error is kept as the previous exception for logging,
 * never shown to the user.
 */
final class DatabaseConnectionException extends DomainException
{
    public static function forProject(string $projectName, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            "Could not connect to the database for project \"{$projectName}\": {$reason}",
            previous: $previous,
        );
    }
}
