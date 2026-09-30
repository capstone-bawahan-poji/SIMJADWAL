<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->index()->constrained()->restrictOnDelete();
            $table->smallInteger('class_number');
            $table->foreignId('lecturer_id')->index()->constrained()->restrictOnDelete();
            // TPB classes only: the group fixes the slot, the room is allocated by hand.
            $table->foreignId('tpb_group_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'class_number']);
        });

        DB::statement('ALTER TABLE course_lecturers ADD CONSTRAINT chk_course_lecturers_class_number CHECK (class_number >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lecturers');
    }
};
