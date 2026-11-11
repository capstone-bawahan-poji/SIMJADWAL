<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One group of one TPB offering: classes that always share a slot, attended by a set of programs.
     * The slot comes from the active TPB scheduling run, never from this table.
     */
    public function up(): void
    {
        Schema::create('tpb_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tpb_offering_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('cohort', 12);
            $table->timestamps();

            $table->unique(['tpb_offering_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpb_groups');
    }
};
