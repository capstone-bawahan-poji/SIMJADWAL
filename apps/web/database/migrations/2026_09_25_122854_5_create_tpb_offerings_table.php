<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A TPB course opened in one period. The same course code may be offered in GANJIL for some
     * programs and in GENAP for the others, each time with its own class count and capacity.
     */
    public function up(): void
    {
        Schema::create('tpb_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('period', 20)->index();
            $table->smallInteger('parallel_class_count');
            $table->smallInteger('class_capacity');
            $table->timestamps();

            $table->unique(['course_id', 'period']);
        });

        DB::statement('ALTER TABLE tpb_offerings ADD CONSTRAINT chk_tpb_offerings_parallel_class_count CHECK (parallel_class_count BETWEEN 1 AND 99)');
        DB::statement('ALTER TABLE tpb_offerings ADD CONSTRAINT chk_tpb_offerings_class_capacity CHECK (class_capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tpb_offerings');
    }
};
