<?php

namespace App\Integrations\Sms;

use App\Integrations\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Generic JSON SMS adapter driven by the sms_providers table.
 * configuration keys: method (post/get), payload_template {to,message,sender},
 * id_path (dot path to provider message id in the response).
 */
class HttpSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $url,
        private readonly array $headers,
        private readonly array $configuration,
    ) {}

    public function send(string $to, string $message): array
    {
        $payload = $this->configuration['payload_template'] ?? ['to' => ':to', 'message' => ':message'];
        $body = json_decode(str_replace([':to', ':message'], [$to, $message], json_encode($payload)), true);

        try {
            $response = Http::timeout(15)->withHeaders($this->headers)
                ->send($this->configuration['method'] ?? 'post', $this->url, ['json' => $body]);

            $id = ! empty($this->configuration['id_path'])
                ? data_get($response->json(), $this->configuration['id_path'])
                : null;

            return [
                'provider_message_id' => $id ? (string) $id : null,
                'status' => $response->successful() ? 'sent' : 'failed',
            ];
        } catch (Throwable) {
            return ['provider_message_id' => null, 'status' => 'failed'];
        }
    }

    public function testConnection(): array
    {
        try {
            $response = Http::timeout(15)->withHeaders($this->headers)->get($this->url);

            return ['ok' => $response->successful(), 'message' => $response->successful()
                ? 'Provider reachable (HTTP '.$response->status().').'
                : 'Provider returned HTTP '.$response->status().'. Check API URL and credentials.'];
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Could not reach the provider endpoint. Check network/API URL.'];
        }
    }
}
