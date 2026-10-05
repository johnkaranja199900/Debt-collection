<?php

namespace App\Integrations\Contracts;

interface SmsGateway
{
    /** @return array{provider_message_id:?string, status:string} */
    public function send(string $to, string $message): array;

    public function testConnection(): array;
}
