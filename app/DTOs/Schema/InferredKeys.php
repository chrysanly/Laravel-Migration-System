<?php

declare(strict_types=1);

namespace App\DTOs\Schema;

/**
 * Keys the tool proposes because the live table lacks them. Everything here is
 * opt-in in the generation preview — nothing is written unless the user selects it.
 */
final readonly class InferredKeys
{
    /**
     * @param  array<int, string>  $primaryKeyColumns  proposed PK column(s); empty if none proposed
     * @param  bool  $addIdColumn  true when we propose introducing a new `id` bigint PK (no usable PK column exists)
     * @param  array<int, ForeignKeyDefinition>  $foreignKeys  proposed FKs (each ->inferred === true)
     */
    public function __construct(
        public array $primaryKeyColumns,
        public bool $addIdColumn,
        public array $foreignKeys,
    ) {}

    public function hasAny(): bool
    {
        return $this->primaryKeyColumns !== [] || $this->addIdColumn || $this->foreignKeys !== [];
    }
}
