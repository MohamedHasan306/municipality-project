<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('complaint_work_unit', function (Blueprint $table) {
            $table->id();

            $table->foreignId('complaint_id')
                ->constrained('complaints')
                ->restrictOnDelete();

            $table->foreignId('work_unit_id')
                ->constrained('work_units')
                ->restrictOnDelete();

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['complaint_id', 'work_unit_id'],
                'complaint_work_unit_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_work_unit');
    }
};
