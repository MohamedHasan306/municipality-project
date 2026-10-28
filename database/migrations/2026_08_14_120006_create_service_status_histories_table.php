<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')
                ->constrained('service_requests')
                ->cascadeOnDelete();
            $table->foreignId('from_status_id')
                ->nullable()
                ->constrained('service_statuses')
                ->restrictOnDelete();
            $table->foreignId('to_status_id')
                ->constrained('service_statuses')
                ->restrictOnDelete();
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['service_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_status_histories');
    }
};
