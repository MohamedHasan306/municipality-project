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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();


                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->text('fcm_token');
                $table->string('platform', 20);
                $table->string('device_name')->nullable();

                $table->timestamp('last_used_at')->nullable();

                $table->timestamps();

                $table->unique('fcm_token');
                $table->index(['user_id', 'platform']);
            });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
