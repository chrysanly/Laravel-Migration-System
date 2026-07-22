<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\GenerateMigrationRequest;

/**
 * Validated intent to generate one migration file, captured from the preview form.
 */
final readonly class GenerateMigrationData
{
    /**
     * @param  array<int, string>  $inferredForeignKeyColumns  local column names of inferred FKs to include
     */
    public function __construct(
        public string $table,
        public bool $modular,
        public ?string $module,
        public bool $includeExistingForeignKeys,
        public bool $addIdColumn,
        public bool $applyInferredPrimaryKey,
        public array $inferredForeignKeyColumns,
    ) {}

    public static function fromRequest(GenerateMigrationRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            table: (string) $validated['table'],
            modular: (bool) ($validated['modular'] ?? false),
            module: isset($validated['module']) ? (string) $validated['module'] : null,
            includeExistingForeignKeys: (bool) ($validated['include_existing_foreign_keys'] ?? true),
            addIdColumn: (bool) ($validated['add_id_column'] ?? false),
            applyInferredPrimaryKey: (bool) ($validated['apply_inferred_primary_key'] ?? false),
            inferredForeignKeyColumns: array_values(array_map(
                'strval',
                (array) ($validated['inferred_foreign_key_columns'] ?? [])
            )),
        );
    }
}
