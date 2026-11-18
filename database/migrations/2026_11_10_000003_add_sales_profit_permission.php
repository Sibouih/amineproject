<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Sales_profit" permission: see the profit of a sale (sale detail > Profit).
 * Given to the administrator roles only: the built-in Owner role (id 1) and the role named "Admin".
 * Other roles can get it from Settings > Permissions.
 */
class AddSalesProfitPermission extends Migration
{
    public function up()
    {
        $permission_id = DB::table('permissions')->where('name', 'Sales_profit')->value('id');
        if (!$permission_id) {
            $permission_id = DB::table('permissions')->insertGetId(['name' => 'Sales_profit']);
        }

        $role_ids = DB::table('roles')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('id', 1)->orWhere('name', 'Admin');
            })
            ->pluck('id');

        foreach ($role_ids as $role_id) {
            $exists = DB::table('permission_role')
                ->where('permission_id', $permission_id)
                ->where('role_id', $role_id)
                ->exists();
            if (!$exists) {
                DB::table('permission_role')->insert(['permission_id' => $permission_id, 'role_id' => $role_id]);
            }
        }
    }

    public function down()
    {
        $permission_id = DB::table('permissions')->where('name', 'Sales_profit')->value('id');
        if ($permission_id) {
            DB::table('permission_role')->where('permission_id', $permission_id)->delete();
            DB::table('permissions')->where('id', $permission_id)->delete();
        }
    }
}
