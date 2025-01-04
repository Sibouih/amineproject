<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    protected $fillable = [
        'date',
        'Ref',
        'customer_id',
        'supplier_id', 
        'warehouse_id',
        'tax_rate',
        'TaxNet',
        'discount',
        'shipping',
        'GrandTotal',
        'paid_amount',
        'payment_status',
        'status',
        'notes',
        'user_id'
    ];

    public function details()
    {
        return $this->hasMany(ExchangeDetail::class);
    }

    public function customer()
    {
        return $this->belongsTo(Client::class, 'customer_id');
    }

    public function supplier() 
    {
        return $this->belongsTo(Provider::class, 'supplier_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

class ExchangeDetail extends Model {
    protected $fillable = [
        'exchange_id',
        'product_id',
        'product_variant_id',
        'exchange_unit_id',
        'quantity',
        'price', 
        'TaxNet',
        'tax_method',
        'discount',
        'discount_method',
        'total',
        'direction', // 'in' for purchase, 'out' for sale
        'imei_number'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function exchange()
    {
        return $this->belongsTo(Exchange::class);
    }
}