<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'public_id', 'customer_code', 'created_at', 'updated_at', 'deleted_at'];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            $customer->public_id ??= (string) Str::uuid();
            if (! $customer->customer_code) {
                // Sequential code with lock-free uniqueness retry handled by unique index.
                $last = static::withTrashed()->max('id');
                $customer->customer_code = 'CUST-'.str_pad((string) ((int) $last + 1), 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function debts()
    {
        return $this->hasMany(Debt::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    /** Outstanding balance across unpaid/partially paid invoices (server-side truth). */
    public function outstandingBalance(): float
    {
        return (float) $this->invoices()
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->sum('balance');
    }
}
