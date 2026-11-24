<div class="flex flex-wrap items-center justify-between gap-3 bg-white border-b border-slate-200 px-4 lg:px-6 py-3 no-print">
    <div class="flex items-center gap-3">
        <button @click="sidebarOpen = !sidebarOpen" type="button" class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        @if($openSession)
            <a href="{{ route('cash') }}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Caja Abierta: {{ $openSession->cashRegister?->name }}
            </a>
        @else
            <a href="{{ route('cash') }}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                Caja Cerrada — Abrir Turno
            </a>
        @endif
    </div>

    <div class="flex items-center gap-3">
        {{-- Tasa BCV Badge --}}
        <div class="flex items-center gap-1.5 bg-slate-100 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs">
            <span class="font-semibold text-slate-600">BCV:</span>
            <button wire:click="openRateModal" type="button" class="font-bold text-indigo-700 hover:underline" title="Editar tasa manualmente">
                {{ number_format($activeRate, 2, ',', '.') }} Bs/$
            </button>
            <button wire:click="syncBcv" wire:loading.attr="disabled" type="button" class="ml-1 text-slate-500 hover:text-indigo-600" title="Sincronizar tasa BCV">
                <svg class="w-3.5 h-3.5" wire:loading.class="animate-spin" wire:target="syncBcv" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </div>

        {{-- Dual Currency Toggle --}}
        <div class="inline-flex rounded-lg bg-slate-100 p-0.5 border border-slate-200">
            <button wire:click="setCurrency('USD')" type="button"
                class="px-2.5 py-1 text-xs font-semibold rounded-md transition {{ $displayCurrency === 'USD' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                USD ($)
            </button>
            <button wire:click="setCurrency('VES')" type="button"
                class="px-2.5 py-1 text-xs font-semibold rounded-md transition {{ $displayCurrency === 'VES' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                VES (Bs.)
            </button>
        </div>

        {{-- User --}}
        <div class="hidden sm:flex items-center gap-2 pl-2 border-l border-slate-200">
            <div class="text-right">
                <div class="text-xs font-semibold text-slate-800">{{ auth()->user()->name }}</div>
                <div class="text-[11px] text-slate-500 uppercase">{{ auth()->user()->role }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="p-1.5 rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600" title="Cerrar sesión">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </form>
        </div>
    </div>

    {{-- Modal edición rápida tasa BCV --}}
    @if($showRateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-5">
                <h3 class="text-base font-bold text-slate-900">Actualizar Tasa BCV (Bs./USD)</h3>
                <p class="text-xs text-slate-500 mt-1">Se aplicará inmediatamente a la visualización dual y nuevas ventas.</p>
                <form wire:submit="saveManualRate" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tasa Bs. por 1 USD</label>
                        <input type="number" step="0.0001" wire:model="manualRateInput" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        @error('manualRateInput') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showRateModal', false)" class="px-3 py-2 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Guardar Tasa</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
