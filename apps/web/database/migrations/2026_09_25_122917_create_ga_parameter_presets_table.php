<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ga_parameter_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('population_size')->default(100);
            $table->integer('min_generations')->default(500);
            $table->integer('max_generations')->default(1000);
            $table->integer('stagnation_limit')->default(200);
            $table->decimal('crossover_rate', 4, 3);
            $table->decimal('mutation_rate', 4, 3);
            $table->integer('tournament_size')->default(2);
            $table->string('selection_operator', 3);
            $table->smallInteger('parallel_class_limit')->default(5);
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE ga_parameter_presets ADD CONSTRAINT chk_ga_presets_population_size CHECK (population_size > 0)');
        DB::statement('ALTER TABLE ga_parameter_presets ADD CONSTRAINT chk_ga_presets_min_generations CHECK (min_generations > 0)');
        DB::statement('ALTER TABLE ga_parameter_presets ADD CONSTRAINT chk_ga_presets_max_generations CHECK (max_generations >= min_generations)');
        DB::statement('ALTER TABLE ga_parameter_presets ADD CONSTRAINT chk_ga_presets_crossover_rate CHECK (crossover_rate BETWEEN 0 AND 1)');
        DB::statement('ALTER TABLE ga_parameter_presets ADD CONSTRAINT chk_ga_presets_mutation_rate CHECK (mutation_rate BETWEEN 0 AND 1)');
        DB::statement('ALTER TABLE ga_parameter_presets ADD CONSTRAINT chk_ga_presets_tournament_size CHECK (tournament_size >= 2)');
        DB::statement('ALTER TABLE ga_parameter_presets ADD CONSTRAINT chk_ga_presets_parallel_class_limit CHECK (parallel_class_limit >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ga_parameter_presets');
    }
};
