<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pay_run_id');
            $table->uuid('user_id');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->string('document_key')->nullable();
            $table->timestamps();

            $table->foreign('pay_run_id')->references('id')->on('pay_runs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payslips');
    }
};