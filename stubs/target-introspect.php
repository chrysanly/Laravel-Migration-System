<?php

/**
 * Standalone, framework-agnostic database introspection script.
 *
 * Run by a *target project's* PHP binary (which has the right PDO driver) so the
 * migration-system app never needs the target's driver and never touches its .env
 * beyond reading credentials passed in here.
 *
 * Usage:  php target-introspect.php <path-to-json-input>
 * Input JSON: { mode: 'ping'|'tables'|'table', driver, host, port, database,
 *               username, password, trust_server_certificate, sqlite_path, table? }
 * Output JSON to stdout: { ok: true, ... } or { ok: false, error: '...' }
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // errors must never corrupt the JSON payload

function fail(string $message): never
{
    echo json_encode(['ok' => false, 'error' => $message]);
    exit(0);
}

function ok(array $payload): never
{
    echo json_encode(['ok' => true] + $payload);
    exit(0);
}

$inputPath = $argv[1] ?? '';
if ($inputPath === '' || ! is_file($inputPath)) {
    fail('missing input file');
}

$raw = file_get_contents($inputPath);
$cfg = json_decode($raw ?: '', true);
if (! is_array($cfg)) {
    fail('invalid input json');
}

$driver = (string) ($cfg['driver'] ?? '');
$mode = (string) ($cfg['mode'] ?? 'ping');
$database = (string) ($cfg['database'] ?? '');
$table = (string) ($cfg['table'] ?? '');

try {
    $pdo = connect($cfg);
} catch (Throwable $e) {
    fail($e->getMessage());
}

try {
    switch ($mode) {
        case 'ping':
            ok([]);
            // no break — ok() exits
        case 'tables':
            ok(['tables' => listTables($pdo, $driver, $database)]);
            // no break
        case 'summary':
            ok([
                'summary' => summary($pdo, $driver, $database),
                'ran' => ranMigrations($pdo, $driver, $database),
            ]);
            // no break
        case 'migrated':
            ok(['migrated' => migratedList($pdo, $driver, $database)]);
            // no break
        case 'table':
            if ($table === '') {
                fail('missing table');
            }
            ok(inspectTable($pdo, $driver, $database, $table));
            // no break
        default:
            fail("unknown mode: {$mode}");
    }
} catch (Throwable $e) {
    fail($e->getMessage());
}

function connect(array $cfg): PDO
{
    $driver = (string) ($cfg['driver'] ?? '');
    $host = (string) ($cfg['host'] ?? '');
    $port = (string) ($cfg['port'] ?? '');
    $database = (string) ($cfg['database'] ?? '');
    $username = (string) ($cfg['username'] ?? '');
    $password = (string) ($cfg['password'] ?? '');
    $trustCert = (bool) ($cfg['trust_server_certificate'] ?? true);

    // sqlsrv rejects PDO::ATTR_TIMEOUT as a constructor attribute — it uses a DSN LoginTimeout.
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    $withTimeout = $options + [PDO::ATTR_TIMEOUT => 5];

    switch ($driver) {
        case 'mysql':
        case 'mariadb':
            $dsn = "mysql:host={$host};".($port !== '' ? "port={$port};" : '')."dbname={$database}";

            return new PDO($dsn, $username, $password, $withTimeout);

        case 'pgsql':
            $dsn = "pgsql:host={$host};".($port !== '' ? "port={$port};" : '')."dbname={$database}";

            return new PDO($dsn, $username, $password, $withTimeout);

        case 'sqlsrv':
            // Named instance (host contains "\") must not also carry a port.
            $server = str_contains($host, '\\')
                ? $host
                : ($port !== '' ? "{$host},{$port}" : $host);
            $dsn = "sqlsrv:Server={$server};Database={$database};LoginTimeout=5";
            if ($trustCert) {
                $dsn .= ';TrustServerCertificate=1';
            }
            // Empty username => Windows Authentication (integrated security).
            if ($username === '') {
                return new PDO($dsn, null, null, $options);
            }

            return new PDO($dsn, $username, $password, $options);

        case 'sqlite':
            $path = (string) ($cfg['sqlite_path'] ?? $database);
            if (! is_file($path)) {
                throw new RuntimeException('the sqlite database file was not found');
            }

            return new PDO("sqlite:{$path}", null, null, $options);

        default:
            throw new RuntimeException("unsupported driver: {$driver}");
    }
}

/** @return array<int, string> */
function listTables(PDO $pdo, string $driver, string $database): array
{
    switch ($driver) {
        case 'sqlite':
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            break;
        case 'pgsql':
            $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' ORDER BY table_name");
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
        case 'sqlsrv':
            $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' AND TABLE_CATALOG = ? ORDER BY TABLE_NAME");
            $stmt->execute([$database]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
        default: // mysql / mariadb
            $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA = ? ORDER BY TABLE_NAME");
            $stmt->execute([$database]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    return array_values(array_map('strval', $rows));
}

/**
 * Per-table primary-key presence and foreign-key count for the whole database, in one pass.
 *
 * @return array<string, array{pk: bool, fks: int}>
 */
function summary(PDO $pdo, string $driver, string $database): array
{
    $tables = listTables($pdo, $driver, $database);
    $out = [];
    foreach ($tables as $t) {
        $out[$t] = ['pk' => false, 'fks' => 0];
    }

    if ($driver === 'sqlite') {
        foreach ($tables as $t) {
            foreach ($pdo->query('PRAGMA table_info(' . $pdo->quote($t) . ')')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if ((int) $c['pk'] > 0) {
                    $out[$t]['pk'] = true;
                }
            }
            $out[$t]['fks'] = count($pdo->query('PRAGMA foreign_key_list(' . $pdo->quote($t) . ')')->fetchAll());
        }

        return $out;
    }

    $schemaCol = $driver === 'sqlsrv' ? 'TABLE_CATALOG' : 'TABLE_SCHEMA';
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME, CONSTRAINT_TYPE
         FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
         WHERE {$schemaCol} = ? AND CONSTRAINT_TYPE IN ('PRIMARY KEY', 'FOREIGN KEY')"
    );
    $stmt->execute([$database]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $t = (string) $r['TABLE_NAME'];
        if (! isset($out[$t])) {
            continue;
        }
        if ($r['CONSTRAINT_TYPE'] === 'PRIMARY KEY') {
            $out[$t]['pk'] = true;
        } else {
            $out[$t]['fks']++;
        }
    }

    return $out;
}

/**
 * The migration names recorded as run in the target's `migrations` table
 * (Laravel stores the file name without the .php extension). Empty if there is
 * no migrations table.
 *
 * @return array<int, string>
 */
function ranMigrations(PDO $pdo, string $driver, string $database): array
{
    $tables = listTables($pdo, $driver, $database);
    if (! in_array('migrations', $tables, true)) {
        return [];
    }

    try {
        $rows = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_map('strval', $rows));
    } catch (Throwable) {
        return [];
    }
}

/**
 * Rows from the `migrations` table (name + batch), for the rollback view.
 *
 * @return array<int, array{migration: string, batch: int}>
 */
function migratedList(PDO $pdo, string $driver, string $database): array
{
    $tables = listTables($pdo, $driver, $database);
    if (! in_array('migrations', $tables, true)) {
        return [];
    }

    try {
        $rows = $pdo->query('SELECT migration, batch FROM migrations ORDER BY batch DESC, migration DESC')->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $r): array => ['migration' => (string) $r['migration'], 'batch' => (int) $r['batch']],
            $rows,
        );
    } catch (Throwable) {
        return [];
    }
}

/** @return array<string, mixed> */
function inspectTable(PDO $pdo, string $driver, string $database, string $table): array
{
    if ($driver === 'sqlite') {
        return inspectSqlite($pdo, $table);
    }

    return [
        'columns' => columns($pdo, $driver, $database, $table),
        'primary_key' => primaryKey($pdo, $driver, $database, $table),
        'foreign_keys' => foreignKeys($pdo, $driver, $database, $table),
        'indexes' => uniqueIndexes($pdo, $driver, $database, $table),
    ];
}

/** @return array<int, array<string, mixed>> */
function columns(PDO $pdo, string $driver, string $database, string $table): array
{
    $schemaCol = $driver === 'sqlsrv' ? 'TABLE_CATALOG' : 'TABLE_SCHEMA';
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_NAME = ? AND {$schemaCol} = ?
         ORDER BY ORDINAL_POSITION"
    );
    $stmt->execute([$table, $database]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Driver-specific extras.
    $mysqlExtra = [];
    $unsignedCols = [];
    if ($driver === 'mysql' || $driver === 'mariadb') {
        $s = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?');
        $s->execute([$table, $database]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $mysqlExtra[$r['COLUMN_NAME']] = $r;
            if (str_contains(strtolower((string) $r['COLUMN_TYPE']), 'unsigned')) {
                $unsignedCols[$r['COLUMN_NAME']] = true;
            }
        }
    }
    $identityCols = [];
    if ($driver === 'sqlsrv') {
        $s = $pdo->query('SELECT c.name FROM sys.columns c WHERE c.is_identity = 1 AND c.object_id = OBJECT_ID('.$pdo->quote($table).')');
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $identityCols[$name] = true;
        }
    }

    $out = [];
    foreach ($rows as $r) {
        $name = (string) $r['COLUMN_NAME'];
        $typeName = strtolower((string) $r['DATA_TYPE']);
        $full = buildFullType($typeName, $r, $driver, $mysqlExtra[$name]['COLUMN_TYPE'] ?? null);

        $autoIncrement = false;
        if ($driver === 'mysql' || $driver === 'mariadb') {
            $autoIncrement = str_contains(strtolower((string) ($mysqlExtra[$name]['EXTRA'] ?? '')), 'auto_increment');
        } elseif ($driver === 'sqlsrv') {
            $autoIncrement = isset($identityCols[$name]);
        } elseif ($driver === 'pgsql') {
            $autoIncrement = str_contains(strtolower((string) ($r['COLUMN_DEFAULT'] ?? '')), 'nextval');
        }

        $out[] = [
            'name' => $name,
            'type_name' => $typeName,
            'full_type' => $full,
            'nullable' => strtoupper((string) $r['IS_NULLABLE']) === 'YES',
            'default' => normaliseDefault($r['COLUMN_DEFAULT'] ?? null),
            'auto_increment' => $autoIncrement,
            'unsigned' => isset($unsignedCols[$name]),
        ];
    }

    return $out;
}

function buildFullType(string $typeName, array $row, string $driver, ?string $mysqlColumnType): string
{
    if (($driver === 'mysql' || $driver === 'mariadb') && $mysqlColumnType) {
        return strtolower($mysqlColumnType); // e.g. "varchar(255)", "int unsigned", "enum('a','b')"
    }

    $len = $row['CHARACTER_MAXIMUM_LENGTH'] ?? null;
    $precision = $row['NUMERIC_PRECISION'] ?? null;
    $scale = $row['NUMERIC_SCALE'] ?? null;

    if (in_array($typeName, ['decimal', 'numeric'], true) && $precision !== null) {
        return "{$typeName}({$precision},".(int) $scale.')';
    }
    if ($len !== null && (int) $len > 0) {
        return "{$typeName}(".(int) $len.')';
    }

    return $typeName;
}

/** @return array<int, string> */
function primaryKey(PDO $pdo, string $driver, string $database, string $table): array
{
    $schemaCol = $driver === 'sqlsrv' ? 'tc.TABLE_CATALOG' : 'tc.TABLE_SCHEMA';
    $stmt = $pdo->prepare(
        "SELECT kcu.COLUMN_NAME
         FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
         JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
           ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND tc.TABLE_NAME = kcu.TABLE_NAME
         WHERE tc.CONSTRAINT_TYPE = 'PRIMARY KEY' AND tc.TABLE_NAME = ? AND {$schemaCol} = ?
         ORDER BY kcu.ORDINAL_POSITION"
    );
    $stmt->execute([$table, $database]);

    return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}

/** @return array<int, array<string, mixed>> */
function foreignKeys(PDO $pdo, string $driver, string $database, string $table): array
{
    if ($driver === 'sqlsrv') {
        $sql = 'SELECT fk.name AS cname, cpa.name AS col, rt.name AS ref_table, cref.name AS ref_col,
                       fk.update_referential_action_desc AS upd, fk.delete_referential_action_desc AS del,
                       fkc.constraint_column_id AS pos
                FROM sys.foreign_keys fk
                JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
                JOIN sys.columns cpa ON cpa.object_id = fkc.parent_object_id AND cpa.column_id = fkc.parent_column_id
                JOIN sys.tables rt ON rt.object_id = fk.referenced_object_id
                JOIN sys.columns cref ON cref.object_id = fkc.referenced_object_id AND cref.column_id = fkc.referenced_column_id
                WHERE fk.parent_object_id = OBJECT_ID('.$pdo->quote($table).')
                ORDER BY fk.name, fkc.constraint_column_id';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $schemaCol = 'kcu.TABLE_SCHEMA';
        $sql = "SELECT rc.CONSTRAINT_NAME AS cname, kcu.COLUMN_NAME AS col,
                       kcu2.TABLE_NAME AS ref_table, kcu2.COLUMN_NAME AS ref_col,
                       rc.UPDATE_RULE AS upd, rc.DELETE_RULE AS del, kcu.ORDINAL_POSITION AS pos
                FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                  ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu2
                  ON kcu2.CONSTRAINT_NAME = rc.UNIQUE_CONSTRAINT_NAME AND kcu2.ORDINAL_POSITION = kcu.ORDINAL_POSITION
                WHERE kcu.TABLE_NAME = ? AND {$schemaCol} = ?
                ORDER BY rc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $database]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $grouped = [];
    foreach ($rows as $r) {
        $key = (string) $r['cname'];
        $grouped[$key] ??= [
            'columns' => [],
            'foreign_table' => (string) $r['ref_table'],
            'foreign_columns' => [],
            'on_update' => normaliseRule((string) ($r['upd'] ?? '')),
            'on_delete' => normaliseRule((string) ($r['del'] ?? '')),
        ];
        $grouped[$key]['columns'][] = (string) $r['col'];
        $grouped[$key]['foreign_columns'][] = (string) $r['ref_col'];
    }

    return array_values($grouped);
}

/** @return array<int, array<string, mixed>> */
function uniqueIndexes(PDO $pdo, string $driver, string $database, string $table): array
{
    // Unique constraints via information_schema (portable). Primary key excluded (handled separately).
    if ($driver === 'sqlsrv') {
        $sql = 'SELECT i.name AS iname, c.name AS col, ic.key_ordinal AS pos
                FROM sys.indexes i
                JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
                JOIN sys.columns c ON c.object_id = i.object_id AND c.column_id = ic.column_id
                WHERE i.is_unique = 1 AND i.is_primary_key = 0 AND i.object_id = OBJECT_ID('.$pdo->quote($table).')
                ORDER BY i.name, ic.key_ordinal';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $schemaCol = 'tc.TABLE_SCHEMA';
        $sql = "SELECT tc.CONSTRAINT_NAME AS iname, kcu.COLUMN_NAME AS col, kcu.ORDINAL_POSITION AS pos
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                  ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND tc.TABLE_NAME = kcu.TABLE_NAME
                WHERE tc.CONSTRAINT_TYPE = 'UNIQUE' AND tc.TABLE_NAME = ? AND {$schemaCol} = ?
                ORDER BY tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table, $database]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $grouped = [];
    foreach ($rows as $r) {
        $key = (string) $r['iname'];
        $grouped[$key] ??= ['name' => $key, 'columns' => [], 'unique' => true, 'primary' => false];
        $grouped[$key]['columns'][] = (string) $r['col'];
    }

    return array_values($grouped);
}

/** @return array<string, mixed> */
function inspectSqlite(PDO $pdo, string $table): array
{
    $cols = $pdo->query('PRAGMA table_info('.$pdo->quote($table).')')->fetchAll(PDO::FETCH_ASSOC);
    $columns = [];
    $primaryKey = [];
    foreach ($cols as $c) {
        $type = strtolower((string) $c['type']);
        if ((int) $c['pk'] > 0) {
            $primaryKey[] = (string) $c['name'];
        }
        $columns[] = [
            'name' => (string) $c['name'],
            'type_name' => preg_replace('/\(.*/', '', $type) ?: $type,
            'full_type' => $type,
            'nullable' => (int) $c['notnull'] === 0,
            'default' => normaliseDefault($c['dflt_value'] ?? null),
            'auto_increment' => (int) $c['pk'] === 1 && str_contains($type, 'int'),
            'unsigned' => false,
        ];
    }

    $fks = [];
    foreach ($pdo->query('PRAGMA foreign_key_list('.$pdo->quote($table).')')->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $fks[] = [
            'columns' => [(string) $f['from']],
            'foreign_table' => (string) $f['table'],
            'foreign_columns' => [(string) $f['to']],
            'on_update' => normaliseRule((string) ($f['on_update'] ?? '')),
            'on_delete' => normaliseRule((string) ($f['on_delete'] ?? '')),
        ];
    }

    return ['columns' => $columns, 'primary_key' => $primaryKey, 'foreign_keys' => $fks, 'indexes' => []];
}

function normaliseDefault(mixed $default): string|int|float|null
{
    if ($default === null) {
        return null;
    }
    $value = (string) $default;
    // SQL Server wraps defaults: ((0)) , ('text') , (getdate())
    while (strlen($value) >= 2 && $value[0] === '(' && $value[strlen($value) - 1] === ')') {
        $value = substr($value, 1, -1);
    }
    if (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
        $value = substr($value, 1, -1);
    }

    return $value;
}

function normaliseRule(string $rule): ?string
{
    $rule = strtoupper(str_replace('_', ' ', trim($rule)));

    return ($rule === '' || $rule === 'NO ACTION') ? null : $rule;
}
