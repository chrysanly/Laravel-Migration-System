<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Exceptions\DatabaseConnectionException;
use App\Models\Project;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Introspects a target database by running the standalone introspection script
 * with the *target's* PHP binary (which carries the right PDO driver). The
 * migration-system app never opens the target connection itself, so it never
 * needs the target's driver and its own .env is never involved.
 */
final readonly class TargetIntrospector
{
    public function __construct(
        private TargetDatabaseResolver $resolver,
        private PhpBinaryLocator $locator,
        private Filesystem $files,
    ) {}

    public function ping(Project $project): void
    {
        $this->run($project, 'ping');
    }

    /**
     * @return array<int, string>
     */
    public function tables(Project $project): array
    {
        $payload = $this->run($project, 'tables');

        return array_values(array_map('strval', $payload['tables'] ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    public function table(Project $project, string $table): array
    {
        return $this->run($project, 'table', $table);
    }

    /**
     * Rows from the target's `migrations` table (name + batch), newest first.
     *
     * @return array<int, array{migration: string, batch: int}>
     */
    public function migrated(Project $project): array
    {
        $payload = $this->run($project, 'migrated');

        /** @var array<int, array{migration: string, batch: int}> $rows */
        $rows = $payload['migrated'] ?? [];

        return array_values($rows);
    }

    /**
     * Per-table {pk, fks} plus the migrations recorded as run, in one call.
     *
     * @return array{tables: array<string, array{pk: bool, fks: int}>, ran: array<int, string>}
     */
    public function summary(Project $project): array
    {
        $payload = $this->run($project, 'summary');

        /** @var array<string, array{pk: bool, fks: int}> $tables */
        $tables = $payload['summary'] ?? [];
        /** @var array<int, string> $ran */
        $ran = $payload['ran'] ?? [];

        return ['tables' => $tables, 'ran' => $ran];
    }

    /**
     * @return array{binary: string, version: string, matched: bool, driver: string}
     */
    public function resolvedPhp(Project $project): array
    {
        $config = $this->resolver->resolve($project);
        $php = $this->locator->locate($project, $config->driver);

        return [...$php, 'driver' => $config->driver];
    }

    /**
     * @return array<string, mixed>
     */
    private function run(Project $project, string $mode, ?string $table = null): array
    {
        $config = $this->resolver->resolve($project);
        $php = $this->locator->locate($project, $config->driver);

        if ($php['binary'] === '') {
            throw DatabaseConnectionException::forProject(
                $project->name,
                "no PHP binary with the '{$config->driver}' driver was found on this machine",
            );
        }

        $creds = [
            'mode' => $mode,
            'driver' => $config->driver,
            'host' => $config->host,
            'port' => $config->port,
            'database' => $config->database,
            'username' => $config->username,
            'password' => $config->password,
            'sqlite_path' => $config->sqlitePath,
            'trust_server_certificate' => true,
        ];
        if ($table !== null) {
            $creds['table'] = $table;
        }

        $this->files->ensureDirectoryExists(storage_path('app/introspect'));
        $credsPath = storage_path('app/introspect/'.bin2hex(random_bytes(8)).'.json');
        $this->files->put($credsPath, (string) json_encode($creds));

        try {
            $process = new Process([$php['binary'], base_path('stubs/target-introspect.php'), $credsPath]);
            $process->setTimeout(60);
            $process->run();

            $output = $process->getOutput();
            $payload = $this->decode($output);

            if ($payload === null) {
                $err = trim($process->getErrorOutput()) ?: trim($output) ?: 'no output from introspection process';
                throw DatabaseConnectionException::forProject($project->name, $this->reason($err));
            }

            if (($payload['ok'] ?? false) !== true) {
                throw DatabaseConnectionException::forProject($project->name, $this->reason((string) ($payload['error'] ?? 'unknown error')));
            }

            return $payload;
        } catch (DatabaseConnectionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw DatabaseConnectionException::forProject($project->name, $this->reason($e->getMessage()), $e);
        } finally {
            $this->files->delete($credsPath);
        }
    }

    /**
     * Extract the JSON object from process output (which may be prefixed by a
     * PHP startup warning line from some builds).
     *
     * @return array<string, mixed>|null
     */
    private function decode(string $output): ?array
    {
        if (preg_match('/\{.*\}/s', $output, $m) !== 1) {
            return null;
        }

        $decoded = json_decode($m[0], true);

        return is_array($decoded) ? $decoded : null;
    }

    private function reason(string $message): string
    {
        return match (true) {
            str_contains($message, 'actively refused'),
            str_contains($message, 'Connection refused'),
            str_contains($message, 'could not open a connection'),
            str_contains($message, '08001') => 'the database server refused the connection (is it running?)',
            str_contains($message, 'Unknown database'),
            str_contains($message, 'Cannot open database') => 'the database name does not exist on the server',
            str_contains($message, 'Access denied'),
            str_contains($message, 'Login failed'),
            str_contains($message, '28000') => 'authentication failed (check the user/password or Windows auth)',
            str_contains($message, 'getaddrinfo'),
            str_contains($message, 'No such host'),
            str_contains($message, 'php_network_getaddresses') => 'the database host could not be resolved',
            str_contains($message, 'could not find driver') => 'the selected PHP has no driver for this database',
            str_contains($message, 'sqlite database file was not found') => 'the sqlite database file was not found',
            default => 'could not connect — '.Str::limit($message, 140),
        };
    }
}
