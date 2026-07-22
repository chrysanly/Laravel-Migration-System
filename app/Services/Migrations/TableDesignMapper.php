<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\DTOs\Design\DesignedColumn;
use App\DTOs\Design\TableDesign;
use App\DTOs\Schema\ColumnDefinition;
use App\DTOs\Schema\GenerationOptions;
use App\DTOs\Schema\IndexDefinition;
use App\DTOs\Schema\TableSchema;

/**
 * Maps a user-designed table onto the same TableSchema/GenerationOptions the
 * introspection generator uses — so brand-new tables reuse the exact create-code
 * path (types, id(), primary/foreign keys, down()).
 */
final class TableDesignMapper
{
    /** Blueprint type key => [db type_name, full type template]. %d = length/precision. */
    private const TYPE_MAP = [
        'string' => 'varchar',
        'text' => 'text',
        'integer' => 'int',
        'bigInteger' => 'bigint',
        'boolean' => 'boolean',
        'decimal' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'dateTime' => 'datetime',
        'date' => 'date',
        'time' => 'time',
        'timestamp' => 'timestamp',
        'json' => 'json',
        'uuid' => 'uuid',
    ];

    /**
     * @return array{schema: TableSchema, options: GenerationOptions}
     */
    public function map(TableDesign $design): array
    {
        $columns = [];
        $indexes = [];

        foreach ($design->columns as $column) {
            $columns[] = $this->column($column);

            if ($column->unique) {
                $indexes[] = new IndexDefinition(
                    name: "{$design->table}_{$column->name}_unique",
                    columns: [$column->name],
                    unique: true,
                    primary: false,
                );
            }
        }

        $schema = new TableSchema(
            table: $design->table,
            columns: $columns,
            primaryKey: $design->autoIncrementId ? [] : $design->primaryKeyColumns,
            foreignKeys: $design->foreignKeys,
            indexes: $indexes,
        );

        $options = new GenerationOptions(
            includeExistingForeignKeys: true,
            extraForeignKeys: [],
            overridePrimaryKey: [],
            addIdColumn: $design->autoIncrementId,
            includeIndexes: true,
        );

        return ['schema' => $schema, 'options' => $options];
    }

    private function column(DesignedColumn $c): ColumnDefinition
    {
        $typeName = self::TYPE_MAP[$c->type] ?? 'varchar';
        $fullType = $this->fullType($typeName, $c);

        return new ColumnDefinition(
            name: $c->name,
            typeName: $typeName,
            fullType: $fullType,
            nullable: $c->nullable,
            default: $c->default,
            autoIncrement: false,
            unsigned: $c->unsigned,
        );
    }

    private function fullType(string $typeName, DesignedColumn $c): string
    {
        return match ($typeName) {
            'varchar' => 'varchar('.($c->length ?? 255).')',
            'decimal' => 'decimal('.($c->precision ?? 8).','.($c->scale ?? 2).')',
            'boolean' => 'tinyint(1)',
            default => $typeName,
        };
    }
}
