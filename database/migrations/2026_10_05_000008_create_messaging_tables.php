<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('channel', 20); // sms, whatsapp, email
            $table->string('type', 30); // payment_reminder, invoice_sent, quotation_sent, receipt, follow_up, thank_you
            $table->text('content');
            $table->json('variables')->nullable()->comment('List of allowed {{variable}} placeholders');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('external_conversation_id', 150)->nullable()->unique();
            $table->string('remote_contact', 30)->index()->comment('E.164 phone number of the customer side');
            $table->string('status', 20)->default('open')->index(); // open, human_required, automated, closed
            $table->boolean('automation_enabled')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10); // inbound, outbound
            $table->string('message_type', 20)->default('text'); // text,image,document,audio,location,interactive
            $table->text('message_body');
            $table->string('external_message_id', 150)->nullable()->unique()->comment('Idempotency: provider message id');
            $table->string('status', 20)->default('received')->index(); // received,sent,delivered,read,failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->json('metadata')->nullable()->comment('Sanitised provider metadata only - no credentials');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->index(); // whatsapp, mpesa
            $table->string('event_id', 150)->unique()->comment('Global idempotency key for webhook deliveries');
            $table->string('event_type', 60);
            $table->json('payload')->comment('Sanitised payload (no tokens/secrets)');
            $table->string('status', 20)->default('pending')->index(); // pending, processed, failed, ignored
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('message_templates');
    }
};
