<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturer_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecturer_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('constraint_type_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('time_slot_id')->index()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['lecturer_id', 'time_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturer_preferences');
    }
};
