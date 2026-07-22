<?php

declare(strict_types=1);

namespace App\DTOs\Schema;

/**
 * One column as reported by the database's own schema (authoritative types).
 */
final readonly class ColumnDefinition
{
    public function __construct(
        public string $name,
        /** Normalised type name, e.g. "bigint", "varchar", "datetime". */
        public string $typeName,
        /** Full DB type, e.g. "varchar(255)", "decimal(12,2)", "int unsigned". */
        public string $fullType,
        public bool $nullable,
        public string|int|float|null $default,
        public bool $autoIncrement,
        public bool $unsigned,
        public ?string $comment = null,
    ) {}
}
