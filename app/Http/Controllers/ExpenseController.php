<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('manage_expenses'), 403);

        $expenses = Expense::with('category')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->string('search');
                $q->where(fn ($w) => $w->where('description', 'like', "%{$s}%")->orWhere('supplier', 'like', "%{$s}%"));
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('expense_category_id', $request->integer('category_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->date('to')))
            ->latest('expense_date')->latest('id')
            ->paginate(15)->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::where('status', 'active')->orderBy('name')->get(),
            'monthTotal' => round((float) Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->where('status', 'recorded')->sum('amount'), 2),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('manage_expenses'), 403);

        return view('expenses.create', [
            'categories' => ExpenseCategory::where('status', 'active')->orderBy('name')->get(),
            'expense' => new Expense(['expense_date' => today(), 'payment_method' => 'cash']),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }
        unset($data['receipt']);

        $expense = Expense::create($data);
        $this->audit->log('expense_created', $expense, [], ['amount' => (string) $expense->amount]);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Request $request, Expense $expense): View
    {
        abort_unless($request->user()->can('manage_expenses'), 403);

        return view('expenses.create', [
            'categories' => ExpenseCategory::where('status', 'active')->orderBy('name')->get(),
            'expense' => $expense,
        ]);
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $old = $expense->only(array_keys($request->validated()));
        $expense->update($request->validated());
        $this->audit->log('expense_updated', $expense, $old, $request->validated());

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->isOwner() || $request->user()->isManager(), 403);

        $expense->delete();
        $this->audit->log('expense_deleted', $expense);

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }
}
