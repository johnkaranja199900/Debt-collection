<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    public const METHODS = ['cash', 'mpesa', 'bank', 'card', 'other'];

    protected $guarded = ['id', 'public_id', 'payment_reference', 'created_at', 'updated_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->public_id ??= (string) Str::uuid();
            $payment->payment_reference ??= 'PAY-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
