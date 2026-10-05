<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->string('expense_number', 40)->unique();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('expense_date')->index();
            $table->string('payment_method', 20)->default('cash');
            $table->string('supplier')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('receipt_path')->nullable()->comment('Stored on a private disk, never public');
            $table->string('status', 20)->default('recorded')->index(); // recorded, voided
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->string('quotation_number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('issue_date');
            $table->date('valid_until');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('draft')->index(); // draft,sent,accepted,rejected,expired,converted
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
