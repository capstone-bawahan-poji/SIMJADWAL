<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_run_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_detail_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('constraint_type_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('penalty', 12, 4);
            $table->text('message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_logs');
    }
};
