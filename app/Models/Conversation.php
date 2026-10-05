<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Conversation extends Model
{
    protected $guarded = ['id', 'public_id', 'created_at', 'updated_at'];

    protected $casts = ['last_message_at' => 'datetime', 'automation_enabled' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn (Conversation $c) => $c->public_id ??= (string) Str::uuid());
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /** Human handoff: automation must stay silent while true (spec section 29). */
    public function isAwaitingHuman(): bool
    {
        return $this->status === 'human_required' || ! $this->automation_enabled;
    }
}
