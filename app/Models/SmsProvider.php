<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsProvider extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** Encrypted-at-rest columns must never serialise anywhere. */
    protected $hidden = ['api_key_encrypted', 'api_secret_encrypted', 'sender_id_encrypted'];

    protected $casts = [
        'api_key_encrypted' => 'encrypted',
        'api_secret_encrypted' => 'encrypted',
        'sender_id_encrypted' => 'encrypted',
        'configuration' => 'array',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    /** Masked display value e.g. ************ABCD - never expose full key (spec 21/46). */
    public static function mask(?string $secret): string
    {
        if (! $secret) {
            return '(not set)';
        }

        return str_repeat('*', max(8, strlen($secret) - 4)).substr($secret, -4);
    }

    public function getMaskedApiKeyAttribute(): string
    {
        return self::mask($this->api_key_encrypted);
    }

    public function getMaskedSenderIdAttribute(): string
    {
        return self::mask($this->sender_id_encrypted);
    }
}
