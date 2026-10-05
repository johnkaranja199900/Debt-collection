<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sms_providers: controllers use provider_name + status_callback_url while the
 * original migration used name. Rename for consistency (single source of truth).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sms_providers', 'name')) {
            Schema::table('sms_providers', function (Blueprint $table) {
                $table->renameColumn('name', 'provider_name');
            });
        }
        if (! Schema::hasColumn('sms_providers', 'status_callback_url')) {
            Schema::table('sms_providers', function (Blueprint $table) {
                $table->string('status_callback_url', 500)->nullable()->after('api_url');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sms_providers', function (Blueprint $table) {
            $table->dropColumn('status_callback_url');
            $table->renameColumn('provider_name', 'name');
        });
    }
};
