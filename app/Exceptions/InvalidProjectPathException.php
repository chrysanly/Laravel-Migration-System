<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a registered path is not a usable Laravel project
 * (missing folder, no artisan / composer.json, or unreadable .env).
 */
final class InvalidProjectPathException extends DomainException
{
    public static function notADirectory(string $path): self
    {
        return new self("The path does not exist or is not a directory: {$path}");
    }

    public static function notALaravelProject(string $path): self
    {
        return new self("This folder does not look like a Laravel project (no artisan / composer.json): {$path}");
    }

    public static function envUnreadable(string $path): self
    {
        return new self("The project's .env file is missing or unreadable, and no manual credentials were provided: {$path}");
    }
}
