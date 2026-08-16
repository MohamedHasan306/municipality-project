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
        Schema::create('complaint_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')
                ->nullable()
                ->constrained('complaints')
                ->nullOnDelete();

            $table->foreignId('citizen_profile_id')
                ->constrained('citizen_profiles')
                ->restrictOnDelete();

            $table->foreignId('municipality_id')
                ->nullable()
                ->constrained('municipalities')
                ->restrictOnDelete();

            $table->foreignId('current_status_id')
                ->constrained('complaint_statuses')
                ->restrictOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('complaint_categories')
                ->restrictOnDelete();

            $table->string('title')->nullable();
            $table->text('description')->nullable();

            $table->string('text_location')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('linked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('linked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'municipality_id',
                'category_id',
                'submitted_at',
            ], 'complaint_reports_duplicate_lookup_index');

            $table->index(
                ['latitude', 'longitude'],
                'complaint_reports_location_index'
            );

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_reports');
    }
};
