<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('smtp_action_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('smtp_id');
            $table->string('action');
            $table->text('reason')->nullable();
            $table->enum('triggered_by', ['system', 'admin'])->default('system');
            $table->json('details')->nullable();
            $table->timestamps();
            
            // Foreign key
            $table->foreign('smtp_id')->references('id')->on('smtp_servers')->onDelete('cascade');
            
            // Indexes
            $table->index('smtp_id');
            $table->index('action');
            $table->index('triggered_by');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('smtp_action_logs');
    }
};
