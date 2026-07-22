<?php

declare(strict_types=1);

namespace App\Actions\Migrations;

use App\DTOs\GenerateMigrationData;
use App\DTOs\Schema\InferredKeys;
use App\DTOs\Schema\TableSchema;
use App\Models\Project;
use App\Services\Migrations\GenerationOptionsFactory;
use App\Services\Migrations\MigrationCodeGenerator;
use App\Services\Migrations\MigrationWriter;
use App\Services\Migrations\ProjectInspectionService;

/**
 * Generates a create-migration for one live table and writes it into the target
 * project (root or a module's migrations folder).
 *
 * Inferred keys are reconstructed server-side from the user's selections (never
 * trusted from the client) before being folded into the generation options.
 */
final readonly class GenerateMigration
{
    public function __construct(
        private ProjectInspectionService $inspection,
        private MigrationCodeGenerator $generator,
        private GenerationOptionsFactory $options,
        private MigrationWriter $writer,
    ) {}

    /**
     * @return array{path: string, file: string, code: string}
     */
    public function handle(Project $project, GenerateMigrationData $data): array
    {
        $inspected = $this->inspection->inspectTable($project, $data->table);
        /** @var TableSchema $schema */
        $schema = $inspected['schema'];
        /** @var InferredKeys $inferred */
        $inferred = $inspected['inferred'];

        $options = $this->options->build(
            inferred: $inferred,
            includeExistingForeignKeys: $data->includeExistingForeignKeys,
            addIdColumn: $data->addIdColumn,
            applyInferredPrimaryKey: $data->applyInferredPrimaryKey,
            selectedForeignKeyColumns: $data->inferredForeignKeyColumns,
        );
        $code = $this->generator->generate($schema, $options);

        return $this->writer->write($project, "create_{$data->table}_table", $code, $data->modular, $data->module);
    }
}
