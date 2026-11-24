<?php

namespace App\Livewire\Recipes;

use App\Models\RawMaterial;
use App\Models\SubRecipe;
use App\Models\SubRecipeItem;
use App\Models\Unit;
use App\Services\CostCascadeService;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sub-Recetas (Masa y Relleno)')]
class SubRecipesManager extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public string $activeTab = 'all';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'dough';

    public string $yield_units = '50';

    public string $notes = '';

    /** @var array<int, array<string, mixed>> */
    public array $ingredients = [];

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

    public function openCreate(string $defaultType = 'dough'): void
    {
        $this->reset(['editingId', 'name', 'notes', 'ingredients']);
        $this->type = $defaultType;
        $this->yield_units = '50';
        $this->addIngredient();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $recipe = SubRecipe::query()->with('items')->findOrFail($id);
        $this->editingId = $recipe->id;
        $this->name = $recipe->name;
        $this->type = $recipe->type;
        $this->yield_units = (string) $recipe->yield_units;
        $this->notes = (string) ($recipe->notes ?? '');
        $this->ingredients = $recipe->items->map(fn ($item) => [
            'raw_material_id' => $item->raw_material_id,
            'unit_id' => $item->unit_id,
            'quantity' => (string) $item->quantity,
        ])->toArray();

        if (empty($this->ingredients)) {
            $this->addIngredient();
        }
        $this->showModal = true;
    }

    public function addIngredient(): void
    {
        $rm = RawMaterial::query()->where('is_active', true)->orderBy('name')->first();
        $this->ingredients[] = [
            'raw_material_id' => $rm?->id ?? '',
            'unit_id' => $rm?->base_unit_id ?? Unit::query()->value('id'),
            'quantity' => '1',
        ];
    }

    public function removeIngredient(int $index): void
    {
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);
    }

    public function save(CostCascadeService $costCascadeService): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:dough,filling'],
            'yield_units' => ['required', 'numeric', 'gt:0'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.raw_material_id' => ['required', 'exists:raw_materials,id'],
            'ingredients.*.unit_id' => ['required', 'exists:units,id'],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        DB::transaction(function () use ($costCascadeService) {
            if ($this->editingId) {
                $recipe = SubRecipe::query()->findOrFail($this->editingId);
                $recipe->update([
                    'name' => $this->name,
                    'type' => $this->type,
                    'yield_units' => (float) $this->yield_units,
                    'notes' => $this->notes ?: null,
                ]);
                $recipe->items()->delete();
            } else {
                $recipe = SubRecipe::query()->create([
                    'name' => $this->name,
                    'type' => $this->type,
                    'yield_units' => (float) $this->yield_units,
                    'notes' => $this->notes ?: null,
                    'is_active' => true,
                ]);
            }

            foreach ($this->ingredients as $row) {
                $unit = Unit::query()->findOrFail($row['unit_id']);
                $qty = (float) $row['quantity'];
                SubRecipeItem::query()->create([
                    'sub_recipe_id' => $recipe->id,
                    'raw_material_id' => $row['raw_material_id'],
                    'unit_id' => $unit->id,
                    'quantity' => $qty,
                    'base_quantity' => $unit->toBaseQty($qty),
                    'line_cost_usd' => 0,
                ]);
            }

            $costCascadeService->recalculateSubRecipe($recipe, 'recipe_update', $recipe->id, cascadeToProducts: true);
        });

        $this->showModal = false;
        $this->dispatch('notify', type: 'success', message: 'Sub-receta guardada y costos de pasteles recalculados.');
    }

    public function render()
    {
        $recipes = SubRecipe::query()
            ->with(['items.rawMaterial', 'items.unit'])
            ->when($this->activeTab !== 'all', fn ($q) => $q->where('type', $this->activeTab))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('livewire.recipes.sub-recipes-manager', [
            'recipes' => $recipes,
            'rawMaterials' => RawMaterial::query()->where('is_active', true)->with('baseUnit')->orderBy('name')->get(),
            'units' => Unit::query()->orderBy('name')->get(),
        ]);
    }
}
