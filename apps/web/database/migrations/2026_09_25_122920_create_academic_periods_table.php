<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lecture dates of a period. scheduling_runs.period stores the code as text, so there is no
     * foreign key: AcademicPeriodService refuses to rename or delete a code that runs still use.
     */
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE academic_periods ADD CONSTRAINT chk_academic_periods_date_range CHECK (end_date > start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
