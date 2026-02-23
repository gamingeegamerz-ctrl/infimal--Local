<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smtps', function (Blueprint $table) {
            $table->integer('reputation_score')->default(100)->after('id');
            $table->integer('hard_bounces')->default(0)->after('reputation_score');
            $table->integer('soft_bounces')->default(0)->after('hard_bounces');
            $table->integer('spam_reports')->default(0)->after('soft_bounces');
            $table->timestamp('disabled_at')->nullable()->after('spam_reports');
        });
    }

    public function down(): void
    {
        Schema::table('smtps', function (Blueprint $table) {
            $table->dropColumn([
                'reputation_score',
                'hard_bounces',
                'soft_bounces',
                'spam_reports',
                'disabled_at',
            ]);
        });
    }
};
