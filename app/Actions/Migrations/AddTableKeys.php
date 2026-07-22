<?php

declare(strict_types=1);

namespace App\Actions\Migrations;

use App\DTOs\Schema\ForeignKeyDefinition;
use App\Http\Requests\AddKeysRequest;
use App\Models\Project;
use App\Services\Migrations\MigrationCodeGenerator;
use App\Services\Migrations\MigrationWriter;

/**
 * Generates and writes an "add keys" migration (Schema::table) that adds a
 * primary key and/or foreign keys to an existing table, with a real down().
 */
final readonly class AddTableKeys
{
    public function __construct(
        private MigrationCodeGenerator $generator,
        private MigrationWriter $writer,
    ) {}

    /**
     * @return array{path: string, file: string, code: string}
     */
    public function handle(Project $project, string $table, AddKeysRequest $request): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array<int, string> $primaryKey */
        $primaryKey = array_values(array_map('strval', (array) ($validated['primary_key'] ?? [])));

        $foreignKeys = array_map(
            static fn (array $fk): ForeignKeyDefinition => new ForeignKeyDefinition(
                columns: [(string) $fk['column']],
                foreignTable: (string) $fk['foreign_table'],
                foreignColumns: [(string) $fk['foreign_column']],
                onUpdate: isset($fk['on_update']) ? (string) $fk['on_update'] : null,
                onDelete: isset($fk['on_delete']) ? (string) $fk['on_delete'] : null,
            ),
            (array) ($validated['foreign_keys'] ?? []),
        );

        $code = $this->generator->generateAddKeys($table, $primaryKey, $foreignKeys);

        return $this->writer->write(
            $project,
            "add_keys_to_{$table}_table",
            $code,
            (bool) ($validated['modular'] ?? false),
            isset($validated['module']) ? (string) $validated['module'] : null,
        );
    }
}
