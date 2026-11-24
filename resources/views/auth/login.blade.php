<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 px-6 py-7 text-center">
            <div class="inline-flex w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-amber-500 items-center justify-center font-black text-white text-2xl shadow-lg mb-3">N</div>
            <h1 class="text-2xl font-black tracking-wider text-white">NEXORA</h1>
            <p class="text-xs text-amber-400 font-medium mt-1">{{ config('app.slogan') }}</p>
        </div>

        <form method="POST" action="{{ route('login.post') }}" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Correo electrónico</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                @error('email') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Contraseña</label>
                <input type="password" name="password" required
                    class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="inline-flex items-center gap-2 text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600">
                    Recordar sesión
                </label>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm shadow transition">
                Ingresar al Sistema
            </button>

            @if(app()->isLocal())
                <div class="mt-4 pt-4 border-t border-slate-100 text-[11px] text-slate-500 space-y-1">
                    <div class="font-semibold text-slate-700">Accesos iniciales (entorno local):</div>
                    <div>Admin: <span class="font-mono">admin@nexora.centralces.pro</span> / <span class="font-mono">Nexora2026*</span></div>
                    <div>Cajero: <span class="font-mono">caja@nexora.centralces.pro</span> / <span class="font-mono">Caja2026*</span></div>
                </div>
            @endif
        </form>
    </div>
</body>
</html>
