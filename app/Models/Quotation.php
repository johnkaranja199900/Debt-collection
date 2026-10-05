<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'];

    protected $guarded = ['id', 'public_id', 'quotation_number', 'subtotal', 'discount', 'tax', 'total', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'issue_date' => 'date', 'valid_until' => 'date',
        'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Quotation $quotation) {
            $quotation->public_id ??= (string) Str::uuid();
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }
}
