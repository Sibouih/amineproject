<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProviderIdToPaymentPurchases extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_purchases', function (Blueprint $table) {
            // Make purchase_id nullable since payments can be linked to provider directly
            $table->integer('purchase_id')->nullable()->change();
            
            // Add provider_id column
            $table->integer('provider_id')->nullable()->after('purchase_id');
            $table->index('provider_id', 'payment_purchases_provider_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_purchases', function (Blueprint $table) {
            $table->dropIndex('payment_purchases_provider_id');
            $table->dropColumn('provider_id');
        });
    }
}

