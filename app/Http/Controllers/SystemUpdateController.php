<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Self-update: check for a newer released version, kick off a background update, and
 * report progress. The update runs against THIS app's own repository.
 */
final class SystemUpdateController extends Controller
{
    private const LATEST_RELEASE_URL = 'https://api.github.com/repos/chrysanly/Laravel-Migration-System/releases/latest';

    public function check(): JsonResponse
    {
        $current = $this->currentVersion();

        // Cache the remote lookup to stay well under GitHub's unauthenticated rate limit.
        $release = Cache::remember('system.update.latest', now()->addMinutes(30), fn (): array => $this->latestRelease());

        $latest = $release['version'] ?? $current;

        return response()->json([
            'current' => $current,
            'latest' => $latest,
            'update_available' => version_compare($latest, $current, '>'),
            'url' => $release['url'] ?? null,
            'name' => $release['name'] ?? null,
        ]);
    }

    /**
     * @return array{version: string|null, url: string|null, name: string|null}
     */
    private function latestRelease(): array
    {
        try {
            $res = Http::withHeaders([
                'User-Agent' => 'MigrationSystem',
                'Accept' => 'application/vnd.github+json',
            ])->timeout(6)->get(self::LATEST_RELEASE_URL);

            if (! $res->ok()) {
                return ['version' => null, 'url' => null, 'name' => null];
            }

            $tag = ltrim(trim((string) $res->json('tag_name')), 'vV');

            return [
                'version' => preg_match('/^\d+\.\d+\.\d+/', $tag) === 1 ? $tag : null,
                'url' => $res->json('html_url'),
                'name' => $res->json('name'),
            ];
        } catch (Throwable) {
            return ['version' => null, 'url' => null, 'name' => null];
        }
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
