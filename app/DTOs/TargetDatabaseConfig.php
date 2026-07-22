<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resolved database connection parameters for a target project.
 *
 * Built either from the target's own .env (auto) or from manual overrides
 * stored on the Project. Passwords live here only in memory during a request.
 */
final readonly class TargetDatabaseConfig
{
    public function __construct(
        public string $driver,
        public string $host,
        public string $port,
        public string $database,
        public string $username,
        public string $password,
        /** For sqlite: absolute path to the .sqlite file (database holds it too). */
        public ?string $sqlitePath = null,
    ) {}

    /** Human-safe summary (no password) for UI/logging. */
    public function describe(): string
    {
        if ($this->driver === 'sqlite') {
            return "sqlite:{$this->database}";
        }

        return "{$this->driver}://{$this->username}@{$this->host}:{$this->port}/{$this->database}";
    }

    /**
     * Connection details safe to send to the UI (password never included).
     *
     * @return array{driver: string, host: string, port: string, database: string, username: string, has_password: bool, auth: string}
     */
    public function toSafeArray(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->driver === 'sqlite' ? '' : $this->host,
            'port' => $this->driver === 'sqlite' ? '' : $this->port,
            'database' => $this->driver === 'sqlite' ? ($this->sqlitePath ?? $this->database) : $this->database,
            'username' => $this->username,
            'has_password' => $this->password !== '',
            // Empty username on sqlsrv means integrated (Windows) authentication.
            'auth' => $this->driver === 'sqlsrv' && $this->username === '' ? 'windows' : ($this->username !== '' ? 'user' : 'none'),
        ];
    }
}
