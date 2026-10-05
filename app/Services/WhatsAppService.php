<?php

namespace App\Services;

use App\Integrations\Contracts\WhatsappGateway;
use App\Integrations\WhatsApp\MetaCloudWhatsappGateway;
use App\Integrations\WhatsApp\NullWhatsappGateway;
use App\Models\WhatsappSetting;

class WhatsAppService
{
    public function gateway(): WhatsappGateway
    {
        $settings = WhatsappSetting::where('is_active', true)->first();

        if (! $settings || ! $settings->access_token_encrypted || ! $settings->phone_number_id_encrypted) {
            return new NullWhatsappGateway();
        }

        return new MetaCloudWhatsappGateway(
            accessToken: $settings->access_token_encrypted,
            phoneNumberId: $settings->phone_number_id_encrypted,
            apiVersion: $settings->api_version ?: 'v21.0',
        );
    }

    public function isConfigured(): bool
    {
        return ! ($this->gateway() instanceof NullWhatsappGateway);
    }

    public function sendText(string $to, string $message): array
    {
        return $this->gateway()->sendText($to, $message);
    }

    public function sendDocument(string $to, string $fileUrl, string $filename, string $caption = ''): array
    {
        return $this->gateway()->sendDocument($to, $fileUrl, $filename, $caption);
    }

    public function testConnection(): array
    {
        return $this->gateway()->testConnection();
    }
}
