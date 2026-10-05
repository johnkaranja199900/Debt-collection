<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SaleItem;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic profit + insight engine (master spec 32-33, 64).
 * Every number below comes from an actual database calculation.
 */
class ProfitIntelligenceService
{
    /** P&L for a period. Revenue = completed sales totals; COGS from line cost snapshots. */
    public function profitAndLoss(Carbon $from, Carbon $to): array
    {
        $revenue = (float) \App\Models\Sale::query()
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'completed')
            ->sum('total');

        $cogs = (float) SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
                ->where('status', 'completed'))
            ->select(DB::raw('COALESCE(SUM(quantity * cost_price), 0) as agg'))
            ->value('agg');

        $expenses = (float) Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'recorded')
            ->sum('amount');

        $gross = round($revenue - $cogs, 2);
        $net = round($gross - $expenses, 2);

        return [
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => $gross,
            'expenses' => round($expenses, 2),
            'net_profit' => $net,
            'profit_margin' => $revenue > 0 ? round($net / $revenue * 100, 2) : 0.0,
            'receivables' => round((float) Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])->sum('balance'), 2),
        ];
    }

    /** Month-over-month growth figures used by the dashboard and insights. */
    public function trends(): array
    {
        $thisMonth = [now()->startOfMonth(), now()->endOfMonth()];
        $lastMonth = [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()];

        $cur = $this->profitAndLoss(Carbon::parse($thisMonth[0]), Carbon::parse($thisMonth[1]));
        $prev = $this->profitAndLoss(Carbon::parse($lastMonth[0]), Carbon::parse($lastMonth[1]));

        $growth = fn (string $key) => $prev[$key] > 0
            ? round(($cur[$key] - $prev[$key]) / $prev[$key] * 100, 1)
            : null;

        return [
            'current' => $cur,
            'previous' => $prev,
            'revenue_growth' => $growth('revenue'),
            'profit_growth' => $growth('net_profit'),
            'expense_growth' => $growth('expenses'),
        ];
    }

    public function debtSnapshot(): array
    {
        $open = Debt::whereNotIn('status', ['paid', 'written_off', 'disputed']);

        return [
            'total_outstanding' => round((float) (clone $open)->sum('balance'), 2),
            'total_overdue' => round((float) (clone $open)->where('days_overdue', '>', 0)->sum('balance'), 2),
            'due_today' => round((float) (clone $open)->whereDate('due_date', today())->sum('balance'), 2),
            'due_this_week' => round((float) (clone $open)->whereBetween('due_date', [today(), today()->addDays(7)])->sum('balance'), 2),
            'over_30' => round((float) (clone $open)->where('aging_bucket', 'in', ['31_60', '61_90', '90_plus'])->sum('balance'), 2),
            'over_60' => round((float) (clone $open)->where('aging_bucket', 'in', ['61_90', '90_plus'])->sum('balance'), 2),
            'over_90' => round((float) (clone $open)->where('aging_bucket', '90_plus')->sum('balance'), 2),
            'aging' => (clone $open)->where('days_overdue', '>', 0)
                ->select('aging_bucket', DB::raw('SUM(balance) as amount'), DB::raw('COUNT(*) as count'))
                ->groupBy('aging_bucket')->pluck('amount', 'aging_bucket')->all(),
        ];
    }

    public function topCustomers(int $limit = 5): array
    {
        return \App\Models\Sale::query()
            ->where('status', 'completed')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereNull('customers.deleted_at')
            ->select('customers.id', 'customers.name', DB::raw('SUM(sales.total) as revenue'), DB::raw('COUNT(*) as orders'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('revenue')->limit($limit)->get()->all();
    }

    public function topProducts(int $limit = 5): array
    {
        return SaleItem::query()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->select('products.id', 'products.name', DB::raw('SUM(sale_items.quantity) as qty'), DB::raw('SUM(sale_items.total) as revenue'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty')->limit($limit)->get()->all();
    }

    public function topDebtors(int $limit = 5): array
    {
        return Debt::query()
            ->whereNotIn('status', ['paid', 'written_off', 'disputed'])
            ->where('days_overdue', '>', 0)
            ->join('customers', 'customers.id', '=', 'debts.customer_id')
            ->select('customers.id', 'customers.name', DB::raw('SUM(debts.balance) as owed'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('owed')->limit($limit)->get()->all();
    }

    /** Natural-language business insights derived ONLY from computed figures. */
    public function insights(): array
    {
        $out = [];
        $trends = $this->trends();
        $debts = $this->debtSnapshot();

        if ($trends['revenue_growth'] !== null) {
            $g = $trends['revenue_growth'];
            $out[] = sprintf('Revenue %s by %.1f%% compared with last month.', $g >= 0 ? 'increased' : 'decreased', abs($g));
        }

        if ($trends['expense_growth'] !== null && abs($trends['expense_growth']) >= 5) {
            $g = $trends['expense_growth'];
            $out[] = sprintf('Expenses %s by %.1f%% month-over-month.', $g >= 0 ? 'increased' : 'decreased', abs($g));
        }

        $topDebtor = $this->topDebtors(1)[0] ?? null;
        if ($topDebtor && $debts['total_overdue'] > 0) {
            $share = round($topDebtor->owed / $debts['total_overdue'] * 100);
            $out[] = sprintf('%s represents %d%% of overdue debt (KSh %s).', $topDebtor->name, $share, number_format($topDebtor->owed));
        }

        $topProduct = $this->topProducts(1)[0] ?? null;
        if ($topProduct) {
            $out[] = sprintf('%s has the highest sales volume (%s units).', $topProduct->name, rtrim(rtrim(number_format($topProduct->qty, 2), '0'), '.'));
        }

        if ($debts['over_90'] > 0) {
            $out[] = sprintf('You have KSh %s in debts overdue by more than 90 days - consider escalation.', number_format($debts['over_90']));
        }

        $lowStock = Product::where('type', 'product')->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count();
        if ($lowStock > 0) {
            $out[] = sprintf('%d product%s at or below low-stock threshold.', $lowStock, $lowStock === 1 ? ' is' : 's are');
        }

        if ($trends['current']['revenue'] > 0 && $trends['current']['profit_margin'] < 10) {
            $out[] = sprintf('Net profit margin is only %.1f%% this month - review pricing and expenses.', $trends['current']['profit_margin']);
        }

        return $out;
    }
}

// Placeholder type alias kept intentionally simple to avoid unused-import lint noise.
if (! class_exists(CarbonImmutableRange::class, false)) {
    class_alias('stdClass', 'App\Services\CarbonImmutableRange');
}
