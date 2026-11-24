<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Panel General — NEXORA</h1>
            <p class="text-xs text-slate-500">Resumen operativo y financiero en tiempo real ({{ $currency === 'VES' ? 'Bolívares Bs.' : 'Dólares USD' }})</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('pos') }}" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm">
                Ir al Punto de Venta
            </a>
            @if(auth()->user()->isSupervisorOrAbove() || auth()->user()->role === 'kitchen')
                <a href="{{ route('production') }}" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold shadow-sm">
                    + Registrar Producción
                </a>
            @endif
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500">Ventas de Hoy ({{ $salesCount }} tickets)</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ \App\Support\CurrencyHelper::format($revenueToday, $currency, $rate) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">{{ \App\Support\CurrencyHelper::formatOpposite($revenueToday, $currency, $rate) }}</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500">Costo Real de Mercancía Vendida</div>
            <div class="text-2xl font-extrabold text-slate-800 mt-1">{{ \App\Support\CurrencyHelper::format($costToday, $currency, $rate) }}</div>
            <div class="text-[11px] text-slate-400 mt-1">Calculado con snapshot histórico</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500">Ganancia Neta Hoy</div>
            <div class="text-2xl font-extrabold {{ $profitToday >= 0 ? 'text-emerald-600' : 'text-rose-600' }} mt-1">
                {{ \App\Support\CurrencyHelper::format($profitToday, $currency, $rate) }}
            </div>
            <div class="text-[11px] text-emerald-700 font-semibold mt-1">Margen neto: {{ $marginToday }}%</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="text-xs font-medium text-slate-500">Producción Hoy</div>
            <div class="text-2xl font-extrabold text-amber-600 mt-1">{{ $unitsProduced }} und</div>
            <div class="text-[11px] text-slate-400 mt-1">{{ $batchesCount }} lotes registrados</div>
        </div>
    </div>

    {{-- Alerts + Recent Sales --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900 mb-3">Alertas de Materia Prima</h2>
                @forelse($lowRawMaterials as $rm)
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0 text-xs">
                        <span class="font-medium text-slate-800">{{ $rm->name }}</span>
                        <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 font-semibold">
                            {{ number_format((float)$rm->stock_qty, 2) }} {{ $rm->baseUnit?->symbol }}
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400">Sin insumos bajo el mínimo.</p>
                @endforelse
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900 mb-3">Alertas de Productos</h2>
                @forelse($lowProducts as $prod)
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0 text-xs">
                        <span class="font-medium text-slate-800">{{ $prod->name }}</span>
                        <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-semibold">
                            {{ (int)$prod->stock_qty }} und
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400">Todos los productos con stock saludable.</p>
                @endforelse
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-slate-900">Últimas Ventas Registradas</h2>
                <a href="{{ route('reports') }}" class="text-xs text-indigo-600 hover:underline font-medium">Ver reportes completos</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-3 py-2">Ticket</th>
                            <th class="px-3 py-2">Cajero</th>
                            <th class="px-3 py-2">Total</th>
                            <th class="px-3 py-2">Ganancia Neta</th>
                            <th class="px-3 py-2">Hora</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentSales as $sale)
                            <tr>
                                <td class="px-3 py-2 font-mono font-semibold text-slate-800">{{ $sale->invoice_number }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $sale->user?->name }}</td>
                                <td class="px-3 py-2 font-bold text-slate-900">{{ \App\Support\CurrencyHelper::format((float)$sale->total_usd, $currency, $rate) }}</td>
                                <td class="px-3 py-2 font-semibold text-emerald-600">{{ \App\Support\CurrencyHelper::format((float)$sale->net_profit_usd, $currency, $rate) }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $sale->sold_at->format('d/m H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-slate-400">Aún no se han registrado ventas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
