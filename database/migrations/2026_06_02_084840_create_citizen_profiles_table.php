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
        Schema::create('citizen_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained('municipalities')->cascadeOnDelete();
            $table->enum('gender', ['Male', 'Female'])->default('Male');
            $table->date('birth_date');
            $table->string('national_id')->unique();
            $table->string('front_id_photo')->nullable();
            $table->string('back_id_photo')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->boolean('needs_special_care')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citizen_profiles');
    }
};
