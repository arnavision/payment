<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    public function up()
    {
        Schema::table('payment_gateway_log', function (Blueprint $table) {
            $table->string('response_refund')->nullable()->after('response_settle');
        });
    }


    public function down()
    {
        Schema::dropColumns('payment_gateway_log', ['response_refund']);
    }


};


