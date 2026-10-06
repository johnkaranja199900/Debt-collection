<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'public_id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock_quantity' => 'decimal:2',
        'low_stock_threshold' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Product $product) => $product->public_id ??= (string) Str::uuid());
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->type === 'product' && (float) $this->stock_quantity <= (float) $this->low_stock_threshold;
    }
}
