<?php

declare(strict_types=1);

namespace App\DTOs\Design;

/**
 * One column defined by the user in the "new table" designer.
 * $type is a Blueprint type key (string, integer, bigInteger, boolean, ...).
 */
final readonly class DesignedColumn
{
    public function __construct(
        public string $name,
        public string $type,
        public ?int $length,
        public ?int $precision,
        public ?int $scale,
        public bool $nullable,
        public ?string $default,
        public bool $unsigned,
        public bool $unique,
    ) {}
}
