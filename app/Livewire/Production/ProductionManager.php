<?php

namespace App\Livewire\Production;

use App\Models\Product;
use App\Models\ProductionBatch;
use App\Services\ProductionService;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Producción (Lotes)')]
class ProductionManager extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public ?int $product_id = null;

    public int $planned_qty = 50;

    public int $produced_qty = 50;

    public int $waste_qty = 0;

    public string $notes = '';

    public function mount(): void
    {
        $this->refreshCurrency();
        $this->product_id = Product::query()->where('type', 'manufactured')->where('is_active', true)->orderBy('name')->value('id');
    }

    #[On('currency-changed')]
    #[On('rate-updated')]
    public function refreshCurrency(): void
    {
        $this->currency = CurrencyHelper::displayCurrency();
        $this->rate = CurrencyHelper::activeRate();
    }

    public function updatedPlannedQty($value): void
    {
        $this->produced_qty = max(1, (int) $value);
    }

    public function executeProduction(ProductionService $productionService): void
    {
        $this->validate([
            'product_id' => ['required', 'exists:products,id'],
            'planned_qty' => ['required', 'integer', 'min:1'],
            'produced_qty' => ['required', 'integer', 'min:1'],
            'waste_qty' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $product = Product::query()->findOrFail($this->product_id);
            $batch = $productionService->executeBatch(
                $product,
                $this->planned_qty,
                $this->produced_qty,
                $this->waste_qty,
                Auth::id(),
                $this->notes ?: null
            );

            $this->notes = '';
            $this->waste_qty = 0;
            $this->dispatch('notify', type: 'success', message: "Lote {$batch->batch_code} completado: +{$batch->produced_qty} {$product->name} agregados al stock.");
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render(ProductionService $productionService)
    {
        $products = Product::query()->where('type', 'manufactured')->where('is_active', true)->orderBy('name')->get();
        $preview = null;
        $previewError = null;

        if ($this->product_id && $this->planned_qty > 0) {
            try {
                $selected = $products->firstWhere('id', $this->product_id) ?? Product::query()->find($this->product_id);
                if ($selected) {
                    $preview = $productionService->previewRequirements($selected, $this->planned_qty);
                }
            } catch (\Throwable $e) {
                $previewError = $e->getMessage();
            }
        }

        return view('livewire.production.production-manager', [
            'products' => $products,
            'preview' => $preview,
            'previewError' => $previewError,
            'batches' => ProductionBatch::query()->with(['product', 'user'])->latest('produced_at')->limit(15)->get(),
        ]);
    }
}
