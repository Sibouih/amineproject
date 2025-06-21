<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentSale extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'sale_id', 'date', 'montant', 'Ref','change', 'Reglement', 'user_id', 'notes','account_id', 'type_credit', 'client_id', 'client_payment_id'
    ];

    protected $casts = [
        'montant' => 'double',
        'change'  => 'double',
        'sale_id' => 'integer',
        'user_id' => 'integer',
        'account_id' => 'integer',
        'client_id' => 'integer',
        'client_payment_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function sale()
    {
        return $this->belongsTo('App\Models\Sale');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function clientPayment()
    {
        return $this->belongsTo('App\Models\ClientPayment');
    }
}
