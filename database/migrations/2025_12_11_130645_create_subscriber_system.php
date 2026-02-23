<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('list_id')->constrained('mailing_lists')->onDelete('cascade');
            $table->string('email');
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->enum('status', ['active', 'unsubscribed', 'bounced'])->default('active');
            $table->string('source')->default('manual'); // manual, csv_import, api, form
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'list_id', 'email']);
            $table->index(['list_id', 'status']);
            $table->index(['email', 'list_id']);
            
            // Unique constraint: one email per list per user
            $table->unique(['user_id', 'list_id', 'email']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscribers');
    }
};