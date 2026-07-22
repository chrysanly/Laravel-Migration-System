<?php

declare(strict_types=1);

namespace App\DTOs\Schema;

/**
 * User choices for a single migration generation, captured from the preview.
 */
final readonly class GenerationOptions
{
    /**
     * @param  bool  $includeExistingForeignKeys  emit the FKs that already exist on the table
     * @param  array<int, ForeignKeyDefinition>  $extraForeignKeys  inferred FKs the user opted into
     * @param  array<int, string>  $overridePrimaryKey  inferred PK columns the user opted into (empty = use existing)
     * @param  bool  $addIdColumn  prepend a new `$table->id()` surrogate key
     * @param  bool  $includeIndexes  emit non-primary unique/plain indexes
     */
    public function __construct(
        public bool $includeExistingForeignKeys = true,
        public array $extraForeignKeys = [],
        public array $overridePrimaryKey = [],
        public bool $addIdColumn = false,
        public bool $includeIndexes = true,
    ) {}
}
