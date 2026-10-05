<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DebtController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('view_debts'), 403);

        $debts = Debt::with(['customer', 'invoice'])
            ->when($request->filled('bucket'), fn ($q) => $q->where('aging_bucket', $request->string('bucket')))
            ->when($request->boolean('overdue_only'), fn ($q) => $q->where('days_overdue', '>', 0))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search');
                $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
            })
            ->whereNotIn('status', ['paid'])
            ->orderByDesc('days_overdue')
            ->paginate(20)->withQueryString();

        return view('debts.index', [
            'debts' => $debts,
            'summary' => app(\App\Services\ProfitIntelligenceService::class)->debtSnapshot(),
        ]);
    }

    public function show(Request $request, Debt $debt): View
    {
        abort_unless($request->user()->can('view_debts'), 403);

        return view('debts.show', ['debt' => $debt->load(['customer', 'invoice', 'reminderLogs.template'])]);
    }

    /** Owner-only write-off: financial corrections are never done casually. */
    public function writeOff(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $debt->update(['status' => 'written_off']);
        if ($debt->invoice && ! $debt->invoice->isPaid()) {
            $debt->invoice->update(['status' => 'cancelled']);
        }

        $this->audit->log('debt_written_off', $debt, [], ['reason' => $request->string('reason')]);

        return back()->with('success', 'Debt written off and audit-logged.');
    }

    public function markDisputed(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($request->user()->can('view_debts'), 403);

        $debt->update(['status' => $debt->status === 'disputed' ? 'current' : 'disputed']);
        $this->audit->log('debt_status_toggled', $debt, [], ['status' => $debt->status]);

        return back()->with('success', 'Debt status updated (reminders pause while disputed).');
    }
}
