<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('token');
            $table->uuid('invited_by');
            $table->enum('role', ['staff', 'admin', 'hr', 'payroll']);
            $table->timestamp('expires_at');
            $table->enum('status', ['pending', 'accepted', 'revoked'])->default('pending');
            $table->timestamps();

            $table->foreign('invited_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('token');
            $table->index('email');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invitations');
    }
};