<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('pay_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pay_runs');
    }
};