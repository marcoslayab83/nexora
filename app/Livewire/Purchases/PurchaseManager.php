<?php

namespace App\Livewire\Purchases;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\PurchaseService;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Compras & Proveedores')]
class PurchaseManager extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public ?int $supplier_id = null;

    public string $invoice_number = '';

    public string $purchase_currency = 'USD';

    public string $exchange_rate_bs = '1';

    public string $notes = '';

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    // Supplier quick modal
    public bool $showSupplierModal = false;

    public string $supName = '';

    public string $supTaxId = '';

    public string $supPhone = '';

    public function mount(): void
    {
        $this->refreshCurrency();
        $this->exchange_rate_bs = (string) round($this->rate, 4);
        $this->addLine();
    }

    #[On('currency-changed')]
    #[On('rate-updated')]
    public function refreshCurrency(): void
    {
        $this->currency = CurrencyHelper::displayCurrency();
        $this->rate = CurrencyHelper::activeRate();
    }

    public function addLine(): void
    {
        $firstRm = RawMaterial::query()->where('is_active', true)->orderBy('name')->first();
        $this->items[] = [
            'purchasable_type' => 'raw_material',
            'purchasable_id' => $firstRm?->id ?? '',
            'unit_id' => $firstRm?->default_purchase_unit_id ?? Unit::query()->value('id'),
            'qty_purchased' => '1',
            'unit_cost_original' => '0',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        if (empty($this->items)) {
            $this->addLine();
        }
    }

    public function updatedItems($value, $key): void
    {
        if (str_ends_with($key, '.purchasable_type')) {
            $index = (int) explode('.', $key)[0];
            if ($value === 'raw_material') {
                $rm = RawMaterial::query()->where('is_active', true)->orderBy('name')->first();
                $this->items[$index]['purchasable_id'] = $rm?->id ?? '';
                $this->items[$index]['unit_id'] = $rm?->default_purchase_unit_id ?? Unit::query()->value('id');
            } else {
                $prod = Product::query()->where('type', 'resale')->where('is_active', true)->orderBy('name')->first();
                $und = Unit::query()->where('symbol', 'und')->first() ?? Unit::query()->first();
                $this->items[$index]['purchasable_id'] = $prod?->id ?? '';
                $this->items[$index]['unit_id'] = $und?->id ?? '';
            }
        }

        if (str_ends_with($key, '.purchasable_id')) {
            $index = (int) explode('.', $key)[0];
            if (($this->items[$index]['purchasable_type'] ?? '') === 'raw_material' && $value) {
                $rm = RawMaterial::query()->find($value);
                if ($rm) {
                    $this->items[$index]['unit_id'] = $rm->default_purchase_unit_id;
                }
            }
        }
    }

    public function savePurchase(PurchaseService $purchaseService): void
    {
        $this->validate([
            'purchase_currency' => ['required', 'in:USD,VES'],
            'exchange_rate_bs' => ['required', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchasable_id' => ['required', 'integer'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.qty_purchased' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost_original' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $purchase = $purchaseService->createPurchase([
                'supplier_id' => $this->supplier_id ?: null,
                'invoice_number' => $this->invoice_number ?: null,
                'currency' => $this->purchase_currency,
                'exchange_rate_bs' => (float) $this->exchange_rate_bs,
                'notes' => $this->notes ?: null,
            ], $this->items, Auth::id());

            $this->reset(['invoice_number', 'notes', 'items']);
            $this->addLine();
            $this->dispatch('notify', type: 'success', message: "Compra #{$purchase->id} registrada. Costos e inventarios actualizados en cascada.");
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function saveSupplier(): void
    {
        $this->validate([
            'supName' => ['required', 'string', 'max:150'],
        ]);

        $supplier = Supplier::query()->create([
            'name' => $this->supName,
            'tax_id' => $this->supTaxId ?: null,
            'phone' => $this->supPhone ?: null,
        ]);

        $this->supplier_id = $supplier->id;
        $this->reset(['supName', 'supTaxId', 'supPhone', 'showSupplierModal']);
        $this->dispatch('notify', type: 'success', message: 'Proveedor creado.');
    }

    public function render()
    {
        return view('livewire.purchases.purchase-manager', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'rawMaterials' => RawMaterial::query()->where('is_active', true)->with('baseUnit')->orderBy('name')->get(),
            'resaleProducts' => Product::query()->where('type', 'resale')->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::query()->orderBy('name')->get(),
            'recentPurchases' => Purchase::query()->with(['supplier', 'items'])->latest('purchased_at')->limit(12)->get(),
        ]);
    }
}
