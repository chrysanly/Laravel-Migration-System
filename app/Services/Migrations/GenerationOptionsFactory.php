<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\DTOs\Schema\ForeignKeyDefinition;
use App\DTOs\Schema\GenerationOptions;
use App\DTOs\Schema\InferredKeys;

/**
 * Builds GenerationOptions from user selections + the server-computed inferred
 * keys. Inferred FKs are reconstructed here (by local column name) so the client
 * only ever sends which ones to include, never their definitions.
 */
final class GenerationOptionsFactory
{
    /**
     * @param  array<int, string>  $selectedForeignKeyColumns
     */
    public function build(
        InferredKeys $inferred,
        bool $includeExistingForeignKeys,
        bool $addIdColumn,
        bool $applyInferredPrimaryKey,
        array $selectedForeignKeyColumns,
    ): GenerationOptions {
        $selected = array_flip($selectedForeignKeyColumns);

        $extraForeignKeys = array_values(array_filter(
            $inferred->foreignKeys,
            static fn (ForeignKeyDefinition $fk): bool => isset($selected[$fk->columns[0] ?? '']),
        ));

        return new GenerationOptions(
            includeExistingForeignKeys: $includeExistingForeignKeys,
            extraForeignKeys: $extraForeignKeys,
            overridePrimaryKey: $applyInferredPrimaryKey ? $inferred->primaryKeyColumns : [],
            addIdColumn: $addIdColumn && $inferred->addIdColumn,
            includeIndexes: true,
        );
    }
}
