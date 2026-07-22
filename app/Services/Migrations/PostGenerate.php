<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Builds the toast for a just-generated migration, optionally running it right
 * after generation (the "generate & migrate" option). Shared by every generate flow.
 */
final readonly class PostGenerate
{
    public function __construct(
        private MigrationRunner $runner,
        private OperationLogger $logger,
    ) {}

    /**
     * Log the generate, optionally run the new migration (logging that too), and
     * return the toast payload.
     *
     * @param  string  $action  generate | create | keys
     * @param  array{path: string, file: string, code: string}  $result
     * @return array{type: string, message: string}
     */
    public function toast(Project $project, string $action, array $result, bool $migrate): array
    {
        $this->logger->log($project, $action, $result['file'], 'success', "Created {$result['file']}");

        if (! $migrate) {
            return ['type' => 'success', 'message' => "Migration created: {$result['file']}"];
        }

        $run = $this->runner->runFile($project, $result['path']);
        $this->logger->log(
            $project,
            'migrate',
            $result['file'],
            $run['ok'] ? 'success' : 'failed',
            $run['output'],
            $run['command'],
            $run['php'],
        );

        if ($run['ok']) {
            return ['type' => 'success', 'message' => "Created & migrated: {$result['file']}"];
        }

        return [
            'type' => 'error',
            'message' => "Created {$result['file']}, but migrate failed: ".Str::limit($run['output'], 180),
        ];
    }
}
