<?php

namespace App\Livewire\Products;

use App\Models\Category;
use App\Models\CostHistory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductSubRecipe;
use App\Models\PurchaseItem;
use App\Models\SaleComboSelection;
use App\Models\SaleItem;
use App\Models\SubRecipe;
use App\Services\CostCascadeService;
use App\Services\InventoryService;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Productos & Márgenes')]
class ProductsManager extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public string $filterType = 'all';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $sku = '';

    public string $type = 'manufactured';

    public ?int $category_id = null;

    public string $sale_price_usd = '1.00';

    public string $min_stock_qty = '10';

    public string $initial_stock_qty = '0';

    public string $resale_cost_usd = '0';

    public ?int $dough_sub_recipe_id = null;

    public string $dough_factor = '1.0';

    public ?int $filling_sub_recipe_id = null;

    public string $filling_factor = '1.0';

    public bool $is_active = true;

    public bool $hasSales = false;

    public bool $showHistoryModal = false;

    public ?int $historyProductId = null;

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
        $this->reset(['editingId', 'name', 'sku', 'initial_stock_qty', 'resale_cost_usd', 'hasSales']);
        $this->type = 'manufactured';
        $this->category_id = Category::query()->value('id');
        $this->sale_price_usd = '1.20';
        $this->min_stock_qty = '10';
        $this->initial_stock_qty = '0';
        $this->resale_cost_usd = '0';
        $this->dough_sub_recipe_id = SubRecipe::query()->where('type', 'dough')->value('id');
        $this->filling_sub_recipe_id = SubRecipe::query()->where('type', 'filling')->value('id');
        $this->dough_factor = '1.0';
        $this->filling_factor = '1.0';
        $this->is_active = true;
        $this->hasSales = false;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $product = Product::query()->with('subRecipeLinks')->findOrFail($id);
        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->sku = (string) ($product->sku ?? '');
        $this->type = $product->type;
        $this->category_id = $product->category_id;
        $this->sale_price_usd = (string) round((float) $product->sale_price_usd, 4);
        $this->min_stock_qty = (string) $product->min_stock_qty;
        $this->resale_cost_usd = (string) round((float) $product->effectiveUnitCostUsd(), 4);
        $this->is_active = $product->is_active;
        $this->hasSales = $this->productHasSales($product->id);

        $dough = $product->subRecipeLinks->firstWhere('role', 'dough');
        $filling = $product->subRecipeLinks->firstWhere('role', 'filling');
        $this->dough_sub_recipe_id = $dough?->sub_recipe_id;
        $this->dough_factor = (string) ($dough?->portions_factor ?? '1.0');
        $this->filling_sub_recipe_id = $filling?->sub_recipe_id;
        $this->filling_factor = (string) ($filling?->portions_factor ?? '1.0');

        $this->showModal = true;
    }

    public function save(CostCascadeService $costCascadeService, InventoryService $inventoryService): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:40', 'unique:products,sku'.($this->editingId ? ','.$this->editingId : '')],
            'type' => ['required', 'in:manufactured,resale'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sale_price_usd' => ['required', 'numeric', 'min:0'],
            'min_stock_qty' => ['required', 'numeric', 'min:0'],
        ];

        if ($this->type === 'resale') {
            $rules['resale_cost_usd'] = ['nullable', 'numeric', 'min:0'];
        }

        if ($this->type === 'manufactured') {
            $rules['dough_sub_recipe_id'] = ['required', 'exists:sub_recipes,id'];
            $rules['filling_sub_recipe_id'] = ['required', 'exists:sub_recipes,id'];
            $rules['dough_factor'] = ['required', 'numeric', 'gt:0'];
            $rules['filling_factor'] = ['required', 'numeric', 'gt:0'];
        }

        $this->validate($rules);

        DB::transaction(function () use ($costCascadeService, $inventoryService) {
            if ($this->editingId) {
                $product = Product::query()->findOrFail($this->editingId);
                $hasSales = $this->productHasSales($product->id);

                $updateData = [
                    'category_id' => $this->category_id,
                    'sku' => $this->sku ?: null,
                    'name' => $this->name,
                    'type' => $this->type,
                    'min_stock_qty' => (float) $this->min_stock_qty,
                    'sale_price_usd' => (float) $this->sale_price_usd,
                    'is_active' => $this->is_active,
                ];

                if ($this->type === 'resale' && ! $hasSales) {
                    $newResaleCost = max(0, (float) $this->resale_cost_usd);
                    $oldCost = (float) $product->avg_stock_cost_usd;

                    $updateData['cost_usd'] = $newResaleCost;
                    $updateData['avg_stock_cost_usd'] = $newResaleCost;
                    $updateData['last_cost_usd'] = $newResaleCost;

                    if (abs($oldCost - $newResaleCost) > 0.0000005) {
                        CostHistory::query()->create([
                            'costable_type' => 'product',
                            'costable_id' => $product->id,
                            'old_cost_usd' => $oldCost,
                            'new_cost_usd' => $newResaleCost,
                            'trigger_source' => 'initial_cost_edit',
                            'reference_id' => $product->id,
                            'notes' => 'Edición de costo inicial (sin ventas previas)',
                            'created_at' => now(),
                        ]);
                    }
                }

                $product->update($updateData);
            } else {
                $resaleCost = $this->type === 'resale' ? max(0, (float) $this->resale_cost_usd) : 0.0;
                $product = Product::query()->create([
                    'category_id' => $this->category_id,
                    'sku' => $this->sku ?: null,
                    'name' => $this->name,
                    'type' => $this->type,
                    'stock_qty' => 0,
                    'min_stock_qty' => (float) $this->min_stock_qty,
                    'cost_usd' => $resaleCost,
                    'avg_stock_cost_usd' => $resaleCost,
                    'last_cost_usd' => $resaleCost,
                    'sale_price_usd' => (float) $this->sale_price_usd,
                    'is_sellable' => true,
                    'is_active' => $this->is_active,
                ]);

                $initQty = (float) $this->initial_stock_qty;
                if ($initQty > 0) {
                    $inventoryService->recordProductMovement(
                        $product,
                        $initQty,
                        'adjustment_in',
                        $resaleCost,
                        'Product',
                        $product->id,
                        Auth::id(),
                        'Stock inicial'
                    );
                }
            }

            if ($this->type === 'manufactured') {
                ProductSubRecipe::query()->updateOrCreate(
                    ['product_id' => $product->id, 'role' => 'dough'],
                    ['sub_recipe_id' => $this->dough_sub_recipe_id, 'portions_factor' => (float) $this->dough_factor]
                );
                ProductSubRecipe::query()->updateOrCreate(
                    ['product_id' => $product->id, 'role' => 'filling'],
                    ['sub_recipe_id' => $this->filling_sub_recipe_id, 'portions_factor' => (float) $this->filling_factor]
                );

                $costCascadeService->recalculateProductCost($product, 'recipe_update', $product->id);
            } else {
                $product->subRecipeLinks()->delete();
            }
        });

        $this->showModal = false;
        $this->dispatch('notify', type: 'success', message: 'Producto guardado y margen actualizado.');
    }

    public function openHistory(int $id): void
    {
        $this->historyProductId = $id;
        $this->showHistoryModal = true;
    }

    public function deleteProduct(int $id): void
    {
        $product = Product::query()->findOrFail($id);

        $hasHistory = $this->productHasSales($product->id)
            || $product->productionBatches()->exists()
            || PurchaseItem::query()->where('purchasable_type', 'product')->where('purchasable_id', $product->id)->exists();

        if ($hasHistory) {
            $this->dispatch('notify', type: 'error', message: "No se puede eliminar '{$product->name}' porque ya tiene ventas, compras o producción registrada. Puedes desactivarlo desde Editar.");

            return;
        }

        DB::transaction(function () use ($product) {
            InventoryMovement::query()
                ->where('inventoriable_type', 'product')
                ->where('inventoriable_id', $product->id)
                ->delete();

            CostHistory::query()
                ->where('costable_type', 'product')
                ->where('costable_id', $product->id)
                ->delete();

            $product->delete();
        });

        $this->dispatch('notify', type: 'success', message: "Producto '{$product->name}' eliminado.");
    }

    protected function productHasSales(int $productId): bool
    {
        return SaleItem::query()->where('product_id', $productId)->exists()
            || SaleComboSelection::query()->where('product_id', $productId)->exists();
    }

    public function createCategory(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }
        $cat = Category::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'sort_order' => 10]
        );
        $this->category_id = $cat->id;
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'subRecipeLinks.subRecipe'])
            ->when($this->filterType !== 'all', fn ($q) => $q->where('type', $this->filterType))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $histories = $this->showHistoryModal && $this->historyProductId
            ? CostHistory::query()
                ->where('costable_type', 'product')
                ->where('costable_id', $this->historyProductId)
                ->latest('id')
                ->limit(25)
                ->get()
            : collect();

        return view('livewire.products.products-manager', [
            'products' => $products,
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'doughs' => SubRecipe::query()->where('type', 'dough')->where('is_active', true)->orderBy('name')->get(),
            'fillings' => SubRecipe::query()->where('type', 'filling')->where('is_active', true)->orderBy('name')->get(),
            'histories' => $histories,
            'historyProduct' => $this->historyProductId ? Product::query()->find($this->historyProductId) : null,
        ]);
    }
}
