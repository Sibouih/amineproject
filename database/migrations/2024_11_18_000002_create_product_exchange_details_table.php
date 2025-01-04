<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductExchangeDetailsTable extends Migration
{
    public function up()
    {
        Schema::create('product_exchange_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('product_exchange_id')->index('product_exchange_id');
            $table->integer('product_id')->index('product_id');
            $table->integer('product_variant_id')->nullable()->index('product_variant_id');
            $table->decimal('quantity', 8, 2);
            $table->decimal('price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->enum('exchange_type', ['in', 'out']);
            $table->string('imei_number')->nullable();
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_exchange_details');
    }
} 