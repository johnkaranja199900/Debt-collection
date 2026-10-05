<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Models\SmsProvider;
use App\Models\WhatsappSetting;
use App\Services\AuditService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Integration configuration (owner only). Secrets are write-only: stored via the
 * encrypted casts, never echoed back - the UI shows masked values only.
 */
class IntegrationController extends Controller
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly WhatsAppService $whatsapp,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(Auth::user()->isOwner(), 403);

        return view('settings.integrations', [
            'smsProvider' => SmsProvider::first() ?? new SmsProvider(),
            'whatsapp' => WhatsappSetting::first() ?? new WhatsappSetting(),
            'templates' => MessageTemplate::all(),
            'smsConfigured' => SmsProvider::where('is_active', true)->whereNotNull('api_url')->exists(),
            'waConfigured' => $this->whatsapp->isConfigured(),
        ]);
    }

    public function saveSms(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner(), 403);

        $data = $request->validate([
            'provider_name' => ['required', 'string', 'max:120'],
            'sender_id' => ['nullable', 'string', 'max:60'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_secret' => ['nullable', 'string', 'max:500'],
            'api_url' => ['nullable', 'url', 'max:500'],
            'status_callback_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $provider = SmsProvider::first() ?? new SmsProvider();
        $provider->fill([
            'provider_name' => $data['provider_name'],
            'api_url' => $data['api_url'] ?? null,
            'status_callback_url' => $data['status_callback_url'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'configuration' => $provider->configuration ?? [],
        ]);
        // Only overwrite secrets when new values are provided (write-only fields).
        if (! empty($data['api_key'])) {
            $provider->api_key_encrypted = $data['api_key'];
        }
        if (! empty($data['api_secret'])) {
            $provider->api_secret_encrypted = $data['api_secret'];
        }
        if (! empty($data['sender_id'])) {
            $provider->sender_id_encrypted = $data['sender_id'];
        }
        $provider->save();

        $this->audit->log('sms_provider_updated', $provider, [], ['provider_name' => $provider->provider_name]);

        return back()->with('success', 'SMS provider saved. Secrets stored encrypted.');
    }

    public function testSms(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner(), 403);

        $result = $this->sms->testConnection();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function saveWhatsapp(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner(), 403);

        $data = $request->validate([
            'phone_number_id' => ['nullable', 'string', 'max:60'],
            'business_account_id' => ['nullable', 'string', 'max:60'],
            'access_token' => ['nullable', 'string', 'max:1000'],
            'verify_token' => ['nullable', 'string', 'max:255'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'api_version' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $settings = WhatsappSetting::first() ?? new WhatsappSetting();
        $settings->fill(['api_version' => $data['api_version'] ?: 'v21.0', 'is_active' => $request->boolean('is_active')]);
        foreach (['phone_number_id' => 'phone_number_id_encrypted', 'business_account_id' => 'business_account_id_encrypted',
                  'access_token' => 'access_token_encrypted', 'verify_token' => 'verify_token_encrypted',
                  'app_secret' => 'app_secret_encrypted'] as $field => $column) {
            if (! empty($data[$field])) {
                $settings->{$column} = $data[$field];
            }
        }
        $settings->save();

        $this->audit->log('whatsapp_settings_updated', $settings);

        return back()->with('success', 'WhatsApp settings saved. Tokens stored encrypted.');
    }

    /** Real Graph API verification - success/failure comes from Meta, not us. */
    public function testWhatsapp(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner(), 403);

        $result = $this->whatsapp->testConnection();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
