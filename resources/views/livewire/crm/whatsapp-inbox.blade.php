<div class="space-y-4" wire:poll.5s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Bandeja WhatsApp & Despacho (Human-in-the-Loop)</h1>
            <p class="text-xs text-slate-500">Agente de cierre con validación de pago por la tienda y despacho automático al proveedor de delivery</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($pendingCount > 0)
                <button wire:click="$set('filterState', 'pending')" type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-rose-600 text-white text-xs font-bold animate-pulse shadow">
                    <span>{{ $pendingCount }} Pago(s) por Autorizar</span>
                </button>
            @endif
            <a href="{{ route('whatsapp.agent-settings') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50">
                Configurar Agente IA & Delivery
            </a>
            <button wire:click="$set('showNewChatModal', true)" class="px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                + Nuevo Chat
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 min-h-[72vh] overflow-hidden">
        {{-- Conversation List --}}
        <div class="lg:col-span-4 border-b lg:border-b-0 lg:border-r border-slate-200 flex flex-col">
            <div class="p-3 border-b border-slate-200 space-y-2">
                <input type="text" wire:model.live.debounce.250ms="search" placeholder="Buscar cliente o teléfono..."
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none">
                <div class="flex gap-1.5 text-[11px]">
                    <button wire:click="$set('filterState', 'all')" class="flex-1 py-1 rounded font-semibold {{ $filterState === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' }}">Todos</button>
                    <button wire:click="$set('filterState', 'pending')" class="flex-1 py-1 rounded font-semibold {{ $filterState === 'pending' ? 'bg-rose-600 text-white' : 'bg-rose-50 text-rose-700' }}">
                        Por Aprobar ({{ $pendingCount }})
                    </button>
                </div>
            </div>

            <div class="divide-y divide-slate-100 overflow-y-auto max-h-[62vh]">
                @forelse($conversations as $conv)
                    @php
                        $isPending = $conv->agent_state === \App\Models\WhatsAppOrder::STATE_PENDING_STORE_APPROVAL;
                    @endphp
                    <button wire:click="selectConversation({{ $conv->id }})" type="button"
                        class="w-full text-left p-3 hover:bg-slate-50 transition flex items-start justify-between gap-2 {{ $selectedConversationId === $conv->id ? 'bg-indigo-50/70 border-l-4 border-indigo-600' : ($isPending ? 'bg-amber-50/50 border-l-4 border-rose-500' : '') }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1">
                                <span class="font-bold text-xs text-slate-900 truncate">{{ $conv->customer?->displayName() }}</span>
                                <span class="px-1.5 py-0.5 rounded text-[9px] uppercase font-bold {{ $isPending ? 'bg-rose-600 text-white' : ($conv->agent_state === 'APPROVED_AND_DISPATCHED' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $conv->agent_state ?? 'GREETING' }}
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 truncate mt-0.5">{{ $conv->last_message_preview ?? 'Sin mensajes' }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[10px] text-slate-400">{{ $conv->last_message_at?->format('H:i') }}</div>
                            @if($conv->unread_count > 0)
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded-full bg-emerald-600 text-white text-[10px] font-bold">{{ $conv->unread_count }}</span>
                            @endif
                        </div>
                    </button>
                @empty
                    <div class="p-6 text-center text-xs text-slate-400">Aún no hay conversaciones de WhatsApp.</div>
                @endforelse
            </div>
        </div>

        {{-- Chat Thread + Human-in-the-Loop Approval --}}
        <div class="lg:col-span-8 flex flex-col bg-slate-50/60">
            @if($selectedConversation)
                @php
                    $activeOrder = $selectedConversation->activeOrder;
                    $latestDispatched = $selectedConversation->whatsappOrders->where('status', 'APPROVED_AND_DISPATCHED')->sortByDesc('id')->first();
                @endphp

                <div class="bg-white px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-sm text-slate-900">{{ $selectedConversation->customer?->displayName() }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $selectedConversation->agent_state === 'PENDING_STORE_APPROVAL' ? 'bg-rose-100 text-rose-800' : 'bg-indigo-100 text-indigo-800' }}">
                                {{ $selectedConversation->agent_state }}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-500">
                            +{{ $selectedConversation->customer?->phone }} •
                            Pedidos: {{ $selectedConversation->customer?->total_orders ?? 0 }} •
                            Gastado: ${{ number_format((float)($selectedConversation->customer?->total_spent_usd ?? 0), 2) }}
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <select wire:change="setConversationStatus($event.target.value)" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <option value="bot" @selected($selectedConversation->status === 'bot')>Agente IA Activo</option>
                            <option value="human" @selected($selectedConversation->status === 'human')>Modo Manual (Humano)</option>
                            <option value="closed" @selected($selectedConversation->status === 'closed')>Cerrada</option>
                        </select>
                        <button wire:click="sendLiveMenu" type="button" class="px-2.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold">
                            Enviar Menú
                        </button>
                        @if(!$activeOrder)
                            <button wire:click="createManualPendingOrder" type="button" class="px-2.5 py-1.5 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 font-semibold hover:bg-rose-100">
                                + Crear Orden por Aprobar
                            </button>
                        @endif
                        <a href="{{ route('pos', ['customer_id' => $selectedConversation->customer_id, 'channel' => 'whatsapp']) }}"
                           class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">
                            POS Manual
                        </a>
                    </div>
                </div>

                {{-- Human-in-the-Loop Order & Payment Approval Box --}}
                @if($activeOrder)
                    <div class="m-3 p-4 rounded-xl border-2 {{ $activeOrder->isPendingApproval() ? 'border-rose-400 bg-amber-50/70 shadow-md' : 'border-slate-200 bg-white' }} text-xs space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-extrabold text-sm text-slate-900">Orden {{ $activeOrder->order_code }}</span>
                                <span class="ml-2 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $activeOrder->isPendingApproval() ? 'bg-rose-600 text-white' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $activeOrder->status }}
                                </span>
                            </div>
                            <div class="text-right font-bold text-slate-800">
                                Total: ${{ number_format((float)$activeOrder->total_usd, 2) }} (Bs. {{ number_format((float)$activeOrder->total_bs, 2, ',', '.') }})
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            {{-- Payment Info --}}
                            <div class="bg-white p-3 rounded-lg border border-slate-200 space-y-2">
                                <div class="font-bold text-slate-800">Verificación de Pago</div>
                                @if($activeOrder->payment_raw_text)
                                    <div class="text-[11px] bg-slate-50 p-2 rounded border border-slate-100 text-slate-600">
                                        Comprobante capturado: "{{ $activeOrder->payment_raw_text }}"
                                        @if($activeOrder->payment_bank) • Banco: <b>{{ $activeOrder->payment_bank }}</b> @endif
                                        @if($activeOrder->payment_date) • Fecha: <b>{{ $activeOrder->payment_date }}</b> @endif
                                    </div>
                                @endif
                                <div class="grid grid-cols-3 gap-2">
                                    <select wire:model="approvalPaymentMethodId" class="col-span-3 sm:col-span-1 rounded border border-slate-300 px-2 py-1.5">
                                        @foreach($paymentMethods as $pm)
                                            <option value="{{ $pm->id }}">{{ $pm->name }} ({{ $pm->currency }})</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model="approvalPaymentReference" placeholder="Referencia" class="rounded border border-slate-300 px-2 py-1.5">
                                    <input type="number" step="0.01" wire:model="approvalPaymentAmount" placeholder="Monto" class="rounded border border-slate-300 px-2 py-1.5">
                                </div>
                            </div>

                            {{-- Order Items --}}
                            <div class="bg-white p-3 rounded-lg border border-slate-200 space-y-2">
                                <div class="font-bold text-slate-800">Ítems del Pedido (Inventario Nexora)</div>
                                <div class="space-y-1 max-h-24 overflow-y-auto">
                                    @forelse($approvalItems as $iIdx => $it)
                                        <div class="flex items-center justify-between bg-slate-50 px-2 py-1 rounded">
                                            <span><b>{{ $it['quantity'] }}x</b> {{ $it['name'] }} (${{ number_format((float)($it['unit_price_usd'] ?? 0), 2) }})</span>
                                            <button type="button" wire:click="removeApprovalItem({{ $iIdx }})" class="text-rose-600 font-bold">&times;</button>
                                        </div>
                                    @empty
                                        <div class="text-[11px] text-amber-700">Agrega al menos un producto para descontar del inventario al aprobar:</div>
                                    @endforelse
                                </div>
                                <div class="flex gap-1.5 pt-1">
                                    <select wire:model="addProductId" class="flex-1 rounded border border-slate-300 px-2 py-1 text-[11px]">
                                        @foreach($catalogProducts as $cp)
                                            <option value="{{ $cp->id }}">{{ $cp->name }} (${{ number_format((float)$cp->sale_price_usd, 2) }})</option>
                                        @endforeach
                                    </select>
                                    <input type="number" min="1" wire:model="addProductQty" class="w-14 rounded border border-slate-300 px-2 py-1 text-[11px]">
                                    <button type="button" wire:click="addItemToApproval" class="px-2.5 py-1 rounded bg-slate-800 text-white font-semibold">+</button>
                                </div>
                            </div>
                        </div>

                        {{-- Delivery Fields --}}
                        <div class="bg-white p-3 rounded-lg border border-slate-200">
                            <div class="font-bold text-slate-800 mb-2">Datos para el Proveedor de Delivery</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                @foreach($approvalDeliveryData as $fieldLabel => $fieldVal)
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-500 mb-0.5">{{ $fieldLabel }}</label>
                                        <input type="text" wire:model="approvalDeliveryData.{{ $fieldLabel }}" class="w-full rounded border border-slate-300 px-2 py-1 text-[11px]">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @if($activeOrder->isPendingApproval())
                            <button wire:click="authorizeAndDispatchOrder" wire:loading.attr="disabled" type="button"
                                class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-sm shadow-lg transition flex items-center justify-center gap-2">
                                <span>Autorizar Pago y Despachar Delivery</span>
                            </button>
                        @else
                            <div class="flex justify-end">
                                <button wire:click="authorizeAndDispatchOrder" wire:loading.attr="disabled" type="button"
                                    class="py-2 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs">
                                    Autorizar Pago y Despachar Delivery (Manual)
                                </button>
                            </div>
                        @endif
                    </div>
                @elseif($latestDispatched)
                    <div class="mx-3 mt-3 px-4 py-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex flex-wrap justify-between items-center gap-2">
                        <span><b>Último pedido aprobado y despachado:</b> {{ $latestDispatched->order_code }} (Ticket {{ $latestDispatched->sale?->invoice_number }})</span>
                        <span class="text-[11px]">Despachado: {{ $latestDispatched->dispatched_to_delivery_at?->format('d/m H:i') }}</span>
                    </div>
                @endif

                <div class="flex-1 p-4 space-y-2.5 overflow-y-auto max-h-[45vh]">
                    @forelse($selectedConversation->messages as $msg)
                        <div class="flex {{ $msg->direction === 'outbound' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[78%] rounded-xl px-3.5 py-2 text-xs shadow-sm whitespace-pre-line {{ $msg->direction === 'outbound' ? 'bg-emerald-600 text-white' : 'bg-white border border-slate-200 text-slate-800' }}">
                                <div>{{ $msg->body }}</div>
                                <div class="mt-1 text-[10px] {{ $msg->direction === 'outbound' ? 'text-emerald-100' : 'text-slate-400' }} flex justify-between gap-3">
                                    <span>{{ $msg->sent_at->format('d/m H:i') }}</span>
                                    @if($msg->direction === 'outbound')
                                        <span>{{ $msg->status }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-xs text-slate-400 py-8">Sin mensajes en esta conversación.</div>
                    @endforelse
                </div>

                <form wire:submit="sendReply" class="bg-white p-3 border-t border-slate-200 flex gap-2">
                    <input type="text" wire:model="replyText" placeholder="Escribe una respuesta por WhatsApp..."
                        class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                        Enviar
                    </button>
                </form>
            @else
                <div class="flex-1 flex items-center justify-center p-8 text-xs text-slate-400">
                    Selecciona una conversación o inicia un chat nuevo para comenzar.
                </div>
            @endif
        </div>
    </div>

    @if($showNewChatModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 text-xs">
                <h3 class="text-base font-bold text-slate-900">Iniciar Chat por WhatsApp</h3>
                <form wire:submit="startNewChat" class="mt-4 space-y-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Teléfono (ej. 04141234567 o 584141234567)</label>
                        <input type="text" wire:model="newPhone" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @error('newPhone') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nombre del Cliente (opcional)</label>
                        <input type="text" wire:model="newName" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Mensaje inicial</label>
                        <textarea wire:model="newMessage" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2" required></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showNewChatModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 font-semibold">Cancelar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold">Enviar WhatsApp</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
