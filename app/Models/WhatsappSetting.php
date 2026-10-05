<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSetting extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = [
        'phone_number_id_encrypted', 'business_account_id_encrypted',
        'access_token_encrypted', 'verify_token_encrypted', 'app_secret_encrypted',
    ];

    protected $casts = [
        'phone_number_id_encrypted' => 'encrypted',
        'business_account_id_encrypted' => 'encrypted',
        'access_token_encrypted' => 'encrypted',
        'verify_token_encrypted' => 'encrypted',
        'app_secret_encrypted' => 'encrypted',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public static function mask(?string $secret): string
    {
        if (! $secret) {
            return '(not set)';
        }

        return str_repeat('*', max(8, strlen($secret) - 4)).substr($secret, -4);
    }
}
