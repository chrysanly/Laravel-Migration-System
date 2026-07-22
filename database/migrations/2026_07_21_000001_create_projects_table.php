<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('root_path', 500)->unique();

            // Credential source: when true, read the target project's own .env.
            // When false, use the db_* overrides stored below.
            $table->boolean('use_env_credentials')->default(true);

            // Optional manual credential overrides (used when use_env_credentials = false).
            $table->string('db_connection', 30)->nullable();
            $table->string('db_host', 190)->nullable();
            $table->string('db_port', 10)->nullable();
            $table->string('db_database', 190)->nullable();
            $table->string('db_username', 190)->nullable();
            $table->text('db_password')->nullable(); // encrypted cast on the model

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
