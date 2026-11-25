<div class="space-y-4">
    @if(!$openSession)
        <div class="rounded-xl bg-amber-50 border border-amber-300 p-4 flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs text-amber-900">
                <span class="font-bold">Caja cerrada:</span> Debes registrar el fondo de apertura antes de procesar ventas en el POS.
            </div>
            <a href="{{ route('cash') }}" class="px-4 py-2 rounded-lg bg-amber-600 text-white text-xs font-bold hover:bg-amber-700">Ir a Abrir Caja</a>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        {{-- Catalog (Left 7 cols) --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-sm space-y-3">
                <div class="flex flex-wrap gap-2 items-center">
                    <input type="text" wire:model.live.debounce.200ms="search" placeholder="Buscar pastel, bebida o combo..."
                        class="flex-1 min-w-[180px] rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none">
                </div>
                <div class="flex flex-wrap gap-1.5 text-xs">
                    <button wire:click="$set('activeCategory', 'all')" class="px-3 py-1.5 rounded-lg font-semibold {{ $activeCategory === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' }}">Todos</button>
                    <button wire:click="$set('activeCategory', 'combos')" class="px-3 py-1.5 rounded-lg font-semibold {{ $activeCategory === 'combos' ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-800' }}">Combos Mixtos</button>
                    @foreach($categories as $cat)
                        <button wire:click="$set('activeCategory', '{{ $cat->id }}')" class="px-3 py-1.5 rounded-lg font-semibold {{ $activeCategory == $cat->id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700' }}">
                            {{ $cat->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Combos Grid --}}
            @if($combos->isNotEmpty())
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-amber-700 mb-2">Combos Promocionales (Armado Flexible)</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($combos as $combo)
                            <button wire:click="openComboBuilder({{ $combo->id }})" type="button"
                                class="text-left bg-gradient-to-br from-amber-50 to-white rounded-xl border-2 border-amber-200 hover:border-amber-400 p-3.5 shadow-sm transition">
                                <div class="text-[10px] font-bold uppercase text-amber-700">Combo Mixto</div>
                                <div class="font-bold text-slate-900 text-sm mt-0.5 leading-tight">{{ $combo->name }}</div>
                                <div class="mt-2 font-extrabold text-indigo-700 text-sm">{{ \App\Support\CurrencyHelper::format((float)$combo->promo_price_usd, $currency, $rate) }}</div>
                                <div class="text-[10px] text-slate-400">{{ \App\Support\CurrencyHelper::formatOpposite((float)$combo->promo_price_usd, $currency, $rate) }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Individual Products Grid --}}
            @if($products->isNotEmpty())
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Productos Individuales</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($products as $prod)
                            <button wire:click="addProduct({{ $prod->id }})" type="button"
                                class="text-left bg-white rounded-xl border border-slate-200 hover:border-indigo-400 p-3.5 shadow-sm transition">
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="font-semibold text-slate-400 uppercase">{{ $prod->type === 'manufactured' ? 'Pastel' : 'Reventa' }}</span>
                                    <span class="font-bold {{ $prod->stock_qty <= 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ (int)$prod->stock_qty }} disp</span>
                                </div>
                                <div class="font-bold text-slate-900 text-sm mt-1 leading-tight">{{ $prod->name }}</div>
                                <div class="mt-2 font-extrabold text-indigo-700 text-sm">{{ \App\Support\CurrencyHelper::format((float)$prod->sale_price_usd, $currency, $rate) }}</div>
                                <div class="text-[10px] text-slate-400">{{ \App\Support\CurrencyHelper::formatOpposite((float)$prod->sale_price_usd, $currency, $rate) }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Cart & Mixed Checkout (Right 5 cols) --}}
        <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h2 class="text-sm font-bold text-slate-900">Ticket Actual</h2>
                <span class="text-xs text-slate-500">Tasa: {{ number_format($rate, 2, ',', '.') }} Bs/$</span>
            </div>

            <div class="grid grid-cols-3 gap-2 text-xs">
                <select wire:model="customer_id" class="col-span-2 rounded-lg border border-slate-300 px-2.5 py-1.5">
                    <option value="">Cliente General (Mostrador)</option>
                    @foreach($customers as $cust)
                        <option value="{{ $cust->id }}">{{ $cust->displayName() }} (+{{ $cust->phone }})</option>
                    @endforeach
                </select>
                <select wire:model="channel" class="rounded-lg border border-slate-300 px-2 py-1.5">
                    <option value="pos">Mostrador</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
            </div>

            <div class="space-y-2 max-h-60 overflow-y-auto">
                @forelse($cart as $idx => $item)
                    <div class="flex items-start justify-between gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-xs">
                        <div class="flex-1">
                            <div class="font-bold text-slate-900">{{ $item['name'] }}</div>
                            <div class="text-[11px] text-slate-500">{{ $item['summary'] }}</div>
                            <div class="text-[11px] font-semibold text-indigo-700 mt-0.5">
                                {{ \App\Support\CurrencyHelper::format($item['unit_price_usd'] * $item['quantity'], $currency, $rate) }}
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button wire:click="decrementCart({{ $idx }})" class="w-6 h-6 rounded bg-white border border-slate-300 font-bold">-</button>
                            <span class="w-6 text-center font-bold">{{ $item['quantity'] }}</span>
                            <button wire:click="incrementCart({{ $idx }})" class="w-6 h-6 rounded bg-white border border-slate-300 font-bold">+</button>
                            <button wire:click="removeCart({{ $idx }})" class="ml-1 text-rose-500 font-bold px-1">&times;</button>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-slate-400">Selecciona pasteles, bebidas o combos para agregar al ticket.</div>
                @endforelse
            </div>

            {{-- Totals --}}
            <div class="border-t border-slate-200 pt-3 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Descuento (USD $)</span>
                    <input type="number" step="0.01" min="0" wire:model.live="discountUsd" class="w-24 text-right rounded border border-slate-300 px-2 py-1">
                </div>
                <div class="flex items-center justify-between text-base font-extrabold text-slate-900">
                    <span>Total a Pagar</span>
                    <div class="text-right">
                        <div>{{ \App\Support\CurrencyHelper::format($totalUsd, $currency, $rate) }}</div>
                        <div class="text-xs font-semibold text-slate-500">{{ \App\Support\CurrencyHelper::formatOpposite($totalUsd, $currency, $rate) }}</div>
                    </div>
                </div>
            </div>

            {{-- Mixed / Split Payments --}}
            <div class="border-t border-slate-200 pt-3 space-y-2.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-800">Pagos Mixtos / Divididos</span>
                    <button wire:click="addPaymentRow" type="button" class="px-2 py-1 rounded bg-indigo-50 text-indigo-700 font-semibold">+ Añadir Método</button>
                </div>

                @foreach($payments as $pIdx => $p)
                    @php
                        $pmObj = $paymentMethods->firstWhere('id', $p['payment_method_id']);
                    @endphp
                    <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 space-y-2">
                        <div class="grid grid-cols-12 gap-1.5 items-center">
                            <select wire:model.live="payments.{{ $pIdx }}.payment_method_id" class="col-span-6 rounded border border-slate-300 bg-white px-2 py-1.5">
                                @foreach($paymentMethods as $pm)
                                    <option value="{{ $pm->id }}">{{ $pm->name }} ({{ $pm->currency }})</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" wire:model.live="payments.{{ $pIdx }}.amount_original" placeholder="Monto"
                                class="col-span-4 rounded border border-slate-300 bg-white px-2 py-1.5">
                            <button type="button" wire:click="fillRemainingInPayment({{ $pIdx }})" title="Completar restante"
                                class="col-span-1 py-1.5 rounded bg-emerald-100 text-emerald-700 font-bold">=</button>
                            <button type="button" wire:click="removePaymentRow({{ $pIdx }})" class="col-span-1 text-rose-600 font-bold">&times;</button>
                        </div>
                        @if($pmObj?->requires_reference)
                            <input type="text" wire:model="payments.{{ $pIdx }}.reference_number" placeholder="Nro. de referencia / últimos dígitos"
                                class="w-full rounded border border-slate-300 bg-white px-2 py-1">
                        @endif
                    </div>
                @endforeach

                <div class="grid grid-cols-2 gap-2 pt-1">
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-0.5">Vuelto entregado (Bs.)</label>
                        <input type="number" step="0.01" min="0" wire:model.live="changeBs" class="w-full rounded border border-slate-300 px-2 py-1">
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-0.5">Vuelto entregado (USD $)</label>
                        <input type="number" step="0.01" min="0" wire:model.live="changeUsd" class="w-full rounded border border-slate-300 px-2 py-1">
                    </div>
                </div>

                <div class="flex justify-between items-center p-2 rounded-lg {{ $balanceUsd > 0.02 ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800' }} font-semibold">
                    <span>{{ $balanceUsd > 0.02 ? 'Restante por pagar:' : 'Cambio / Excedente:' }}</span>
                    <span>${{ number_format(abs($balanceUsd), 2) }} / Bs. {{ number_format(abs($balanceUsd) * $rate, 2, ',', '.') }}</span>
                </div>

                <button wire:click="completeSale" @disabled(empty($cart) || !$openSession) type="button"
                    class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white font-bold text-sm shadow">
                    Cobrar y Emitir Ticket
                </button>
            </div>
        </div>
    </div>

    {{-- Combo Mixto Modal --}}
    @if($showComboModal && $selectedCombo)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-xl w-full p-5 max-h-[90vh] overflow-y-auto text-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Armar {{ $selectedCombo->name }}</h3>
                        <p class="text-slate-500">Precio base: {{ \App\Support\CurrencyHelper::format((float)$selectedCombo->promo_price_usd, $currency, $rate) }}</p>
                    </div>
                    <button wire:click="$set('showComboModal', false)" class="text-lg text-slate-400">&times;</button>
                </div>

                @foreach($selectedCombo->slots as $slot)
                    @php $picked = array_sum($comboSelections[$slot->id] ?? []); @endphp
                    <div class="p-3 rounded-lg border {{ $picked === $slot->required_qty ? 'border-emerald-300 bg-emerald-50/40' : 'border-amber-300 bg-amber-50/40' }} space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-slate-800">{{ $slot->label }}</span>
                            <span class="px-2 py-0.5 rounded-full font-bold {{ $picked === $slot->required_qty ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' }}">
                                {{ $picked }} / {{ $slot->required_qty }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($slot->options as $opt)
                                @if($opt->product)
                                    @php $qty = $comboSelections[$slot->id][$opt->product_id] ?? 0; @endphp
                                    <div class="flex items-center justify-between bg-white p-2 rounded-lg border border-slate-200">
                                        <div>
                                            <div class="font-semibold text-slate-800">{{ $opt->product->name }}</div>
                                            @if($opt->extra_price_usd > 0)
                                                <div class="text-[10px] text-amber-700">+${{ number_format((float)$opt->extra_price_usd, 2) }}</div>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" wire:click="decrementComboOption({{ $slot->id }}, {{ $opt->product_id }})" class="w-6 h-6 rounded bg-slate-100 font-bold">-</button>
                                            <span class="w-5 text-center font-bold">{{ $qty }}</span>
                                            <button type="button" wire:click="incrementComboOption({{ $slot->id }}, {{ $opt->product_id }}, {{ $slot->required_qty }})" class="w-6 h-6 rounded bg-indigo-600 text-white font-bold">+</button>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showComboModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 font-semibold">Cancelar</button>
                    <button type="button" wire:click="confirmComboToCart" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-bold">Agregar Combo al Ticket</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Receipt Modal --}}
    @if($lastSale)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-5 text-xs space-y-3">
                <div class="text-center border-b border-dashed border-slate-300 pb-3">
                    <div class="text-base font-black tracking-wider">NEXORA</div>
                    <div class="text-[11px] text-slate-500">{{ config('app.slogan') }}</div>
                    <div class="font-mono font-bold mt-2">{{ $lastSale->invoice_number }}</div>
                    <div class="text-[11px] text-slate-500">{{ $lastSale->sold_at->format('d/m/Y H:i') }} • Cajero: {{ $lastSale->user?->name }}</div>
                </div>

                <div class="space-y-1.5 border-b border-dashed border-slate-300 pb-3">
                    @foreach($lastSale->items as $sItem)
                        <div class="flex justify-between font-semibold">
                            <span>{{ $sItem->quantity }}x {{ $sItem->description }}</span>
                            <span>${{ number_format((float)$sItem->total_price_usd, 2) }}</span>
                        </div>
                        @foreach($sItem->comboSelections as $sel)
                            <div class="pl-3 text-[11px] text-slate-500">↳ {{ $sel->quantity }}x {{ $sel->product_name }}</div>
                        @endforeach
                    @endforeach
                </div>

                <div class="space-y-1">
                    <div class="flex justify-between font-bold text-sm">
                        <span>TOTAL USD:</span>
                        <span>${{ number_format((float)$lastSale->total_usd, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-indigo-700">
                        <span>TOTAL BS (Tasa {{ number_format((float)$lastSale->exchange_rate_bs, 2) }}):</span>
                        <span>Bs. {{ number_format((float)$lastSale->total_bs, 2, ',', '.') }}</span>
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100 space-y-1 text-[11px] text-slate-600">
                    <div class="font-semibold">Pagos recibidos:</div>
                    @foreach($lastSale->payments as $sp)
                        <div class="flex justify-between">
                            <span>{{ $sp->paymentMethod?->name }} @if($sp->reference_number) (Ref: {{ $sp->reference_number }}) @endif</span>
                            <span>{{ $sp->currency }} {{ number_format((float)$sp->amount_original, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-2 pt-3 no-print">
                    @if($lastSale->customer)
                        <button wire:click="sendReceiptToWhatsApp" type="button" class="w-full py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                            Enviar Ticket por WhatsApp
                        </button>
                    @endif
                    <button onclick="window.print()" type="button" class="flex-1 py-2 rounded-lg bg-slate-800 text-white font-semibold">Imprimir Ticket</button>
                    <button wire:click="$set('lastSaleId', null)" type="button" class="flex-1 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Nueva Venta</button>
                </div>
            </div>
        </div>
    @endif
</div>
