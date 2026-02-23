<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('list_subscriber')) {
            Schema::table('list_subscriber', function (Blueprint $table) {
                if (!Schema::hasColumn('list_subscriber', 'status')) {
                    $table->enum('status', ['active', 'unsubscribed', 'bounced'])
                          ->default('active')
                          ->after('subscriber_id');
                }
                
                if (!Schema::hasColumn('list_subscriber', 'subscribed_at')) {
                    $table->timestamp('subscribed_at')
                          ->useCurrent()
                          ->after('status');
                }
                
                if (!Schema::hasColumn('list_subscriber', 'unsubscribed_at')) {
                    $table->timestamp('unsubscribed_at')
                          ->nullable()
                          ->after('subscribed_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('list_subscriber')) {
            Schema::table('list_subscriber', function (Blueprint $table) {
                $columns = ['status', 'subscribed_at', 'unsubscribed_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('list_subscriber', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
