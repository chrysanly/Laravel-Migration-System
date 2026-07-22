<?php

use App\Http\Controllers\DocsController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SystemUpdateController;
use App\Http\Controllers\TableDesignController;
use Illuminate\Support\Facades\Route;

// Single-user local tool — no login. Open straight into the projects list.
Route::redirect('/', '/projects')->name('home');

// Kept (unlinked) so generated route helpers still resolve; not shown in the UI.
Route::inertia('dashboard', 'dashboard')->name('dashboard');

// In-app documentation (rendered from markdown).
Route::get('docs', [DocsController::class, 'index'])->name('docs');

// Self-update (checks this app's own repo for a newer version).
Route::get('system/update/check', [SystemUpdateController::class, 'check'])->name('system.update.check');
Route::post('system/update/run', [SystemUpdateController::class, 'run'])->name('system.update.run');
Route::get('system/update/status', [SystemUpdateController::class, 'status'])->name('system.update.status');

// Migration system — registered target projects + migrations.
Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::get('projects/{project}/logs', [ProjectController::class, 'logs'])->name('projects.logs');
Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

// Brand-new table designer.
Route::get('projects/{project}/design', [TableDesignController::class, 'create'])->name('projects.design');
Route::post('projects/{project}/design', [TableDesignController::class, 'store'])->name('projects.design.store');

// Run a single migration file on the target.
Route::post('projects/{project}/migrate', [MigrationController::class, 'migrate'])->name('projects.migrate');

// {table} is the DB table name (not a model) — validated in the controllers/requests.
Route::get('projects/{project}/tables/{table}', [MigrationController::class, 'preview'])
    ->name('projects.tables.preview')
    ->where('table', '[A-Za-z0-9_]+');
Route::post('projects/{project}/tables/{table}/generate', [MigrationController::class, 'store'])
    ->name('projects.tables.generate')
    ->where('table', '[A-Za-z0-9_]+');

// Add primary/foreign keys to an existing table.
Route::get('projects/{project}/tables/{table}/keys', [MigrationController::class, 'keys'])
    ->name('projects.tables.keys')
    ->where('table', '[A-Za-z0-9_]+');
Route::post('projects/{project}/tables/{table}/keys', [MigrationController::class, 'storeKeys'])
    ->name('projects.tables.keys.store')
    ->where('table', '[A-Za-z0-9_]+');

require __DIR__.'/settings.php';
