<?php

namespace App\Services;

use App\Jobs\SendMessageJob;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\MessageTemplate;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use Illuminate\Support\Carbon;

/**
 * Deterministic reminder engine with spam protection (master spec 19, 51):
 * - one reminder per debt per rule
 * - cooldown_days between any reminders on the same debt+channel
 */
class ReminderService
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    /** Scan open debts and queue due reminders. Returns number queued. */
    public function processDue(?Carbon $now = null): int
    {
        $now = $now ?? now();
        $rules = ReminderRule::where('is_active', true)->with('template')->get();
        $queued = 0;

        Debt::whereNotIn('status', ['paid', 'written_off', 'disputed'])
            ->where('balance', '>', 0)
            ->with(['customer', 'invoice'])
            ->chunkById(200, function ($debts) use ($rules, $now, &$queued) {
                foreach ($debts as $debt) {
                    foreach ($rules as $rule) {
                        if ($this->shouldSend($debt, $rule, $now)) {
                            $this->queueReminder($debt, $rule, $now);
                            $queued++;
                            break; // one reminder per debt per run
                        }
                    }
                }
            });

        return $queued;
    }

    private function shouldSend(Debt $debt, ReminderRule $rule, Carbon $now): bool
    {
        if (! $debt->customer || ! $debt->invoice) {
            return false;
        }

        $targetDate = Carbon::parse($debt->due_date)->addDays($rule->offset_days)->startOfDay();
        if ($now->copy()->startOfDay()->lessThan($targetDate)) {
            return false;
        }

        // Never re-send the same rule for the same debt.
        $alreadyForRule = ReminderLog::where('debt_id', $debt->id)
            ->where('reminder_rule_id', $rule->id)
            ->whereIn('status', ['pending', 'queued', 'sent', 'delivered'])
            ->exists();
        if ($alreadyForRule) {
            return false;
        }

        // Per-debt, per-channel cooldown (duplicate-message detection).
        $recent = ReminderLog::where('debt_id', $debt->id)
            ->where('channel', $rule->channel)
            ->whereIn('status', ['queued', 'sent', 'delivered'])
            ->where('created_at', '>=', $now->copy()->subDays(max($rule->cooldown_days, 1)))
            ->exists();

        return ! $recent;
    }

    private function queueReminder(Debt $debt, ReminderRule $rule, Carbon $now): void
    {
        $log = ReminderLog::create([
            'customer_id' => $debt->customer_id,
            'invoice_id' => $debt->invoice_id,
            'debt_id' => $debt->id,
            'reminder_rule_id' => $rule->id,
            'channel' => $rule->channel,
            'message_template_id' => $rule->message_template_id,
            'message' => $rule->template?->content ?? '(no template)',
            'scheduled_at' => $now,
            'status' => 'pending',
        ]);

        SendMessageJob::dispatch($log);

        $debt->update([
            'last_reminder_at' => $now,
            'next_reminder_at' => $now->copy()->addDays(max($rule->cooldown_days, 1)),
        ]);
    }

    /** Manual "Send reminder" from the UI - queues immediately (master spec 52). */
    public function sendManual(Debt $debt, string $channel, MessageTemplate $template, Customer $customer): ReminderLog
    {
        $log = ReminderLog::create([
            'customer_id' => $customer->id,
            'invoice_id' => $debt->invoice_id,
            'debt_id' => $debt->id,
            'channel' => $channel,
            'message_template_id' => $template->id,
            'message' => $template->content,
            'scheduled_at' => now(),
            'status' => 'pending',
        ]);

        SendMessageJob::dispatch($log);

        $debt->update(['last_reminder_at' => now()]);

        return $log;
    }
}
