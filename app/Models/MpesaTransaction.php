<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MpesaTransaction extends Model
{
    protected $guarded = ['id', 'public_id', 'raw_callback', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2', 'raw_callback' => 'array', 'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (MpesaTransaction $t) => $t->public_id ??= (string) Str::uuid());
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'transaction_reference', 'mpesa_receipt_number');
    }
}
