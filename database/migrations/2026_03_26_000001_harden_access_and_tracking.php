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
            if (!Schema::hasColumn('users', 'otp_code')) {
                $table->string('otp_code')->nullable()->after('license_status');
            }
            if (!Schema::hasColumn('users', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            }
            if (!Schema::hasColumn('users', 'otp_verified_at')) {
                $table->timestamp('otp_verified_at')->nullable()->after('otp_expires_at');
            }
        });

        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('payment_id')->unique();
                $table->string('plan')->default('InfiMal Pro');
                $table->decimal('amount', 10, 2);
                $table->string('currency', 8)->default('USD');
                $table->string('status')->index();
                $table->string('payment_method')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }


        Schema::table('smtps', function (Blueprint $table) {
            if (!Schema::hasColumn('smtps', 'last_test_status')) {
                $table->string('last_test_status')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('smtps', 'last_tested_at')) {
                $table->timestamp('last_tested_at')->nullable()->after('last_test_status');
            }
            if (!Schema::hasColumn('smtps', 'last_error_message')) {
                $table->text('last_error_message')->nullable()->after('last_tested_at');
            }
        });

        Schema::table('email_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('email_logs', 'opened')) {
                $table->boolean('opened')->default(false)->after('status');
            }
            if (!Schema::hasColumn('email_logs', 'clicked')) {
                $table->boolean('clicked')->default(false)->after('opened');
            }
            if (!Schema::hasColumn('email_logs', 'opened_at')) {
                $table->timestamp('opened_at')->nullable()->after('clicked');
            }
            if (!Schema::hasColumn('email_logs', 'clicked_at')) {
                $table->timestamp('clicked_at')->nullable()->after('opened_at');
            }
            if (!Schema::hasColumn('email_logs', 'bounced_at')) {
                $table->timestamp('bounced_at')->nullable()->after('clicked_at');
            }
            if (!Schema::hasColumn('email_logs', 'smtp_id')) {
                $table->unsignedBigInteger('smtp_id')->nullable()->after('campaign_id');
            }
            if (!Schema::hasColumn('email_logs', 'recipient_email')) {
                $table->string('recipient_email')->nullable()->after('to_email');
            }
        });
    }

    public function down(): void
    {
        // no destructive rollback for hardening migration
    }
};
