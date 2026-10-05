<?php

namespace App\Integrations\Contracts;

interface WhatsappGateway
{
    public function sendText(string $to, string $message): array;

    public function sendDocument(string $to, string $fileUrl, string $filename, string $caption = ''): array;

    public function testConnection(): array;
}
