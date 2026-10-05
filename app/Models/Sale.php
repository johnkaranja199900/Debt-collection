<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'public_id', 'sale_number', 'subtotal', 'discount', 'tax', 'total', 'amount_paid', 'balance', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'sale_date' => 'date',
        'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2',
        'total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'balance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            $sale->public_id ??= (string) Str::uuid();
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
