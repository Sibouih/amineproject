<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'code', 'adresse', 'phone', 'country', 'email', 'city','tax_number','remise', 'credit_initial',
    ];

    protected $casts = [
        'code' => 'integer',
    ];

}
