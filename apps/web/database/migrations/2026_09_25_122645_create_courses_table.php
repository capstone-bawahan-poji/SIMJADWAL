<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_program_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->boolean('is_tpb')->default(false);
            $table->string('code', 20);
            $table->string('name', 150);
            $table->smallInteger('sks');
            $table->smallInteger('semester')->nullable();
            $table->smallInteger('parallel_class_count')->nullable();
            $table->smallInteger('class_capacity')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX uq_courses_code ON courses (code) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_owner CHECK (is_tpb = (study_program_id IS NULL))');

        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_offering_columns CHECK ((semester IS NULL AND parallel_class_count IS NULL AND class_capacity IS NULL) = is_tpb)');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_semester CHECK (semester BETWEEN 1 AND 8)');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_parallel_class_count CHECK (parallel_class_count >= 1)');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_class_capacity CHECK (class_capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
