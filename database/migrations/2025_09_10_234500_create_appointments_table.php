<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->string('unit_service');
            $table->timestamp('datetime');
            $table->enum('type', ['in_person', 'online', 'follow_up']);
            $table->text('notes')->nullable();
            $table->string('confirmation_code')->unique();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->index('confirmation_code');
            $table->index('datetime');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('appointments');
    }
};