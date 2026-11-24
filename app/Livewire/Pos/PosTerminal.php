<?php

namespace App\Livewire\Pos;

use App\Models\Category;
use App\Models\Combo;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Services\CashService;
use App\Services\PosCheckoutService;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Punto de Venta (POS)')]
class PosTerminal extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public string $search = '';

    public string $activeCategory = 'all';

    /** @var array<int, array<string, mixed>> */
    public array $cart = [];

    public string $discountUsd = '0';

    /** @var array<int, array{payment_method_id: int, amount_original: string, reference_number: string}> */
    public array $payments = [];

    public string $changeBs = '0';

    public string $changeUsd = '0';

    // Combo builder modal
    public bool $showComboModal = false;

    public ?int $selectedComboId = null;

    /** @var array<int, array<int, int>> [slot_id => [product_id => qty]] */
    public array $comboSelections = [];

    // Receipt modal
    public ?int $lastSaleId = null;

    public function mount(): void
    {
        $this->refreshCurrency();
        $this->resetPayments();
    }

    #[On('currency-changed')]
    #[On('rate-updated')]
    public function refreshCurrency(): void
    {
        $this->currency = CurrencyHelper::displayCurrency();
        $this->rate = CurrencyHelper::activeRate();
    }

    public function addProduct(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        foreach ($this->cart as $idx => $item) {
            if ($item['item_type'] === 'product' && (int) $item['product_id'] === $product->id) {
                $this->cart[$idx]['quantity']++;

                return;
            }
        }

        $this->cart[] = [
            'uid' => Str::random(8),
            'item_type' => 'product',
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price_usd' => (float) $product->sale_price_usd,
            'quantity' => 1,
            'summary' => $product->type === 'manufactured' ? 'Pastel' : 'Reventa',
        ];
    }

    public function openComboBuilder(int $comboId): void
    {
        $combo = Combo::query()->with('slots.options.product')->findOrFail($comboId);
        $this->selectedComboId = $combo->id;
        $this->comboSelections = [];

        foreach ($combo->slots as $slot) {
            $this->comboSelections[$slot->id] = [];
            foreach ($slot->options as $opt) {
                $this->comboSelections[$slot->id][$opt->product_id] = 0;
            }
        }

        $this->showComboModal = true;
    }

    public function incrementComboOption(int $slotId, int $productId, int $maxQty): void
    {
        $currentSlotSum = array_sum($this->comboSelections[$slotId] ?? []);
        if ($currentSlotSum >= $maxQty) {
            return;
        }
        $this->comboSelections[$slotId][$productId] = ($this->comboSelections[$slotId][$productId] ?? 0) + 1;
    }

    public function decrementComboOption(int $slotId, int $productId): void
    {
        $current = $this->comboSelections[$slotId][$productId] ?? 0;
        if ($current > 0) {
            $this->comboSelections[$slotId][$productId] = $current - 1;
        }
    }

    public function confirmComboToCart(): void
    {
        $combo = Combo::query()->with('slots.options.product')->findOrFail($this->selectedComboId);

        $selections = [];
        $summaryParts = [];
        $extraUsd = 0.0;

        foreach ($combo->slots as $slot) {
            $slotPicks = $this->comboSelections[$slot->id] ?? [];
            $pickedTotal = array_sum($slotPicks);
            if ($pickedTotal !== (int) $slot->required_qty) {
                $this->dispatch('notify', type: 'error', message: "Debes completar {$slot->required_qty} unidades en '{$slot->label}'.");

                return;
            }

            foreach ($slotPicks as $productId => $qty) {
                if ($qty <= 0) {
                    continue;
                }
                $opt = $slot->options->firstWhere('product_id', $productId);
                $prodName = $opt?->product?->name ?? "#{$productId}";
                $extraUsd += (float) ($opt?->extra_price_usd ?? 0) * $qty;
                $summaryParts[] = "{$qty}x {$prodName}";
                $selections[] = [
                    'combo_slot_id' => $slot->id,
                    'product_id' => (int) $productId,
                    'quantity' => (int) $qty,
                ];
            }
        }

        $this->cart[] = [
            'uid' => Str::random(8),
            'item_type' => 'combo',
            'combo_id' => $combo->id,
            'name' => $combo->name,
            'unit_price_usd' => round((float) $combo->promo_price_usd + $extraUsd, 4),
            'quantity' => 1,
            'selections' => $selections,
            'summary' => implode(' • ', $summaryParts),
        ];

        $this->showComboModal = false;
    }

    public function incrementCart(int $index): void
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity']++;
        }
    }

    public function decrementCart(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }
        if ($this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity']--;
        } else {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
        }
    }

    public function removeCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function addPaymentRow(): void
    {
        $firstMethod = PaymentMethod::active()->first();
        $this->payments[] = [
            'payment_method_id' => $firstMethod?->id ?? 0,
            'amount_original' => '',
            'reference_number' => '',
        ];
    }

    public function removePaymentRow(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
        if (empty($this->payments)) {
            $this->addPaymentRow();
        }
    }

    public function fillRemainingInPayment(int $index): void
    {
        if (! isset($this->payments[$index])) {
            return;
        }

        $totalUsd = $this->cartTotalUsd();
        $paidWithoutIndex = 0.0;
        $methods = PaymentMethod::active()->get()->keyBy('id');

        foreach ($this->payments as $idx => $p) {
            if ($idx === $index) {
                continue;
            }
            $amt = (float) ($p['amount_original'] ?: 0);
            $m = $methods->get($p['payment_method_id']);
            if ($m && $amt > 0) {
                $paidWithoutIndex += $m->currency === 'VES' ? ($amt / $this->rate) : $amt;
            }
        }

        $remainingUsd = max(0, $totalUsd - $paidWithoutIndex);
        $targetMethod = $methods->get($this->payments[$index]['payment_method_id']);

        if ($targetMethod && $targetMethod->currency === 'VES') {
            $this->payments[$index]['amount_original'] = (string) round($remainingUsd * $this->rate, 2);
        } else {
            $this->payments[$index]['amount_original'] = (string) round($remainingUsd, 2);
        }
    }

    protected function resetPayments(): void
    {
        $firstMethod = PaymentMethod::active()->first();
        $this->payments = [[
            'payment_method_id' => $firstMethod?->id ?? 0,
            'amount_original' => '',
            'reference_number' => '',
        ]];
        $this->changeBs = '0';
        $this->changeUsd = '0';
        $this->discountUsd = '0';
    }

    public function cartSubtotalUsd(): float
    {
        return round(collect($this->cart)->sum(fn ($i) => $i['unit_price_usd'] * $i['quantity']), 4);
    }

    public function cartTotalUsd(): float
    {
        $sub = $this->cartSubtotalUsd();

        return round(max(0, $sub - max(0, (float) $this->discountUsd)), 4);
    }

    public function completeSale(CashService $cashService, PosCheckoutService $checkoutService): void
    {
        $session = $cashService->anyOpenSession();
        if (! $session) {
            $this->dispatch('notify', type: 'error', message: 'Debes abrir una jornada de caja antes de facturar.');

            return;
        }

        try {
            $sale = $checkoutService->checkout(
                $session,
                Auth::id(),
                $this->cart,
                $this->payments,
                (float) $this->discountUsd,
                (float) $this->changeBs,
                (float) $this->changeUsd
            );

            $this->cart = [];
            $this->resetPayments();
            $this->lastSaleId = $sale->id;
            $this->dispatch('notify', type: 'success', message: "Venta {$sale->invoice_number} procesada.");
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render(CashService $cashService)
    {
        $openSession = $cashService->anyOpenSession();
        $paymentMethods = PaymentMethod::active()->get();

        $paidUsd = 0.0;
        $pmMap = $paymentMethods->keyBy('id');
        foreach ($this->payments as $p) {
            $amt = (float) ($p['amount_original'] ?: 0);
            $m = $pmMap->get($p['payment_method_id']);
            if ($m && $amt > 0) {
                $paidUsd += $m->currency === 'VES' ? ($amt / $this->rate) : $amt;
            }
        }

        $totalUsd = $this->cartTotalUsd();
        $balanceUsd = round($totalUsd - $paidUsd, 4);

        $products = Product::query()
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when(! in_array($this->activeCategory, ['all', 'combos'], true), fn ($q) => $q->where('category_id', (int) $this->activeCategory))
            ->orderBy('name')
            ->get();

        $combos = in_array($this->activeCategory, ['all', 'combos'], true)
            ? Combo::query()
                ->where('is_active', true)
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->with('slots.options.product')
                ->orderBy('name')
                ->get()
            : collect();

        return view('livewire.pos.pos-terminal', [
            'openSession' => $openSession,
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'products' => $this->activeCategory === 'combos' ? collect() : $products,
            'combos' => $combos,
            'paymentMethods' => $paymentMethods,
            'subtotalUsd' => $this->cartSubtotalUsd(),
            'totalUsd' => $totalUsd,
            'paidUsd' => round($paidUsd, 4),
            'balanceUsd' => $balanceUsd,
            'selectedCombo' => $this->selectedComboId ? Combo::query()->with('slots.options.product')->find($this->selectedComboId) : null,
            'lastSale' => $this->lastSaleId ? Sale::query()->with(['items.comboSelections', 'payments.paymentMethod', 'user'])->find($this->lastSaleId) : null,
        ]);
    }
}
