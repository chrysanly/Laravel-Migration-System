<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\DTOs\TargetDatabaseConfig;
use App\Exceptions\InvalidProjectPathException;
use App\Models\Project;

/**
 * Resolves the database connection parameters for a target project — either from
 * the project's own .env (auto) or from manual overrides stored on the Project.
 */
final readonly class TargetDatabaseResolver
{
    public function __construct(
        private EnvFileParser $envParser,
    ) {}

    public function resolve(Project $project): TargetDatabaseConfig
    {
        return $project->use_env_credentials
            ? $this->fromEnv($project)
            : $this->fromOverrides($project);
    }

    private function fromEnv(Project $project): TargetDatabaseConfig
    {
        $env = $this->envParser->parse(rtrim($project->root_path, '/\\').DIRECTORY_SEPARATOR.'.env');

        if ($env === []) {
            throw InvalidProjectPathException::envUnreadable($project->root_path);
        }

        $driver = $env['DB_CONNECTION'] ?? 'mysql';

        if ($driver === 'sqlite') {
            return new TargetDatabaseConfig(
                driver: 'sqlite',
                host: '',
                port: '',
                database: $env['DB_DATABASE'] ?? '',
                username: '',
                password: '',
                sqlitePath: $this->resolveSqlitePath($project->root_path, $env['DB_DATABASE'] ?? ''),
            );
        }

        return new TargetDatabaseConfig(
            driver: $driver,
            host: $env['DB_HOST'] ?? '127.0.0.1',
            port: $env['DB_PORT'] ?? '3306',
            database: $env['DB_DATABASE'] ?? '',
            username: $env['DB_USERNAME'] ?? 'root',
            password: $env['DB_PASSWORD'] ?? '',
        );
    }

    private function fromOverrides(Project $project): TargetDatabaseConfig
    {
        $driver = $project->db_connection ?? 'mysql';

        if ($driver === 'sqlite') {
            return new TargetDatabaseConfig(
                driver: 'sqlite',
                host: '',
                port: '',
                database: (string) $project->db_database,
                username: '',
                password: '',
                sqlitePath: $this->resolveSqlitePath($project->root_path, (string) $project->db_database),
            );
        }

        return new TargetDatabaseConfig(
            driver: $driver,
            host: $project->db_host ?? '127.0.0.1',
            port: $project->db_port ?? '3306',
            database: (string) $project->db_database,
            username: $project->db_username ?? 'root',
            password: (string) $project->db_password,
        );
    }

    /**
     * A sqlite DB_DATABASE may be an absolute path or relative to the project root.
     */
    private function resolveSqlitePath(string $rootPath, string $dbDatabase): string
    {
        if ($dbDatabase === '') {
            return rtrim($rootPath, '/\\').DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';
        }

        $isAbsolute = preg_match('/^([A-Za-z]:[\/\\\\]|\/)/', $dbDatabase) === 1;

        return $isAbsolute
            ? $dbDatabase
            : rtrim($rootPath, '/\\').DIRECTORY_SEPARATOR.ltrim($dbDatabase, '/\\');
    }
}
