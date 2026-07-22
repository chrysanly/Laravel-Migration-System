<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Migrations\CreateDesignedTable;
use App\Exceptions\DatabaseConnectionException;
use App\Http\Requests\CreateTableRequest;
use App\Models\Project;
use App\Services\Migrations\MigrationCodeGenerator;
use App\Services\Migrations\PostGenerate;
use App\Services\Migrations\ProjectInspectionService;
use App\Services\Migrations\TableDesignFactory;
use App\Services\Migrations\TableDesignMapper;
use App\Services\Projects\TargetProjectPaths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class TableDesignController extends Controller
{
    /**
     * The "new table" designer, with a live preview computed from the current form.
     */
    public function create(
        Project $project,
        Request $request,
        TableDesignFactory $factory,
        TableDesignMapper $mapper,
        MigrationCodeGenerator $generator,
        ProjectInspectionService $inspection,
        TargetProjectPaths $paths,
    ): Response {
        // Existing tables (for foreign-key targets); tolerate a down DB.
        $existingTables = [];
        try {
            $existingTables = $inspection->overview($project);
            $existingTables = array_map(static fn (array $t): string => $t['name'], $existingTables);
        } catch (DatabaseConnectionException) {
            // designer still works; FK target list will just be empty
        }

        $preview = '';
        if ($request->has('columns')) {
            $design = $factory->fromArray($request->all());
            if ($design->table !== '' && $design->columns !== []) {
                $mapped = $mapper->map($design);
                $preview = $generator->generate($mapped['schema'], $mapped['options']);
            }
        }

        return Inertia::render('migrations/create-table', [
            'project' => ['id' => $project->public_id, 'name' => $project->name],
            'existingTables' => $existingTables,
            'modules' => $paths->modules($project),
            'columnTypes' => CreateTableRequest::TYPES,
            'preview' => $preview,
        ]);
    }

    public function store(
        Project $project,
        CreateTableRequest $request,
        CreateDesignedTable $action,
        PostGenerate $postGenerate,
    ): RedirectResponse {
        try {
            $result = $action->handle($project, $request);
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', $postGenerate->toast($project, 'create', $result, $request->boolean('migrate')));

        return to_route('projects.show', $project);
    }
}
