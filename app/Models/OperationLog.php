<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A record of one operation the tool performed against a target project.
 *
 * @property int $id
 * @property string $public_id
 * @property int $project_id
 * @property string $action
 * @property string|null $target
 * @property string $status
 * @property string|null $php_version
 * @property string|null $command
 * @property string|null $output
 * @property Carbon|null $created_at
 */
final class OperationLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'action',
        'target',
        'status',
        'php_version',
        'command',
        'output',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
