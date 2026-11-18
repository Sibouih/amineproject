<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS settings per user:
 *  - pos_all_clients: 1 = the user can sell to every customer, 0 = only to the customers in user_pos_clients
 *  - pos_default_client_id: customer selected when the POS opens
 *  - pos_default_warehouse_id: warehouse selected when the POS opens
 */
class AddPosSettingsToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pos_all_clients')) {
                $table->tinyInteger('pos_all_clients')->default(1)->after('is_all_warehouses');
            }
            if (!Schema::hasColumn('users', 'pos_default_client_id')) {
                $table->integer('pos_default_client_id')->nullable()->after('pos_all_clients');
            }
            if (!Schema::hasColumn('users', 'pos_default_warehouse_id')) {
                $table->integer('pos_default_warehouse_id')->nullable()->after('pos_default_client_id');
            }
        });

        if (!Schema::hasTable('user_pos_clients')) {
            Schema::create('user_pos_clients', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->integer('user_id');
                $table->integer('client_id');
                $table->primary(['user_id', 'client_id']);
                $table->index('client_id', 'user_pos_clients_client_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('user_pos_clients');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pos_all_clients', 'pos_default_client_id', 'pos_default_warehouse_id']);
        });
    }
}
