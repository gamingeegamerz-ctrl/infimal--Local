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
        Schema::table('smtps', function (Blueprint $table) {
            // Warmup / hourly control
            $table->integer('hourly_sent')->default(0)->after('sent_today');
            $table->timestamp('last_hour_at')->nullable()->after('hourly_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtps', function (Blueprint $table) {
            $table->dropColumn([
                'hourly_sent',
                'last_hour_at',
            ]);
        });
    }
};
