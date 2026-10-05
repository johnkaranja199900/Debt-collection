<?php

namespace App\Integrations\WhatsApp;

use App\Integrations\Contracts\WhatsappGateway;

class NullWhatsappGateway implements WhatsappGateway
{
    public function sendText(string $to, string $message): array
    {
        return ['provider_message_id' => null, 'status' => 'skipped_not_configured'];
    }

    public function sendDocument(string $to, string $fileUrl, string $filename, string $caption = ''): array
    {
        return ['provider_message_id' => null, 'status' => 'skipped_not_configured'];
    }

    public function testConnection(): array
    {
        return ['ok' => false, 'message' => 'WhatsApp is not configured yet.'];
    }
}
