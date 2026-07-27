<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Migrations\AddTableKeys;
use App\Actions\Migrations\GenerateMigration;
use App\DTOs\GenerateMigrationData;
use App\DTOs\Schema\ForeignKeyDefinition;
use App\DTOs\Schema\InferredKeys;
use App\DTOs\Schema\TableSchema;
use App\Exceptions\DatabaseConnectionException;
use App\Http\Presenters\SchemaPresenter;
use App\Http\Requests\AddKeysRequest;
use App\Http\Requests\GenerateMigrationRequest;
use App\Http\Requests\RunMigrationRequest;
use App\Models\Project;
use App\Services\Migrations\GenerationOptionsFactory;
use App\Services\Migrations\MigrationCodeGenerator;
use App\Services\Migrations\MigrationRunner;
use App\Services\Migrations\OperationLogger;
use App\Services\Migrations\PostGenerate;
use App\Services\Migrations\ProjectInspectionService;
use App\Services\Projects\TargetProjectPaths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class MigrationController extends Controller
{
    public function preview(
        Project $project,
        string $table,
        Request $request,
        ProjectInspectionService $inspection,
        GenerationOptionsFactory $optionsFactory,
        MigrationCodeGenerator $generator,
        SchemaPresenter $presenter,
        TargetProjectPaths $paths,
    ): Response|RedirectResponse {
        try {
            $inspected = $inspection->inspectTable($project, $table);
        } catch (DatabaseConnectionException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->userMessage()]);

            return to_route('projects.show', $project);
        }

        /** @var TableSchema $schema */
        $schema = $inspected['schema'];
        /** @var InferredKeys $inferred */
        $inferred = $inspected['inferred'];

        $selectedFkColumns = array_values(array_map('strval', (array) $request->input('inferred_foreign_key_columns', [])));

        $options = $optionsFactory->build(
            inferred: $inferred,
            includeExistingForeignKeys: $request->boolean('include_existing_foreign_keys', true),
            addIdColumn: $request->boolean('add_id_column'),
            applyInferredPrimaryKey: $request->boolean('apply_inferred_primary_key'),
            selectedForeignKeyColumns: $selectedFkColumns,
        );

        return Inertia::render('migrations/preview', [
            'project' => [
                'id' => $project->public_id,
                'name' => $project->name,
            ],
            'schema' => $presenter->table($schema, $inferred),
            'preview' => $generator->generate($schema, $options),
            'modules' => $paths->modules($project),
            'selectedOptions' => [
                'include_existing_foreign_keys' => $request->boolean('include_existing_foreign_keys', true),
                'add_id_column' => $request->boolean('add_id_column'),
                'apply_inferred_primary_key' => $request->boolean('apply_inferred_primary_key'),
                'inferred_foreign_key_columns' => $selectedFkColumns,
            ],
        ]);
    }

    public function store(
        Project $project,
        GenerateMigrationRequest $request,
        GenerateMigration $action,
        PostGenerate $postGenerate,
    ): RedirectResponse {
        // Table name is validated from the {table} route segment in the FormRequest.
        try {
            $result = $action->handle($project, GenerateMigrationData::fromRequest($request));
        } catch (DatabaseConnectionException|RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', $postGenerate->toast($project, 'generate', $result, $request->boolean('migrate')));

        return to_route('projects.show', $project);
    }

    /**
     * Add-primary/foreign-key migration builder for an existing table (with live preview).
     */
    public function keys(
        Project $project,
        string $table,
        Request $request,
        ProjectInspectionService $inspection,
        MigrationCodeGenerator $generator,
        SchemaPresenter $presenter,
        TargetProjectPaths $paths,
    ): Response|RedirectResponse {
        try {
            $inspected = $inspection->inspectTable($project, $table);
        } catch (DatabaseConnectionException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->userMessage()]);

            return to_route('projects.show', $project);
        }

        /** @var TableSchema $schema */
        $schema = $inspected['schema'];
        /** @var InferredKeys $inferred */
        $inferred = $inspected['inferred'];
        /** @var array<int, string> $existingTables */
        $existingTables = $inspected['existing_tables'];

        // First load with no selections → prefill from inference.
        $hasSelections = $request->has('primary_key') || $request->has('foreign_keys');
        $primaryKey = $hasSelections
            ? array_values(array_map('strval', (array) $request->input('primary_key', [])))
            : $inferred->primaryKeyColumns;
        $foreignKeys = $hasSelections
            ? $this->foreignKeysFromInput((array) $request->input('foreign_keys', []))
            : $inferred->foreignKeys;

        return Inertia::render('migrations/keys', [
            'project' => ['id' => $project->public_id, 'name' => $project->name],
            'table' => $table,
            'schema' => $presenter->table($schema, $inferred),
            'existingTables' => $existingTables,
            'modules' => $paths->modules($project),
            'preview' => $generator->generateAddKeys($table, $primaryKey, $foreignKeys),
        ]);
    }

    public function storeKeys(
        Project $project,
        string $table,
        AddKeysRequest $request,
        AddTableKeys $action,
        PostGenerate $postGenerate,
    ): RedirectResponse {
        try {
            $result = $action->handle($project, $table, $request);
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', $postGenerate->toast($project, 'keys', $result, $request->boolean('migrate')));

        return to_route('projects.show', $project);
    }

    /**
     * Run a single migration file on the target (rebuilds the path server-side).
     */
    public function migrate(
        Project $project,
        RunMigrationRequest $request,
        MigrationRunner $runner,
        TargetProjectPaths $paths,
        OperationLogger $logger,
    ): RedirectResponse {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $file = (string) $data['file'];
        $location = (string) $data['location'];
        $module = isset($data['module']) ? (string) $data['module'] : null;

        $directory = $location === 'module' && $module !== null
            ? $paths->moduleMigrationsPath($project, $module)
            : $paths->rootMigrationsPath($project);

        $absolute = $directory.DIRECTORY_SEPARATOR.$file;
        if (! is_file($absolute)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Migration file not found: {$file}"]);

            return back();
        }

        // Path relative to the project root, for `migrate --path=`.
        $rootNormalised = str_replace('\\', '/', $paths->root($project));
        $relative = ltrim(substr(str_replace('\\', '/', $absolute), strlen($rootNormalised)), '/');

        $result = $runner->run($project, $relative);

        $logger->log(
            $project,
            'migrate',
            $file,
            $result['ok'] ? 'success' : 'failed',
            $result['output'],
            $result['command'],
            $result['php'],
        );

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['ok']
                ? "Migrated: {$file}"
                : 'Migration failed: '.Str::limit($result['output'], 200),
        ]);

        return back();
    }

    /**
     * Run ALL pending migrations for the project (Laravel orders them).
     */
    public function migrateAll(
        Project $project,
        MigrationRunner $runner,
        OperationLogger $logger,
    ): RedirectResponse {
        $result = $runner->runAll($project);

        $logger->log(
            $project,
            'migrate',
            'all pending',
            $result['ok'] ? 'success' : 'failed',
            $result['output'],
            $result['command'],
            $result['php'],
        );

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['ok']
                ? 'All pending migrations ran.'
                : 'Migrate failed: '.Str::limit($result['output'], 200),
        ]);

        return to_route('projects.show', $project);
    }

    /**
     * Roll back migrations on the target (last batch, or the last N migrations).
     */
    public function rollback(
        Project $project,
        Request $request,
        MigrationRunner $runner,
        OperationLogger $logger,
    ): RedirectResponse {
        $validated = $request->validate([
            'steps' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $steps = isset($validated['steps']) ? (int) $validated['steps'] : null;
        $result = $runner->rollback($project, $steps);

        $logger->log(
            $project,
            'rollback',
            $steps !== null ? "last {$steps} migration(s)" : 'last batch',
            $result['ok'] ? 'success' : 'failed',
            $result['output'],
            $result['command'],
            $result['php'],
        );

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['ok']
                ? 'Rollback complete.'
                : 'Rollback failed: '.Str::limit($result['output'], 200),
        ]);

        return to_route('projects.show', $project);
    }

    /**
     * @param  array<int, mixed>  $input
     * @return array<int, ForeignKeyDefinition>
     */
    private function foreignKeysFromInput(array $input): array
    {
        $out = [];
        foreach ($input as $fk) {
            if (! is_array($fk) || ($fk['column'] ?? '') === '' || ($fk['foreign_table'] ?? '') === '') {
                continue;
            }
            $out[] = new ForeignKeyDefinition(
                columns: [(string) $fk['column']],
                foreignTable: (string) $fk['foreign_table'],
                foreignColumns: [(string) ($fk['foreign_column'] ?? 'id')],
                onUpdate: isset($fk['on_update']) && $fk['on_update'] !== '' ? (string) $fk['on_update'] : null,
                onDelete: isset($fk['on_delete']) && $fk['on_delete'] !== '' ? (string) $fk['on_delete'] : null,
            );
        }

        return $out;
    }
}
