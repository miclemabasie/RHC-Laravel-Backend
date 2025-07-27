<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->date('dob')->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('email');
        });
    }

    public function down()
    {
        Schema::dropIfExists('patients');
    }
};