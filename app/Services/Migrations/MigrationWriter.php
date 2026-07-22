<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\Exceptions\InvalidProjectPathException;
use App\Models\Project;
use App\Services\Projects\TargetProjectPaths;
use DateTimeImmutable;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Writes a migration file into a target project — root migrations or a module's
 * migrations folder — with a timestamped name. Shared by every generation flow.
 */
final readonly class MigrationWriter
{
    public function __construct(
        private TargetProjectPaths $paths,
        private Filesystem $files,
    ) {}

    /**
     * @param  string  $nameBase  filename without the timestamp, e.g. "create_orders_table"
     * @return array{path: string, file: string, code: string}
     */
    public function write(Project $project, string $nameBase, string $code, bool $modular, ?string $module): array
    {
        $directory = $this->resolveDirectory($project, $modular, $module);
        $this->files->ensureDirectoryExists($directory);

        $fileName = (new DateTimeImmutable)->format('Y_m_d_His')."_{$nameBase}.php";
        $fullPath = $directory.DIRECTORY_SEPARATOR.$fileName;

        if ($this->files->exists($fullPath)) {
            throw new RuntimeException("A migration file with this name already exists: {$fileName}");
        }

        $this->files->put($fullPath, $code);

        return ['path' => $fullPath, 'file' => $fileName, 'code' => $code];
    }

    private function resolveDirectory(Project $project, bool $modular, ?string $module): string
    {
        if (! $modular) {
            return $this->paths->rootMigrationsPath($project);
        }

        $module = (string) $module;
        $moduleFolder = $this->paths->modulesBasePath($project).DIRECTORY_SEPARATOR.$module;

        if (! is_dir($moduleFolder)) {
            throw InvalidProjectPathException::notADirectory($moduleFolder);
        }

        return $this->paths->moduleMigrationsPath($project, $module);
    }
}
