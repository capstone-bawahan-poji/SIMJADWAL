<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial: a soft-deleted faculty must not block reusing its code or name.
        DB::statement('CREATE UNIQUE INDEX uq_faculties_code ON faculties (code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_faculties_name ON faculties (name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('faculties');
    }
};
