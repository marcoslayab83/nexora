<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Control de Caja y Arqueo (VES / USD)</h1>
        <p class="text-xs text-slate-500">Apertura obligatoria con fondo inicial, movimientos de efectivo y cierre totalizado por turno</p>
    </div>

    @if(!$openSession)
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm max-w-lg">
            <h2 class="text-base font-bold text-slate-900">Abrir Jornada de Caja</h2>
            <p class="text-xs text-slate-500 mt-1">Registra el fondo inicial de sencillo en Bolívares y Dólares para habilitar el POS.</p>
            <form wire:submit="openSession" class="mt-4 space-y-3 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Caja Registradora</label>
                    <select wire:model="cash_register_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($registers as $reg)
                            <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Fondo Inicial (Bs.)</label>
                        <input type="number" step="0.01" wire:model="opening_bs" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Fondo Inicial (USD $)</label>
                        <input type="number" step="0.01" wire:model="opening_usd" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    </div>
                </div>
                <button type="submit" class="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-sm">
                    Abrir Turno de Caja
                </button>
            </form>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="text-xs text-slate-500">Efectivo Esperado en Bs.</div>
                <div class="text-xl font-extrabold text-slate-900 mt-1">Bs. {{ number_format((float)$openSession->expected_cash_bs, 2, ',', '.') }}</div>
                <div class="text-[11px] text-slate-400">Fondo inicial: Bs. {{ number_format((float)$openSession->opening_bs, 2, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="text-xs text-slate-500">Efectivo Esperado en USD</div>
                <div class="text-xl font-extrabold text-slate-900 mt-1">${{ number_format((float)$openSession->expected_cash_usd, 2) }}</div>
                <div class="text-[11px] text-slate-400">Fondo inicial: ${{ number_format((float)$openSession->opening_usd, 2) }}</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="text-xs text-slate-500">Ventas Brutas Turno ({{ $summary['sales_count'] }})</div>
                <div class="text-xl font-extrabold text-indigo-700 mt-1">{{ \App\Support\CurrencyHelper::format($summary['total_usd'], $currency, $rate) }}</div>
                <div class="text-[11px] text-slate-400">Costo merc.: {{ \App\Support\CurrencyHelper::format($summary['total_cost_usd'], $currency, $rate) }}</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="text-xs text-slate-500">Utilidad Estimada Turno</div>
                <div class="text-xl font-extrabold text-emerald-600 mt-1">{{ \App\Support\CurrencyHelper::format($summary['net_profit_usd'], $currency, $rate) }}</div>
                <div class="text-[11px] text-slate-400">Abierto: {{ $openSession->opened_at->format('d/m H:i') }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Breakdown by payment method --}}
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-xs">
                <h3 class="text-sm font-bold text-slate-900 mb-3">Recaudación por Método de Pago</h3>
                <div class="space-y-2">
                    @forelse($summary['payments_by_method'] as $pm)
                        <div class="flex items-center justify-between py-2 border-b border-slate-100">
                            <span class="font-semibold text-slate-700">{{ $pm->paymentMethod?->name }}</span>
                            <div class="text-right">
                                <div class="font-bold text-slate-900">{{ $pm->currency }} {{ number_format((float)$pm->total_original, 2) }}</div>
                                <div class="text-[10px] text-slate-400">Eq. ${{ number_format((float)$pm->total_usd, 2) }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-slate-400">Aún no hay cobros en este turno.</p>
                    @endforelse
                </div>
            </div>

            {{-- Cash movements --}}
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-xs">
                <h3 class="text-sm font-bold text-slate-900 mb-3">Entradas / Salidas de Caja</h3>
                <form wire:submit="registerMovement" class="space-y-2.5">
                    <div class="grid grid-cols-3 gap-2">
                        <select wire:model="movement_type" class="rounded-lg border border-slate-300 px-2 py-1.5">
                            <option value="out">Salida (-)</option>
                            <option value="in">Entrada (+)</option>
                        </select>
                        <select wire:model="movement_currency" class="rounded-lg border border-slate-300 px-2 py-1.5">
                            <option value="VES">Bs.</option>
                            <option value="USD">USD</option>
                        </select>
                        <input type="number" step="0.01" wire:model="movement_amount" placeholder="Monto" class="rounded-lg border border-slate-300 px-2 py-1.5">
                    </div>
                    <div class="flex gap-2">
                        <input type="text" wire:model="movement_reason" placeholder="Motivo (ej. pago hielo o retiro)" class="flex-1 rounded-lg border border-slate-300 px-2.5 py-1.5">
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-800 text-white font-semibold">Registrar</button>
                    </div>
                </form>

                <div class="mt-4 space-y-1.5 max-h-40 overflow-y-auto">
                    @foreach($openSession->movements as $mov)
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span>{{ $mov->reason }}</span>
                            <span class="font-bold {{ $mov->type === 'in' ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $mov->type === 'in' ? '+' : '-' }}{{ $mov->currency }} {{ number_format((float)$mov->amount, 2) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Close Shift --}}
            <div class="bg-white rounded-xl border border-rose-200 p-5 shadow-sm text-xs">
                <h3 class="text-sm font-bold text-rose-800 mb-3">Arqueo y Cierre de Jornada</h3>
                <form wire:submit="closeSession" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Efectivo Contado (Bs.)</label>
                            <input type="number" step="0.01" wire:model="counted_cash_bs" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Efectivo Contado (USD)</label>
                            <input type="number" step="0.01" wire:model="counted_cash_usd" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Notas de cierre</label>
                        <input type="text" wire:model="closing_notes" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold">
                        Cerrar Jornada y Generar Arqueo
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- History --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-200 font-bold text-sm text-slate-900">Cierres de Caja Recientes</div>
        <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-500 uppercase">
                <tr>
                    <th class="px-4 py-2.5">Cierre</th>
                    <th class="px-4 py-2.5">Responsable</th>
                    <th class="px-4 py-2.5">Ventas Brutas</th>
                    <th class="px-4 py-2.5">Costo Merc.</th>
                    <th class="px-4 py-2.5">Utilidad Est.</th>
                    <th class="px-4 py-2.5">Dif. Bs / USD</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($closedSessions as $cs)
                    <tr>
                        <td class="px-4 py-2.5 text-slate-600">{{ $cs->closed_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2.5 font-semibold">{{ $cs->user?->name }}</td>
                        <td class="px-4 py-2.5 font-bold">{{ \App\Support\CurrencyHelper::format((float)$cs->total_sales_usd, $currency, $rate) }}</td>
                        <td class="px-4 py-2.5">{{ \App\Support\CurrencyHelper::format((float)$cs->total_cost_usd, $currency, $rate) }}</td>
                        <td class="px-4 py-2.5 font-bold text-emerald-600">{{ \App\Support\CurrencyHelper::format((float)$cs->estimated_profit_usd, $currency, $rate) }}</td>
                        <td class="px-4 py-2.5">Bs {{ number_format((float)$cs->difference_bs, 2) }} / ${{ number_format((float)$cs->difference_usd, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Sin cierres previos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
