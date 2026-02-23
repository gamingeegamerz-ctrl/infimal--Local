<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            if (!Schema::hasColumn('subscribers', 'source')) {
                $table->string('source')->nullable()->after('status');
            }
            if (!Schema::hasColumn('subscribers', 'subscribed_at')) {
                $table->timestamp('subscribed_at')->nullable()->after('source');
            }
            if (!Schema::hasColumn('subscribers', 'unsubscribed_at')) {
                $table->timestamp('unsubscribed_at')->nullable()->after('subscribed_at');
            }
        });
    }

    public function down()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['source', 'subscribed_at', 'unsubscribed_at']);
        });
    }
};
