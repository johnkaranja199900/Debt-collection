<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid("public_id")->unique();
            $table->string('customer_code', 30)->unique();
            $table->string('name');
            $table->string('business_name')->nullable();
            $table->string('phone', 20)->index();
            $table->string('alternative_phone', 20)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('physical_address')->nullable();
            $table->string('postal_address')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->unsignedSmallInteger('payment_terms')->default(30)->comment('Days');
            $table->string('status', 20)->default('active')->index(); // active, inactive
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid("public_id")->unique();
            $table->string('sku', 60)->unique();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->string('type', 20)->default('product'); // product, service
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('stock_quantity', 15, 2)->default(0);
            $table->decimal('low_stock_threshold', 15, 2)->default(0);
            $table->string('unit', 20)->default('pcs');
            $table->string('status', 20)->default('active')->index(); // active, inactive
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('customers');
    }
};
