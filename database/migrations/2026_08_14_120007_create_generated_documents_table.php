<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_request_id')
                ->constrained('service_requests')
                ->cascadeOnDelete();

            $table->string('document_number')->unique();

            $table->string('file_path');

            $table->char('document_hash', 64);

            $table->string('verification_code', 64)->unique();

            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('issued_at');

            $table->dateTime('expires_at');

            $table->timestamps();

            $table->unique('service_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
