<?php

namespace App\Integrations\Sms;

use App\Integrations\Contracts\SmsGateway;

/**
 * Safe default when no SMS provider is configured: records intent, sends nothing.
 */
class NullSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): array
    {
        return ['provider_message_id' => null, 'status' => 'skipped_not_configured'];
    }

    public function testConnection(): array
    {
        return ['ok' => false, 'message' => 'No SMS provider configured yet.'];
    }
}
