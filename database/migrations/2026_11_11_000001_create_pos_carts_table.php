<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS cart saved on the server, one per user: products are added all day long and the cart becomes
 * one sale when the cashier clicks "Confirm sales". Survives reloads, closed tabs and another computer.
 */
class CreatePosCartsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('pos_carts')) {
            Schema::create('pos_carts', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->integer('user_id')->unique();
                $table->integer('warehouse_id')->nullable();
                $table->integer('client_id')->nullable();
                $table->longText('lines');           // JSON list of cart lines
                $table->timestamp('started_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('pos_carts');
    }
}
