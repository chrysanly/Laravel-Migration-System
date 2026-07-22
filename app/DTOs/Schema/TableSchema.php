<?php

declare(strict_types=1);

namespace App\DTOs\Schema;

/**
 * The full introspected schema of a single database table.
 */
final readonly class TableSchema
{
    /**
     * @param  array<int, ColumnDefinition>  $columns
     * @param  array<int, string>  $primaryKey  column names forming the PK (may be empty)
     * @param  array<int, ForeignKeyDefinition>  $foreignKeys
     * @param  array<int, IndexDefinition>  $indexes
     */
    public function __construct(
        public string $table,
        public array $columns,
        public array $primaryKey,
        public array $foreignKeys,
        public array $indexes,
    ) {}

    public function hasPrimaryKey(): bool
    {
        return $this->primaryKey !== [];
    }

    public function hasColumn(string $name): bool
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return true;
            }
        }

        return false;
    }
}
