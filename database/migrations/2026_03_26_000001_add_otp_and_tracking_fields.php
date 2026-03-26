<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'otp_code')) {
                $table->string('otp_code')->nullable()->after('license_key');
            }
            if (!Schema::hasColumn('users', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            }
            if (!Schema::hasColumn('users', 'otp_verified_at')) {
                $table->timestamp('otp_verified_at')->nullable()->after('otp_expires_at');
            }
        });

        Schema::table('email_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('email_logs', 'opened_at')) {
                $table->timestamp('opened_at')->nullable()->after('opened');
            }
            if (!Schema::hasColumn('email_logs', 'clicked_at')) {
                $table->timestamp('clicked_at')->nullable()->after('clicked');
            }
            if (!Schema::hasColumn('email_logs', 'bounced_at')) {
                $table->timestamp('bounced_at')->nullable()->after('clicked_at');
            }
            if (!Schema::hasColumn('email_logs', 'message_id')) {
                $table->uuid('message_id')->nullable()->unique()->after('provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['otp_code', 'otp_expires_at', 'otp_verified_at']);
        });

        Schema::table('email_logs', function (Blueprint $table): void {
            $table->dropColumn(['opened_at', 'clicked_at', 'bounced_at']);
        });
    }
};
