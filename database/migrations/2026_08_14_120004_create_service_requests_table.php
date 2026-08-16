<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_profile_id')
                ->constrained('citizen_profiles')
                ->restrictOnDelete();
            $table->foreignId('service_type_version_id')
                ->constrained('service_type_versions')
                ->restrictOnDelete();
            $table->foreignId('current_status_id')
                ->constrained('service_statuses')
                ->restrictOnDelete();
            $table->json('data_json');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['citizen_profile_id', 'current_status_id']);
            $table->index(['service_type_version_id', 'current_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
