<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $guarded = ['id', 'total', 'tax', 'created_at', 'updated_at'];

    protected $casts = [
        'quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'discount' => 'decimal:2',
        'tax' => 'decimal:2', 'total' => 'decimal:2',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
