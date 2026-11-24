<?php

namespace App\Livewire\Combos;

use App\Models\Combo;
use App\Models\ComboSlot;
use App\Models\ComboSlotOption;
use App\Models\Product;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Combos Flexibles / Mixtos')]
class CombosManager extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $promo_price_usd = '3.50';

    public bool $is_active = true;

    /** @var array<int, array{label: string, required_qty: int, selected_products: array<int, bool>, extras: array<int, string>}> */
    public array $slots = [];

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

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'description', 'slots']);
        $this->promo_price_usd = '3.50';
        $this->is_active = true;
        $this->addSlot('Elige tus Pasteles', 3);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $combo = Combo::query()->with('slots.options')->findOrFail($id);
        $this->editingId = $combo->id;
        $this->name = $combo->name;
        $this->description = (string) ($combo->description ?? '');
        $this->promo_price_usd = (string) round((float) $combo->promo_price_usd, 2);
        $this->is_active = $combo->is_active;

        $this->slots = $combo->slots->map(function ($slot) {
            $selected = [];
            $extras = [];
            foreach ($slot->options as $opt) {
                $selected[$opt->product_id] = true;
                $extras[$opt->product_id] = (string) round((float) $opt->extra_price_usd, 2);
            }

            return [
                'label' => $slot->label,
                'required_qty' => (int) $slot->required_qty,
                'selected_products' => $selected,
                'extras' => $extras,
            ];
        })->toArray();

        $this->showModal = true;
    }

    public function addSlot(string $label = 'Nueva Sección', int $qty = 1): void
    {
        $this->slots[] = [
            'label' => $label,
            'required_qty' => $qty,
            'selected_products' => [],
            'extras' => [],
        ];
    }

    public function removeSlot(int $index): void
    {
        unset($this->slots[$index]);
        $this->slots = array_values($this->slots);
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'promo_price_usd' => ['required', 'numeric', 'gt:0'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.label' => ['required', 'string', 'max:120'],
            'slots.*.required_qty' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($this->slots as $slot) {
            $count = collect($slot['selected_products'] ?? [])->filter()->count();
            if ($count === 0) {
                $this->dispatch('notify', type: 'error', message: "La sección '{$slot['label']}' debe tener al menos un producto elegible.");

                return;
            }
        }

        DB::transaction(function () {
            if ($this->editingId) {
                $combo = Combo::query()->findOrFail($this->editingId);
                $combo->update([
                    'name' => $this->name,
                    'description' => $this->description ?: null,
                    'promo_price_usd' => (float) $this->promo_price_usd,
                    'is_active' => $this->is_active,
                ]);
                $combo->slots()->delete();
            } else {
                $combo = Combo::query()->create([
                    'name' => $this->name,
                    'description' => $this->description ?: null,
                    'promo_price_usd' => (float) $this->promo_price_usd,
                    'is_active' => $this->is_active,
                ]);
            }

            foreach ($this->slots as $idx => $slotData) {
                $slot = ComboSlot::query()->create([
                    'combo_id' => $combo->id,
                    'label' => $slotData['label'],
                    'required_qty' => (int) $slotData['required_qty'],
                    'sort_order' => $idx + 1,
                ]);

                foreach ($slotData['selected_products'] ?? [] as $productId => $checked) {
                    if (! $checked) {
                        continue;
                    }
                    $extra = max(0, (float) ($slotData['extras'][$productId] ?? 0));
                    ComboSlotOption::query()->create([
                        'combo_slot_id' => $slot->id,
                        'product_id' => (int) $productId,
                        'extra_price_usd' => $extra,
                    ]);
                }
            }
        });

        $this->showModal = false;
        $this->dispatch('notify', type: 'success', message: 'Combo flexible guardado.');
    }

    public function render()
    {
        return view('livewire.combos.combos-manager', [
            'combos' => Combo::query()->with('slots.options.product')->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
        ]);
    }
}
