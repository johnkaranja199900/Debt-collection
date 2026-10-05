<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('group', 50); // invoice, quotation, receipt, tax, currency, notification, reminder, security
            $table->string('key', 100);
            $table->text('value')->nullable(); // may hold encrypted payloads
            $table->timestamps();

            $table->unique(['business_id', 'group', 'key']);
            $table->index(['business_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
