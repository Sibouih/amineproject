<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductWarehousePriceHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_warehouse_price_history', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->integer('warehouse_id');
            $table->integer('product_variant_id')->nullable();
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('new_price', 10, 2)->nullable();
            $table->decimal('old_cost', 10, 2)->nullable();
            $table->decimal('new_cost', 10, 2)->nullable();
            $table->string('change_type'); // 'price', 'cost', 'both'
            $table->text('reason')->nullable();
            $table->integer('changed_by'); // user_id
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['product_id', 'warehouse_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_warehouse_price_history');
    }
}
