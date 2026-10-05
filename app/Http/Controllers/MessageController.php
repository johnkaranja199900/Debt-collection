<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Services\AuditService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly WhatsAppService $whatsapp,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(Auth::user()->can('manage_messages'), 403);

        return view('messages.index', [
            'conversations' => Conversation::with('customer')->latest('updated_at')->paginate(15),
            'templates' => MessageTemplate::all(),
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public show(Request $request, Conversation $conversation): View
    {
        abort_unless(Auth::user()->can('manage_messages'), 403);

        return view('messages.show', [
            'conversation' => $conversation->load(['customer', 'messages' => fn ($q) => $q->orderBy('sent_at')]),
        ]);
    }

    public function compose(Request $request, Customer $customer): View
    {
        abort_unless(Auth::user()->can('manage_messages'), 403);

        return view('messages.compose', [
            'customer' => $customer,
            'templates' => MessageTemplate::all(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_messages'), 403);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'channel' => ['required', 'in:sms,whatsapp'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);

        $conversation = Conversation::firstOrCreate(
            ['customer_id' => $customer->id, 'channel' => $data['channel']],
            ['direction' => 'outbound', 'status' => 'open'],
        );

        $result = $data['channel'] === 'whatsapp'
            ? $this->whatsapp->sendText($customer->phone, $data['body'])
            : $this->sms->send($customer->phone, $data['body']);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'channel' => $data['channel'],
            'body' => $data['body'],
            'status' => $result['status'],
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'sent_at' => now(),
        ]);

        $this->audit->log('message_sent', $conversation, [], ['channel' => $data['channel']]);

        return redirect()->route('messages.show', $conversation)
            ->with('success', $result['status'] === 'sent' ? 'Message sent.' : 'Message recorded ('.$result['status'].').');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner() || Auth::user()->isManager(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:payment_reminder,thank_you,follow_up,general'],
            'channel' => ['nullable', 'in:sms,whatsapp,both'],
            'content' => ['required', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $data['channel'] ??= 'both';
        $data['variables'] = $data['variables'] ?? [];
        $data['is_default'] = $request->boolean('is_default');

        $template = MessageTemplate::create($data);
        $this->audit->log('template_created', $template, [], ['name' => $template->name]);

        return back()->with('success', 'Template saved.');
    }

    public function updateTemplate(Request $request, MessageTemplate $template): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner() || Auth::user()->isManager(), 403);

        $old = $template->only(['name', 'content']);
        $template->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:1000'],
        ]));
        $this->audit->log('template_updated', $template, $old, $template->only(['name', 'content']));

        return back()->with('success', 'Template updated.');
    }
}
