<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductWarehousePriceHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_warehouse_price_history';

    protected $fillable = [
        'product_id',
        'warehouse_id', 
        'product_variant_id',
        'old_price',
        'new_price',
        'old_cost',
        'new_cost',
        'change_type',
        'reason',
        'changed_by'
    ];

    protected $casts = [
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'product_variant_id' => 'integer',
        'old_price' => 'double',
        'new_price' => 'double',
        'old_cost' => 'double',
        'new_cost' => 'double',
        'changed_by' => 'integer',
    ];

    protected $dates = ['deleted_at'];

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }

    public function warehouse()
    {
        return $this->belongsTo('App\Models\Warehouse');
    }

    public function productVariant()
    {
        return $this->belongsTo('App\Models\ProductVariant');
    }

    public function changedBy()
    {
        return $this->belongsTo('App\Models\User', 'changed_by');
    }
}
