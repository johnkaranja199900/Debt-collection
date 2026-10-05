<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Debt extends Model
{
    protected $guarded = ['id', 'public_id', 'original_amount', 'amount_paid', 'balance', 'days_overdue', 'aging_bucket', 'created_at', 'updated_at'];

    protected $casts = [
        'due_date' => 'date', 'promise_to_pay_date' => 'date',
        'original_amount' => 'decimal:2', 'amount_paid' => 'decimal:2', 'balance' => 'decimal:2',
        'last_reminder_at' => 'datetime', 'next_reminder_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Debt $debt) => $debt->public_id ??= (string) Str::uuid());
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function reminderLogs()
    {
        return $this->hasMany(ReminderLog::class);
    }

    /** Pull the latest truth from the invoice ledger, then re-age. */
    public function syncFromInvoice(): void
    {
        if ($this->invoice) {
            $this->amount_paid = $this->invoice->amount_paid;
            $this->balance = $this->invoice->balance;
        }

        $this->refreshAging();
        $this->save();
    }

    /**
     * Deterministic aging engine (master spec sections 17-18).
     * Buckets: current, due_soon (<=7 days to due), 1_7, 8_30, 31_60, 61_90, 90_plus.
     */
    public function refreshAging(?Carbon $today = null): void
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $due = $this->due_date?->copy()->startOfDay();

        $this->days_overdue = ($due && $today->greaterThan($due)) ? $due->diffInDays($today) : 0;

        $this->aging_bucket = match (true) {
            (float) $this->balance <= 0 => 'current',
            $this->days_overdue > 90 => '90_plus',
            $this->days_overdue > 60 => '61_90',
            $this->days_overdue > 30 => '31_60',
            $this->days_overdue >= 8 => '8_30',
            $this->days_overdue >= 1 => '1_7',
            $due && $due->lessThanOrEqualTo($today->copy()->addDays(7)) => 'due_soon',
            default => 'current',
        };

        if (! in_array($this->status, ['disputed', 'written_off'], true)) {
            $this->status = match (true) {
                (float) $this->balance <= 0 => 'paid',
                $this->days_overdue > 0 => 'overdue',
                (float) $this->amount_paid > 0 => 'partially_paid',
                $this->aging_bucket === 'due_soon' => 'due_soon',
                default => 'current',
            };
        }

        $this->save();
    }

    public static function agingSummary(): array
    {
        return static::query()
            ->where('balance', '>', 0)
            ->selectRaw('aging_bucket, SUM(balance) as total')
            ->groupBy('aging_bucket')
            ->pluck('total')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }
}
