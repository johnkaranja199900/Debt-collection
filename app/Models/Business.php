<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Business extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'business_registration_number', 'kra_pin', 'phone',
        'email', 'website', 'physical_address', 'postal_address', 'county', 'town',
        'currency', 'timezone', 'logo', 'invoice_prefix', 'quotation_prefix',
        'default_payment_terms',
    ];

    protected $casts = [
        'default_payment_terms' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Business $business) {
            $business->public_id ??= (string) Str::uuid();
        });
    }

    public function settings()
    {
        return $this->hasMany(BusinessSetting::class);
    }

    /**
     * Single-business installation by design; multi-tenancy can key off this later.
     */
    public static function current(): ?self
    {
        return cache()->remember('business.current', 300, fn () => static::first());
    }

    public function getSetting(string $group, string $key, mixed $default = null): mixed
    {
        $setting = BusinessSetting::query()
            ->where('business_id', $this->id)
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        return $setting?->decodedValue ?? $default;
    }
}
