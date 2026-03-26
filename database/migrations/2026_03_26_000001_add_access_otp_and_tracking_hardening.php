<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'is_paid')) {
                $table->boolean('is_paid')->default(false)->after('payment_status');
            }
            if (!Schema::hasColumn('users', 'otp_code')) {
                $table->string('otp_code', 6)->nullable()->after('license_status');
            }
            if (!Schema::hasColumn('users', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            }
            if (!Schema::hasColumn('users', 'otp_verified_at')) {
                $table->timestamp('otp_verified_at')->nullable()->after('otp_expires_at');
            }
        });

        Schema::table('email_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('email_logs', 'opened_at')) {
                $table->timestamp('opened_at')->nullable()->after('opened');
            }
            if (!Schema::hasColumn('email_logs', 'clicked_at')) {
                $table->timestamp('clicked_at')->nullable()->after('clicked');
            }
            if (!Schema::hasColumn('email_logs', 'bounced_at')) {
                $table->timestamp('bounced_at')->nullable()->after('clicked_at');
            }
        });
    }

    public function down(): void
    {
        // intentionally non-destructive
    }
};
