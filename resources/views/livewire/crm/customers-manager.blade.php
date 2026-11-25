<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Directorio de Clientes (CRM)</h1>
            <p class="text-xs text-slate-500">Historial de compras, frecuencia por cliente y acceso directo a WhatsApp o POS</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por nombre o teléfono..."
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs">
            <button wire:click="openCreate" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
                + Nuevo Cliente
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-500 uppercase border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Teléfono</th>
                    <th class="px-4 py-3">Pedidos</th>
                    <th class="px-4 py-3">Total Comprado</th>
                    <th class="px-4 py-3">Último Pedido</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($customers as $c)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $c->displayName() }}</div>
                            @if($c->address) <div class="text-[11px] text-slate-400">{{ $c->address }}</div> @endif
                        </td>
                        <td class="px-4 py-3 font-mono">+{{ $c->phone }}</td>
                        <td class="px-4 py-3 font-bold">{{ $c->total_orders }}</td>
                        <td class="px-4 py-3 font-bold text-emerald-700">{{ \App\Support\CurrencyHelper::format((float)$c->total_spent_usd, $currency, $rate) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $c->last_order_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <button wire:click="openWhatsAppChat({{ $c->id }})" class="px-2.5 py-1 rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-semibold">WhatsApp</button>
                            <a href="{{ route('pos', ['customer_id' => $c->id]) }}" class="px-2.5 py-1 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-semibold">POS</a>
                            <button wire:click="openEdit({{ $c->id }})" class="px-2.5 py-1 rounded bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin clientes registrados aún.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 text-xs">
                <h3 class="text-base font-bold text-slate-900">{{ $editingId ? 'Editar Cliente' : 'Nuevo Cliente' }}</h3>
                <form wire:submit="save" class="mt-4 space-y-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nombre</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @error('name') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Teléfono WhatsApp (ej. 04141234567)</label>
                        <input type="text" wire:model="phone" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @error('phone') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Dirección / Zona Delivery</label>
                        <input type="text" wire:model="address" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Notas</label>
                        <input type="text" wire:model="notes" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 font-semibold">Cancelar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
