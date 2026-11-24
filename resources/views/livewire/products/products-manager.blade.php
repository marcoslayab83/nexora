<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Productos Terminados & Reventa</h1>
            <p class="text-xs text-slate-500">Pasteles (Masa + Relleno) y bebidas/snacks con cálculo automático de margen proyectado</p>
        </div>
        <button wire:click="openCreate" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
            + Nuevo Producto
        </button>
    </div>

    <div class="flex gap-2 text-xs">
        <button wire:click="$set('filterType', 'all')" class="px-3 py-1.5 rounded-lg font-semibold {{ $filterType === 'all' ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">Todos</button>
        <button wire:click="$set('filterType', 'manufactured')" class="px-3 py-1.5 rounded-lg font-semibold {{ $filterType === 'manufactured' ? 'bg-amber-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">Producción Propia (Pasteles)</button>
        <button wire:click="$set('filterType', 'resale')" class="px-3 py-1.5 rounded-lg font-semibold {{ $filterType === 'resale' ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">Reventa (Bebidas / Snacks)</button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-500 uppercase border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Fórmula / Tipo</th>
                    <th class="px-4 py-3">Stock</th>
                    <th class="px-4 py-3">Costo Unitario</th>
                    <th class="px-4 py-3">Precio Venta</th>
                    <th class="px-4 py-3">Margen Proyectado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($products as $prod)
                    @php
                        $unitCost = $prod->effectiveUnitCostUsd();
                        $profit = $prod->projectedProfitUsd();
                        $margin = $prod->projectedMarginPercent();
                    @endphp
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $prod->name }}</div>
                            <div class="text-[11px] text-slate-400">{{ $prod->category?->name ?? 'General' }} • SKU: {{ $prod->sku ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($prod->type === 'manufactured')
                                <div class="space-y-0.5 text-[11px]">
                                    @foreach($prod->subRecipeLinks as $link)
                                        <div>
                                            <span class="font-semibold {{ $link->role === 'dough' ? 'text-amber-700' : 'text-indigo-700' }}">
                                                {{ $link->role === 'dough' ? 'Masa:' : 'Relleno:' }}
                                            </span>
                                            {{ $link->subRecipe?->name }}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold">Reventa</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-1 rounded-full font-bold {{ $prod->isLowStock() ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ (int)$prod->stock_qty }} und
                            </span>
                        </td>
                        <td class="px-4 py-3 font-semibold text-slate-800">
                            {{ \App\Support\CurrencyHelper::format($unitCost, $currency, $rate, 4) }}
                        </td>
                        <td class="px-4 py-3 font-bold text-slate-900">
                            {{ \App\Support\CurrencyHelper::format((float)$prod->sale_price_usd, $currency, $rate) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-bold {{ $profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ \App\Support\CurrencyHelper::format($profit, $currency, $rate) }} ({{ $margin }}%)
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <button wire:click="openHistory({{ $prod->id }})" class="px-2.5 py-1 rounded bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold">Historial</button>
                            <button wire:click="openEdit({{ $prod->id }})" class="px-2.5 py-1 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-semibold">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No hay productos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-xl w-full p-5 max-h-[90vh] overflow-y-auto text-xs">
                <h3 class="text-base font-bold text-slate-900">{{ $editingId ? 'Editar Producto' : 'Nuevo Producto' }}</h3>
                <form wire:submit="save" class="mt-4 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-slate-700 mb-1">Nombre del producto</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">SKU</label>
                            <input type="text" wire:model="sku" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Tipo</label>
                            <select wire:model.live="type" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="manufactured">Pastel (Producción)</option>
                                <option value="resale">Reventa (Bebida/Snack)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Categoría</label>
                            <select wire:model="category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Precio Venta (USD)</label>
                            <input type="number" step="0.01" wire:model="sale_price_usd" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>
                    </div>

                    @if($type === 'manufactured')
                        <div class="p-3 rounded-lg bg-amber-50/70 border border-amber-200 space-y-3">
                            <div class="font-bold text-amber-900">Composición de Sub-Recetas (Masa + Relleno)</div>
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                                <div class="sm:col-span-3">
                                    <label class="block font-semibold text-slate-700 mb-1">Masa</label>
                                    <select wire:model="dough_sub_recipe_id" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                        <option value="">-- Seleccionar Masa --</option>
                                        @foreach($doughs as $d)
                                            <option value="{{ $d->id }}">{{ $d->name }} (${{ number_format((float)$d->unit_cost_usd, 4) }}/und)</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Factor Masa</label>
                                    <input type="number" step="0.1" wire:model="dough_factor" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                                <div class="sm:col-span-3">
                                    <label class="block font-semibold text-slate-700 mb-1">Relleno</label>
                                    <select wire:model="filling_sub_recipe_id" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                        <option value="">-- Seleccionar Relleno --</option>
                                        @foreach($fillings as $f)
                                            <option value="{{ $f->id }}">{{ $f->name }} (${{ number_format((float)$f->unit_cost_usd, 4) }}/und)</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Factor Relleno</label>
                                    <input type="number" step="0.1" wire:model="filling_factor" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Stock Mínimo</label>
                            <input type="number" wire:model="min_stock_qty" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                        @if(!$editingId)
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Stock Inicial</label>
                                <input type="number" wire:model="initial_stock_qty" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            </div>
                            @if($type === 'resale')
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Costo Inicial USD</label>
                                    <input type="number" step="0.0001" wire:model="resale_cost_usd" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="$set('showModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 font-semibold">Cancelar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Guardar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showHistoryModal && $historyProduct)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full p-5 max-h-[85vh] flex flex-col text-xs">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-slate-900">Historial de Costos: {{ $historyProduct->name }}</h3>
                    <button wire:click="$set('showHistoryModal', false)" class="text-lg text-slate-400 hover:text-slate-700">&times;</button>
                </div>
                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase">
                            <tr>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Origen</th>
                                <th class="px-3 py-2">Costo Anterior</th>
                                <th class="px-3 py-2">Costo Nuevo</th>
                                <th class="px-3 py-2">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($histories as $h)
                                <tr>
                                    <td class="px-3 py-2 text-slate-500">{{ $h->created_at->format('d/m H:i') }}</td>
                                    <td class="px-3 py-2 font-semibold">{{ $h->trigger_source }}</td>
                                    <td class="px-3 py-2 text-slate-500">${{ number_format((float)$h->old_cost_usd, 4) }}</td>
                                    <td class="px-3 py-2 font-bold {{ $h->new_cost_usd > $h->old_cost_usd ? 'text-rose-600' : 'text-emerald-600' }}">${{ number_format((float)$h->new_cost_usd, 4) }}</td>
                                    <td class="px-3 py-2">{{ $h->notes }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-4 text-center text-slate-400">Sin variaciones registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
