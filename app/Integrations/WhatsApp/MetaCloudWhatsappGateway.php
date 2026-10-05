<?php

namespace App\Integrations\WhatsApp;

use App\Integrations\Contracts\WhatsappGateway;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Official Meta WhatsApp Cloud API adapter
 * (graph.facebook.com/{version}/{phone_number_id}/messages).
 * Credentials come only from encrypted WhatsappSetting fields - never the browser.
 */
class MetaCloudWhatsappGateway implements WhatsappGateway
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $phoneNumberId,
        private readonly string $apiVersion = 'v21.0',
    ) {}

    public function sendText(string $to, string $message): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => ltrim($to, '+'),
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $message],
        ]);
    }

    public function sendDocument(string $to, string $fileUrl, string $filename, string $caption = ''): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => ltrim($to, '+'),
            'type' => 'document',
            'document' => array_filter(['link' => $fileUrl, 'filename' => $filename, 'caption' => $caption]),
        ]);
    }

    private function post(array $payload): array
    {
        try {
            $response = Http::timeout(20)->withToken($this->accessToken)
                ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload);

            $json = $response->json();

            return [
                'provider_message_id' => data_get($json, 'messages.0.id'),
                'status' => $response->successful() ? 'sent' : 'failed',
                'error' => $response->successful() ? null : data_get($json, 'error.message', 'WhatsApp API error'),
            ];
        } catch (Throwable) {
            return ['provider_message_id' => null, 'status' => 'failed', 'error' => 'Network error contacting WhatsApp API'];
        }
    }

    public function testConnection(): array
    {
        try {
            $response = Http::timeout(15)->withToken($this->accessToken)
                ->get("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}", ['fields' => 'id']);

            return ['ok' => $response->successful(), 'message' => $response->successful()
                ? 'Phone Number ID verified against Meta Graph API.'
                : 'Authentication failed. Check access token and phone number ID (Meta error: '.data_get($response->json(), 'error.message', 'unknown').').'];
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Could not reach graph.facebook.com.'];
        }
    }
}
