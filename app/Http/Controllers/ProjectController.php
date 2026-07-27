<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Projects\RegisterProject;
use App\Exceptions\DatabaseConnectionException;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\Migrations\ProjectInspectionService;
use App\Services\Projects\TargetDatabaseResolver;
use App\Services\Projects\TargetIntrospector;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class ProjectController extends Controller
{
    public function index(): Response
    {
        $projects = Project::query()->latest()->get();

        return Inertia::render('projects/index', [
            'projects' => ProjectResource::collection($projects)->resolve(),
        ]);
    }

    public function store(StoreProjectRequest $request, RegisterProject $action): RedirectResponse
    {
        $project = $action->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Project \"{$project->name}\" registered."]);

        return to_route('projects.show', $project);
    }

    public function show(
        Project $project,
        ProjectInspectionService $inspection,
        TargetIntrospector $introspector,
        TargetDatabaseResolver $resolver,
    ): Response {
        $tables = [];
        $connectionError = null;

        try {
            $tables = $inspection->overview($project);
        } catch (DatabaseConnectionException $e) {
            $connectionError = $e->userMessage();
        }

        // Resolve the connection from the project's own .env (never our config).
        $connection = null;
        try {
            $connection = $resolver->resolve($project)->toSafeArray();
        } catch (Throwable) {
            // .env missing/unreadable — leave null; the UI shows a hint.
        }

        return Inertia::render('projects/show', [
            'project' => (new ProjectResource($project))->resolve(),
            'tables' => $tables,
            'connectionError' => $connectionError,
            'php' => $introspector->resolvedPhp($project),
            'connection' => $connection,
        ]);
    }

    public function pending(Project $project, ProjectInspectionService $inspection): Response|RedirectResponse
    {
        try {
            $groups = $inspection->pendingMigrations($project);
        } catch (DatabaseConnectionException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->userMessage()]);

            return to_route('projects.show', $project);
        }

        return Inertia::render('projects/pending', [
            'project' => ['id' => $project->public_id, 'name' => $project->name],
            'groups' => $groups,
        ]);
    }

    public function rollback(Project $project, ProjectInspectionService $inspection): Response|RedirectResponse
    {
        try {
            $batches = $inspection->migratedBatches($project);
        } catch (DatabaseConnectionException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->userMessage()]);

            return to_route('projects.show', $project);
        }

        return Inertia::render('projects/rollback', [
            'project' => ['id' => $project->public_id, 'name' => $project->name],
            'batches' => $batches,
        ]);
    }

    public function logs(Project $project): Response
    {
        $logs = $project->logs()->limit(200)->get()->map(fn ($log): array => [
            'id' => $log->public_id,
            'action' => $log->action,
            'target' => $log->target,
            'status' => $log->status,
            'php_version' => $log->php_version,
            'command' => $log->command,
            'output' => $log->output,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->all();

        return Inertia::render('projects/logs', [
            'project' => ['id' => $project->public_id, 'name' => $project->name],
            'logs' => $logs,
        ]);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $name = $project->name;
        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Project \"{$name}\" removed."]);

        return to_route('projects.index');
    }
}
