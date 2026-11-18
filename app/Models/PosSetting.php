<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSetting extends Model
{

    protected $fillable = [
        'note_customer', 'show_note', 'show_barcode', 'show_discount', 'show_customer',
         'show_email','show_phone','show_address','is_printable','show_Warehouse',
         'charge_categories',
    ];

    // expense categories offered for the POS charges: null = all, otherwise a list of ids
    public function chargeCategoryIds()
    {
        if ($this->charge_categories === null || $this->charge_categories === '') {
            return null;
        }
        $ids = json_decode($this->charge_categories, true);
        return is_array($ids) && count($ids) ? array_map('intval', $ids) : null;
    }

    protected $casts = [
        'show_note' => 'integer',
        'show_barcode' => 'integer',
        'show_discount' => 'integer',
        'show_customer' => 'integer',
        'show_Warehouse' => 'integer',
        'show_email' => 'integer',
        'show_phone' => 'integer',
        'show_address' => 'integer',
        'is_printable' => 'integer',
    ];


}
