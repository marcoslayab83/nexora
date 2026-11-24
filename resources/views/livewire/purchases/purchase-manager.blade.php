<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Compras & Abastecimiento</h1>
            <p class="text-xs text-slate-500">Al registrar compras de materia prima, el sistema recalcula en cascada los costos de sub-recetas y pasteles</p>
        </div>
        <button wire:click="$set('showSupplierModal', true)" type="button" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
            + Nuevo Proveedor
        </button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <h2 class="text-sm font-bold text-slate-900 mb-4">Registrar Factura de Compra</h2>
        <form wire:submit="savePurchase" class="space-y-4 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Proveedor</label>
                    <select wire:model="supplier_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">-- Compra directa / Sin proveedor --</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nro. Factura / Nota</label>
                    <input type="text" wire:model="invoice_number" placeholder="FAC-00123" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Moneda de Factura</label>
                    <select wire:model.live="purchase_currency" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="USD">Dólares (USD)</option>
                        <option value="VES">Bolívares (VES)</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Tasa BCV aplicada (Bs/$)</label>
                    <input type="number" step="0.0001" wire:model.live="exchange_rate_bs" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-700">Ítems de la Compra</span>
                    <button type="button" wire:click="addLine" class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 font-semibold hover:bg-indigo-100">
                        + Agregar Línea
                    </button>
                </div>

                @foreach($items as $idx => $item)
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tipo</label>
                            <select wire:model.live="items.{{ $idx }}.purchasable_type" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                <option value="raw_material">Materia Prima</option>
                                <option value="product">Reventa</option>
                            </select>
                        </div>
                        <div class="md:col-span-4">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Artículo</label>
                            <select wire:model.live="items.{{ $idx }}.purchasable_id" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                @if(($item['purchasable_type'] ?? 'raw_material') === 'raw_material')
                                    @foreach($rawMaterials as $rm)
                                        <option value="{{ $rm->id }}">{{ $rm->name }}</option>
                                    @endforeach
                                @else
                                    @foreach($resaleProducts as $prod)
                                        <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Unidad</label>
                            <select wire:model="items.{{ $idx }}.unit_id" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->symbol }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Cant.</label>
                            <input type="number" step="0.01" wire:model.live="items.{{ $idx }}.qty_purchased" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Costo Unit. ({{ $purchase_currency }})</label>
                            <input type="number" step="0.0001" wire:model.live="items.{{ $idx }}.unit_cost_original" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                        </div>
                        <div class="md:col-span-1 flex justify-end">
                            <button type="button" wire:click="removeLine({{ $idx }})" class="px-2.5 py-1.5 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 font-bold">&times;</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
                <input type="text" wire:model="notes" placeholder="Observaciones opcionales..." class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-sm">
                    Registrar Compra y Recalcular Costos
                </button>
            </div>
        </form>
    </div>

    {{-- Recent Purchases --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-200 font-bold text-sm text-slate-900">Compras Recientes</div>
        <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-500 uppercase">
                <tr>
                    <th class="px-4 py-2.5">Fecha</th>
                    <th class="px-4 py-2.5">Factura</th>
                    <th class="px-4 py-2.5">Proveedor</th>
                    <th class="px-4 py-2.5">Ítems</th>
                    <th class="px-4 py-2.5">Total Factura</th>
                    <th class="px-4 py-2.5">Equivalente</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($recentPurchases as $p)
                    <tr>
                        <td class="px-4 py-2.5 text-slate-500">{{ $p->purchased_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2.5 font-mono font-semibold">{{ $p->invoice_number ?? '#'.$p->id }}</td>
                        <td class="px-4 py-2.5">{{ $p->supplier?->name ?? 'Directa' }}</td>
                        <td class="px-4 py-2.5">{{ $p->items->count() }}</td>
                        <td class="px-4 py-2.5 font-semibold">{{ $p->currency }} {{ number_format((float)$p->total_original, 2) }}</td>
                        <td class="px-4 py-2.5 font-bold text-indigo-700">{{ \App\Support\CurrencyHelper::format((float)$p->total_usd, $currency, $rate) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Sin compras registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showSupplierModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 text-xs">
                <h3 class="text-base font-bold text-slate-900">Nuevo Proveedor</h3>
                <form wire:submit="saveSupplier" class="mt-4 space-y-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Razón Social / Nombre</label>
                        <input type="text" wire:model="supName" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">RIF / Cédula</label>
                            <input type="text" wire:model="supTaxId" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Teléfono</label>
                            <input type="text" wire:model="supPhone" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showSupplierModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 font-semibold">Cancelar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
