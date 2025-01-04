<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToProductExchangesTable extends Migration
{
    public function up()
    {
        Schema::table('product_exchanges', function(Blueprint $table)
        {
            $table->foreign('client_id', 'exchange_client_id')->references('id')->on('clients')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('provider_id', 'exchange_provider_id')->references('id')->on('providers')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('created_by', 'exchange_user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }

    public function down()
    {
        Schema::table('product_exchanges', function(Blueprint $table)
        {
            $table->dropForeign('exchange_client_id');
            $table->dropForeign('exchange_provider_id');
            $table->dropForeign('exchange_user_id');
        });
    }
}