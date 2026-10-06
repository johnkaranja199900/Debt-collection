<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock ledger. Every quantity change to a physical product is recorded here:
 *   sale_out        - sale created (out)
 *   sale_cancel_in  - sale cancelled, stock restored (in)
 *   purchase_in     - restock / purchase received (in)
 *   adjustment      - manual correction signed by an authorised user (in/out)
 * Balance integrity: products.stock_quantity must equal SUM(sign * quantity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // sale_out | purchase_in | adjustment | sale_cancel_in
            $table->integer('direction'); // +1 in, -1 out
            $table->decimal('quantity', 15, 2); // always positive; direction carries the sign
            $table->decimal('unit_cost', 15, 2)->default(0); // cost per unit at movement time
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('reference'); // Sale / Quotation etc.
            $table->string('reason')->nullable(); // for adjustments
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
