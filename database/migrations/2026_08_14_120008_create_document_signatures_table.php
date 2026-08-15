<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_document_id')
                ->unique()
                ->constrained('generated_documents')
                ->cascadeOnDelete();
            $table->longText('signature_value');
            $table->char('signed_document_hash', 64);
            $table->string('algorithm');
            $table->foreignId('signed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('signed_at');
            $table->string('certificate_serial')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};
