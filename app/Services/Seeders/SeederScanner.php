<?php

declare(strict_types=1);

namespace App\Services\Seeders;

use App\Models\Project;
use App\Services\Projects\TargetProjectPaths;

/**
 * Lists a target project's seeder classes — the root database/seeders folder and
 * every module's Database/Seeders folder — with their fully-qualified class name
 * (for `db:seed --class`) and source code (for the preview).
 */
final readonly class SeederScanner
{
    public function __construct(
        private TargetProjectPaths $paths,
    ) {}

    /**
     * @return array<int, array{name: string, fqcn: string, file: string, module: string|null, location: string, code: string}>
     */
    public function scan(Project $project): array
    {
        $seeders = [];

        // Root seeders.
        $rootDir = $this->paths->root($project).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders';
        foreach ($this->phpFiles($rootDir) as $file) {
            $seeder = $this->parse($file, 'root', null);
            if ($seeder !== null) {
                $seeders[] = $seeder;
            }
        }

        // Module seeders.
        foreach ($this->paths->modules($project) as $module) {
            $dir = $this->paths->modulesBasePath($project).DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR.'Database'.DIRECTORY_SEPARATOR.'Seeders';
            foreach ($this->phpFiles($dir) as $file) {
                $seeder = $this->parse($file, 'module', $module);
                if ($seeder !== null) {
                    $seeders[] = $seeder;
                }
            }
        }

        usort($seeders, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $seeders;
    }

    /**
     * All valid fully-qualified seeder class names (for validating a seed request).
     *
     * @return array<int, string>
     */
    public function classNames(Project $project): array
    {
        return array_map(static fn (array $s): string => $s['fqcn'], $this->scan($project));
    }

    /**
     * @return array{name: string, fqcn: string, file: string, module: string|null, location: string, code: string}|null
     */
    private function parse(string $file, string $location, ?string $module): ?array
    {
        $code = file_get_contents($file);
        if ($code === false) {
            return null;
        }

        if (preg_match('/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)/', $code, $m) !== 1) {
            return null;
        }
        $class = $m[1];

        $namespace = preg_match('/^\s*namespace\s+([^;]+);/m', $code, $n) === 1 ? trim($n[1]) : null;
        $fqcn = $namespace !== null ? $namespace.'\\'.$class : $class;

        return [
            'name' => $class,
            'fqcn' => $fqcn,
            'file' => basename($file),
            'module' => $module,
            'location' => $location,
            'code' => $code,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'*.php');

        return $files === false ? [] : $files;
    }
}
