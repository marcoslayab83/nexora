<?php

namespace App\Livewire;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\RawMaterial;
use App\Models\Sale;
use App\Support\CurrencyHelper;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Panel General')]
class Dashboard extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public function mount(): void
    {
        $this->refreshCurrency();
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
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $salesStats = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$todayStart, $todayEnd])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_usd), 0) as revenue_usd, COALESCE(SUM(total_cost_usd), 0) as cost_usd, COALESCE(SUM(net_profit_usd), 0) as profit_usd')
            ->first();

        $revenueToday = (float) ($salesStats->revenue_usd ?? 0);
        $profitToday = (float) ($salesStats->profit_usd ?? 0);
        $marginToday = $revenueToday > 0 ? round(($profitToday / $revenueToday) * 100, 1) : 0.0;

        $batchesToday = ProductionBatch::query()
            ->whereBetween('produced_at', [$todayStart, $todayEnd])
            ->selectRaw('COUNT(*) as batches_count, COALESCE(SUM(produced_qty), 0) as units_produced')
            ->first();

        $lowRawMaterials = RawMaterial::query()
            ->where('is_active', true)
            ->whereColumn('stock_qty', '<=', 'min_stock_qty')
            ->with('baseUnit')
            ->orderBy('stock_qty')
            ->limit(6)
            ->get();

        $lowProducts = Product::query()
            ->where('is_active', true)
            ->whereColumn('stock_qty', '<=', 'min_stock_qty')
            ->orderBy('stock_qty')
            ->limit(6)
            ->get();

        $recentSales = Sale::query()
            ->with('user')
            ->latest('sold_at')
            ->limit(8)
            ->get();

        $openSession = CashSession::query()
            ->where('status', 'open')
            ->with(['cashRegister', 'user'])
            ->first();

        return view('livewire.dashboard', [
            'salesCount' => (int) ($salesStats->count ?? 0),
            'revenueToday' => $revenueToday,
            'costToday' => (float) ($salesStats->cost_usd ?? 0),
            'profitToday' => $profitToday,
            'marginToday' => $marginToday,
            'batchesCount' => (int) ($batchesToday->batches_count ?? 0),
            'unitsProduced' => (int) ($batchesToday->units_produced ?? 0),
            'lowRawMaterials' => $lowRawMaterials,
            'lowProducts' => $lowProducts,
            'recentSales' => $recentSales,
            'openSession' => $openSession,
        ]);
    }
}
