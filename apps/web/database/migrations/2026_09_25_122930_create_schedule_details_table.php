<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->index()->constrained()->restrictOnDelete();
            $table->smallInteger('class_number');
            $table->smallInteger('meeting_number')->default(1);
            $table->foreignId('lecturer_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('time_slot_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->boolean('is_overridden')->default(false);
            $table->foreignId('overridden_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('overridden_at')->nullable();

            $table->unique(['scheduling_run_id', 'course_id', 'class_number', 'meeting_number']);
            $table->index(['scheduling_run_id', 'time_slot_id']);
            $table->index(['scheduling_run_id', 'lecturer_id']);
            $table->index(['scheduling_run_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_details');
    }
};
