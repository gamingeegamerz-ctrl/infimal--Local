<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            // 1. Pehle existing constraints check karo aur safe changes karo
            if (!Schema::hasColumn('subscribers', 'list_id')) {
                $table->unsignedBigInteger('list_id')->nullable()->after('last_name');
            }
            
            if (!Schema::hasColumn('subscribers', 'source')) {
                $table->string('source')->default('manual')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('subscribers', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('source');
            }
            
            if (!Schema::hasColumn('subscribers', 'unsubscribed_at')) {
                $table->timestamp('unsubscribed_at')->nullable()->after('confirmed_at');
            }
            
            if (!Schema::hasColumn('subscribers', 'bounced_at')) {
                $table->timestamp('bounced_at')->nullable()->after('unsubscribed_at');
            }
            
            if (!Schema::hasColumn('subscribers', 'metadata')) {
                $table->json('metadata')->nullable()->after('bounced_at');
            }
            
            // 2. Status column update karo (add bounced option)
            DB::statement("ALTER TABLE subscribers MODIFY COLUMN status ENUM('active','unsubscribed','bounced') DEFAULT 'active'");
            
            // 3. Foreign key add karo agar nahi hai
            $sm = Schema::getConnection()->getSchemaBuilder();
            $foreignKeys = $sm->getConnection()->getDoctrineSchemaManager()->listTableForeignKeys('subscribers');
            
            $hasListIdForeignKey = false;
            foreach ($foreignKeys as $fk) {
                if (in_array('list_id', $fk->getColumns())) {
                    $hasListIdForeignKey = true;
                    break;
                }
            }
            
            if (!$hasListIdForeignKey) {
                $table->foreign('list_id')->references('id')->on('mailing_lists')->onDelete('cascade');
            }
        });
        
        // 4. Unique index add karo (email + list_id per user)
        Schema::table('subscribers', function (Blueprint $table) {
            $table->unique(['user_id', 'list_id', 'email'], 'subscribers_user_list_email_unique');
            $table->index('list_id', 'subscribers_list_id_index');
            $table->index(['status', 'list_id'], 'subscribers_status_list_index');
        });
    }

    public function down()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            // Drop indexes
            $table->dropUnique('subscribers_user_list_email_unique');
            $table->dropIndex('subscribers_list_id_index');
            $table->dropIndex('subscribers_status_list_index');
            
            // Drop foreign key
            $table->dropForeign(['list_id']);
            
            // Drop columns (carefully)
            $table->dropColumn(['list_id', 'source', 'confirmed_at', 'unsubscribed_at', 'bounced_at', 'metadata']);
            
            // Revert status column
            DB::statement("ALTER TABLE subscribers MODIFY COLUMN status ENUM('active','unsubscribed') DEFAULT 'active'");
        });
    }
};
