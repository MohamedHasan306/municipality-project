<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_version_id')
                ->constrained('service_type_versions')
                ->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label');
            $table->string('field_type', 30);
            $table->boolean('is_required')->default(false);
            $table->json('options_json')->nullable();
            $table->json('validation_json')->nullable();
            $table->json('condition_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['service_type_version_id', 'field_key']);
            $table->index(['service_type_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_form_fields');
    }
};
