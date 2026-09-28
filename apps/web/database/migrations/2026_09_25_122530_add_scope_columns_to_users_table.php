<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('faculty_id')->nullable()->after('password')->index()->constrained()->restrictOnDelete();
            $table->foreignId('study_program_id')->nullable()->after('faculty_id')->index()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_program_id');
            $table->dropConstrainedForeignId('faculty_id');
        });
    }
};
