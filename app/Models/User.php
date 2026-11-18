<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname', 'lastname', 'username', 'email', 'password', 'phone', 'statut', 'avatar', 'role_id','is_all_warehouses',
        'pos_all_clients', 'pos_default_client_id', 'pos_default_warehouse_id', 'pos_only',
        'pos_edit_price', 'pos_edit_confirmed',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'role_id' => 'integer',
        'statut' => 'integer',
        'is_all_warehouses' => 'integer',
        'pos_all_clients' => 'integer',
        'pos_default_client_id' => 'integer',
        'pos_default_warehouse_id' => 'integer',
        'pos_only' => 'integer',
        'pos_edit_price' => 'integer',
        'pos_edit_confirmed' => 'integer',
    ];

    public function oauthAccessToken()
    {
        return $this->hasMany('\App\Models\OauthAccessToken');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function assignRole(Role $role)
    {
        return $this->roles()->save($role);
    }

    public function hasRole($role)
    {
        if (is_string($role)) {
            return $this->roles->contains('name', $role);
        }
        return !!$role->intersect($this->roles)->count();
    }

    public function assignedWarehouses()
    {
        return $this->belongsToMany('App\Models\Warehouse');
    }

    // Customers this user may sell to in the POS (used only when pos_all_clients = 0)
    public function posClients()
    {
        return $this->belongsToMany('App\Models\Client', 'user_pos_clients', 'user_id', 'client_id');
    }

}
