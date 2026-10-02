<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('study_program_id')->index()->constrained()->restrictOnDelete();
            $table->string('nip', 20);
            $table->string('name', 150);
            $table->string('title', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX uq_lecturers_nip ON lecturers (nip) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturers');
    }
};
