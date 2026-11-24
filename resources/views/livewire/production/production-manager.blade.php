<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Módulo de Producción (Lotes)</h1>
        <p class="text-xs text-slate-500">Descuenta automáticamente la materia prima de Masa y Relleno y suma pasteles listos al inventario</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 mb-4">Nueva Orden de Producción</h2>
            <form wire:submit="executeProduction" class="space-y-4 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Pastel a Producir</label>
                    <select wire:model.live="product_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} (Stock actual: {{ (int)$p->stock_qty }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Cant. Planificada</label>
                        <input type="number" min="1" wire:model.live="planned_qty" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Und. Aprobadas</label>
                        <input type="number" min="1" wire:model="produced_qty" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Merma (und)</label>
                        <input type="number" min="0" wire:model="waste_qty" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Notas del Lote</label>
                    <input type="text" wire:model="notes" placeholder="Ej. Turno mañana / Lote de apertura" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                @if($preview)
                    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 flex justify-between items-center">
                        <div>
                            <div class="text-slate-500">Costo estimado del lote</div>
                            <div class="text-base font-extrabold text-slate-900">{{ \App\Support\CurrencyHelper::format($preview['estimated_total_cost_usd'], $currency, $rate) }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-slate-500">Costo unitario</div>
                            <div class="text-sm font-bold text-indigo-700">{{ \App\Support\CurrencyHelper::format($preview['estimated_unit_cost_usd'], $currency, $rate, 4) }}</div>
                        </div>
                    </div>
                @endif

                <button type="submit" class="w-full py-2.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold shadow-sm">
                    Ejecutar Producción y Descontar Materia Prima
                </button>
            </form>
        </div>

        <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 mb-3">Requerimiento de Materia Prima (Masa + Relleno)</h2>

            @if($previewError)
                <div class="p-3 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold">{{ $previewError }}</div>
            @elseif($preview)
                @if($preview['has_shortages'])
                    <div class="mb-3 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">
                        Atención: Hay insumos con stock insuficiente para este lote.
                    </div>
                @endif
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase">
                            <tr>
                                <th class="px-3 py-2">Insumo</th>
                                <th class="px-3 py-2">Requerido</th>
                                <th class="px-3 py-2">Stock Disponible</th>
                                <th class="px-3 py-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($preview['aggregated'] as $agg)
                                <tr>
                                    <td class="px-3 py-2 font-semibold text-slate-800">{{ $agg['name'] }}</td>
                                    <td class="px-3 py-2 font-bold">{{ number_format($agg['required_qty'], 2) }} {{ $agg['unit_symbol'] }}</td>
                                    <td class="px-3 py-2">{{ number_format($agg['stock_qty'], 2) }} {{ $agg['unit_symbol'] }}</td>
                                    <td class="px-3 py-2">
                                        @if($agg['shortage_qty'] > 0)
                                            <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-700 font-bold">Falta {{ number_format($agg['shortage_qty'], 2) }} {{ $agg['unit_symbol'] }}</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 font-semibold">Disponible</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-200 font-bold text-sm text-slate-900">Historial de Lotes Producidos</div>
        <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-500 uppercase">
                <tr>
                    <th class="px-4 py-2.5">Código Lote</th>
                    <th class="px-4 py-2.5">Producto</th>
                    <th class="px-4 py-2.5">Producido</th>
                    <th class="px-4 py-2.5">Costo Total</th>
                    <th class="px-4 py-2.5">Costo Unitario</th>
                    <th class="px-4 py-2.5">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($batches as $b)
                    <tr>
                        <td class="px-4 py-2.5 font-mono font-semibold">{{ $b->batch_code }}</td>
                        <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $b->product?->name }}</td>
                        <td class="px-4 py-2.5 font-bold text-emerald-700">+{{ $b->produced_qty }} und @if($b->waste_qty > 0) <span class="text-rose-500">({{ $b->waste_qty }} merma)</span> @endif</td>
                        <td class="px-4 py-2.5">{{ \App\Support\CurrencyHelper::format((float)$b->total_cost_usd, $currency, $rate) }}</td>
                        <td class="px-4 py-2.5 font-semibold">{{ \App\Support\CurrencyHelper::format((float)$b->unit_cost_usd, $currency, $rate, 4) }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $b->produced_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Sin lotes registrados aún.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
