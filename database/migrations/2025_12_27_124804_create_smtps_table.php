<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtps', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            // SMTP credentials
            $table->string('smtp_host');
            $table->integer('smtp_port');
            $table->string('smtp_username');
            $table->string('smtp_password');

            // Reputation system
            $table->integer('reputation_score')->default(100);
            $table->boolean('is_active')->default(true);

            // Stats & rotation helpers
            $table->integer('sent_today')->default(0);
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('is_active');
            $table->index('reputation_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtps');
    }
};
