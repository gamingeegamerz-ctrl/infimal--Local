<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'license_status')) {
                $table->string('license_status')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'license_key')) {
                $table->string('license_key')->nullable()->after('license_status');
            }
            if (!Schema::hasColumn('users', 'license_expires_at')) {
                $table->timestamp('license_expires_at')->nullable()->after('license_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['license_status', 'license_key', 'license_expires_at']);
        });
    }
};
