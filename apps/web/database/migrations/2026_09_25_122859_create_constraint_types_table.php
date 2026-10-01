<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constraint_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->char('category', 2);
            $table->text('description');
            $table->decimal('weight', 8, 2);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE constraint_types ADD CONSTRAINT chk_constraint_types_weight CHECK (weight >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('constraint_types');
    }
};
