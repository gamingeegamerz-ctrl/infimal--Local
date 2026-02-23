<?php
// database/migrations/xxxx_create_licenses_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('plan_type')->default('Premium');
            $table->decimal('price', 10, 2)->default(299.00);
            $table->integer('duration_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_lifetime')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->json('features')->nullable();
            $table->timestamps();
            $table->index(['license_key', 'is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};