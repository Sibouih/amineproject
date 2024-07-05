<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'code', 'adresse', 'email', 'phone', 'country', 'city','tax_number', 'remise', 'credit_initial',
    ];

    protected $casts = [
        'code' => 'integer',
    ];
}
