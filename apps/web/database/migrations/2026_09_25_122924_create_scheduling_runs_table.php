<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index();
            $table->foreignId('faculty_id')->index()->constrained()->restrictOnDelete();
            $table->string('period', 20);
            $table->foreignId('preset_id')->nullable()->index()->constrained('ga_parameter_presets')->restrictOnDelete();
            $table->jsonb('parameter_snapshot');
            $table->string('status', 10)->default('queued');
            $table->integer('current_generation')->default(0);
            $table->decimal('current_fitness', 12, 4)->nullable();
            $table->decimal('best_fitness', 12, 4)->nullable();
            $table->decimal('max_fitness', 12, 4)->nullable();
            $table->integer('best_generation')->nullable();
            $table->jsonb('fitness_history')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('triggered_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('published_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['faculty_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_runs');
    }
};
