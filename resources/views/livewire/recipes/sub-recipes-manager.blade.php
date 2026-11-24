<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Sub-Recetas / Escandallos (Masa + Relleno)</h1>
            <p class="text-xs text-slate-500">Define el rendimiento por lote y los ingredientes de cada masa y relleno independiente</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="openCreate('dough')" class="px-3.5 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold">+ Nueva Masa</button>
            <button wire:click="openCreate('filling')" class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">+ Nuevo Relleno</button>
        </div>
    </div>

    <div class="flex gap-2 text-xs">
        <button wire:click="$set('activeTab', 'all')" class="px-3 py-1.5 rounded-lg font-semibold {{ $activeTab === 'all' ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">Todas</button>
        <button wire:click="$set('activeTab', 'dough')" class="px-3 py-1.5 rounded-lg font-semibold {{ $activeTab === 'dough' ? 'bg-amber-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">Masas</button>
        <button wire:click="$set('activeTab', 'filling')" class="px-3 py-1.5 rounded-lg font-semibold {{ $activeTab === 'filling' ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">Rellenos</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($recipes as $recipe)
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $recipe->type === 'dough' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800' }}">
                                {{ $recipe->typeLabel() }}
                            </span>
                            <h3 class="text-sm font-bold text-slate-900 mt-1">{{ $recipe->name }}</h3>
                        </div>
                        <button wire:click="openEdit({{ $recipe->id }})" class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700">Editar</button>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mt-3 p-2.5 rounded-lg bg-slate-50 text-xs">
                        <div>
                            <div class="text-[10px] text-slate-500">Rendimiento</div>
                            <div class="font-bold text-slate-800">{{ number_format((float)$recipe->yield_units, 0) }} und</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-500">Costo Lote</div>
                            <div class="font-bold text-slate-800">{{ \App\Support\CurrencyHelper::format((float)$recipe->total_cost_usd, $currency, $rate) }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-slate-500">Costo / Pastel</div>
                            <div class="font-bold text-emerald-700">{{ \App\Support\CurrencyHelper::format((float)$recipe->unit_cost_usd, $currency, $rate, 4) }}</div>
                        </div>
                    </div>

                    <div class="mt-3 space-y-1 text-xs">
                        @foreach($recipe->items as $item)
                            <div class="flex justify-between text-slate-600 border-b border-slate-100 pb-1 last:border-0">
                                <span>{{ $item->rawMaterial?->name }} ({{ number_format((float)$item->quantity, 2) }} {{ $item->unit?->symbol }})</span>
                                <span class="font-medium text-slate-800">{{ \App\Support\CurrencyHelper::format((float)$item->line_cost_usd, $currency, $rate, 4) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-slate-200 p-8 text-center text-xs text-slate-400">
                No hay sub-recetas registradas en esta categoría.
            </div>
        @endforelse
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full p-5 max-h-[90vh] overflow-y-auto text-xs">
                <h3 class="text-base font-bold text-slate-900">{{ $editingId ? 'Editar Sub-Receta' : 'Nueva Sub-Receta' }}</h3>
                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-slate-700 mb-1">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Ej. Masa Crujiente / Relleno Carne Molida" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Tipo</label>
                            <select wire:model="type" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="dough">Masa</option>
                                <option value="filling">Relleno</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Rendimiento (pasteles por lote)</label>
                            <input type="number" step="0.01" wire:model="yield_units" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Notas</label>
                            <input type="text" wire:model="notes" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-800">Ingredientes del Lote</span>
                            <button type="button" wire:click="addIngredient" class="px-2.5 py-1 rounded bg-indigo-50 text-indigo-700 font-semibold">+ Ingrediente</button>
                        </div>
                        @foreach($ingredients as $idx => $row)
                            <div class="grid grid-cols-12 gap-2 items-center bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                                <div class="col-span-6">
                                    <select wire:model="ingredients.{{ $idx }}.raw_material_id" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                        @foreach($rawMaterials as $rm)
                                            <option value="{{ $rm->id }}">{{ $rm->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <input type="number" step="0.01" wire:model="ingredients.{{ $idx }}.quantity" placeholder="Cant." class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5">
                                </div>
                                <div class="col-span-2">
                                    <select wire:model="ingredients.{{ $idx }}.unit_id" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5">
                                        @foreach($units as $u)
                                            <option value="{{ $u->id }}">{{ $u->symbol }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-1 text-right">
                                    <button type="button" wire:click="removeIngredient({{ $idx }})" class="px-2 py-1 rounded bg-rose-50 text-rose-600 font-bold">&times;</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="$set('showModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 font-semibold">Cancelar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Guardar y Recalcular</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
