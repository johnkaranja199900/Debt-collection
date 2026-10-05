<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('unit', 20)->default('pcs')->after('quantity');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('unit_price');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->string('unit', 20)->default('pcs')->after('quantity');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', fn (Blueprint $t) => $t->dropColumn(['unit', 'tax_rate']));
        Schema::table('quotation_items', fn (Blueprint $t) => $t->dropColumn(['unit', 'tax_rate']));
    }
};
