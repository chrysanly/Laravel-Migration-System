<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Self-update: check for a newer version, kick off a background update, and report
 * progress. The update runs against THIS app's own repository.
 */
final class SystemUpdateController extends Controller
{
    private const REMOTE_VERSION_URL = 'https://raw.githubusercontent.com/chrysanly/Laravel-Migration-System/main/VERSION';

    public function check(): JsonResponse
    {
        $current = $this->currentVersion();
        $latest = $current;

        try {
            $body = trim(Http::timeout(6)->get(self::REMOTE_VERSION_URL)->body());
            if ($body !== '' && preg_match('/^\d+\.\d+\.\d+/', $body) === 1) {
                $latest = $body;
            }
        } catch (Throwable) {
            // Offline or unreachable — treat as up to date.
        }

        return response()->json([
            'current' => $current,
            'latest' => $latest,
            'update_available' => version_compare($latest, $current, '>'),
        ]);
    }

    public function run(): JsonResponse
    {
        // Reset status, then launch the updater detached so it survives this request.
        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path('app/update-status.json'), (string) json_encode([
            'running' => true, 'step' => 0, 'total' => 6, 'label' => 'Starting…', 'done' => false, 'ok' => false,
        ]));

        $command = 'start "" /B "'.PHP_BINARY.'" artisan app:self-update';
        Process::fromShellCommandline($command, base_path())->setTimeout(15)->run();

        return response()->json(['started' => true]);
    }

    public function status(): JsonResponse
    {
        $path = storage_path('app/update-status.json');
        if (! is_file($path)) {
            return response()->json(['running' => false, 'done' => false]);
        }

        $data = json_decode((string) file_get_contents($path), true);

        return response()->json(is_array($data) ? $data : ['running' => false, 'done' => false]);
    }

    private function currentVersion(): string
    {
        $version = trim((string) @file_get_contents(base_path('VERSION')));

        return $version !== '' ? $version : '1.0.0';
    }
}
