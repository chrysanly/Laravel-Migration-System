<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Registers a target project folder so the tool can inspect it.
 */
final class RegisterProject
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Project
    {
        // Normalise the path separators/trailing slash so uniqueness is meaningful.
        $attributes['root_path'] = rtrim(
            str_replace('\\', '/', (string) $attributes['root_path']),
            '/'
        );

        try {
            return Project::create($attributes);
        } catch (UniqueConstraintViolationException) {
            // Path already registered — return the existing record gracefully.
            return Project::query()->where('root_path', $attributes['root_path'])->firstOrFail();
        }
    }
}
