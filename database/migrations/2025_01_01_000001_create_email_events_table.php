<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email');
            $table->string('event_type'); // delivered, bounce, hard_bounce, spam
            $table->string('smtp')->nullable();
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_events');
    }
};
