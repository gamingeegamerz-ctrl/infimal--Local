<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        if (Schema::hasColumn('users', 'payment_status') && Schema::hasColumn('users', 'is_paid')) {
            DB::table('users')->where('payment_status', 'paid')->update(['is_paid' => true]);
        }
    }

    public function down(): void
    {
        // non-destructive
    }
};
