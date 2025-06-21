<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPriceCostToProductWarehouseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_warehouse', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('qte');
            $table->decimal('cost', 10, 2)->default(0)->after('price');
        });

        // Populate the new fields with values from the products table
        DB::statement('
            UPDATE product_warehouse pw
            INNER JOIN products p ON pw.product_id = p.id
            SET pw.price = p.price, pw.cost = p.cost
            WHERE pw.deleted_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_warehouse', function (Blueprint $table) {
            $table->dropColumn(['price', 'cost']);
        });
    }
}
