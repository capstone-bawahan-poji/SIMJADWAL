<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('constraint_status', 10)->default('draft');
            $table->timestamp('constraint_submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX uq_study_programs_code ON study_programs (code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_study_programs_name ON study_programs (name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('study_programs');
    }
};
