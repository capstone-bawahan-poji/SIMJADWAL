<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tpb_group_study_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tpb_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_program_id')->index()->constrained()->restrictOnDelete();
            $table->smallInteger('target_semester')->nullable();
            $table->timestamps();

            $table->unique(['tpb_group_id', 'study_program_id']);
        });

        DB::statement('ALTER TABLE tpb_group_study_programs ADD CONSTRAINT chk_tpb_group_study_programs_target_semester CHECK (target_semester BETWEEN 1 AND 8)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tpb_group_study_programs');
    }
};
