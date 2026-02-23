<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This table tracks all email events: sent, opened, clicked, bounced, unsubscribed
     */
    public function up(): void
    {
        Schema::create('campaign_analytics', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->onDelete('cascade');
            
            $table->foreignId('subscriber_id')
                ->constrained('subscribers')
                ->onDelete('cascade');
            
            // Event details
            $table->enum('event_type', [
                'sent',
                'delivered',
                'opened',
                'clicked',
                'bounced',
                'unsubscribed',
                'spam_complaint'
            ]);
            
            // Tracking data
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('link_url')->nullable(); // For click tracking
            $table->string('bounce_reason')->nullable(); // For bounces
            $table->timestamp('event_time');
            
            // Metadata
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['campaign_id', 'event_type'], 'idx_campaign_event');
            $table->index(['subscriber_id', 'event_type'], 'idx_subscriber_event');
            $table->index(['campaign_id', 'subscriber_id'], 'idx_campaign_subscriber');
            $table->index('event_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_analytics');
    }
};
