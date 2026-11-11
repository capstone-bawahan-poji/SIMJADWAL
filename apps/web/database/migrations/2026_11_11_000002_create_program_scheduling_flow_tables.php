<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tpb_occupancy_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained()->restrictOnDelete();
            $table->string('period', 20);
            $table->string('file_name')->nullable();
            $table->foreignId('imported_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['faculty_id', 'period']);
        });

        Schema::create('tpb_occupancy_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tpb_occupancy_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_slot_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('lecturer_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('course_code', 20)->nullable();
            $table->string('course_name', 150)->nullable();
            $table->string('class_label', 10)->nullable();
            $table->smallInteger('participants')->nullable();
        });

        Schema::create('slot_requests', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20);
            $table->foreignId('study_program_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->index()->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->jsonb('needs');
            $table->text('note')->nullable();
            $table->text('response_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['period', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX uq_slot_requests_pending ON slot_requests (study_program_id, period) WHERE status = 'pending'");

        Schema::create('room_slot_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20);
            $table->foreignId('faculty_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_program_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('time_slot_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('slot_request_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignId('allocated_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // One cell belongs to one program across the institute, so faculties cannot share a room twice.
            $table->unique(['period', 'time_slot_id', 'room_id']);
            $table->index(['faculty_id', 'period']);
            $table->index(['study_program_id', 'period']);
        });

        Schema::create('program_schedule_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20);
            $table->foreignId('study_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('scheduling_run_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('draft');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('return_note')->nullable();
            $table->timestamps();

            $table->unique(['study_program_id', 'period']);
        });

        Schema::create('schedule_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained()->restrictOnDelete();
            $table->string('period', 20);
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->string('checksum', 80);
            $table->timestamps();

            $table->unique(['faculty_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_publications');
        Schema::dropIfExists('program_schedule_submissions');
        Schema::dropIfExists('room_slot_allocations');
        Schema::dropIfExists('slot_requests');
        Schema::dropIfExists('tpb_occupancy_import_rows');
        Schema::dropIfExists('tpb_occupancy_imports');
    }
};
