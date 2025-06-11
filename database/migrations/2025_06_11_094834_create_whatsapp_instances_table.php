<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('container_id')->nullable();
            $table->integer('port')->unique();
            $table->enum('status', ['creating', 'running', 'stopped', 'error'])->default('creating');
            $table->text('webhook_url')->nullable();
            $table->text('config')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instances');
    }
};
