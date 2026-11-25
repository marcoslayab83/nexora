<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Configuración, Tasa BCV y Métodos de Pago</h1>
        <p class="text-xs text-slate-500">Parámetros de costeo, reglas de inventario, tasa oficial y usuarios</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Costing & Inventory Settings --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-xs space-y-4">
            <h2 class="text-sm font-bold text-slate-900">Motor de Costeo e Inventario</h2>
            <form wire:submit="saveGeneralSettings" class="space-y-3">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Método de Costeo de Materia Prima</label>
                    <select wire:model="costing_method" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="weighted_average">Costo Promedio Ponderado</option>
                        <option value="last_cost">Último Costo de Compra (Reposición)</option>
                    </select>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="allow_negative_stock" class="rounded border-slate-300 text-indigo-600">
                    <span class="font-medium text-slate-700">Permitir ventas o producción con stock negativo (con alerta)</span>
                </label>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="bcv_auto_sync" class="rounded border-slate-300 text-indigo-600">
                    <span class="font-medium text-slate-700">Sincronización automática programada de Tasa BCV</span>
                </label>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Guardar Preferencias</button>
                    <button type="button" wire:click="recalculateAllCosts" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">Recalcular Cascada Completa</button>
                </div>
            </form>
        </div>

        {{-- BCV Rate History --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-xs space-y-4">
            <h2 class="text-sm font-bold text-slate-900">Tasa BCV (USD / VES)</h2>
            <form wire:submit="updateBcvManual" class="flex gap-2">
                <input type="number" step="0.0001" wire:model="manual_bcv_rate" class="flex-1 rounded-lg border border-slate-300 px-3 py-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold">Fijar Tasa</button>
            </form>

            <div class="max-h-48 overflow-y-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-slate-500 uppercase">
                        <tr>
                            <th class="px-2 py-1.5">Tasa</th>
                            <th class="px-2 py-1.5">Origen</th>
                            <th class="px-2 py-1.5">Fecha</th>
                            <th class="px-2 py-1.5">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($ratesHistory as $r)
                            <tr>
                                <td class="px-2 py-1.5 font-bold">{{ number_format((float)$r->rate_bs, 2, ',', '.') }} Bs/$</td>
                                <td class="px-2 py-1.5">{{ $r->source }}</td>
                                <td class="px-2 py-1.5 text-slate-500">{{ $r->effective_at->format('d/m H:i') }}</td>
                                <td class="px-2 py-1.5">
                                    @if($r->is_active)
                                        <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 font-bold">Activa</span>
                                    @else
                                        <span class="text-slate-400">Histórica</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Dynamic Payment Methods --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-xs space-y-4">
        <h2 class="text-sm font-bold text-slate-900">Métodos de Pago Dinámicos</h2>
        <form wire:submit="createPaymentMethod" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div class="sm:col-span-2">
                <label class="block font-semibold text-slate-700 mb-1">Nombre del método</label>
                <input type="text" wire:model="pm_name" placeholder="Ej. Binance Pay / Biopago / Cashea" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2" required>
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Moneda</label>
                <select wire:model="pm_currency" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                    <option value="VES">Bolívares (VES)</option>
                    <option value="USD">Dólares (USD)</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" wire:model="pm_is_cash" class="rounded border-slate-300">
                    <span>Es efectivo físico</span>
                </label>
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" wire:model="pm_requires_reference" class="rounded border-slate-300">
                    <span>Exige referencia</span>
                </label>
            </div>
            <button type="submit" class="py-2 px-4 rounded-lg bg-indigo-600 text-white font-semibold">+ Crear Método</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr>
                        <th class="px-3 py-2">Método</th>
                        <th class="px-3 py-2">Moneda</th>
                        <th class="px-3 py-2">Efectivo en Caja</th>
                        <th class="px-3 py-2">Pide Referencia</th>
                        <th class="px-3 py-2 text-right">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($paymentMethods as $pm)
                        <tr>
                            <td class="px-3 py-2 font-semibold text-slate-800">{{ $pm->name }}</td>
                            <td class="px-3 py-2">{{ $pm->currency }}</td>
                            <td class="px-3 py-2">{{ $pm->is_cash ? 'Sí' : 'No' }}</td>
                            <td class="px-3 py-2">{{ $pm->requires_reference ? 'Sí' : 'No' }}</td>
                            <td class="px-3 py-2 text-right">
                                <button wire:click="togglePaymentMethod({{ $pm->id }})" class="px-2.5 py-1 rounded font-semibold {{ $pm->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $pm->is_active ? 'Activo' : 'Inactivo' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Users --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm text-xs space-y-4">
        <h2 class="text-sm font-bold text-slate-900">Usuarios y Roles</h2>
        <form wire:submit="createUser" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end bg-slate-50 p-3 rounded-lg border border-slate-200">
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Nombre</label>
                <input type="text" wire:model="user_name" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2" required>
                @error('user_name') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Correo</label>
                <input type="email" wire:model="user_email" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2" required>
                @error('user_email') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Contraseña</label>
                <input type="password" wire:model="user_password" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2" required>
                @error('user_password') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Rol</label>
                <select wire:model="user_role" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                    <option value="cashier">Cajero (POS y Caja)</option>
                    <option value="kitchen">Cocina (Producción y Recetas)</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
            <button type="submit" class="py-2 px-4 rounded-lg bg-slate-900 text-white font-semibold">+ Crear Usuario</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase">
                    <tr>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2">Correo</th>
                        <th class="px-3 py-2">Rol</th>
                        <th class="px-3 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $u)
                        <tr>
                            <td class="px-3 py-2 font-semibold">{{ $u->name }}</td>
                            <td class="px-3 py-2 text-slate-500">{{ $u->email }}</td>
                            <td class="px-3 py-2 uppercase font-semibold">{{ $u->role }}</td>
                            <td class="px-3 py-2 text-right space-x-1">
                                <button wire:click="editUser({{ $u->id }})" class="px-2.5 py-1 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-semibold">Editar</button>
                                <button wire:click="toggleUser({{ $u->id }})" class="px-2.5 py-1 rounded font-semibold {{ $u->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                    {{ $u->is_active ? 'Activo' : 'Inactivo' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Edit User Modal --}}
    @if($showEditUserModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 text-xs">
                <h3 class="text-base font-bold text-slate-900">Editar Usuario</h3>
                <form wire:submit="updateUser" class="mt-4 space-y-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nombre</label>
                        <input type="text" wire:model="edit_user_name" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @error('edit_user_name') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Correo</label>
                        <input type="email" wire:model="edit_user_email" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @error('edit_user_email') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nueva Contraseña <span class="text-slate-400 font-normal">(dejar vacío para mantener la actual)</span></label>
                        <input type="password" wire:model="edit_user_password" placeholder="Mínimo 6 caracteres" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('edit_user_password') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Rol</label>
                        <select wire:model="edit_user_role" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="cashier">Cajero (POS y Caja)</option>
                            <option value="kitchen">Cocina (Producción y Recetas)</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer pt-1">
                        <input type="checkbox" wire:model="edit_user_active" class="rounded border-slate-300 text-indigo-600">
                        <span class="font-semibold text-slate-700">Usuario activo</span>
                    </label>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="$set('showEditUserModal', false)" class="px-3 py-2 rounded-lg border border-slate-300 font-semibold">Cancelar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
