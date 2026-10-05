<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_STAFF = 'staff';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret',
        'public_id', 'failed_login_attempts', 'locked_until',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /**
     * Permission matrix. Staff must NOT automatically have integration or
     * financial-settings access (master spec section 45).
     */
    public function hasPermission(string $permission): bool
    {
        return match ($this->role) {
            self::ROLE_OWNER => true,
            self::ROLE_MANAGER => in_array($permission, [
                'view_dashboard', 'manage_customers', 'manage_sales', 'manage_invoices',
                'manage_payments', 'manage_expenses', 'manage_debts', 'send_messages',
                'view_reports', 'manage_quotations', 'view_audit_logs',
            ], true),
            self::ROLE_STAFF => in_array($permission, [
                'view_dashboard', 'manage_customers', 'manage_sales', 'send_messages',
                'manage_quotations',
            ], true),
            default => false,
        };
    }
}
