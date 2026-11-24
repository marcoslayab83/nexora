<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Reporte de Rentabilidad Real</h1>
            <p class="text-xs text-slate-500">Ganancia neta exacta calculada sobre el costo histórico de cada pastel, bebida y combo mixto vendido</p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <input type="date" wire:model.live="startDate" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5">
            <span class="text-slate-400">a</span>
            <input type="date" wire:model.live="endDate" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-xs text-slate-500">Ingresos Brutos ({{ $salesCount }} ventas)</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ \App\Support\CurrencyHelper::format($revenue, $currency, $rate) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-xs text-slate-500">Costo Real Acumulado</div>
            <div class="text-2xl font-extrabold text-slate-800 mt-1">{{ \App\Support\CurrencyHelper::format($cost, $currency, $rate) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-xs text-slate-500">Ganancia Neta Real</div>
            <div class="text-2xl font-extrabold {{ $profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }} mt-1">{{ \App\Support\CurrencyHelper::format($profit, $currency, $rate) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-xs text-slate-500">Margen Neto del Período</div>
            <div class="text-2xl font-extrabold text-indigo-700 mt-1">{{ $margin }}%</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-200 font-bold text-sm text-slate-900">Rentabilidad por Producto Individual</div>
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr>
                        <th class="px-3 py-2">Producto</th>
                        <th class="px-3 py-2">Cant.</th>
                        <th class="px-3 py-2">Venta</th>
                        <th class="px-3 py-2">Costo</th>
                        <th class="px-3 py-2">Ganancia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($productBreakdown as $row)
                        <tr>
                            <td class="px-3 py-2 font-semibold text-slate-800">{{ $row->description }}</td>
                            <td class="px-3 py-2">{{ (int)$row->qty_sold }}</td>
                            <td class="px-3 py-2">{{ \App\Support\CurrencyHelper::format((float)$row->revenue_usd, $currency, $rate) }}</td>
                            <td class="px-3 py-2 text-slate-500">{{ \App\Support\CurrencyHelper::format((float)$row->cost_usd, $currency, $rate) }}</td>
                            <td class="px-3 py-2 font-bold text-emerald-600">{{ \App\Support\CurrencyHelper::format((float)$row->profit_usd, $currency, $rate) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-400">Sin ventas individuales en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
                <div class="px-4 py-3 border-b border-slate-200 font-bold text-sm text-slate-900">Desglose de Ítems Despachados dentro de Combos</div>
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-3 py-2">Sabor / Bebida</th>
                            <th class="px-3 py-2">Und. en Combos</th>
                            <th class="px-3 py-2">Costo Real Consumido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($comboFlavorsSummary as $cf)
                            <tr>
                                <td class="px-3 py-2 font-semibold">{{ $cf->product_name }}</td>
                                <td class="px-3 py-2 font-bold">{{ (int)$cf->qty_used }}</td>
                                <td class="px-3 py-2">{{ \App\Support\CurrencyHelper::format((float)$cf->total_cost_usd, $currency, $rate) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-6 text-center text-slate-400">Sin combos vendidos en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
                <div class="px-4 py-3 border-b border-slate-200 font-bold text-sm text-slate-900">Ingresos por Método de Pago</div>
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-3 py-2">Método</th>
                            <th class="px-3 py-2">Monto Original</th>
                            <th class="px-3 py-2">Equivalente</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($paymentBreakdown as $pb)
                            <tr>
                                <td class="px-3 py-2 font-semibold">{{ $pb->paymentMethod?->name }}</td>
                                <td class="px-3 py-2">{{ $pb->currency }} {{ number_format((float)$pb->total_original, 2) }}</td>
                                <td class="px-3 py-2 font-bold text-indigo-700">{{ \App\Support\CurrencyHelper::format((float)$pb->total_usd, $currency, $rate) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-6 text-center text-slate-400">Sin cobros registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-200 font-bold text-sm text-slate-900">Detalle de Rentabilidad por Combo Mixto Armado</div>
        <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-500 uppercase">
                <tr>
                    <th class="px-3 py-2">Ticket</th>
                    <th class="px-3 py-2">Combo</th>
                    <th class="px-3 py-2">Sabores Elegidos</th>
                    <th class="px-3 py-2">Precio Promo</th>
                    <th class="px-3 py-2">Costo Exacto</th>
                    <th class="px-3 py-2">Ganancia Real</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($comboSales as $cs)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $cs->sale?->invoice_number }}</td>
                        <td class="px-3 py-2 font-bold">{{ $cs->quantity }}x {{ $cs->description }}</td>
                        <td class="px-3 py-2 text-slate-600">
                            {{ $cs->comboSelections->map(fn($s) => "{$s->quantity}x {$s->product_name}")->implode(', ') }}
                        </td>
                        <td class="px-3 py-2 font-semibold">{{ \App\Support\CurrencyHelper::format((float)$cs->total_price_usd, $currency, $rate) }}</td>
                        <td class="px-3 py-2 text-slate-500">{{ \App\Support\CurrencyHelper::format((float)$cs->total_cost_usd, $currency, $rate) }}</td>
                        <td class="px-3 py-2 font-bold text-emerald-600">{{ \App\Support\CurrencyHelper::format((float)$cs->profit_usd, $currency, $rate) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-400">Sin combos vendidos en este rango.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
