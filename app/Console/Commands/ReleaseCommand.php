<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Cuts a new release: bump VERSION, commit, tag, push (and publish via gh if present).
 *
 * Usage: composer release 1.1.0   (or: php artisan app:release 1.1.0)
 */
final class ReleaseCommand extends Command
{
    protected $signature = 'app:release {version : New semver, e.g. 1.1.0} {--no-push : Do not push} {--force : Skip confirmation}';

    protected $description = 'Bump VERSION, commit, tag, and push a release';

    public function handle(): int
    {
        $version = ltrim(trim((string) $this->argument('version')), 'vV');

        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            $this->error("Invalid version \"{$version}\". Use semver, e.g. 1.1.0");

            return self::FAILURE;
        }

        $tag = "v{$version}";

        try {
            if (trim($this->capture('git status --porcelain')) !== '') {
                $this->error('Working tree is not clean — commit or stash your changes first.');

                return self::FAILURE;
            }

            $branch = trim($this->capture('git rev-parse --abbrev-ref HEAD'));

            if (trim($this->capture("git tag --list {$tag}")) !== '') {
                $this->error("Tag {$tag} already exists.");

                return self::FAILURE;
            }

            if (! $this->option('force') && ! $this->confirm("Release {$tag} from branch \"{$branch}\"?", true)) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }

            File::put(base_path('VERSION'), $version."\n");
            $this->updateChangelog($version);
            $this->runProcess('git add VERSION CHANGELOG.md');
            $this->runProcess("git commit -m \"Release {$tag}\"");
            $this->runProcess("git tag -a {$tag} -m \"{$tag}\"");

            if (! $this->option('no-push')) {
                $this->runProcess("git push origin {$branch} --follow-tags");
                $this->publishRelease($tag);
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Released {$tag}.");

        return self::SUCCESS;
    }

    private function publishRelease(string $tag): void
    {
        if (! $this->commandExists('gh --version')) {
            $this->warn("Tag pushed. Publish the GitHub Release from tag {$tag}:");
            $this->line('  repo → Releases → Draft a new release → choose '.$tag.' → Publish');
            $this->line('  (or install GitHub CLI and run: gh release create '.$tag.' --title '.$tag.' --generate-notes)');

            return;
        }

        $this->info('Publishing GitHub Release via gh…');
        $this->runProcess("gh release create {$tag} --title {$tag} --generate-notes");
    }

    /**
     * Roll the CHANGELOG "[Unreleased]" section into a new dated version section,
     * leaving a fresh empty "[Unreleased]" on top.
     */
    private function updateChangelog(string $version): void
    {
        $path = base_path('CHANGELOG.md');
        if (! File::exists($path)) {
            return;
        }

        $date = now()->toDateString();
        $content = File::get($path);
        $marker = '## [Unreleased]';

        if (str_contains($content, $marker)) {
            $content = str_replace($marker, "{$marker}\n\n## [{$version}] - {$date}", $content);
        } else {
            $content .= "\n## [{$version}] - {$date}\n";
        }

        File::put($path, $content);
    }

    private function runProcess(string $command): void
    {
        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout(180);
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            throw new RuntimeException("Command failed: {$command}");
        }
    }

    private function capture(string $command): string
    {
        $process = Process::fromShellCommandline($command, base_path());
        $process->run();

        return $process->getOutput();
    }

    private function commandExists(string $command): bool
    {
        $process = Process::fromShellCommandline($command, base_path());
        $process->run();

        return $process->isSuccessful();
    }
}
