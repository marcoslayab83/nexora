<?php

namespace App\Livewire\Inventory;

use App\Models\CostHistory;
use App\Models\InventoryMovement;
use App\Models\ProductionConsumption;
use App\Models\PurchaseItem;
use App\Models\RawMaterial;
use App\Models\SubRecipeItem;
use App\Models\Unit;
use App\Services\CostCascadeService;
use App\Services\InventoryService;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Materia Prima & Kardex')]
class RawMaterialsManager extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $sku = '';

    public string $name = '';

    public int $base_unit_id = 0;

    public int $default_purchase_unit_id = 0;

    public string $min_stock_qty = '0';

    public string $initial_stock_qty = '0';

    public string $initial_cost_usd = '0';

    public bool $is_active = true;

    public bool $hasOperations = false;

    // Adjustment modal
    public bool $showAdjustModal = false;

    public ?int $adjustItemId = null;

    public string $adjustType = 'adjustment_in';

    public string $adjustQty = '0';

    public string $adjustNotes = '';

    // Kardex modal
    public bool $showKardexModal = false;

    public ?int $kardexItemId = null;

    public function mount(): void
    {
        $this->refreshCurrency();
        $firstUnit = Unit::query()->first();
        if ($firstUnit) {
            $this->base_unit_id = $firstUnit->id;
            $this->default_purchase_unit_id = $firstUnit->id;
        }
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
        $this->reset(['editingId', 'sku', 'name', 'min_stock_qty', 'initial_stock_qty', 'initial_cost_usd', 'hasOperations']);
        $this->min_stock_qty = '0';
        $this->initial_stock_qty = '0';
        $this->initial_cost_usd = '0';
        $this->is_active = true;
        $this->hasOperations = false;
        $gram = Unit::query()->where('symbol', 'g')->first() ?? Unit::query()->first();
        $kg = Unit::query()->where('symbol', 'kg')->first() ?? $gram;
        $this->base_unit_id = $gram?->id ?? 0;
        $this->default_purchase_unit_id = $kg?->id ?? 0;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $rm = RawMaterial::query()->findOrFail($id);
        $this->editingId = $rm->id;
        $this->sku = (string) ($rm->sku ?? '');
        $this->name = $rm->name;
        $this->base_unit_id = $rm->base_unit_id;
        $this->default_purchase_unit_id = $rm->default_purchase_unit_id;
        $this->min_stock_qty = (string) $rm->min_stock_qty;
        $this->initial_cost_usd = (string) round((float) $rm->avg_cost_usd, 6);
        $this->is_active = $rm->is_active;
        $this->hasOperations = $this->rawMaterialHasOperations($rm->id);
        $this->showModal = true;
    }

    public function save(InventoryService $inventoryService, CostCascadeService $costCascadeService): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:40', 'unique:raw_materials,sku'.($this->editingId ? ','.$this->editingId : '')],
            'base_unit_id' => ['required', 'exists:units,id'],
            'default_purchase_unit_id' => ['required', 'exists:units,id'],
            'min_stock_qty' => ['required', 'numeric', 'min:0'],
            'initial_cost_usd' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($inventoryService, $costCascadeService) {
            if ($this->editingId) {
                $rm = RawMaterial::query()->findOrFail($this->editingId);
                $hasOps = $this->rawMaterialHasOperations($rm->id);

                $updateData = [
                    'sku' => $this->sku ?: null,
                    'name' => $this->name,
                    'base_unit_id' => $this->base_unit_id,
                    'default_purchase_unit_id' => $this->default_purchase_unit_id,
                    'min_stock_qty' => (float) $this->min_stock_qty,
                    'is_active' => $this->is_active,
                ];

                $costChanged = false;
                if (! $hasOps) {
                    $newCost = max(0, (float) $this->initial_cost_usd);
                    $oldCost = (float) $rm->avg_cost_usd;
                    if (abs($oldCost - $newCost) > 0.0000005) {
                        $updateData['avg_cost_usd'] = $newCost;
                        $updateData['last_cost_usd'] = $newCost;
                        $costChanged = true;

                        CostHistory::query()->create([
                            'costable_type' => 'raw_material',
                            'costable_id' => $rm->id,
                            'old_cost_usd' => $oldCost,
                            'new_cost_usd' => $newCost,
                            'trigger_source' => 'initial_cost_edit',
                            'reference_id' => $rm->id,
                            'notes' => 'Edición de costo inicial de materia prima',
                            'created_at' => now(),
                        ]);
                    }
                }

                $rm->update($updateData);

                if ($costChanged) {
                    $costCascadeService->recalculateFromRawMaterial($rm->fresh(), 'initial_cost_edit', $rm->id);
                }
            } else {
                $initCost = max(0, (float) $this->initial_cost_usd);
                $rm = RawMaterial::query()->create([
                    'sku' => $this->sku ?: null,
                    'name' => $this->name,
                    'base_unit_id' => $this->base_unit_id,
                    'default_purchase_unit_id' => $this->default_purchase_unit_id,
                    'stock_qty' => 0,
                    'min_stock_qty' => (float) $this->min_stock_qty,
                    'avg_cost_usd' => $initCost,
                    'last_cost_usd' => $initCost,
                    'is_active' => $this->is_active,
                ]);

                $initQty = (float) $this->initial_stock_qty;
                if ($initQty > 0) {
                    $inventoryService->recordRawMaterialMovement(
                        $rm,
                        $initQty,
                        'adjustment_in',
                        $initCost,
                        'RawMaterial',
                        $rm->id,
                        Auth::id(),
                        'Inventario inicial'
                    );
                }
            }
        });

        $this->showModal = false;
        $this->dispatch('notify', type: 'success', message: 'Materia prima guardada correctamente.');
    }

    public function openAdjust(int $id): void
    {
        $this->adjustItemId = $id;
        $this->adjustType = 'adjustment_in';
        $this->adjustQty = '0';
        $this->adjustNotes = '';
        $this->showAdjustModal = true;
    }

    public function saveAdjustment(InventoryService $inventoryService): void
    {
        $this->validate([
            'adjustQty' => ['required', 'numeric', 'gt:0'],
            'adjustNotes' => ['required', 'string', 'max:200'],
        ]);

        try {
            DB::transaction(function () use ($inventoryService) {
                $rm = RawMaterial::query()->lockForUpdate()->findOrFail($this->adjustItemId);
                $qty = (float) $this->adjustQty;
                $delta = $this->adjustType === 'adjustment_in' ? $qty : -$qty;

                $inventoryService->recordRawMaterialMovement(
                    $rm,
                    $delta,
                    $this->adjustType,
                    (float) $rm->avg_cost_usd,
                    'Adjustment',
                    $rm->id,
                    Auth::id(),
                    $this->adjustNotes
                );
            });

            $this->showAdjustModal = false;
            $this->dispatch('notify', type: 'success', message: 'Ajuste registrado en Kardex.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function openKardex(int $id): void
    {
        $this->kardexItemId = $id;
        $this->showKardexModal = true;
    }

    public function deleteRawMaterial(int $id): void
    {
        $rm = RawMaterial::query()->findOrFail($id);

        $inUse = SubRecipeItem::query()->where('raw_material_id', $rm->id)->exists()
            || $this->rawMaterialHasOperations($rm->id);

        if ($inUse) {
            $this->dispatch('notify', type: 'error', message: "No se puede eliminar '{$rm->name}' porque está vinculado a sub-recetas, compras o producción. Puedes desactivarlo desde Editar.");

            return;
        }

        DB::transaction(function () use ($rm) {
            InventoryMovement::query()
                ->where('inventoriable_type', 'raw_material')
                ->where('inventoriable_id', $rm->id)
                ->delete();

            CostHistory::query()
                ->where('costable_type', 'raw_material')
                ->where('costable_id', $rm->id)
                ->delete();

            $rm->delete();
        });

        $this->dispatch('notify', type: 'success', message: "Materia prima '{$rm->name}' eliminada.");
    }

    protected function rawMaterialHasOperations(int $rawMaterialId): bool
    {
        return PurchaseItem::query()->where('purchasable_type', 'raw_material')->where('purchasable_id', $rawMaterialId)->exists()
            || ProductionConsumption::query()->where('raw_material_id', $rawMaterialId)->exists();
    }

    public function render()
    {
        $materials = RawMaterial::query()
            ->with(['baseUnit', 'defaultPurchaseUnit'])
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('sku', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();

        $kardexMovements = $this->showKardexModal && $this->kardexItemId
            ? InventoryMovement::query()
                ->where('inventoriable_type', 'raw_material')
                ->where('inventoriable_id', $this->kardexItemId)
                ->with('user')
                ->latest('id')
                ->limit(40)
                ->get()
            : collect();

        return view('livewire.inventory.raw-materials-manager', [
            'materials' => $materials,
            'units' => Unit::query()->orderBy('name')->get(),
            'kardexMovements' => $kardexMovements,
            'kardexMaterial' => $this->kardexItemId ? RawMaterial::query()->with('baseUnit')->find($this->kardexItemId) : null,
        ]);
    }
}
