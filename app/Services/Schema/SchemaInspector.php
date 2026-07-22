<?php

declare(strict_types=1);

namespace App\Services\Schema;

use App\DTOs\Schema\ColumnDefinition;
use App\DTOs\Schema\ForeignKeyDefinition;
use App\DTOs\Schema\IndexDefinition;
use App\DTOs\Schema\TableSchema;
use App\Models\Project;
use App\Services\Projects\TargetIntrospector;

/**
 * Reads a target project's schema via the TargetIntrospector (which runs the
 * standalone script with the target's own PHP) and maps the raw arrays into
 * typed value objects for the generator.
 */
final readonly class SchemaInspector
{
    public function __construct(
        private TargetIntrospector $introspector,
    ) {}

    /**
     * @return array<int, string>
     */
    public function tables(Project $project): array
    {
        return $this->introspector->tables($project);
    }

    /**
     * Per-table {pk, fks} plus migrations recorded as run.
     *
     * @return array{tables: array<string, array{pk: bool, fks: int}>, ran: array<int, string>}
     */
    public function summary(Project $project): array
    {
        return $this->introspector->summary($project);
    }

    public function inspect(Project $project, string $table): TableSchema
    {
        $raw = $this->introspector->table($project, $table);

        $columns = array_map(
            static function (array $c): ColumnDefinition {
                $fullType = (string) $c['full_type'];

                return new ColumnDefinition(
                    name: (string) $c['name'],
                    typeName: (string) $c['type_name'],
                    fullType: $fullType,
                    nullable: (bool) $c['nullable'],
                    default: self::normaliseDefault($c['default'] ?? null),
                    autoIncrement: (bool) $c['auto_increment'],
                    unsigned: (bool) $c['unsigned'],
                );
            },
            $raw['columns'] ?? [],
        );

        $foreignKeys = array_map(
            static fn (array $f): ForeignKeyDefinition => new ForeignKeyDefinition(
                columns: array_map('strval', $f['columns'] ?? []),
                foreignTable: (string) $f['foreign_table'],
                foreignColumns: array_map('strval', $f['foreign_columns'] ?? []),
                onUpdate: isset($f['on_update']) ? (string) $f['on_update'] : null,
                onDelete: isset($f['on_delete']) ? (string) $f['on_delete'] : null,
            ),
            $raw['foreign_keys'] ?? [],
        );

        $indexes = array_map(
            static fn (array $i): IndexDefinition => new IndexDefinition(
                name: (string) $i['name'],
                columns: array_map('strval', $i['columns'] ?? []),
                unique: (bool) $i['unique'],
                primary: (bool) $i['primary'],
            ),
            $raw['indexes'] ?? [],
        );

        return new TableSchema(
            table: $table,
            columns: $columns,
            primaryKey: array_map('strval', $raw['primary_key'] ?? []),
            foreignKeys: $foreignKeys,
            indexes: $indexes,
        );
    }

    private static function normaliseDefault(mixed $default): string|int|float|null
    {
        if ($default === null) {
            return null;
        }

        if (is_int($default) || is_float($default)) {
            return $default;
        }

        return (string) $default;
    }
}
