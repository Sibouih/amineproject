<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'Ref',
        'client_id',
        'user_id',
        'date',
        'montant',
        'Reglement',
        'notes',
        'payment_status',
        'account_id'
    ];

    protected $dates = ['date', 'deleted_at'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function paymentSales()
    {
        return $this->hasMany(PaymentSale::class, 'client_payment_id');
    }
}
