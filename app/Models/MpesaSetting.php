<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaSetting extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['consumer_key_encrypted', 'consumer_secret_encrypted', 'passkey_encrypted'];

    protected $casts = [
        'consumer_key_encrypted' => 'encrypted',
        'consumer_secret_encrypted' => 'encrypted',
        'passkey_encrypted' => 'encrypted',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /** Official Safaricom Daraja endpoints (sandbox vs production differ). */
    public function baseUrl(): string
    {
        return $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    public static function mask(?string $secret): string
    {
        if (! $secret) {
            return '(not set)';
        }

        return str_repeat('*', max(8, strlen($secret) - 4)).substr($secret, -4);
    }
}
