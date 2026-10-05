<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('original_amount', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2);
            $table->date('due_date')->index();
            $table->unsignedInteger('days_overdue')->default(0);
            $table->string('aging_bucket', 20)->default('current')->index(); // current,due_soon,1_7,8_30,31_60,61_90,90_plus
            $table->string('status', 20)->default('current')->index(); // current,due_soon,overdue,partially_paid,paid,disputed,written_off
            $table->string('priority', 10)->default('normal'); // low, normal, high, urgent
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('next_reminder_at')->nullable()->index();
            $table->date('promise_to_pay_date')->nullable();
            $table->decimal('promise_to_pay_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'invoice_id']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('offset_days')->comment('Negative = days before due date, positive = days after due date');
            $table->string('channel', 20)->default('sms'); // sms, whatsapp, email
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('cooldown_days')->default(3)->comment('Minimum days between reminders per debt per channel (spam protection)');
            $table->timestamps();
        });

        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('debt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reminder_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->text('message');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, queued, sent, delivered, failed, skipped_duplicate
            $table->string('provider_message_id', 120)->nullable()->unique()->comment('Idempotency key from provider');
            $table->text('provider_response')->nullable()->comment('Sanitised - never store credentials');
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['debt_id', 'channel', 'created_at'], 'reminder_dedup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
        Schema::dropIfExists('reminder_rules');
        Schema::dropIfExists('debts');
    }
};
