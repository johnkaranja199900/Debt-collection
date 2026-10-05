<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('business_registration_number', 100)->nullable();
            $table->string('kra_pin', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('physical_address')->nullable();
            $table->string('postal_address')->nullable();
            $table->string('county', 100)->nullable();
            $table->string('town', 100)->nullable();
            $table->char('currency', 3)->default('KES');
            $table->string('timezone', 64)->default('Africa/Nairobi');
            $table->string('logo')->nullable();
            $table->string('invoice_prefix', 10)->default('INV');
            $table->string('quotation_prefix', 10)->default('QTN');
            $table->unsignedSmallInteger('default_payment_terms')->default(30)->comment('Days');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
