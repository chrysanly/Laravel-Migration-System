<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\Models\Project;
use App\Services\Projects\PhpBinaryLocator;
use App\Services\Projects\TargetDatabaseResolver;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Runs a single migration file on a target project via its own artisan
 * (`php artisan migrate --path=<file> --force`), executed from the target's
 * root with a driver-capable PHP. Booting the target app needs a framework-
 * compatible PHP, so the lowest satisfying binary is preferred.
 */
final readonly class MigrationRunner
{
    public function __construct(
        private TargetDatabaseResolver $resolver,
        private PhpBinaryLocator $locator,
    ) {}

    /**
     * Run a migration by absolute path (derives the project-relative path).
     *
     * @return array{ok: bool, output: string, php: string, command: string}
     */
    public function runFile(Project $project, string $absolutePath): array
    {
        $root = str_replace('\\', '/', rtrim($project->root_path, '/\\'));
        $relative = ltrim(substr(str_replace('\\', '/', $absolutePath), strlen($root)), '/');

        return $this->run($project, $relative);
    }

    /**
     * Run all pending migrations for the project (Laravel orders them).
     *
     * @return array{ok: bool, output: string, php: string, command: string}
     */
    public function runAll(Project $project): array
    {
        return $this->execute($project, ['artisan', 'migrate', '--force'], 'php artisan migrate --force');
    }

    /**
     * @return array{ok: bool, output: string, php: string, command: string}
     */
    public function run(Project $project, string $relativePath): array
    {
        return $this->execute(
            $project,
            ['artisan', 'migrate', '--path='.$relativePath, '--force'],
            "php artisan migrate --path={$relativePath} --force",
        );
    }

    /**
     * @param  array<int, string>  $args  artisan arguments (after the php binary)
     * @return array{ok: bool, output: string, php: string, command: string}
     */
    private function execute(Project $project, array $args, string $command): array
    {
        $config = $this->resolver->resolve($project);
        $php = $this->locator->locate($project, $config->driver, preferLowest: true);

        if ($php['binary'] === '') {
            return [
                'ok' => false,
                'output' => "No PHP binary with the '{$config->driver}' driver was found to run artisan.",
                'php' => '',
                'command' => $command,
            ];
        }

        $process = new Process(
            [$php['binary'], ...$args],
            rtrim($project->root_path, '/\\'),
            $this->cleanChildEnv(),
        );
        $process->setTimeout(600);
        $process->run();

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return [
            'ok' => $process->isSuccessful(),
            'output' => $output === '' ? 'No output.' : Str::limit($output, 8000),
            'php' => $php['version'],
            'command' => $command,
        ];
    }

    /**
     * Remove this app's DB_/APP_/DATABASE_ env vars from the child so the target
     * project loads its OWN .env and behaves exactly as if the user ran artisan
     * themselves. Laravel's Dotenv never overrides an already-set variable, so any
     * inherited value would hijack the target's connection (e.g. forcing sqlite).
     *
     * Symfony Process builds the child env from getenv() AND $_ENV / $_SERVER, so
     * every source must be scrubbed — clearing only getenv() is not enough.
     *
     * @return array<string, string|false>
     */
    private function cleanChildEnv(): array
    {
        $keys = array_merge(
            array_map('strval', array_keys(getenv())),
            array_map('strval', array_keys($_ENV)),
            array_map('strval', array_keys($_SERVER)),
        );

        $env = [];
        foreach (array_unique($keys) as $key) {
            if (preg_match('/^(DB_|APP_|DATABASE_)/', $key) === 1) {
                $env[$key] = false; // false removes the variable from the child environment
            }
        }

        return $env;
    }
}
