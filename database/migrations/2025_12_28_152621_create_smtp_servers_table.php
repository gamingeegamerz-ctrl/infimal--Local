<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('smtp_servers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('host');
            $table->integer('port')->default(587);
            $table->string('username');
            $table->text('password'); // Encrypted
            $table->enum('provider', ['gmail', 'outlook', 'yahoo', 'custom'])->default('custom');
            $table->boolean('is_active')->default(true);
            $table->integer('reputation_score')->default(100); // 0-100
            $table->boolean('auto_disabled')->default(false);
            $table->integer('emails_today')->default(0);
            $table->integer('emails_this_hour')->default(0);
            $table->integer('total_emails_sent')->default(0);
            $table->integer('hourly_limit')->default(50);
            $table->enum('warmup_stage', ['new', 'warming', 'stable', 'paused'])->default('stable');
            $table->integer('rotation_score')->default(0); // 0-100
            $table->integer('soft_bounces_24h')->default(0);
            $table->integer('hard_bounces_24h')->default(0);
            $table->integer('spam_complaints_24h')->default(0);
            $table->integer('auth_errors_24h')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_skipped_reason')->nullable();
            $table->timestamps();
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index('is_active');
            $table->index('provider');
            $table->index('reputation_score');
            $table->index('warmup_stage');
            $table->index('rotation_score');
            $table->index(['is_active', 'rotation_score']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('smtp_servers');
    }
};
