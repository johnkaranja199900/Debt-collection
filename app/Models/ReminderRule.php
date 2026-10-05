<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderRule extends Model
{
    protected $fillable = ['name', 'offset_days', 'channel', 'message_template_id', 'is_active', 'cooldown_days'];

    protected $casts = ['is_active' => 'boolean', 'offset_days' => 'integer', 'cooldown_days' => 'integer'];

    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }
}
