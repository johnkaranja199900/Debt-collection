<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciles small gaps between controllers/models and the original migrations:
 * - users.is_active (used by EnsureUserIsActive middleware)
 * - message_templates.status -> is_default rename + trigger_type expansion
 * - reminder_rules.trigger_type column (validated in ReminderController)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }
        });

        Schema::table('message_templates', function (Blueprint $table) {
            if (Schema::hasColumn('message_templates', 'status')) {
                $table->renameColumn('status', 'is_default');
            }
            Schema::table('message_templates', function (Blueprint $t) {
                if (! Schema::hasColumn('message_templates', 'is_default')) {
                    $t->boolean('is_default')->default(false);
                }
            });
            // channel now allows 'both'
        });

        Schema::table('reminder_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('reminder_rules', 'trigger_type')) {
                $table->string('trigger_type', 30)->default('custom')->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        Schema::table('message_templates', function (Blueprint $table) {
            $table->renameColumn('is_default', 'status');
        });
        Schema::table('reminder_rules', function (Blueprint $table) {
            $table->dropColumn('trigger_type');
        });
    }
};
