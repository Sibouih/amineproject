<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS day cart: time each product was added to the cart and time it was confirmed (locked),
 * kept on the sale line so the day can be tracked after "Confirm sales".
 */
class AddCartTimesToSaleDetails extends Migration
{
    public function up()
    {
        Schema::table('sale_details', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_details', 'added_at')) {
                $table->dateTime('added_at')->nullable();
            }
            if (!Schema::hasColumn('sale_details', 'confirmed_at')) {
                $table->dateTime('confirmed_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn(['added_at', 'confirmed_at']);
        });
    }
}
