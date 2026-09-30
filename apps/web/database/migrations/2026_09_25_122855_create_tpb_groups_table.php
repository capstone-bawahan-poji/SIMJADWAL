<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One group of one TPB course: a fixed set of study programs whose classes all run in one slot.
     * The TPB admin places groups by hand; the slot is then blocked for the participating programs.
     */
    public function up(): void
    {
        Schema::create('tpb_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->index()->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->foreignId('time_slot_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpb_groups');
    }
};
