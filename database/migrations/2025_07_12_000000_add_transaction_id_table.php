<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    public function up()
    {

        Schema::table('payment_gateway_log', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->after('token');
        });

    }


    public function down()
    {
        Schema::dropColumns('payment_gateway_log', ['transaction_id']);
    }


};


