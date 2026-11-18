<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS cart permissions per user (People > Users > Point of sale):
 *  - pos_edit_price: may change the price of a product in the cart
 *  - pos_edit_confirmed: may still change or remove a confirmed (locked) product
 * Administrators (Owner role id 1, role "Admin") keep changing prices as before; nobody edits confirmed products by default.
 */
class AddPosEditPermissionsToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pos_edit_price')) {
                $table->tinyInteger('pos_edit_price')->default(0);
            }
            if (!Schema::hasColumn('users', 'pos_edit_confirmed')) {
                $table->tinyInteger('pos_edit_confirmed')->default(0);
            }
        });

        $admin_roles = DB::table('roles')->where('id', 1)->orWhere('name', 'Admin')->pluck('id')->toArray();
        DB::table('users')->whereIn('role_id', $admin_roles)->update(['pos_edit_price' => 1]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pos_edit_price', 'pos_edit_confirmed']);
        });
    }
}
