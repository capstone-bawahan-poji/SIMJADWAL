<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE study_programs ADD CONSTRAINT chk_constraint_status CHECK (constraint_status IN ('draft','submitted','accepted'))");
        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_sks CHECK (sks IN (2,3,4))');
        DB::statement('ALTER TABLE time_slots ADD CONSTRAINT chk_slot_type CHECK (type IN (2,3))');
        DB::statement("ALTER TABLE constraint_types ADD CONSTRAINT chk_category CHECK (category IN ('HC','SC'))");
        DB::statement("ALTER TABLE ga_parameter_presets ADD CONSTRAINT chk_selection_operator CHECK (selection_operator IN ('TS','TSR'))");
        DB::statement("ALTER TABLE scheduling_runs ADD CONSTRAINT chk_run_status CHECK (status IN ('queued','running','done','failed'))");
        DB::statement("ALTER TABLE scheduling_runs ADD CONSTRAINT chk_active_must_be_done CHECK (NOT is_active OR status = 'done')");

        DB::statement('CREATE UNIQUE INDEX uq_active_schedule ON scheduling_runs (faculty_id, period) WHERE is_active');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_active_schedule');
        DB::statement('ALTER TABLE scheduling_runs DROP CONSTRAINT IF EXISTS chk_active_must_be_done');
        DB::statement('ALTER TABLE scheduling_runs DROP CONSTRAINT IF EXISTS chk_run_status');
        DB::statement('ALTER TABLE ga_parameter_presets DROP CONSTRAINT IF EXISTS chk_selection_operator');
        DB::statement('ALTER TABLE constraint_types DROP CONSTRAINT IF EXISTS chk_category');
        DB::statement('ALTER TABLE time_slots DROP CONSTRAINT IF EXISTS chk_slot_type');
        DB::statement('ALTER TABLE courses DROP CONSTRAINT IF EXISTS chk_sks');
        DB::statement('ALTER TABLE study_programs DROP CONSTRAINT IF EXISTS chk_constraint_status');
    }
};
