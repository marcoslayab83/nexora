<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'NEXORA' }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-900 antialiased" x-data="{ sidebarOpen: false }">
    <div class="min-h-screen flex">
        {{-- Mobile Sidebar Overlay --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

        {{-- Sidebar --}}
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-slate-100 flex flex-col transition-transform duration-200 lg:translate-x-0 lg:static lg:inset-0 no-print">
            <div class="px-5 py-4 border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-amber-500 flex items-center justify-center font-black text-white text-lg shadow">N</div>
                    <div>
                        <div class="font-extrabold tracking-wide text-base text-white leading-tight">NEXORA</div>
                        <div class="text-[10px] text-amber-400 font-medium leading-tight">{{ config('app.slogan') }}</div>
                    </div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6 text-sm">
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Operación</div>
                    <div class="space-y-1">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Panel General</span>
                        </a>
                        <a href="{{ route('pos') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('pos') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Punto de Venta (POS)</span>
                        </a>
                        <a href="{{ route('cash') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('cash') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Control de Caja</span>
                        </a>
                        @if(auth()->user()->isSupervisorOrAbove() || auth()->user()->role === 'kitchen')
                            <a href="{{ route('production') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('production') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                                <span>Producción (Lotes)</span>
                            </a>
                        @endif
                    </div>
                </div>

                @if(auth()->user()->isSupervisorOrAbove() || auth()->user()->role === 'kitchen')
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Inventario y Costos</div>
                    <div class="space-y-1">
                        <a href="{{ route('raw-materials') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('raw-materials') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Materia Prima & Kardex</span>
                        </a>
                        <a href="{{ route('purchases') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('purchases') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Compras & Proveedores</span>
                        </a>
                        <a href="{{ route('sub-recipes') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('sub-recipes') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Sub-Recetas (Masa/Relleno)</span>
                        </a>
                        <a href="{{ route('products') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('products') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Productos & Márgenes</span>
                        </a>
                        <a href="{{ route('combos') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('combos') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Combos Mixtos</span>
                        </a>
                    </div>
                </div>
                @endif

                @if(auth()->user()->isSupervisorOrAbove())
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Gerencia</div>
                    <div class="space-y-1">
                        <a href="{{ route('reports') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('reports') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Rentabilidad Real</span>
                        </a>
                        <a href="{{ route('settings') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('settings') ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Configuración & Pagos</span>
                        </a>
                    </div>
                </div>
                @endif
            </nav>

            <div class="p-4 border-t border-slate-800 text-[11px] text-slate-400">
                <div>NEXORA ERP & POS</div>
                <div class="text-slate-500">v1.0 • {{ now()->format('d/m/Y') }}</div>
            </div>
        </aside>

        {{-- Main Content --}}
        <div class="flex-1 flex flex-col min-w-0">
            <livewire:top-bar />

            {{-- Toast notifications --}}
            <div
                x-data="{ toasts: [] }"
                @notify.window="
                    const id = Date.now() + Math.random();
                    toasts.push({ id, type: $event.detail.type || 'success', message: $event.detail.message });
                    setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4500);
                "
                class="fixed bottom-4 right-4 z-50 space-y-2 max-w-sm w-full pointer-events-none no-print"
            >
                <template x-for="toast in toasts" :key="toast.id">
                    <div
                        class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg border text-xs font-semibold flex items-center justify-between gap-3"
                        :class="{
                            'bg-emerald-600 text-white border-emerald-700': toast.type === 'success',
                            'bg-amber-500 text-white border-amber-600': toast.type === 'warning',
                            'bg-rose-600 text-white border-rose-700': toast.type === 'error'
                        }"
                    >
                        <span x-text="toast.message"></span>
                        <button @click="toasts = toasts.filter(t => t.id !== toast.id)" class="opacity-80 hover:opacity-100">&times;</button>
                    </div>
                </template>
            </div>

            <main class="flex-1 p-4 lg:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
