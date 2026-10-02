<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tpb_group_study_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tpb_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_program_id')->index()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tpb_group_id', 'study_program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpb_group_study_programs');
    }
};
