<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pos_only = 1: the user is always sent to the POS and can use nothing else
 * (every other page redirects to /app/pos, other API calls are refused).
 */
class AddPosOnlyToUsers extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('users', 'pos_only')) {
            Schema::table('users', function (Blueprint $table) {
                $table->tinyInteger('pos_only')->default(0)->after('pos_default_warehouse_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'pos_only')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pos_only');
            });
        }
    }
}
