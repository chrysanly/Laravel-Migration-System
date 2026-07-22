<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Project;

/**
 * Resolves filesystem locations inside a target project — root migrations, the
 * modules base directory, and the per-module migration sub-path — honouring the
 * project's own nwidart/laravel-modules config where present.
 *
 * config/modules.php is not include()d (it references base_path() and the target's
 * container); the two values we need are read with narrow regexes + safe defaults.
 */
final class TargetProjectPaths
{
    private const DEFAULT_MODULES_DIR = 'Modules';

    private const DEFAULT_MODULE_MIGRATION_SUBPATH = 'Database/Migrations';

    public function root(Project $project): string
    {
        return rtrim($project->root_path, '/\\');
    }

    public function rootMigrationsPath(Project $project): string
    {
        return $this->root($project).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
    }

    public function modulesBasePath(Project $project): string
    {
        return $this->root($project).DIRECTORY_SEPARATOR.$this->modulesDir($project);
    }

    /**
     * @return array<int, string> module folder names, sorted
     */
    public function modules(Project $project): array
    {
        $base = $this->modulesBasePath($project);

        if (! is_dir($base)) {
            return [];
        }

        $names = array_values(array_filter(
            scandir($base) ?: [],
            static fn (string $entry): bool => $entry !== '.'
                && $entry !== '..'
                && is_dir($base.DIRECTORY_SEPARATOR.$entry)
        ));

        sort($names);

        return $names;
    }

    public function moduleMigrationsPath(Project $project, string $module): string
    {
        $subPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->moduleMigrationSubPath($project));

        return $this->modulesBasePath($project).DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR.$subPath;
    }

    public function moduleMigrationSubPath(Project $project): string
    {
        $config = $this->readModulesConfig($project);

        if ($config !== null
            && preg_match("/'migration'\s*=>\s*\[\s*'path'\s*=>\s*'([^']+)'/", $config, $m) === 1) {
            return $m[1];
        }

        return self::DEFAULT_MODULE_MIGRATION_SUBPATH;
    }

    private function modulesDir(Project $project): string
    {
        $config = $this->readModulesConfig($project);

        // e.g. 'modules' => base_path('Modules'),
        if ($config !== null
            && preg_match("/'modules'\s*=>\s*base_path\(\s*'([^']+)'\s*\)/", $config, $m) === 1) {
            return $m[1];
        }

        return self::DEFAULT_MODULES_DIR;
    }

    private function readModulesConfig(Project $project): ?string
    {
        $path = $this->root($project).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'modules.php';

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}
