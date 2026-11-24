<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Materia Prima & Kardex</h1>
            <p class="text-xs text-slate-500">Control de ingredientes base, stock mínimo y costo por unidad de consumo</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar insumo o SKU..."
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none">
            <button wire:click="openCreate" type="button" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
                + Nuevo Insumo
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-500 uppercase border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Insumo</th>
                    <th class="px-4 py-3">Stock Actual</th>
                    <th class="px-4 py-3">Stock Mínimo</th>
                    <th class="px-4 py-3">Costo Prom. (Base)</th>
                    <th class="px-4 py-3">Último Costo (Base)</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($materials as $rm)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $rm->name }}</div>
                            <div class="text-[11px] text-slate-400">SKU: {{ $rm->sku ?? '—' }} • Compra: {{ $rm->defaultPurchaseUnit?->symbol }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-1 rounded-full font-bold {{ $rm->isLowStock() ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ number_format((float)$rm->stock_qty, 2) }} {{ $rm->baseUnit?->symbol }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ number_format((float)$rm->min_stock_qty, 2) }} {{ $rm->baseUnit?->symbol }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-800">
                            {{ \App\Support\CurrencyHelper::format((float)$rm->avg_cost_usd, $currency, $rate, 6) }} / {{ $rm->baseUnit?->symbol }}
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ \App\Support\CurrencyHelper::format((float)$rm->last_cost_usd, $currency, $rate, 6) }} / {{ $rm->baseUnit?->symbol }}
                        </td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <button wire:click="openAdjust({{ $rm->id }})" class="px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 hover:bg-amber-100 font-semibold">Ajustar</button>
                            <button wire:click="openKardex({{ $rm->id }})" class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold">Kardex</button>
                            <button wire:click="openEdit({{ $rm->id }})" class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-semibold">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay materia prima registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Create/Edit Modal --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-5">
                <h3 class="text-base font-bold text-slate-900">{{ $editingId ? 'Editar Materia Prima' : 'Nueva Materia Prima' }}</h3>
                <form wire:submit="save" class="mt-4 space-y-3 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">SKU</label>
                            <input type="text" wire:model="sku" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('sku') <span class="text-rose-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-slate-700 mb-1">Nombre del insumo</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                            @error('name') <span class="text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Unidad Base (Recetas)</label>
                            <select wire:model="base_unit_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->symbol }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Unidad Compra por defecto</label>
                            <select wire:model="default_purchase_unit_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->symbol }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Stock Mínimo (base)</label>
                            <input type="number" step="0.01" wire:model="min_stock_qty" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                        @if(!$editingId)
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Stock Inicial (base)</label>
                                <input type="number" step="0.01" wire:model="initial_stock_qty" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Costo Inicial USD (base)</label>
                                <input type="number" step="0.000001" wire:model="initial_cost_usd" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 pt-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-700 font-semibold">Cancelar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Adjust Modal --}}
    @if($showAdjustModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 text-xs">
                <h3 class="text-base font-bold text-slate-900">Ajuste / Merma de Inventario</h3>
                <form wire:submit="saveAdjustment" class="mt-4 space-y-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Tipo de movimiento</label>
                        <select wire:model="adjustType" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="adjustment_in">Entrada / Ajuste Positivo (+)</option>
                            <option value="adjustment_out">Salida / Ajuste Negativo (-)</option>
                            <option value="waste">Merma / Desperdicio (-)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Cantidad (en unidad base)</label>
                        <input type="number" step="0.01" wire:model="adjustQty" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @error('adjustQty') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Motivo</label>
                        <input type="text" wire:model="adjustNotes" placeholder="Ej. Conteo físico o merma de cocina" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @error('adjustNotes') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showAdjustModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 font-semibold">Cancelar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 text-white font-semibold">Aplicar Ajuste</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Kardex Modal --}}
    @if($showKardexModal && $kardexMaterial)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full p-5 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-slate-900">Kardex: {{ $kardexMaterial->name }} ({{ $kardexMaterial->baseUnit?->symbol }})</h3>
                    <button wire:click="$set('showKardexModal', false)" class="text-slate-400 hover:text-slate-700 text-lg">&times;</button>
                </div>
                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase">
                            <tr>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Tipo</th>
                                <th class="px-3 py-2">Variación</th>
                                <th class="px-3 py-2">Saldo Final</th>
                                <th class="px-3 py-2">Nota</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($kardexMovements as $mov)
                                <tr>
                                    <td class="px-3 py-2 text-slate-500">{{ $mov->created_at->format('d/m H:i') }}</td>
                                    <td class="px-3 py-2 font-semibold">{{ $mov->movement_type }}</td>
                                    <td class="px-3 py-2 font-bold {{ $mov->qty_delta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $mov->qty_delta >= 0 ? '+' : '' }}{{ number_format((float)$mov->qty_delta, 2) }}
                                    </td>
                                    <td class="px-3 py-2 font-semibold">{{ number_format((float)$mov->stock_after, 2) }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $mov->notes }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-4 text-center text-slate-400">Sin movimientos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
