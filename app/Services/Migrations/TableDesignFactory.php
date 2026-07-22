<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\DTOs\Design\DesignedColumn;
use App\DTOs\Design\TableDesign;
use App\DTOs\Schema\ForeignKeyDefinition;

/**
 * Builds a TableDesign from a raw input array — shared by the live preview
 * (unvalidated query) and the write action (validated request data).
 */
final class TableDesignFactory
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function fromArray(array $input): TableDesign
    {
        $columns = array_map(
            static fn (array $c): DesignedColumn => new DesignedColumn(
                name: (string) ($c['name'] ?? ''),
                type: (string) ($c['type'] ?? 'string'),
                length: isset($c['length']) && $c['length'] !== '' ? (int) $c['length'] : null,
                precision: isset($c['precision']) && $c['precision'] !== '' ? (int) $c['precision'] : null,
                scale: isset($c['scale']) && $c['scale'] !== '' ? (int) $c['scale'] : null,
                nullable: filter_var($c['nullable'] ?? false, FILTER_VALIDATE_BOOL),
                default: isset($c['default']) && $c['default'] !== '' ? (string) $c['default'] : null,
                unsigned: filter_var($c['unsigned'] ?? false, FILTER_VALIDATE_BOOL),
                unique: filter_var($c['unique'] ?? false, FILTER_VALIDATE_BOOL),
            ),
            array_values(array_filter(
                (array) ($input['columns'] ?? []),
                static fn (mixed $c): bool => is_array($c) && ($c['name'] ?? '') !== '',
            )),
        );

        $foreignKeys = array_map(
            static fn (array $fk): ForeignKeyDefinition => new ForeignKeyDefinition(
                columns: [(string) ($fk['column'] ?? '')],
                foreignTable: (string) ($fk['foreign_table'] ?? ''),
                foreignColumns: [(string) ($fk['foreign_column'] ?? 'id')],
                onUpdate: isset($fk['on_update']) && $fk['on_update'] !== '' ? (string) $fk['on_update'] : null,
                onDelete: isset($fk['on_delete']) && $fk['on_delete'] !== '' ? (string) $fk['on_delete'] : null,
            ),
            array_values(array_filter(
                (array) ($input['foreign_keys'] ?? []),
                static fn (mixed $fk): bool => is_array($fk) && ($fk['column'] ?? '') !== '' && ($fk['foreign_table'] ?? '') !== '',
            )),
        );

        return new TableDesign(
            table: (string) ($input['table'] ?? ''),
            modular: filter_var($input['modular'] ?? false, FILTER_VALIDATE_BOOL),
            module: isset($input['module']) && $input['module'] !== '' ? (string) $input['module'] : null,
            autoIncrementId: filter_var($input['auto_increment_id'] ?? false, FILTER_VALIDATE_BOOL),
            primaryKeyColumns: array_values(array_map('strval', (array) ($input['primary_key_columns'] ?? []))),
            columns: $columns,
            foreignKeys: $foreignKeys,
        );
    }
}
