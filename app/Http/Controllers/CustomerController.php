<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('manage_customers'), 403);

        $customers = Customer::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search');
                $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('customer_code', 'like', "%{$s}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('manage_customers'), 403);

        return view('customers.create', ['customer' => new Customer(['status' => 'active', 'payment_terms' => 30])]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());
        $this->audit->log('customer_created', $customer, [], $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Customer created.');
    }

    public function show(Request $request, Customer $customer): View
    {
        abort_unless($request->user()->can('manage_customers'), 403);

        return view('customers.show', [
            'customer' => $customer,
            'invoices' => $customer->invoices()->latest()->limit(10)->get(),
            'sales' => $customer->sales()->latest('sale_date')->limit(10)->get(),
            'payments' => $customer->payments()->latest('payment_date')->limit(10)->get(),
            'debts' => $customer->debts()->where('balance', '>', 0)->get(),
            'outstanding' => $customer->outstandingBalance(),
        ]);
    }

    public function edit(Request $request, Customer $customer): View
    {
        abort_unless($request->user()->can('manage_customers'), 403);

        return view('customers.create', ['customer' => $customer]);
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $old = $customer->only(array_keys($request->validated()));
        $customer->update($request->validated());
        $this->audit->log('customer_updated', $customer, $old, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()->isOwner() || $request->user()->isManager(), 403);

        if ($customer->outstandingBalance() > 0) {
            return back()->with('error', 'Cannot delete a customer with outstanding invoices. Set them inactive instead.');
        }

        $customer->delete();
        $this->audit->log('customer_deleted', $customer);

        return redirect()->route('customers.index')->with('success', 'Customer deleted (soft).');
    }
}
