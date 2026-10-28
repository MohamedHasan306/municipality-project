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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')
                ->constrained('municipalities')
                ->restrictOnDelete();

            $table->foreignId('category_id')
                ->constrained('complaint_categories')
                ->restrictOnDelete();

            $table->foreignId('current_status_id')
                ->constrained('complaint_statuses')
                ->restrictOnDelete();



            $table->string('title');

            $table->string('text_location')->nullable();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->timestamp('submitted_at')->nullable();


            $table->timestamps();

            $table->index([
                'municipality_id',
                'category_id',
                'current_status_id',
            ], 'complaints_filter_index');

            $table->index(
                ['latitude', 'longitude'],
                'complaints_location_index'
            );

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
