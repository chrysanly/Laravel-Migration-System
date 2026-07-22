<?php

declare(strict_types=1);

namespace App\Services\Schema;

use App\DTOs\Schema\ForeignKeyDefinition;
use App\DTOs\Schema\InferredKeys;
use App\DTOs\Schema\TableSchema;
use Illuminate\Support\Str;

/**
 * Proposes primary/foreign keys the live table is missing, from naming
 * conventions — so a table built without proper constraints can still get a
 * faithful, relational migration. All proposals are opt-in in the preview.
 */
final class KeyInference
{
    /**
     * @param  array<int, string>  $existingTables  every table in the DB (to validate FK targets)
     */
    public function infer(TableSchema $schema, array $existingTables): InferredKeys
    {
        return new InferredKeys(
            primaryKeyColumns: $this->proposePrimaryKey($schema),
            addIdColumn: $this->shouldAddIdColumn($schema),
            foreignKeys: $this->proposeForeignKeys($schema, $existingTables),
        );
    }

    /**
     * If the table has no PK but has an integer column literally named "id",
     * propose promoting it to primary key.
     *
     * @return array<int, string>
     */
    private function proposePrimaryKey(TableSchema $schema): array
    {
        if ($schema->hasPrimaryKey()) {
            return [];
        }

        foreach ($schema->columns as $column) {
            if ($column->name === 'id' && $this->isIntegerType($column->typeName)) {
                return ['id'];
            }
        }

        return [];
    }

    private function shouldAddIdColumn(TableSchema $schema): bool
    {
        return ! $schema->hasPrimaryKey() && ! $schema->hasColumn('id');
    }

    /**
     * Columns named "<singular>_id" with no existing FK, where the pluralised
     * name matches a real table, become proposed foreign keys.
     *
     * @param  array<int, string>  $existingTables
     * @return array<int, ForeignKeyDefinition>
     */
    private function proposeForeignKeys(TableSchema $schema, array $existingTables): array
    {
        $alreadyConstrained = [];
        foreach ($schema->foreignKeys as $fk) {
            foreach ($fk->columns as $col) {
                $alreadyConstrained[$col] = true;
            }
        }

        $tableLookup = array_flip($existingTables);
        $proposals = [];

        foreach ($schema->columns as $column) {
            if (! str_ends_with($column->name, '_id')) {
                continue;
            }

            if (isset($alreadyConstrained[$column->name])) {
                continue;
            }

            $base = substr($column->name, 0, -3); // strip "_id"
            $candidate = Str::plural($base);

            // Don't reference the table itself unless the column clearly self-refs.
            if ($candidate === $schema->table && $column->name !== 'parent_id') {
                continue;
            }

            if (! isset($tableLookup[$candidate])) {
                continue;
            }

            $proposals[] = new ForeignKeyDefinition(
                columns: [$column->name],
                foreignTable: $candidate,
                foreignColumns: ['id'],
                onUpdate: null,
                onDelete: 'cascade',
                inferred: true,
            );
        }

        return $proposals;
    }

    private function isIntegerType(string $typeName): bool
    {
        return in_array(
            strtolower($typeName),
            ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint'],
            true,
        );
    }
}
