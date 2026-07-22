<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Updates MigrationSystem itself to the latest version: pull, install deps, build,
 * migrate, clear caches. Progress is written to a status file the UI polls, so it
 * can run in the background while the user keeps working.
 */
final class SelfUpdate extends Command
{
    protected $signature = 'app:self-update';

    protected $description = 'Update MigrationSystem to the latest version';

    public function handle(): int
    {
        $php = '"'.PHP_BINARY.'"';

        $steps = [
            ['label' => 'Pulling latest changes', 'cmd' => 'git pull --ff-only'],
            ['label' => 'Installing PHP dependencies', 'cmd' => 'composer install --no-interaction --prefer-dist --no-progress'],
            ['label' => 'Installing JS dependencies', 'cmd' => 'npm install --no-audit --no-fund'],
            ['label' => 'Building assets', 'cmd' => 'npm run build'],
            ['label' => 'Running migrations', 'cmd' => "{$php} artisan migrate --force"],
            ['label' => 'Clearing caches', 'cmd' => "{$php} artisan optimize:clear"],
        ];

        $total = count($steps);
        $this->status(['running' => true, 'step' => 0, 'total' => $total, 'label' => 'Starting…', 'done' => false, 'ok' => false]);

        foreach ($steps as $i => $step) {
            $this->status(['running' => true, 'step' => $i + 1, 'total' => $total, 'label' => $step['label'], 'done' => false, 'ok' => false]);

            $process = Process::fromShellCommandline($step['cmd'], base_path());
            $process->setTimeout(1800);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->status([
                    'running' => false,
                    'step' => $i + 1,
                    'total' => $total,
                    'label' => $step['label'],
                    'done' => true,
                    'ok' => false,
                    'error' => Str::limit(trim($process->getErrorOutput()."\n".$process->getOutput()), 600),
                ]);

                return self::FAILURE;
            }
        }

        $version = trim((string) @file_get_contents(base_path('VERSION')));
        $this->status([
            'running' => false,
            'step' => $total,
            'total' => $total,
            'label' => 'Updated',
            'done' => true,
            'ok' => true,
            'version' => $version,
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function status(array $data): void
    {
        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/update-status.json'), (string) json_encode($data));
    }
}
