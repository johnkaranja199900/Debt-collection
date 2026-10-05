<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\MessageTemplate;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Services\AuditService;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ReminderService $reminders,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(Auth::user()->can('manage_reminders'), 403);

        return view('reminders.index', [
            'rules' => ReminderRule::with('template')->orderBy('offset_days')->get(),
            'logs' => ReminderLog::with(['customer', 'template'])->latest()->paginate(20),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner() || Auth::user()->isManager(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trigger_type' => ['required', 'in:before_due,on_due,after_due_7,after_due_30,after_due_60,after_due_90,custom'],
            'offset_days' => ['required', 'integer', 'min:-365', 'max:365'],
            'channel' => ['required', 'in:sms,whatsapp'],
            'message_template_id' => ['required', 'exists:message_templates,id'],
            'cooldown_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $rule = ReminderRule::create($data);
        $this->audit->log('reminder_rule_created', $rule, [], $data);

        return back()->with('success', 'Reminder rule created.');
    }

    public function toggleRule(Request $request, ReminderRule $rule): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner() || Auth::user()->isManager(), 403);

        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', 'Rule '.($rule->is_active ? 'enabled' : 'disabled').'.');
    }

    /** Manual send from the UI - queued, spam-guarded at job level. */
    public function sendNow(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_reminders'), 403);

        $data = $request->validate([
            'channel' => ['required', 'in:sms,whatsapp'],
            'message_template_id' => ['required', 'exists:message_templates,id'],
        ]);

        $template = MessageTemplate::findOrFail($data['message_template_id']);
        $debt->loadMissing(['customer', 'invoice']);

        if (! $debt->customer || ! $debt->invoice) {
            return back()->with('error', 'This debt is missing customer or invoice data.');
        }

        // Spam protection: same content to same number within cooldown window.
        $duplicate = ReminderLog::where('customer_id', $debt->customer_id)
            ->where('message_template_id', $template->id)
            ->whereIn('status', ['queued', 'sent', 'delivered'])
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($duplicate) {
            return back()->with('error', 'Duplicate reminder blocked: this template was already sent to this customer within 24h.');
        }

        $this->reminders->sendManual($debt, $data['channel'], $template, $debt->customer);
        $this->audit->log('reminder_sent_manually', $debt, [], ['channel' => $data['channel']]);

        return back()->with('success', 'Reminder queued for delivery.');
    }
}
