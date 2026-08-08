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
        Schema::create('complaint_status_histories', function (Blueprint $table) {
            $table->id();

        $table->foreignId('complaint_report_id')
            ->constrained('complaint_reports')
            ->restrictOnDelete();

        $table->foreignId('from_status_id')
            ->nullable()
            ->constrained('complaint_statuses')
            ->restrictOnDelete();

        $table->foreignId('to_status_id')
            ->constrained('complaint_statuses')
            ->restrictOnDelete();

        $table->foreignId('changed_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->text('note')->nullable();

        $table->boolean('is_public')->default(true);

        $table->timestamps();

        $table->index(
            ['complaint_report_id', 'created_at'],
            'complaint_status_history_index'
        );
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_status_histories');
    }
};
