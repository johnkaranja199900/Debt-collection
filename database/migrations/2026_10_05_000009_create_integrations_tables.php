<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // All credential columns are encrypted at rest via Eloquent casts (encrypted:array/string).
        Schema::create('sms_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider', 50); // arkesel, africastalking, custom
            $table->string('api_url', 300)->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->text('api_secret_encrypted')->nullable();
            $table->text('sender_id_encrypted')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('configuration')->nullable()->comment('Non-sensitive options only');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->string('last_error', 500)->nullable()->comment('Sanitised error message only');
            $table->timestamps();
        });

        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->text('phone_number_id_encrypted')->nullable();
            $table->text('business_account_id_encrypted')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->text('verify_token_encrypted')->nullable();
            $table->string('app_secret_encrypted')->nullable();
            $table->string('api_version', 10)->default('v21.0');
            $table->string('webhook_status', 20)->default('not_configured'); // not_configured, verified, failed
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('daily_message_limit')->default(500)->comment('Spam protection');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('mpesa_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20)->default('sandbox'); // sandbox, production
            $table->text('consumer_key_encrypted')->nullable();
            $table->text('consumer_secret_encrypted')->nullable();
            $table->text('passkey_encrypted')->nullable();
            $table->string('business_shortcode', 20)->nullable();
            $table->string('till_number', 20)->nullable();
            $table->string('paybill_number', 20)->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('callback_status', 20)->default('not_tested');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('checkout_request_id', 120)->unique()->comment('Idempotency key from STK push');
            $table->string('merchant_request_id', 120);
            $table->decimal('amount', 15, 2);
            $table->string('phone', 20);
            $table->string('result_code', 20)->nullable();
            $table->string('result_desc', 300)->nullable();
            $table->string('mpesa_receipt_number', 50)->nullable()->unique()->comment('Final idempotency key: prevents duplicate payments');
            $table->string('status', 20)->default('pending')->index(); // pending, completed, failed, duplicate_ignored
            $table->json('raw_callback')->nullable()->comment('Sanitised callback body');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
        Schema::dropIfExists('mpesa_settings');
        Schema::dropIfExists('whatsapp_settings');
        Schema::dropIfExists('sms_providers');
    }
};
