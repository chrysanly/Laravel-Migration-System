<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\DTOs\Schema\ColumnDefinition;
use App\DTOs\Schema\ForeignKeyDefinition;
use App\DTOs\Schema\IndexDefinition;
use App\DTOs\Schema\InferredKeys;
use App\DTOs\Schema\TableSchema;

/**
 * Shapes schema/inference value objects into arrays for the Inertia/React layer.
 * (Value objects, not Eloquent models, so a presenter rather than an API Resource.)
 */
final class SchemaPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function table(TableSchema $schema, InferredKeys $inferred): array
    {
        return [
            'table' => $schema->table,
            'primary_key' => $schema->primaryKey,
            'columns' => array_map(
                static fn (ColumnDefinition $c): array => [
                    'name' => $c->name,
                    'type' => $c->fullType,
                    'type_name' => $c->typeName,
                    'nullable' => $c->nullable,
                    'default' => $c->default,
                    'auto_increment' => $c->autoIncrement,
                    'unsigned' => $c->unsigned,
                ],
                $schema->columns,
            ),
            'foreign_keys' => array_map($this->foreignKey(...), $schema->foreignKeys),
            'indexes' => array_map(
                static fn (IndexDefinition $i): array => [
                    'name' => $i->name,
                    'columns' => $i->columns,
                    'unique' => $i->unique,
                    'primary' => $i->primary,
                ],
                $schema->indexes,
            ),
            'inferred' => [
                'primary_key_columns' => $inferred->primaryKeyColumns,
                'add_id_column' => $inferred->addIdColumn,
                'foreign_keys' => array_map($this->foreignKey(...), $inferred->foreignKeys),
                'has_any' => $inferred->hasAny(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function foreignKey(ForeignKeyDefinition $fk): array
    {
        return [
            'columns' => $fk->columns,
            'foreign_table' => $fk->foreignTable,
            'foreign_columns' => $fk->foreignColumns,
            'on_update' => $fk->onUpdate,
            'on_delete' => $fk->onDelete,
            'inferred' => $fk->inferred,
        ];
    }
}
