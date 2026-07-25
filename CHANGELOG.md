# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-07-25

### Added

- Pending migrations view: migration files that haven't run yet, grouped by table
  (the create migration first, then its updates/changes), with a per-file **Migrate**
  button and a **Migrate all pending** action.

## [1.0.0] - 2026-07-22

### Added

- Register Laravel projects and detect the database connection from each project's own `.env`.
- Inspect the live database: per-table migrated status, primary/foreign-key presence, and related migrations.
- Generate migrations from existing tables, with opt-in inferred primary/foreign keys.
- Design new tables and add primary/foreign keys to existing tables (reversible `up()`/`down()`).
- Run migrations under each project's own path and configuration; operation logs with full command output.
- In-app documentation and self-update (checks the latest GitHub Release).
- Supports MySQL/MariaDB, SQL Server, PostgreSQL, and SQLite.
