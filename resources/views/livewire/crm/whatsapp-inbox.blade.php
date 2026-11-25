<div class="space-y-4" wire:poll.5s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Bandeja WhatsApp CRM (Evolution API)</h1>
            <p class="text-xs text-slate-500">Atiende pedidos por WhatsApp, envía el menú con tasa BCV al día y pásalos al POS en 1 clic</p>
        </div>
        <div class="flex items-center gap-2">
            @if($evoConfigured)
                <span class="px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold">Evolution API Configurado</span>
            @else
                <a href="{{ route('settings') }}" class="px-2.5 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold hover:underline">Configurar Evolution API</a>
            @endif
            <button wire:click="$set('showNewChatModal', true)" class="px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                + Nuevo Chat
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm grid grid-cols-1 lg:grid-cols-12 min-h-[70vh] overflow-hidden">
        {{-- Conversation List --}}
        <div class="lg:col-span-4 border-b lg:border-b-0 lg:border-r border-slate-200 flex flex-col">
            <div class="p-3 border-b border-slate-200">
                <input type="text" wire:model.live.debounce.250ms="search" placeholder="Buscar cliente o teléfono..."
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none">
            </div>

            <div class="divide-y divide-slate-100 overflow-y-auto max-h-[62vh]">
                @forelse($conversations as $conv)
                    <button wire:click="selectConversation({{ $conv->id }})" type="button"
                        class="w-full text-left p-3 hover:bg-slate-50 transition flex items-start justify-between gap-2 {{ $selectedConversationId === $conv->id ? 'bg-indigo-50/70 border-l-4 border-indigo-600' : '' }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-xs text-slate-900 truncate">{{ $conv->customer?->displayName() }}</span>
                                <span class="px-1.5 py-0.5 rounded text-[9px] uppercase font-bold {{ $conv->status === 'bot' ? 'bg-amber-100 text-amber-800' : ($conv->status === 'human' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-600') }}">
                                    {{ $conv->status }}
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

        {{-- Chat Thread --}}
        <div class="lg:col-span-8 flex flex-col bg-slate-50/60">
            @if($selectedConversation)
                <div class="bg-white px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="font-bold text-sm text-slate-900">{{ $selectedConversation->customer?->displayName() }}</div>
                        <div class="text-[11px] text-slate-500">
                            +{{ $selectedConversation->customer?->phone }} •
                            Pedidos: {{ $selectedConversation->customer?->total_orders ?? 0 }} •
                            Gastado: ${{ number_format((float)($selectedConversation->customer?->total_spent_usd ?? 0), 2) }}
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <select wire:change="setConversationStatus($event.target.value)" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <option value="bot" @selected($selectedConversation->status === 'bot')>Modo Bot (Auto-menú)</option>
                            <option value="human" @selected($selectedConversation->status === 'human')>Modo Asesor (Humano)</option>
                            <option value="closed" @selected($selectedConversation->status === 'closed')>Cerrada</option>
                        </select>
                        <button wire:click="sendLiveMenu" type="button" class="px-2.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold">
                            Enviar Menú
                        </button>
                        <a href="{{ route('pos', ['customer_id' => $selectedConversation->customer_id, 'channel' => 'whatsapp']) }}"
                           class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">
                            Facturar en POS
                        </a>
                    </div>
                </div>

                <div class="flex-1 p-4 space-y-2.5 overflow-y-auto max-h-[50vh]">
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
