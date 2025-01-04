<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductExchangesTable extends Migration
{
    public function up()
    {        
        Schema::create('product_exchanges', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->date('date');
            $table->integer('client_id')->index('exchange_client_id');
            $table->integer('provider_id')->index('exchange_provider_id');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->decimal('grand_total', 10, 2);
            $table->integer('created_by')->index('created_by');
            $table->softDeletes();
            $table->timestamps(6);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_exchanges');
    }
} 