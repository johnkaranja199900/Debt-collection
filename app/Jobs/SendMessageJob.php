<?php

namespace App\Jobs;

use App\Models\ReminderLog;
use App\Services\SmsService;
use App\Services\TemplateRenderer;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue a reminder/message delivery (master spec 52). Exponential backoff via
 * tries/backoff; failures are recorded on the ReminderLog, never crash the app.
 */
class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** 5 min, 30 min, 2 h (master spec 54). */
    public array $backoff = [300, 1800, 7200];

    public function __construct(public ReminderLog $log) {}

    public function handle(SmsService $sms, WhatsAppService $whatsapp, TemplateRenderer $renderer): void
    {
        $debt = $this->log->debt()->first();
        $customer = $this->log->customer()->first();

        if (! $debt || ! $customer) {
            $this->fail('Missing debt or customer data.');

            return;
        }

        // Re-render at send time so amounts are always current truth.
        try {
            $message = $debt->invoice && $this->log->messageTemplate
                ? $renderer->forDebt($this->log->messageTemplate, $debt)
                : $this->log->message;
        } catch (Throwable $e) {
            $this->fail('Template render error: '.$e->getMessage());

            return;
        }

        $result = match ($this->log->channel) {
            'whatsapp' => $whatsapp->sendText($customer->phone, $message),
            default => $sms->send($customer->phone, $message),
        };

        $this->log->update([
            'message' => $message,
            'sent_at' => now(),
            'status' => $result['status'],
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'failure_reason' => $result['error'] ?? null,
        ]);

        if ($result['status'] === 'failed') {
            throw new RuntimeException('Provider send failed: '.($result['error'] ?? 'unknown'));
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->log->update([
            'status' => 'failed',
            'failure_reason' => substr($exception->getMessage(), 0, 500),
            'retry_count' => $this->attempts(),
        ]);

        Log::warning('Reminder delivery permanently failed', ['reminder_log_id' => $this->log->id]);
    }

    private function fail(string $reason): void
    {
        $this->log->update(['status' => 'failed', 'failure_reason' => $reason]);
    }
}
