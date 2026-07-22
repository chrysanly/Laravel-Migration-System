<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\DTOs\Schema\InferredKeys;
use App\DTOs\Schema\TableSchema;
use App\Models\Project;
use App\Services\Schema\KeyInference;
use App\Services\Schema\SchemaInspector;

/**
 * Read-only orchestration over a target project: the table overview (which live
 * tables do/don't have a create-migration) and the per-table inspection used by
 * the generation preview. Shared by the preview controller and the write action.
 */
final readonly class ProjectInspectionService
{
    public function __construct(
        private SchemaInspector $inspector,
        private MigrationScanner $scanner,
        private KeyInference $inference,
    ) {}

    /**
     * Every live table with its migration status, PK/FK presence, and related
     * (modify/drop/rename) migrations. Throws DatabaseConnectionException
     * (requirement #2) if the target DB is unreachable.
     *
     * @return array<int, array{
     *     name: string,
     *     has_migration: bool,
     *     migration_file: string|null,
     *     location: string|null,
     *     module: string|null,
     *     has_primary_key: bool,
     *     foreign_key_count: int,
     *     migrated: bool|null,
     *     related: array<int, array{file: string, kind: string, module: string|null, location: string, migrated: bool}>,
     *     related_count: int
     * }>
     */
    public function overview(Project $project): array
    {
        $summary = $this->inspector->summary($project);
        $scan = $this->scanner->scan($project);
        $ran = array_flip($summary['ran']);

        $rows = [];
        foreach ($summary['tables'] as $table => $keys) {
            $entry = $scan[$table] ?? ['create' => null, 'related' => []];
            $create = $entry['create'];

            $migrated = null;
            if ($create !== null) {
                $name = (string) preg_replace('/\.php$/', '', $create['file']);
                $migrated = isset($ran[$name]);
            }

            $rows[] = [
                'name' => $table,
                'has_migration' => $create !== null,
                'migration_file' => $create['file'] ?? null,
                'location' => $create['location'] ?? null,
                'module' => $create['module'] ?? null,
                'has_primary_key' => $keys['pk'],
                'foreign_key_count' => $keys['fks'],
                'migrated' => $migrated,
                'related' => array_map(
                    static function (array $r) use ($ran): array {
                        $name = (string) preg_replace('/\.php$/', '', $r['file']);

                        return [
                            'file' => $r['file'],
                            'kind' => $r['kind'],
                            'module' => $r['module'],
                            'location' => $r['location'],
                            'migrated' => isset($ran[$name]),
                        ];
                    },
                    $entry['related'],
                ),
                'related_count' => count($entry['related']),
            ];
        }

        return $rows;
    }

    /**
     * Full schema + inferred keys for one table.
     *
     * @return array{schema: TableSchema, inferred: InferredKeys, existing_tables: array<int, string>}
     */
    public function inspectTable(Project $project, string $table): array
    {
        $existingTables = $this->inspector->tables($project);
        $schema = $this->inspector->inspect($project, $table);
        $inferred = $this->inference->infer($schema, $existingTables);

        return [
            'schema' => $schema,
            'inferred' => $inferred,
            'existing_tables' => $existingTables,
        ];
    }
}
