<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\Models\Project;
use App\Services\Projects\TargetProjectPaths;

/**
 * Scans a target project's migration files to learn, per table, which file
 * creates it and which other files modify/drop/rename it — across root
 * migrations and every module's migrations directory.
 *
 * @phpstan-type MigrationRef array{file: string, location: string, module: string|null, kind: string}
 */
final readonly class MigrationScanner
{
    public function __construct(
        private TargetProjectPaths $paths,
    ) {}

    /**
     * Full picture per table.
     *
     * @return array<string, array{create: array{file: string, location: string, module: string|null}|null, related: array<int, array{file: string, location: string, module: string|null, kind: string}>}>
     */
    public function scan(Project $project): array
    {
        $map = [];

        $ensure = static function (array &$map, string $table): void {
            $map[$table] ??= ['create' => null, 'related' => []];
        };

        foreach ($this->allMigrationFiles($project) as $entry) {
            $file = $entry['path'];
            $meta = ['file' => basename($file), 'location' => $entry['location'], 'module' => $entry['module']];

            foreach ($this->operationsIn($file) as $op) {
                $table = $op['table'];
                $ensure($map, $table);

                if ($op['kind'] === 'create') {
                    // First create wins as the canonical create file.
                    $map[$table]['create'] ??= $meta;
                } else {
                    $map[$table]['related'][] = $meta + ['kind' => $op['kind']];
                }
            }
        }

        return $map;
    }

    /**
     * Back-compat helper: table => create-migration meta (only tables with a create migration).
     *
     * @return array<string, array{file: string, location: string, module: string|null}>
     */
    public function createdTables(Project $project): array
    {
        $out = [];
        foreach ($this->scan($project) as $table => $info) {
            if ($info['create'] !== null) {
                $out[$table] = $info['create'];
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{path: string, location: string, module: string|null}>
     */
    private function allMigrationFiles(Project $project): array
    {
        $files = [];

        foreach ($this->phpFiles($this->paths->rootMigrationsPath($project)) as $path) {
            $files[] = ['path' => $path, 'location' => 'root', 'module' => null];
        }

        foreach ($this->paths->modules($project) as $module) {
            foreach ($this->phpFiles($this->paths->moduleMigrationsPath($project, $module)) as $path) {
                $files[] = ['path' => $path, 'location' => 'module', 'module' => $module];
            }
        }

        return $files;
    }

    /**
     * @return array<int, string> absolute paths to *.php migration files
     */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'*.php');

        return $files === false ? [] : $files;
    }

    /**
     * All schema operations found in a migration file.
     *
     * @return array<int, array{table: string, kind: string}>
     */
    private function operationsIn(string $file): array
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            return [];
        }

        // Dedupe by table+kind — a file referencing Schema::table('t') in both up()
        // and down() is still a single "update" of that table.
        $seen = [];
        $add = static function (string $table, string $kind) use (&$seen): void {
            $seen[$table.'|'.$kind] = ['table' => $table, 'kind' => $kind];
        };

        foreach ($this->matchTables($contents, '/Schema::create\(\s*[\'"]([^\'"]+)[\'"]/') as $t) {
            $add($t, 'create');
        }
        foreach ($this->matchTables($contents, '/Schema::table\(\s*[\'"]([^\'"]+)[\'"]/') as $t) {
            $add($t, 'update');
        }
        foreach ($this->matchTables($contents, '/Schema::drop(?:IfExists)?\(\s*[\'"]([^\'"]+)[\'"]/') as $t) {
            $add($t, 'drop');
        }
        // Schema::rename('from', 'to') affects both names.
        if (preg_match_all('/Schema::rename\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $contents, $m, PREG_SET_ORDER) !== false) {
            foreach ($m as $pair) {
                $add($pair[1], 'rename');
                $add($pair[2], 'rename');
            }
        }

        return array_values($seen);
    }

    /**
     * @return array<int, string>
     */
    private function matchTables(string $contents, string $pattern): array
    {
        if (preg_match_all($pattern, $contents, $matches) === false) {
            return [];
        }

        return $matches[1];
    }
}
