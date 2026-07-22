<?php

declare(strict_types=1);

namespace App\DTOs\Design;

use App\DTOs\Schema\ForeignKeyDefinition;

/**
 * A brand-new table designed by the user (columns, required primary key, foreign keys).
 */
final readonly class TableDesign
{
    /**
     * @param  array<int, DesignedColumn>  $columns
     * @param  array<int, string>  $primaryKeyColumns  used when $autoIncrementId is false
     * @param  array<int, ForeignKeyDefinition>  $foreignKeys
     */
    public function __construct(
        public string $table,
        public bool $modular,
        public ?string $module,
        public bool $autoIncrementId,
        public array $primaryKeyColumns,
        public array $columns,
        public array $foreignKeys,
    ) {}
}
