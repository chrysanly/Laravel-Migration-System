<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20);          // generate | create | keys | migrate
            $table->string('target', 255)->nullable(); // table or migration file
            $table->string('status', 20);          // success | failed
            $table->string('php_version', 20)->nullable();
            $table->text('command')->nullable();
            $table->longText('output')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
