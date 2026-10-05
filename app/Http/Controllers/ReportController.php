<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Sale;
use App\Services\ProfitIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly ProfitIntelligenceService $intel) {}

    private function period(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth();

        return [$from, $to];
    }

    public function profitLoss(Request $request): View
    {
        abort_unless(Auth::user()->can('view_reports'), 403);
        [$from, $to] = $this->period($request);

        return view('reports.profit_loss', [
            'pl' => $this->intel->profitAndLoss($from, $to),
            'trends' => $this->intel->trends(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function sales(Request $request): View
    {
        abort_unless(Auth::user()->can('view_reports'), 403);
        [$from, $to] = $this->period($request);

        $byDay = Sale::whereBetween('sale_date', [$from, $to])->where('status', 'completed')
            ->selectRaw('DATE(sale_date) as day, SUM(total) as total, COUNT(*) as count')
            ->groupBy('day')->orderBy('day')->get();

        return view('reports.sales', [
            'byDay' => $byDay,
            'topCustomers' => $this->intel->topCustomers(10),
            'topProducts' => $this->intel->topProducts(10),
            'totals' => ['count' => $byDay->sum('count'), 'revenue' => round((float) $byDay->sum('total'), 2)],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function expenses(Request $request): View
    {
        abort_unless(Auth::user()->can('view_reports'), 403);
        [$from, $to] = $this->period($request);

        $byCategory = Expense::with('category')
            ->whereBetween('expense_date', [$from, $to])->where('status', 'recorded')
            ->selectRaw('expense_category_id, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('expense_category_id')->orderByDesc('total')->get();

        return view('reports.expenses', [
            'byCategory' => $byCategory,
            'total' => round((float) $byCategory->sum('total'), 2),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function debtors(Request $request): View
    {
        abort_unless(Auth::user()->can('view_debts'), 403);

        return view('reports.debtors', [
            'snapshot' => $this->intel->debtSnapshot(),
            'debtors' => $this->intel->topDebtors(20),
            'insights' => $this->intel->insights(),
        ]);
    }

    public function aging(Request $request): View
    {
        abort_unless(Auth::user()->can('view_debts'), 403);

        return view('reports.aging', ['snapshot' => $this->intel->debtSnapshot()]);
    }

    public function tax(Request $request): View
    {
        abort_unless(Auth::user()->can('view_reports'), 403);
        [$from, $to] = $this->period($request);

        $invoices = Invoice::whereBetween('issue_date', [$from, $to])
            ->whereIn('status', ['sent', 'partially_paid', 'overdue', 'paid']);

        return view('reports.tax', [
            'taxCollected' => round((float) (clone $invoices)->sum('tax'), 2),
            'netSales' => round((float) (clone $invoices)->sum('subtotal'), 2),
            'invoiceCount' => (clone $invoices)->count(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    /** Deterministic CSV export - columns fixed, no AI involved. */
    public function exportCsv(Request $request, string $type): mixed
    {
        abort_unless(Auth::user()->can('view_reports'), 403);
        [$from, $to] = $this->period($request);

        $rows = match ($type) {
            'sales' => Sale::whereBetween('sale_date', [$from, $to])->where('status', 'completed')
                ->with('customer')->get()
                ->map(fn ($s) => [$s->sale_number, $s->customer?->name, $s->sale_date->toDateString(), $s->subtotal, $s->tax, $s->total]),
            'expenses' => Expense::whereBetween('expense_date', [$from, $to])->with('category')->get()
                ->map(fn ($e) => [$e->expense_date->toDateString(), $e->category?->name, $e->description, $e->amount, $e->payment_method]),
            'invoices' => Invoice::whereBetween('issue_date', [$from, $to])->with('customer')->get()
                ->map(fn ($i) => [$i->invoice_number, $i->customer?->name, $i->issue_date->toDateString(), $i->due_date?->toDateString(), $i->total, $i->balance, $i->status]),
            default => collect(),
        };

        $headers = match ($type) {
            'sales' => ['Sale No', 'Customer', 'Date', 'Subtotal', 'Tax', 'Total'],
            'expenses' => ['Date', 'Category', 'Description', 'Amount', 'Method'],
            default => ['Number', 'Customer', 'Issued', 'Due', 'Total', 'Balance', 'Status'],
        };

        $callback = fn () => (static function (array $headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, is_array($row) ? $row : $row->toArray());
            }
            fclose($out);
        })($headers, $rows);

        return response()->streamDownload($callback, "{$type}-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv']);
    }
}
