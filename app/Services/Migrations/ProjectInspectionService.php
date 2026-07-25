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
     * Migration files that have NOT been run yet, grouped by table: the create
     * migration (parent) followed by its children (update/drop/rename), in order.
     * File-centric (includes tables that don't exist in the DB yet because their
     * create migration hasn't run). Throws DatabaseConnectionException if the DB
     * is unreachable (needed to read which migrations already ran).
     *
     * @return array<int, array{
     *     table: string,
     *     create: array{file: string, module: string|null, location: string}|null,
     *     create_migrated: bool,
     *     children: array<int, array{file: string, kind: string, module: string|null, location: string}>,
     *     count: int
     * }>
     */
    public function pendingMigrations(Project $project): array
    {
        $ran = array_flip($this->inspector->summary($project)['ran']);
        $scan = $this->scanner->scan($project);

        $isPending = static fn (string $file): bool => ! isset($ran[preg_replace('/\.php$/', '', $file)]);

        $groups = [];
        foreach ($scan as $table => $info) {
            $create = $info['create'];

            $createEntry = null;
            $createMigrated = false;
            if ($create !== null) {
                if ($isPending($create['file'])) {
                    $createEntry = ['file' => $create['file'], 'module' => $create['module'], 'location' => $create['location']];
                } else {
                    $createMigrated = true;
                }
            }

            $children = [];
            foreach ($info['related'] as $r) {
                if ($isPending($r['file'])) {
                    $children[] = [
                        'file' => $r['file'],
                        'kind' => $r['kind'],
                        'module' => $r['module'],
                        'location' => $r['location'],
                    ];
                }
            }
            usort($children, static fn (array $a, array $b): int => strcmp($a['file'], $b['file']));

            if ($createEntry === null && $children === []) {
                continue; // nothing pending for this table
            }

            // Earliest pending file → chronological ordering across the project.
            $earliest = $createEntry['file'] ?? ($children[0]['file'] ?? '');

            $groups[] = [
                'table' => (string) $table,
                'create' => $createEntry,
                'create_migrated' => $createMigrated,
                'children' => $children,
                'count' => ($createEntry !== null ? 1 : 0) + count($children),
                'earliest' => $earliest,
            ];
        }

        usort($groups, static fn (array $a, array $b): int => strcmp($a['earliest'], $b['earliest']));

        return array_map(static function (array $group): array {
            unset($group['earliest']);

            return $group;
        }, $groups);
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
