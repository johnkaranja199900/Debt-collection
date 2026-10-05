<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled'];

    protected $guarded = ['id', 'public_id', 'invoice_number', 'subtotal', 'discount', 'tax', 'total', 'amount_paid', 'balance', 'status', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'issue_date' => 'date', 'due_date' => 'date',
        'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2',
        'total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'balance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->public_id ??= (string) Str::uuid();
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function debt()
    {
        return $this->hasOne(Debt::class);
    }

    /** Balances are recomputed from the payment ledger - never trusted from the browser. */
    public function recalculateBalance(): void
    {
        $paid = (float) $this->payments()->where('status', 'completed')->sum('amount');
        $this->amount_paid = round($paid, 2);
        $this->balance = round((float) $this->total - $paid, 2);

        if (! in_array($this->status, ['cancelled', 'draft'], true)) {
            if ($this->balance <= 0) {
                $this->status = 'paid';
            } elseif ($paid > 0) {
                $this->status = $this->isPastDue() ? 'overdue' : 'partially_paid';
            } elseif ($this->isPastDue()) {
                $this->status = 'overdue';
            } elseif ($this->status === 'partially_paid') {
                $this->status = 'sent';
            }
        }

        $this->save();
    }

    public function isPastDue(): bool
    {
        return $this->due_date !== null && $this->due_date->lt(now()->startOfDay());
    }
}
