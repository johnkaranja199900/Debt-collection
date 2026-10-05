<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->boolean('two_factor_enabled')->default(false)->after('password');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_secret');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('last_login_ip');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->string('role', 20)->default('staff')->after('locked_until');
            $table->uuid('public_id')->default(fn () => \Illuminate\Support\Str::uuid())->unique()->after('id');

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn([
                'phone', 'phone_verified_at', 'two_factor_enabled', 'two_factor_secret',
                'last_login_at', 'last_login_ip', 'failed_login_attempts', 'locked_until',
                'role', 'public_id',
            ]);
        });
    }
};
