<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\Models\OperationLog;
use App\Models\Project;

/**
 * Persists a record of each operation (generate / create / keys / migrate) so
 * there's a history of what ran, whether it succeeded, and the full output on failure.
 */
final class OperationLogger
{
    public function log(
        Project $project,
        string $action,
        ?string $target,
        string $status,
        ?string $output = null,
        ?string $command = null,
        ?string $phpVersion = null,
    ): OperationLog {
        /** @var OperationLog $log */
        $log = $project->logs()->create([
            'action' => $action,
            'target' => $target,
            'status' => $status,
            'output' => $output,
            'command' => $command,
            'php_version' => $phpVersion,
        ]);

        return $log;
    }
}
