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
            $table->foreignId('study_program_id')->index()->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->smallInteger('sks');
            $table->smallInteger('semester');
            $table->smallInteger('parallel_class_count')->default(1);
            $table->smallInteger('class_capacity');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_semester CHECK (semester BETWEEN 1 AND 8)');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_parallel_class_count CHECK (parallel_class_count >= 1)');
        DB::statement('ALTER TABLE courses ADD CONSTRAINT chk_courses_class_capacity CHECK (class_capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
