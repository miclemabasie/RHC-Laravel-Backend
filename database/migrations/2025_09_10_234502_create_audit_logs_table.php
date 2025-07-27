<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_user_id');
            $table->string('action_type');
            $table->string('target_type');
            $table->uuid('target_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['target_type', 'target_id']);
            $table->index('action_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};