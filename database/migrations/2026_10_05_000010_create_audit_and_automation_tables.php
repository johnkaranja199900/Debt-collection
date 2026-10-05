<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index(); // login, logout, failed_login, password_change, invoice_cancel ...
            $table->string('model_type', 120)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable()->comment('Secrets stripped before persisting');
            $table->json('new_values')->nullable()->comment('Secrets stripped before persisting');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['model_type', 'model_id']);
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotable_id')->nullable(); // quotation or sale reference (polymorphic-ish id kept simple)
            $table->string('type', 30); // quotation_follow_up, abandoned_inquiry, overdue_invoice, inactive_customer, post_purchase_feedback
            $table->string('channel', 20)->default('whatsapp');
            $table->foreignId('message_template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->unsignedTinyInteger('step')->default(1)->comment('Stage in the follow-up sequence (max 3 - never spam)');
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, sent, completed, cancelled, stopped
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->default(fn () => \Illuminate\Support\Str::uuid());
            $table->string('documentable_type', 120);
            $table->unsignedBigInteger('documentable_id');
            $table->string('type', 30); // invoice_pdf, quotation_pdf, receipt_pdf, statement_pdf
            $table->string('path')->comment('Private disk path - downloads always authorised');
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id']);
        });

        // Laravel notifications table
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('audit_logs');
    }
};
