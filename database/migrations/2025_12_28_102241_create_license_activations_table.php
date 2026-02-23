<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('license_activations')) {
            Schema::create('license_activations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('license_id')->constrained('licenses')->onDelete('cascade');
                $table->string('device_id')->nullable();
                $table->string('device_name')->nullable();
                $table->string('device_type')->nullable();
                $table->string('device_fingerprint')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->string('user_agent')->nullable();
                $table->string('browser')->nullable();
                $table->string('os')->nullable();
                $table->string('platform')->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->boolean('is_current')->default(true);
                $table->integer('activation_count')->default(1);
                $table->json('metadata')->nullable();
                $table->timestamps();
                
                $table->index(['license_id', 'device_id']);
                $table->index(['license_id', 'is_current']);
                $table->index('device_id');
                $table->index('ip_address');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};
