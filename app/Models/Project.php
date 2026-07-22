<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered target Laravel project this tool can inspect.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $root_path
 * @property string|null $php_binary
 * @property bool $use_env_credentials
 * @property string|null $db_connection
 * @property string|null $db_host
 * @property string|null $db_port
 * @property string|null $db_database
 * @property string|null $db_username
 * @property string|null $db_password
 */
final class Project extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'root_path',
        'php_binary',
        'use_env_credentials',
        'db_connection',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
    ];

    /**
     * Route model binding uses the non-enumerable ULID, never the auto-increment id.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return HasMany<OperationLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(OperationLog::class)->latest();
    }

    /**
     * Columns HasUlids should populate with a ULID on create.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'use_env_credentials' => 'boolean',
            'db_password' => 'encrypted',
        ];
    }
}
