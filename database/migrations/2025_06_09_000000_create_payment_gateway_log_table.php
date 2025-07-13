<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{


    public function up()
    {
        Schema::create('payment_gateway_log', function (Blueprint $table) {
            $table->id();
            $table->integer('payment_id')->unique();
            $table->string('bank');
            $table->integer('customer_id')->nullable();
            $table->string('amount');
            $table->text('response_pay')->nullable();
            $table->text('response_callback')->nullable();
            $table->text('response_verify')->nullable();
            $table->text('response_settle')->nullable();
            $table->text('callback');
            $table->longText('data')->nullable();
            $table->string('ip');
            $table->text('token')->nullable();
            $table->string('trace_number')->nullable();
            $table->string('ref_num')->nullable();
            $table->string('state')->nullable();
            $table->boolean('status');
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }


    public function down()
    {
        Schema::dropIfExists('payment_gateway_log');
    }


};


