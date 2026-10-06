<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public const SALE_OUT = 'sale_out';

    public const SALE_CANCEL_IN = 'sale_cancel_in';

    public const PURCHASE_IN = 'purchase_in';

    public const ADJUSTMENT = 'adjustment';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'direction' => 'integer',
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    /** Signed quantity (+ in / - out) for ledger maths. */
    public function signedQuantity(): float
    {
        return $this->direction * (float) $this->quantity;
    }
}
