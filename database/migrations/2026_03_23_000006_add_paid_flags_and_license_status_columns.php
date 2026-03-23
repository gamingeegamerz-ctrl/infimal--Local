<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_paid')) {
                $table->boolean('is_paid')->default(false)->after('payment_status');
            }
        });

        Schema::table('licenses', function (Blueprint $table) {
            if (!Schema::hasColumn('licenses', 'status')) {
                $table->string('status')->default('active')->after('user_id');
            }
        });
    }

    public function down(): void
    {
        // non-destructive
    }
};
