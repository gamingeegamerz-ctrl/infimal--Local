<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_frozen')->default(false);
            $table->string('license_status')->nullable();
            $table->timestamp('license_expires_at')->nullable();
            $table->integer('stage')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_frozen', 'license_status', 'license_expires_at', 'stage']);
        });
    }
};
