<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_type_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')
                ->constrained('service_types')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['service_type_id', 'version_number']);
            $table->index(['service_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_type_versions');
    }
};
