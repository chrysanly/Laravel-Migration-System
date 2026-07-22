<?php

declare(strict_types=1);

namespace App\Services\Migrations;

use App\DTOs\Schema\ColumnDefinition;
use App\DTOs\Schema\ForeignKeyDefinition;
use App\DTOs\Schema\GenerationOptions;
use App\DTOs\Schema\TableSchema;

/**
 * Turns an introspected TableSchema into the source of a Laravel migration
 * (anonymous-class style), faithfully reproducing columns, keys and indexes and
 * folding in any opt-in inferred keys.
 */
final class MigrationCodeGenerator
{
    private const TIMESTAMP_COLUMNS = ['created_at', 'updated_at'];

    public function generate(TableSchema $schema, GenerationOptions $options): string
    {
        $lines = $this->buildBlueprintLines($schema, $options);
        $body = implode("\n", array_map(static fn (string $l): string => '            '.$l, $lines));

        $table = $schema->table;

        return <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table): void {
        {$body}
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };

        PHP;
    }

    /**
     * A migration that adds a primary key and/or foreign keys to an EXISTING table
     * (Schema::table), with a real down() that drops them again.
     *
     * @param  array<int, string>  $primaryKey
     * @param  array<int, ForeignKeyDefinition>  $foreignKeys
     */
    public function generateAddKeys(string $table, array $primaryKey, array $foreignKeys): string
    {
        $up = [];
        $down = [];

        if ($primaryKey !== []) {
            $up[] = $this->primaryKeyLine($primaryKey);
            $down[] = '$table->dropPrimary();';
        }

        foreach ($foreignKeys as $fk) {
            $up[] = $this->foreignKeyLine($fk);
            $local = count($fk->columns) === 1 ? "'{$fk->columns[0]}'" : $this->quotedList($fk->columns);
            $down[] = "\$table->dropForeign([{$local}]);";
        }

        $upBody = implode("\n", array_map(static fn (string $l): string => '            '.$l, $up));
        $downBody = implode("\n", array_map(static fn (string $l): string => '            '.$l, $down));

        return <<<PHP
        <?php

        declare(strict_types=1);

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::table('{$table}', function (Blueprint \$table): void {
        {$upBody}
                });
            }

            public function down(): void
            {
                Schema::table('{$table}', function (Blueprint \$table): void {
        {$downBody}
                });
            }
        };

        PHP;
    }

    /**
     * @return array<int, string>
     */
    private function buildBlueprintLines(TableSchema $schema, GenerationOptions $options): array
    {
        $lines = [];

        $primaryKey = $options->overridePrimaryKey !== [] ? $options->overridePrimaryKey : $schema->primaryKey;
        $autoIncrementColumn = $this->autoIncrementColumn($schema);
        $hasTimestamps = $schema->hasColumn('created_at') && $schema->hasColumn('updated_at');

        if ($options->addIdColumn) {
            $lines[] = '$table->id();';
        }

        foreach ($schema->columns as $column) {
            if ($options->addIdColumn && $column->name === 'id') {
                continue; // surrogate id() already emitted
            }

            if ($hasTimestamps && in_array($column->name, self::TIMESTAMP_COLUMNS, true)) {
                continue; // handled by timestamps()
            }

            if ($column->name === 'deleted_at') {
                continue; // handled by softDeletes()
            }

            if ($autoIncrementColumn === $column->name) {
                $lines[] = $this->autoIncrementLine($column);

                continue;
            }

            $lines[] = $this->columnLine($column);
        }

        if ($hasTimestamps) {
            $lines[] = '$table->timestamps();';
        }

        if ($schema->hasColumn('deleted_at')) {
            $lines[] = '$table->softDeletes();';
        }

        // Primary key that isn't already expressed by an auto-increment column.
        if ($primaryKey !== [] && $autoIncrementColumn === null) {
            $lines[] = $this->primaryKeyLine($primaryKey);
        }

        if ($options->includeIndexes) {
            foreach ($this->indexLines($schema) as $line) {
                $lines[] = $line;
            }
        }

        $foreignKeys = [];
        if ($options->includeExistingForeignKeys) {
            $foreignKeys = $schema->foreignKeys;
        }
        $foreignKeys = array_merge($foreignKeys, $options->extraForeignKeys);

        foreach ($foreignKeys as $fk) {
            $lines[] = $this->foreignKeyLine($fk);
        }

        return $lines;
    }

    private function autoIncrementColumn(TableSchema $schema): ?string
    {
        foreach ($schema->columns as $column) {
            if ($column->autoIncrement) {
                return $column->name;
            }
        }

        return null;
    }

    private function autoIncrementLine(ColumnDefinition $column): string
    {
        $type = strtolower($column->typeName);

        $method = match ($type) {
            'bigint' => 'id',
            'int', 'integer' => 'increments',
            'smallint' => 'smallIncrements',
            'mediumint' => 'mediumIncrements',
            'tinyint' => 'tinyIncrements',
            default => 'id',
        };

        // id() defaults the column name to "id"; for any other name pass it explicitly.
        if ($method === 'id' && $column->name === 'id') {
            return '$table->id();';
        }

        if ($method === 'id') {
            return "\$table->id('{$column->name}');";
        }

        return "\$table->{$method}('{$column->name}');";
    }

    private function columnLine(ColumnDefinition $column): string
    {
        $call = $this->typeCall($column);
        $line = "\$table->{$call}";

        if ($column->nullable) {
            $line .= '->nullable()';
        }

        $default = $this->defaultCall($column);
        if ($default !== null) {
            $line .= $default;
        }

        if ($column->comment !== null && $column->comment !== '') {
            $line .= "->comment('".$this->escape($column->comment)."')";
        }

        return $line.';';
    }

    private function typeCall(ColumnDefinition $column): string
    {
        $name = "'{$column->name}'";
        $type = strtolower($column->typeName);
        $full = strtolower($column->fullType);
        $unsigned = $column->unsigned;

        // tinyint(1) is Laravel's boolean.
        if ($type === 'tinyint' && str_contains($full, 'tinyint(1)')) {
            return "boolean({$name})";
        }

        // SQL Server "bit" is a boolean.
        if ($type === 'bit') {
            return "boolean({$name})";
        }

        return match ($type) {
            'bigint' => $unsigned ? "unsignedBigInteger({$name})" : "bigInteger({$name})",
            'int', 'integer' => $unsigned ? "unsignedInteger({$name})" : "integer({$name})",
            'mediumint' => $unsigned ? "unsignedMediumInteger({$name})" : "mediumInteger({$name})",
            'smallint' => $unsigned ? "unsignedSmallInteger({$name})" : "smallInteger({$name})",
            'tinyint' => $unsigned ? "unsignedTinyInteger({$name})" : "tinyInteger({$name})",
            'char', 'nchar', 'bpchar' => "char({$name}, ".$this->length($full, 255).')',
            'varchar', 'nvarchar', 'string', 'character varying' => $this->stringOrText($name, $full),
            'text', 'tinytext', 'ntext' => "text({$name})",
            'mediumtext' => "mediumText({$name})",
            'longtext' => "longText({$name})",
            'decimal', 'numeric', 'money', 'smallmoney' => "decimal({$name}, ".$this->precision($full).')',
            'float', 'real' => "float({$name})",
            'double', 'double precision' => "double({$name})",
            'boolean', 'bool' => "boolean({$name})",
            'date' => "date({$name})",
            'datetime', 'datetime2', 'smalldatetime' => "dateTime({$name})",
            'datetimeoffset' => "dateTimeTz({$name})",
            'timestamp' => "timestamp({$name})",
            'time' => "time({$name})",
            'year' => "year({$name})",
            'json', 'jsonb' => "json({$name})",
            'uuid', 'uniqueidentifier' => "uuid({$name})",
            'binary', 'blob', 'varbinary', 'longblob', 'mediumblob', 'tinyblob', 'image', 'bytea' => "binary({$name})",
            'xml' => "text({$name})",
            'enum' => "enum({$name}, ".$this->enumValues($column->fullType).')',
            default => "string({$name})", // safe fallback for unknown types
        };
    }

    /**
     * nvarchar(max)/varchar(max) have no numeric length — emit text() instead of string().
     */
    private function stringOrText(string $name, string $fullType): string
    {
        if (str_contains($fullType, 'max') || str_contains($fullType, '-1')) {
            return "text({$name})";
        }

        return "string({$name}, ".$this->length($fullType, 255).')';
    }

    private function defaultCall(ColumnDefinition $column): ?string
    {
        if ($column->default === null) {
            return null;
        }

        $default = $column->default;

        if (is_string($default)) {
            $upper = strtoupper($default);

            if (str_contains($upper, 'CURRENT_TIMESTAMP') || str_contains($upper, 'GETDATE') || str_contains($upper, 'GETUTCDATE')) {
                return '->useCurrent()';
            }

            // Non-literal SQL expressions (e.g. newid(), newsequentialid()) can't be a Blueprint default.
            if (str_contains($upper, 'NEWID') || str_contains($upper, 'NEWSEQUENTIALID')) {
                return null;
            }

            if ($upper === 'NULL') {
                return null;
            }

            // Numeric-looking string default.
            if (is_numeric($default)) {
                return "->default({$default})";
            }

            return "->default('".$this->escape($default)."')";
        }

        return "->default({$default})";
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function primaryKeyLine(array $columns): string
    {
        if (count($columns) === 1) {
            return "\$table->primary('{$columns[0]}');";
        }

        return '$table->primary(['.$this->quotedList($columns).']);';
    }

    /**
     * @return array<int, string>
     */
    private function indexLines(TableSchema $schema): array
    {
        $fkColumnSets = [];
        foreach ($schema->foreignKeys as $fk) {
            $fkColumnSets[implode(',', $fk->columns)] = true;
        }

        $lines = [];

        foreach ($schema->indexes as $index) {
            if ($index->primary) {
                continue;
            }

            // Skip indexes that merely back a foreign key (auto-created by MySQL).
            if (! $index->unique && isset($fkColumnSets[implode(',', $index->columns)])) {
                continue;
            }

            $cols = count($index->columns) === 1
                ? "'{$index->columns[0]}'"
                : '['.$this->quotedList($index->columns).']';

            $method = $index->unique ? 'unique' : 'index';
            $lines[] = "\$table->{$method}({$cols});";
        }

        return $lines;
    }

    private function foreignKeyLine(ForeignKeyDefinition $fk): string
    {
        $local = count($fk->columns) === 1 ? "'{$fk->columns[0]}'" : '['.$this->quotedList($fk->columns).']';
        $foreign = count($fk->foreignColumns) === 1
            ? "'{$fk->foreignColumns[0]}'"
            : '['.$this->quotedList($fk->foreignColumns).']';

        $line = "\$table->foreign({$local})->references({$foreign})->on('{$fk->foreignTable}')";

        if ($fk->onDelete !== null && $fk->onDelete !== '' && strtoupper($fk->onDelete) !== 'NO ACTION') {
            $line .= "->onDelete('".strtolower($fk->onDelete)."')";
        }

        if ($fk->onUpdate !== null && $fk->onUpdate !== '' && strtoupper($fk->onUpdate) !== 'NO ACTION') {
            $line .= "->onUpdate('".strtolower($fk->onUpdate)."')";
        }

        $suffix = $fk->inferred ? ' // inferred' : '';

        return $line.';'.$suffix;
    }

    private function length(string $fullType, int $fallback): int
    {
        if (preg_match('/\((\d+)\)/', $fullType, $m) === 1) {
            return (int) $m[1];
        }

        return $fallback;
    }

    private function precision(string $fullType): string
    {
        if (preg_match('/\((\d+)\s*,\s*(\d+)\)/', $fullType, $m) === 1) {
            return "{$m[1]}, {$m[2]}";
        }

        return '8, 2';
    }

    private function enumValues(string $fullType): string
    {
        if (preg_match('/\((.*)\)/', $fullType, $m) === 1) {
            $inner = $m[1];
            preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $inner, $vals);

            if ($vals[1] !== []) {
                return '['.implode(', ', array_map(fn (string $v): string => "'".$this->escape($v)."'", $vals[1])).']';
            }
        }

        return '[]';
    }

    /**
     * @param  array<int, string>  $items
     */
    private function quotedList(array $items): string
    {
        return implode(', ', array_map(static fn (string $i): string => "'{$i}'", $items));
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
