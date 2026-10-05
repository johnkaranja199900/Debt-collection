<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    /** Groups whose values are encrypted at rest. */
    private const ENCRYPTED_GROUPS = ['security'];

    protected $fillable = ['business_id', 'group', 'key', 'value'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function getValueAttribute(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array($this->group, self::ENCRYPTED_GROUPS, true)) {
            try {
                $value = decrypt($value);
            } catch (\Throwable) {
                return null; // never leak or crash on corrupt payload
            }
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function setValueAttribute(mixed $value): void
    {
        $payload = is_scalar($value) ? (string) $value : json_encode($value);

        if (in_array($this->group, self::ENCRYPTED_GROUPS, true)) {
            $payload = encrypt($payload);
        }

        $this->attributes['value'] = $payload;
    }

    public function getDecodedValueAttribute(): mixed
    {
        return $this->value;
    }
}
