<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): View
    {
        abort_unless(Auth::user()->isOwner(), 403);

        return view('settings.index', [
            'business' => Business::current(),
            'categories' => Category::orderBy('name')->get(),
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner(), 403);

        $business = Business::current();
        $old = $business->only(['name', 'currency', 'default_payment_terms']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', Rule::in(['KES', 'UGX', 'TZS', 'RWF'])],
            'default_payment_terms' => ['required', 'integer', 'min:0', 'max:365'],
            'invoice_prefix' => ['required', 'string', 'max:10'],
            'quotation_prefix' => ['required', 'string', 'max:10'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        unset($data['logo']);

        $business->update($data);
        $this->audit->log('settings_updated', $business, $old, Arr::except($data, ['logo_path']));

        return back()->with('success', 'Business settings saved.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner() || Auth::user()->isManager(), 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:categories,name']]);
        Category::create($data + ['status' => 'active']);

        return back()->with('success', 'Category added.');
    }

    public function storeExpenseCategory(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isOwner() || Auth::user()->isManager(), 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:expense_categories,name']]);
        ExpenseCategory::create($data + ['status' => 'active']);

        return back()->with('success', 'Expense category added.');
    }
}
