<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('name', 100)->unique();
            $table->integer('capacity');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE rooms ADD CONSTRAINT chk_rooms_capacity CHECK (capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
