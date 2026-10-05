<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderLog extends Model
{
    protected $guarded = ['id', 'provider_response', 'created_at', 'updated_at'];

    protected $casts = [
        'scheduled_at' => 'datetime', 'sent_at' => 'datetime', 'retry_count' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function debt()
    {
        return $this->belongsTo(Debt::class);
    }

    public function template()
    {
        return $this->belongsTo(\App\Models\MessageTemplate::class, 'message_template_id');
    }
}
