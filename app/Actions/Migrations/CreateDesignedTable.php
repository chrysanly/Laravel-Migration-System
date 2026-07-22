<?php

declare(strict_types=1);

namespace App\Actions\Migrations;

use App\Http\Requests\CreateTableRequest;
use App\Models\Project;
use App\Services\Migrations\MigrationCodeGenerator;
use App\Services\Migrations\MigrationWriter;
use App\Services\Migrations\TableDesignFactory;
use App\Services\Migrations\TableDesignMapper;

/**
 * Generates and writes a create-migration for a brand-new, user-designed table
 * (columns, required primary key, foreign keys) with a real up()/down().
 */
final readonly class CreateDesignedTable
{
    public function __construct(
        private TableDesignFactory $factory,
        private TableDesignMapper $mapper,
        private MigrationCodeGenerator $generator,
        private MigrationWriter $writer,
    ) {}

    /**
     * @return array{path: string, file: string, code: string}
     */
    public function handle(Project $project, CreateTableRequest $request): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $design = $this->factory->fromArray($validated);
        $mapped = $this->mapper->map($design);
        $code = $this->generator->generate($mapped['schema'], $mapped['options']);

        return $this->writer->write($project, "create_{$design->table}_table", $code, $design->modular, $design->module);
    }
}
