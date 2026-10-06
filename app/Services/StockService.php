<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single writer for stock quantities. Every change to products.stock_quantity
 * goes through recordMovement() so the ledger (stock_movements) always mirrors
 * the balance. Money/quantity math is server-side only.
 */
class StockService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Apply a signed stock movement atomically with a row lock to avoid races.
     *
     * @param  int   $direction +1 (in) or -1 (out)
     * @param  float $quantity  positive magnitude
     * @return StockMovement
     */
    public function record(
        Product $product,
        string $type,
        int $direction,
        float $quantity,
        ?float $unitCost = null,
        ?int $userId = null,
        ?string $reason = null,
        mixed $reference = null,
        ?string $notes = null,
    ): StockMovement {
        $quantity = round(abs($quantity), 2);
        if ($quantity <= 0) {
            throw new RuntimeException('Stock quantity must be greater than zero.');
        }
        if (! in_array($direction, [1, -1], true)) {
            throw new RuntimeException('Stock direction must be +1 or -1.');
        }
        if ($product->type !== 'product') {
            throw new RuntimeException("\"{$product->name}\" is a service and has no stock.");
        }

        return DB::transaction(function () use ($product, $type, $direction, $quantity, $unitCost, $userId, $reason, $reference, $notes) {
            // Lock the product row so concurrent sales/restocks can't corrupt the balance.
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $newQty = round((float) $locked->stock_quantity + ($direction * $quantity), 2);
            if ($newQty < 0) {
                throw new RuntimeException(
                    "Insufficient stock for {$locked->name}: available {$locked->stock_quantity}, required {$quantity}."
                );
            }

            $movement = StockMovement::create([
                'product_id' => $locked->id,
                'type' => $type,
                'direction' => $direction,
                'quantity' => $quantity,
                'unit_cost' => round($unitCost ?? (float) $locked->cost_price, 2),
                'user_id' => $userId,
                'reason' => $reason,
                'notes' => $notes,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
            ]);

            $old = (float) $locked->stock_quantity;
            $locked->update(['stock_quantity' => $newQty]);

            // Weighted-average cost update on restock keeps COGS realistic.
            if ($type === StockMovement::PURCHASE_IN && $unitCost !== null && $newQty > 0 && $old > 0) {
                $weighted = ((float) $locked->cost_price * $old + round($unitCost, 2) * $quantity) / $newQty;
                $locked->update(['cost_price' => round($weighted, 2)]);
            }

            $this->audit->log('stock_'.$type, $locked, ['stock_quantity' => $old], [
                'stock_quantity' => $newQty,
                'movement_id' => $movement->id,
                'quantity' => $direction * $quantity,
            ]);

            return $movement;
        });
    }

    /** Restock from a purchase/supplier delivery. */
    public function restock(Product $product, float $quantity, float $unitCost, int $userId, ?string $supplier = null, ?string $referenceNo = null): StockMovement
    {
        return $this->record(
            product: $product,
            type: StockMovement::PURCHASE_IN,
            direction: 1,
            quantity: $quantity,
            unitCost: $unitCost,
            userId: $userId,
            notes: trim(collect(['Supplier: '.$supplier, 'Ref: '.$referenceNo])->filter(fn ($v) => $v !== 'Ref: ' && $v !== 'Supplier: ')->join(' | ')) ?: null,
        );
    }

    /** Manual correction (count shrinkage, damage, found stock...). Requires a reason. */
    public function adjust(Product $product, int $direction, float $quantity, string $reason, int $userId): StockMovement
    {
        return $this->record(
            product: $product,
            type: StockMovement::ADJUSTMENT,
            direction: $direction,
            quantity: $quantity,
            userId: $userId,
            reason: $reason,
        );
    }

    /** Ledger total check used by reports/integrity commands. */
    public function ledgerBalance(Product $product): float
    {
        $sum = StockMovement::where('product_id', $product->id)
            ->selectRaw('COALESCE(SUM(direction * quantity), 0) as bal')
            ->value('bal');

        return round((float) $sum, 2);
    }
}
