<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
final class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'root_path' => $this->root_path,
            'use_env_credentials' => $this->use_env_credentials,
            'db_database' => $this->when(! $this->use_env_credentials, $this->db_database),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
