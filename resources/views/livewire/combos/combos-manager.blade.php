<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Combos Flexibles y Mixtos</h1>
            <p class="text-xs text-slate-500">Permite armar promociones dinámicas eligiendo distintos sabores/bebidas con control real de costos</p>
        </div>
        <button wire:click="openCreate" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
            + Nuevo Combo
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($combos as $combo)
            @php $range = $combo->costRangeUsd(); @endphp
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <h3 class="text-sm font-bold text-slate-900">{{ $combo->name }}</h3>
                                @if(!$combo->is_active)
                                    <span class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 text-[10px] font-bold">Inactivo</span>
                                @endif
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ $combo->description }}</p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <button wire:click="openEdit({{ $combo->id }})" class="px-2.5 py-1 rounded bg-slate-100 text-xs font-semibold text-slate-700 hover:bg-slate-200">Editar</button>
                            <button wire:click="deleteCombo({{ $combo->id }})" wire:confirm="¿Eliminar combo '{{ $combo->name }}'?" class="px-2.5 py-1 rounded bg-rose-50 text-xs font-semibold text-rose-700 hover:bg-rose-100">Eliminar</button>
                        </div>
                    </div>

                    <div class="mt-3 p-2.5 rounded-lg bg-indigo-50/70 border border-indigo-100 flex items-center justify-between text-xs">
                        <div>
                            <div class="text-[10px] text-indigo-600 font-semibold">Precio Promocional</div>
                            <div class="text-base font-extrabold text-indigo-900">{{ \App\Support\CurrencyHelper::format((float)$combo->promo_price_usd, $currency, $rate) }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] text-slate-500">Ganancia real estimada</div>
                            <div class="font-bold text-emerald-700">
                                {{ \App\Support\CurrencyHelper::format($range['min_profit_usd'], $currency, $rate) }} — {{ \App\Support\CurrencyHelper::format($range['max_profit_usd'], $currency, $rate) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-2 text-xs">
                        @foreach($combo->slots as $slot)
                            <div class="bg-slate-50 rounded-lg p-2 border border-slate-100">
                                <div class="font-semibold text-slate-800">{{ $slot->label }} ({{ $slot->required_qty }} und)</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    {{ $slot->options->map(fn($o) => $o->product?->name)->filter()->implode(', ') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-slate-200 p-8 text-center text-xs text-slate-400">
                No hay combos creados aún.
            </div>
        @endforelse
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full p-5 max-h-[90vh] overflow-y-auto text-xs">
                <h3 class="text-base font-bold text-slate-900">{{ $editingId ? 'Editar Combo' : 'Nuevo Combo Flexible' }}</h3>
                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-slate-700 mb-1">Nombre del Combo</label>
                            <input type="text" wire:model="name" placeholder="Ej. Combo Trío Pastelero" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Precio Promocional (USD)</label>
                            <input type="number" step="0.01" wire:model="promo_price_usd" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Descripción</label>
                        <input type="text" wire:model="description" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>

                    @if($editingId)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-indigo-600">
                            <span class="font-semibold text-slate-700">Combo activo en POS</span>
                        </label>
                    @endif

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-800">Secciones / Ranuras de Elección</span>
                            <button type="button" wire:click="addSlot" class="px-2.5 py-1 rounded bg-indigo-50 text-indigo-700 font-semibold">+ Agregar Sección</button>
                        </div>

                        @foreach($comboSlots as $sIdx => $slot)
                            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-3">
                                <div class="grid grid-cols-12 gap-2 items-end">
                                    <div class="col-span-7">
                                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Etiqueta de sección</label>
                                        <input type="text" wire:model="comboSlots.{{ $sIdx }}.label" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5" required>
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Cant. a elegir</label>
                                        <input type="number" min="1" wire:model="comboSlots.{{ $sIdx }}.required_qty" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5" required>
                                    </div>
                                    <div class="col-span-2 text-right">
                                        <button type="button" wire:click="removeSlot({{ $sIdx }})" class="px-2.5 py-1.5 rounded bg-rose-50 text-rose-600 font-bold">Quitar</button>
                                    </div>
                                </div>

                                <div>
                                    <div class="text-[11px] font-semibold text-slate-600 mb-1.5">Productos elegibles (marca los permitidos y recargo opcional en USD):</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-44 overflow-y-auto bg-white p-2 rounded border border-slate-200">
                                        @foreach($products as $prod)
                                            <div class="flex items-center justify-between gap-2 py-1 px-1.5 rounded hover:bg-slate-50">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="checkbox" wire:model="comboSlots.{{ $sIdx }}.selected_products.{{ $prod->id }}" class="rounded border-slate-300 text-indigo-600">
                                                    <span class="font-medium text-slate-800">{{ $prod->name }}</span>
                                                </label>
                                                <input type="number" step="0.01" min="0" placeholder="+USD"
                                                    wire:model="comboSlots.{{ $sIdx }}.extras.{{ $prod->id }}"
                                                    class="w-16 rounded border border-slate-200 px-1.5 py-0.5 text-[11px]">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="$set('showModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 font-semibold">Cancelar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Guardar Combo</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
