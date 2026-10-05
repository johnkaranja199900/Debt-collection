<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ProfitIntelligenceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly ProfitIntelligenceService $intel) {}

    public function index(): View
    {
        abort_unless(Auth::user()->can('view_dashboard'), 403);

        $month = [now()->startOfMonth(), now()->endOfMonth()];

        return view('dashboard.index', [
            'todaySales' => round((float) Sale::whereDate('sale_date', today())->where('status', 'completed')->sum('total'), 2),
            'monthSales' => round((float) Sale::whereBetween('sale_date', [$month[0], $month[1]])->where('status', 'completed')->sum('total'), 2),
            'outstanding' => round((float) Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])->sum('balance'), 2),
            'customerCount' => Customer::count(),
            'lowStock' => Product::where('type', 'product')->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->orderBy('stock_quantity')->limit(5)->get(),
            'recentInvoices' => Invoice::with('customer')->latest()->limit(5)->get(),
            'recentPayments' => Payment::with('customer')->latest('payment_date')->limit(5)->get(),
            'topDebts' => Debt::with(['customer', 'invoice'])->where('days_overdue', '>', 0)->orderByDesc('days_overdue')->limit(5)->get(),
            'upcomingDue' => Debt::with(['customer', 'invoice'])->whereNotIn('status', ['paid', 'written_off'])->whereBetween('due_date', [today(), today()->addDays(7)])->orderBy('due_date')->limit(5)->get(),
            'profit' => $this->intel->profitAndLoss($month[0], $month[1]),
            'insights' => $this->intel->insights(),
        ]);
    }
}
