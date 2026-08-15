<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')
                ->constrained('municipalities')
                ->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('document_template_key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['municipality_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
