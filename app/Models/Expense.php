<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'public_id', 'expense_number', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            $expense->public_id ??= (string) Str::uuid();
            $expense->expense_number ??= 'EXP-'.now()->format('Ym').'-'.str_pad((string) ((int) static::max('id') + 1), 4, '0', STR_PAD_LEFT);
        });
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
