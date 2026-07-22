<?php

declare(strict_types=1);

namespace App\DTOs\Schema;

/**
 * A foreign key constraint that already exists on the table.
 *
 * When $inferred is true this FK does not exist in the DB — it was proposed by
 * the tool from a naming convention and is opt-in in the generation preview.
 */
final readonly class ForeignKeyDefinition
{
    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $foreignColumns
     */
    public function __construct(
        public array $columns,
        public string $foreignTable,
        public array $foreignColumns,
        public ?string $onUpdate = null,
        public ?string $onDelete = null,
        public bool $inferred = false,
    ) {}
}
