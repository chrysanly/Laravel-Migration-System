<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Project;
use Symfony\Component\Process\Process;

/**
 * Finds a PHP binary to run the introspection script for a target project.
 *
 * Honours (in order): the project's explicit php_binary override, then a binary
 * that both satisfies the target composer.json `require.php` constraint AND has
 * the needed PDO driver, then any binary with the driver. This is why the
 * migration-system's own PHP never needs the target's driver (e.g. sqlsrv).
 */
final class PhpBinaryLocator
{
    /** @var array<string, array{version: string, drivers: array<int, string>}>|null */
    private ?array $cache = null;

    /**
     * @param  bool  $preferLowest  pick the lowest satisfying version instead of the highest.
     *                              Use for booting the target's app (artisan migrate): old
     *                              frameworks often break on the newest PHP, so the lowest
     *                              compatible-with-driver binary is the safe choice.
     * @return array{binary: string, version: string, matched: bool}
     */
    public function locate(Project $project, string $requiredDriver, bool $preferLowest = false): array
    {
        // 1. Explicit per-project override wins.
        if ($project->php_binary !== null && $project->php_binary !== '' && is_file($project->php_binary)) {
            $info = $this->probe($project->php_binary);

            return ['binary' => $project->php_binary, 'version' => $info['version'], 'matched' => true];
        }

        $constraint = $this->composerPhpConstraint($project);
        $candidates = $this->withDriver($requiredDriver);

        // 2. Has the driver AND satisfies the target's php constraint.
        $satisfying = array_filter(
            $candidates,
            fn (array $c): bool => $constraint === null || $this->satisfies($c['version'], $constraint),
        );
        if ($satisfying !== []) {
            $best = $this->pick($satisfying, $preferLowest);

            return ['binary' => $best['binary'], 'version' => $best['version'], 'matched' => true];
        }

        // 3. Any binary with the driver (version mismatch — flagged).
        if ($candidates !== []) {
            $best = $this->pick($candidates, $preferLowest);

            return ['binary' => $best['binary'], 'version' => $best['version'], 'matched' => false];
        }

        // 4. Nothing has the driver — signal it (empty binary).
        return ['binary' => '', 'version' => '', 'matched' => false];
    }

    /**
     * @return array<int, array{binary: string, version: string, drivers: array<int, string>}>
     */
    private function withDriver(string $driver): array
    {
        $out = [];
        foreach ($this->available() as $binary => $info) {
            if (in_array($driver, $info['drivers'], true)) {
                $out[] = ['binary' => $binary, 'version' => $info['version'], 'drivers' => $info['drivers']];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array{binary: string, version: string, drivers?: array<int, string>}>  $items
     * @return array{binary: string, version: string}
     */
    private function pick(array $items, bool $preferLowest = false): array
    {
        usort($items, static fn (array $a, array $b): int => $preferLowest
            ? version_compare($a['version'], $b['version'])
            : version_compare($b['version'], $a['version']));

        return ['binary' => $items[0]['binary'], 'version' => $items[0]['version']];
    }

    /**
     * All discoverable PHP binaries (Herd + Laragon) with version + PDO drivers.
     *
     * @return array<string, array{version: string, drivers: array<int, string>}>
     */
    public function available(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];
        foreach ($this->candidatePaths() as $path) {
            $info = $this->probe($path);
            if ($info['version'] !== '') {
                $this->cache[$path] = $info;
            }
        }

        return $this->cache;
    }

    /**
     * @return array<int, string>
     */
    private function candidatePaths(): array
    {
        $paths = [];

        $home = getenv('USERPROFILE') ?: getenv('HOME') ?: '';
        if ($home !== '') {
            $herd = glob(str_replace('\\', '/', $home).'/.config/herd/bin/php*/php.exe');
            $paths = array_merge($paths, $herd ?: []);
        }

        $laragon = glob('C:/laragon/bin/php/*/php.exe');
        $paths = array_merge($paths, $laragon ?: []);

        return array_values(array_unique($paths));
    }

    /**
     * @return array{version: string, drivers: array<int, string>}
     */
    private function probe(string $binary): array
    {
        try {
            $process = new Process([$binary, '-r', "echo PHP_VERSION.'|'.implode(',', PDO::getAvailableDrivers());"]);
            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful()) {
                return ['version' => '', 'drivers' => []];
            }

            // Output may carry an Imagick startup warning line — take the part with a "|".
            $line = '';
            foreach (preg_split('/\r\n|\r|\n/', trim($process->getOutput())) ?: [] as $candidate) {
                if (str_contains($candidate, '|')) {
                    $line = $candidate;
                }
            }
            if ($line === '') {
                return ['version' => '', 'drivers' => []];
            }

            [$version, $drivers] = explode('|', $line, 2);

            return [
                'version' => trim($version),
                'drivers' => array_values(array_filter(array_map('trim', explode(',', $drivers)))),
            ];
        } catch (\Throwable) {
            return ['version' => '', 'drivers' => []];
        }
    }

    private function composerPhpConstraint(Project $project): ?string
    {
        $path = rtrim($project->root_path, '/\\').'/composer.json';
        if (! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        $constraint = is_array($json) ? ($json['require']['php'] ?? null) : null;

        return is_string($constraint) ? $constraint : null;
    }

    /**
     * Lightweight semver-constraint check covering the forms found in composer `require.php`:
     * alternation (| ||), space-separated AND, ^, ~, >=, >, <=, <, =, and X.* .
     */
    public function satisfies(string $version, string $constraint): bool
    {
        foreach (preg_split('/\s*\|\|?\s*/', trim($constraint)) ?: [] as $orPart) {
            if ($orPart === '') {
                continue;
            }
            $andOk = true;
            foreach (preg_split('/\s+/', trim($orPart)) ?: [] as $atom) {
                if ($atom !== '' && ! $this->atomSatisfies($version, $atom)) {
                    $andOk = false;
                    break;
                }
            }
            if ($andOk) {
                return true;
            }
        }

        return false;
    }

    private function atomSatisfies(string $version, string $atom): bool
    {
        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $atom, $m) === 1) {
            $lower = sprintf('%d.%d.%d', (int) $m[1], (int) ($m[2] ?? 0), (int) ($m[3] ?? 0));
            $upper = sprintf('%d.0.0', (int) $m[1] + 1);

            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        if (preg_match('/^~(\d+)\.(\d+)(?:\.(\d+))?$/', $atom, $m) === 1) {
            $lower = sprintf('%d.%d.%d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
            $upper = sprintf('%d.%d.0', (int) $m[1], (int) $m[2] + 1);

            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        if (preg_match('/^(\d+)\.\*$/', $atom, $m) === 1) {
            $lower = sprintf('%d.0.0', (int) $m[1]);
            $upper = sprintf('%d.0.0', (int) $m[1] + 1);

            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        if (preg_match('/^(>=|<=|>|<|=)?\s*(\d+(?:\.\d+){0,2})$/', $atom, $m) === 1) {
            $op = $m[1] === '' ? '>=' : $m[1];

            return version_compare($version, $m[2], $op);
        }

        return true; // unrecognised → don't over-restrict
    }
}
