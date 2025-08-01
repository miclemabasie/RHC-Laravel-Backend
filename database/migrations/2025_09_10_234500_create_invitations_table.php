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

            $table->string('first_name');
            $table->string('last_name');
            $table->string('department_unit');
            $table->string('job_title');
            // add start date
            $table->date('start_date');


            $table->foreign('invited_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('token');
            $table->index('email');
            // add firstname, lastname, department unit, job title
            $table->index('first_name');
            $table->index('last_name');
            $table->index('department_unit');
            $table->index('job_title');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invitations');
    }
};