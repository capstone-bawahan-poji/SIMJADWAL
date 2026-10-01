<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('day');
            $table->smallInteger('session');
            $table->time('start_time');
            $table->time('end_time');
            $table->smallInteger('type');
            $table->timestamps();

            $table->unique(['day', 'session']);
        });

        DB::statement('ALTER TABLE time_slots ADD CONSTRAINT chk_time_slots_day CHECK (day BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE time_slots ADD CONSTRAINT chk_time_slots_session CHECK (session >= 1)');
        DB::statement('ALTER TABLE time_slots ADD CONSTRAINT chk_time_slots_time_range CHECK (end_time > start_time)');
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
