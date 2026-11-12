<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduling_runs', function (Blueprint $table) {
            $table->foreignId('study_program_id')->nullable()->after('faculty_id')->index()->constrained()->restrictOnDelete();
            $table->string('source', 10)->default('ga')->after('status');
        });

        // uq_active_schedule allowed one active run per faculty.
        DB::statement('DROP INDEX uq_active_schedule');
        DB::statement('DROP INDEX scheduling_runs_active_unique');
        DB::statement('CREATE UNIQUE INDEX scheduling_runs_active_unique ON scheduling_runs (COALESCE(faculty_id, 0), COALESCE(study_program_id, 0), period) WHERE is_active');
        DB::statement('ALTER TABLE scheduling_runs ADD CONSTRAINT chk_scheduling_runs_scope CHECK (study_program_id IS NULL OR faculty_id IS NOT NULL)');
        DB::statement("ALTER TABLE scheduling_runs ADD CONSTRAINT chk_scheduling_runs_source CHECK (source IN ('ga', 'import'))");

        // Swaps go through a NULL room first.
        DB::statement('CREATE UNIQUE INDEX uq_schedule_details_cell ON schedule_details (scheduling_run_id, time_slot_id, room_id) WHERE room_id IS NOT NULL');

        Schema::table('ga_parameter_presets', function (Blueprint $table) {
            $table->foreignId('study_program_id')->nullable()->after('faculty_id')->index()->constrained()->restrictOnDelete();
        });
        // A database created before the create migration was edited has the old Blueprint unique
        // constraint instead of the expression index. Drop whichever of the two exists.
        DB::statement('DROP INDEX IF EXISTS uq_ga_parameter_presets_name');
        DB::statement('ALTER TABLE ga_parameter_presets DROP CONSTRAINT IF EXISTS ga_parameter_presets_faculty_id_name_unique');
        DB::statement('CREATE UNIQUE INDEX uq_ga_parameter_presets_name ON ga_parameter_presets (COALESCE(faculty_id, 0), COALESCE(study_program_id, 0), name)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX uq_ga_parameter_presets_name');
        DB::statement('CREATE UNIQUE INDEX uq_ga_parameter_presets_name ON ga_parameter_presets (COALESCE(faculty_id, 0), name)');
        Schema::table('ga_parameter_presets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_program_id');
        });

        DB::statement('DROP INDEX uq_schedule_details_cell');

        DB::statement('ALTER TABLE scheduling_runs DROP CONSTRAINT chk_scheduling_runs_source');
        DB::statement('ALTER TABLE scheduling_runs DROP CONSTRAINT chk_scheduling_runs_scope');
        DB::statement('DROP INDEX scheduling_runs_active_unique');
        DB::statement('CREATE UNIQUE INDEX scheduling_runs_active_unique ON scheduling_runs (COALESCE(faculty_id, 0), period) WHERE is_active');
        DB::statement('CREATE UNIQUE INDEX uq_active_schedule ON scheduling_runs (faculty_id, period) WHERE is_active');
        Schema::table('scheduling_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_program_id');
            $table->dropColumn('source');
        });
    }
};
