<?php

namespace App\Livewire\Reports;

use App\Models\Sale;
use App\Models\SaleComboSelection;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Support\CurrencyHelper;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Rentabilidad Real')]
class ProfitabilityReport extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public string $startDate = '';

    public string $endDate = '';

    public function mount(): void
    {
        $this->refreshCurrency();
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    #[On('currency-changed')]
    #[On('rate-updated')]
    public function refreshCurrency(): void
    {
        $this->currency = CurrencyHelper::displayCurrency();
        $this->rate = CurrencyHelper::activeRate();
    }

    public function render()
    {
        $from = $this->startDate.' 00:00:00';
        $to = $this->endDate.' 23:59:59';

        $totals = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total_usd), 0) as revenue_usd, COALESCE(SUM(total_cost_usd), 0) as cost_usd, COALESCE(SUM(net_profit_usd), 0) as profit_usd')
            ->first();

        $revenue = (float) ($totals->revenue_usd ?? 0);
        $profit = (float) ($totals->profit_usd ?? 0);
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0;

        $productBreakdown = SaleItem::query()
            ->where('item_type', 'product')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereBetween('sold_at', [$from, $to]))
            ->selectRaw('description, SUM(quantity) as qty_sold, SUM(total_price_usd) as revenue_usd, SUM(total_cost_usd) as cost_usd, SUM(profit_usd) as profit_usd')
            ->groupBy('description')
            ->orderByDesc('profit_usd')
            ->get();

        $comboSales = SaleItem::query()
            ->where('item_type', 'combo')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereBetween('sold_at', [$from, $to]))
            ->with(['sale', 'comboSelections'])
            ->latest('id')
            ->limit(30)
            ->get();

        $comboFlavorsSummary = SaleComboSelection::query()
            ->whereHas('saleItem.sale', fn ($q) => $q->where('status', 'completed')->whereBetween('sold_at', [$from, $to]))
            ->selectRaw('product_name, SUM(quantity) as qty_used, SUM(total_cost_usd) as total_cost_usd')
            ->groupBy('product_name')
            ->orderByDesc('qty_used')
            ->get();

        $paymentBreakdown = SalePayment::query()
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereBetween('sold_at', [$from, $to]))
            ->with('paymentMethod')
            ->selectRaw('payment_method_id, currency, SUM(amount_original) as total_original, SUM(amount_usd_equivalent) as total_usd')
            ->groupBy('payment_method_id', 'currency')
            ->get();

        return view('livewire.reports.profitability-report', [
            'salesCount' => (int) ($totals->sales_count ?? 0),
            'revenue' => $revenue,
            'cost' => (float) ($totals->cost_usd ?? 0),
            'profit' => $profit,
            'margin' => $margin,
            'productBreakdown' => $productBreakdown,
            'comboSales' => $comboSales,
            'comboFlavorsSummary' => $comboFlavorsSummary,
            'paymentBreakdown' => $paymentBreakdown,
        ]);
    }
}
