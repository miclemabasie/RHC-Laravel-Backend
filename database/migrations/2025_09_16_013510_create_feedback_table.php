<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('type', ['suggestion', 'complaint', 'compliment', 'question', 'other']);
            $table->string('title');
            $table->text('content');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->text('admin_notes')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->integer('priority')->default(1); // 1-5, with 5 being highest
            $table->boolean('anonymous')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->index('type');
            $table->index('status');
            $table->index('priority');
        });
    }

    public function down()
    {
        Schema::dropIfExists('feedback');
    }
};