<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->string('sale_number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('sale_date')->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('status', 20)->default('completed')->index(); // completed, cancelled
            $table->string('payment_status', 20)->default('unpaid')->index(); // unpaid, partially_paid, paid
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'sale_date']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0)->comment('Snapshot of product cost at time of sale for COGS');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->date('issue_date')->index();
            $table->date('due_date')->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('status', 20)->default('draft')->index(); // draft,sent,partially_paid,paid,overdue,cancelled
            $table->char('currency', 3)->default('KES');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->string('payment_reference', 60)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 20); // cash, mpesa, bank, card, other
            $table->string('transaction_reference', 100)->nullable()->unique()->comment('M-Pesa global unique id etc. Idempotency key.');
            $table->date('payment_date')->index();
            $table->string('status', 20)->default('completed')->index(); // pending, completed, failed, reversed
            $table->string('source', 20)->default('manual'); // manual, mpesa_callback, stk_push
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
