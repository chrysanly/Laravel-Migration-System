<?php

declare(strict_types=1);

namespace App\DTOs\Schema;

/**
 * An index on the table (primary, unique, or plain).
 */
final readonly class IndexDefinition
{
    /**
     * @param  array<int, string>  $columns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique,
        public bool $primary,
    ) {}
}
