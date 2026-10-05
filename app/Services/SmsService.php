<?php

namespace App\Services;

use App\Integrations\Contracts\SmsGateway;
use App\Integrations\Sms\HttpSmsGateway;
use App\Integrations\Sms\NullSmsGateway;
use App\Models\SmsProvider;

class SmsService
{
    public function gateway(): SmsGateway
    {
        $provider = SmsProvider::where('is_active', true)->first();

        if (! $provider || ! $provider->api_url) {
            return new NullSmsGateway();
        }

        return new HttpSmsGateway(
            url: $provider->api_url,
            headers: array_filter([
                'Authorization' => $provider->api_key_encrypted ? 'Bearer '.$provider->api_key_encrypted : null,
                'Content-Type' => 'application/json',
            ]),
            configuration: $provider->configuration ?? [],
        );
    }

    public function send(string $to, string $message): array
    {
        return $this->gateway()->send($to, $message);
    }

    public function testConnection(): array
    {
        return $this->gateway()->testConnection();
    }
}
