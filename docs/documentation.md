# MigrationSystem — Documentation

A local tool for managing **Laravel database migrations across your other projects**:
inspect a project's live database, generate migrations from existing tables, design new
tables, add primary/foreign keys, and run migrations — all under **each project's own path
and configuration**.

---

## Core rule

> **Every command runs under the target project's own path, using that project's own
> `.env` and configuration — never this tool's.**

MigrationSystem only:

- writes migration files into the target project's migration folders, and
- runs the target project's **own** `php artisan` from the project root.

It never changes a project's requirements or config. When it runs a migration it strips its
own `DB_*` / `APP_*` environment variables so the target project's `.env` always wins. Each
project can use a different database (MySQL, SQL Server, PostgreSQL, SQLite) and a different
PHP version — MigrationSystem adapts per project.

---

## Registering a project

On **Projects → Register a project**:

- **Name** — a label for the project.
- **Project folder** — the absolute path to the Laravel project root (the folder containing
  `artisan`).
- **PHP binary (optional)** — leave blank to auto-pick a PHP that matches the project's
  `composer.json` and has the right database driver; or set an explicit path.
- **Credentials** — by default the tool reads the project's own `.env`. Untick to enter
  connection details manually.

## Connection detection

When you open a project, the **Connection** card shows what will be used, read from the
project's `.env`: driver (mysql / sqlsrv / pgsql / sqlite), host, port, database, and auth
mode (including SQL Server **Windows authentication** when no user is set). If the `.env`
cannot be read, the card tells you.

## PHP selection

- **Introspection** (reading the schema) uses the newest installed PHP that has the required
  driver — it runs a standalone, framework-agnostic script, so the project's Laravel version
  doesn't matter.
- **Migration execution** (`artisan migrate`) prefers the **lowest** installed PHP that
  satisfies the project's `composer.json` and has the driver, because older frameworks may
  not boot on the newest PHP. Herd and Laragon PHP binaries are auto-discovered.

---

## The table overview

For each table in the project's live database you'll see:

- **Migration** — a check/cross with a tooltip:
  - ✓ **migrated** — a create-migration exists and is recorded in the `migrations` table.
  - ✗ **not migrated** — the file exists but hasn't been run yet.
  - ✗ **no file** — no create-migration file was found for this table.
- **PK / FK** — whether the table has a primary key and how many foreign keys it has.
- **Other** — related migration files that modify/drop/rename the table (expandable), each
  with its own migrated status and a Migrate button.
- **Actions** — Generate, Migrate (when applicable), and Keys.

---

## Generating a migration from an existing table

Click **Generate** on a table that has no migration. MigrationSystem reverse-engineers a
`create` migration from the real schema:

- faithful column types, nullability, defaults, and indexes;
- the existing primary key and foreign keys;
- **inferred keys (opt-in)** — if the table has no primary key, or has `*_id` columns that
  look like foreign keys but lack a constraint, they're offered as checkboxes in the preview.

You get a live code preview and choose whether to write to the project root or a module.

## Designing a new table

**New table** opens a builder: add columns (name, type, length/precision, nullable, default,
unsigned, unique), define foreign keys, and a **required primary key** (auto-increment `id`
or chosen columns). It generates a `create` migration with real `up()` **and** `down()`.

## Adding keys to an existing table

**Keys** builds an `add-keys` migration (`Schema::table`) that adds a primary key and/or
foreign keys to an existing table, with a `down()` that drops them again. Inferred keys are
pre-filled and editable, with a live preview.

## Modules

Module migrations use `nwidart/laravel-modules`. Tick **This is a module migration** and
enter the module name; files are written to `Modules/<Name>/Database/Migrations` (the path is
read from the project's own `config/modules.php`).

---

## Running migrations

- Every generate screen has **Run the migration immediately after generating**.
- Any not-migrated file has a **Migrate** button.

Both ask for confirmation, then run `php artisan migrate --path=<file> --force` from the
project root using the project's own environment. A spinner shows progress and a toast
reports success or failure.

> Migrations run as your current OS user. For SQL Server with Windows authentication, that's
> the account used to connect.

## Logs

Every action (generate / create / keys / migrate) is recorded. Open **Logs** on a project to
see the history with status, PHP version, the exact command, and the full output — use the
**View** dialog to diagnose a failed migration.

---

## Supported databases

MySQL / MariaDB, SQL Server (`sqlsrv`), PostgreSQL, and SQLite — provided an installed PHP
binary has the matching PDO driver.
