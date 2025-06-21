<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('client_payments', function (Blueprint $table) {
            $table->id();
            $table->string('Ref', 192);
            $table->integer('client_id');
            $table->integer('user_id')->nullable();
            $table->date('date');
            $table->decimal('montant', 16, 2);
            $table->string('Reglement', 192); // Payment method
            $table->string('notes', 255)->nullable();
            $table->string('payment_status')->default('pending'); // pending, processed
            $table->integer('account_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('client_id')->references('id')->on('clients');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client_payments');
    }
}
