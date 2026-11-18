<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense categories offered for the charges in the POS "Confirm sales" window.
 * NULL = every category, otherwise a JSON list of expense_categories ids.
 */
class AddChargeCategoriesToPosSettings extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('pos_settings', 'charge_categories')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->text('charge_categories')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('pos_settings', 'charge_categories')) {
            Schema::table('pos_settings', function (Blueprint $table) {
                $table->dropColumn('charge_categories');
            });
        }
    }
}
