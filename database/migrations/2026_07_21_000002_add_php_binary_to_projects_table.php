<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // Optional: explicit PHP binary to run introspection for this project.
            // When null, PhpBinaryLocator picks one matching composer.json + driver.
            $table->string('php_binary', 500)->nullable()->after('root_path');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('php_binary');
        });
    }
};
