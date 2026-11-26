<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Agente IA de Ventas & Despacho Delivery</h1>
            <p class="text-xs text-slate-500">Define el contexto extenso del negocio, saludo obligatorio, reglas de cierre, motor IA y plantilla del proveedor de delivery</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            @foreach($profiles as $p)
                <button type="button" wire:click="loadProfile({{ $p->id }})"
                    class="px-3 py-1.5 rounded-lg font-semibold {{ $selectedId === $p->id ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-300 text-slate-700' }}">
                    {{ $p->business_name }} ({{ $p->branch_code }})
                </button>
            @endforeach
            <button type="button" wire:click="startNewProfile" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white font-semibold">
                + Nueva Sucursal
            </button>
        </div>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-12 gap-6 text-xs">
        {{-- Business & Extensive Context (Left 7 cols) --}}
        <div class="lg:col-span-7 space-y-5">
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900">Identidad y Contexto Extenso del Negocio</h2>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Código Sucursal</label>
                        <input type="text" wire:model="branch_code" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @error('branch_code') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">Nombre Comercial (`business_name`)</label>
                        <input type="text" wire:model="business_name" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @error('business_name') <span class="text-rose-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Saludo de Gratitud Inicial Obligatorio (`gratitude_greeting`)</label>
                    <textarea wire:model="gratitude_greeting" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2" required></textarea>
                    <span class="text-[11px] text-slate-400">El agente usará esta directriz obligatoriamente en el estado GREETING antes de atender cualquier solicitud.</span>
                    @error('gratitude_greeting') <span class="text-rose-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="font-semibold text-slate-700">Contexto Extenso del Negocio (`extensive_business_context`)</label>
                        <button type="button" wire:click="loadContextTemplate" class="text-indigo-600 hover:underline font-semibold">
                            Cargar plantilla guía
                        </button>
                    </div>
                    <textarea wire:model="extensive_business_context" rows="13"
                        placeholder="Describe detalladamente tu catálogo/notas, promociones, horarios, zonas de cobertura, cuentas Pago Móvil / Zelle / Efectivo y políticas de entrega..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-[11px] leading-relaxed"></textarea>
                    <span class="text-[11px] text-slate-400">El inventario y precios reales en USD/Bs. (tasa BCV) de Nexora se inyectan automáticamente junto a este texto.</span>
                </div>
            </div>
        </div>

        {{-- Delivery & LLM Config (Right 5 cols) --}}
        <div class="lg:col-span-5 space-y-5">
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-slate-900">Configuración del Proveedor de Delivery</h2>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">WhatsApp del Proveedor de Delivery (`delivery_provider_phone`)</label>
                    <input type="text" wire:model="delivery_provider_phone" placeholder="Ej. 584141234567" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <span class="text-[11px] text-slate-400">Al hacer clic en "Autorizar Pago y Despachar Delivery", se enviará el despacho formateado a este número.</span>
                </div>

                <div class="space-y-2">
                    <label class="block font-semibold text-slate-700">Datos Obligatorios Exigidos por el Delivery (`delivery_required_fields`)</label>
                    <div class="space-y-1.5">
                        @foreach($delivery_required_fields as $idx => $field)
                            <div class="flex items-center gap-2">
                                <input type="text" wire:model="delivery_required_fields.{{ $idx }}" class="flex-1 rounded-lg border border-slate-300 px-2.5 py-1.5">
                                <button type="button" wire:click="removeDeliveryField({{ $idx }})" class="px-2.5 py-1.5 rounded bg-rose-50 text-rose-600 font-bold">&times;</button>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex gap-2 pt-1">
                        <input type="text" wire:model="newFieldLabel" placeholder="Nuevo campo (ej. Hora deseada)" class="flex-1 rounded-lg border border-slate-300 px-2.5 py-1.5">
                        <button type="button" wire:click="addDeliveryField" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">
                            + Campo
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-3">
                <h2 class="text-sm font-bold text-slate-900">Motor LLM (Inteligencia Artificial)</h2>
                <p class="text-[11px] text-slate-500">Si dejas el API Key vacío, el agente operará en modo determinístico seguro usando tu menú e inventario real.</p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Proveedor</label>
                        <select wire:model="llm_provider" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="openai">OpenAI (GPT)</option>
                            <option value="gemini">Google Gemini</option>
                            <option value="openai_compatible">OpenAI Compatible</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Modelo</label>
                        <input type="text" wire:model="llm_model" placeholder="gpt-4o-mini / gemini-2.0-flash" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">API Key del LLM</label>
                    <input type="password" wire:model="llm_api_key" placeholder="sk-... o AIza..." class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                @if($llm_provider === 'openai_compatible')
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Base URL (OpenAI Compatible)</label>
                        <input type="url" wire:model="llm_base_url" placeholder="https://api.groq.com/openai/v1" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                @endif

                <label class="flex items-center gap-2 cursor-pointer pt-1">
                    <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-indigo-600">
                    <span class="font-semibold text-slate-700">Perfil activo para conversaciones entrantes</span>
                </label>

                <div class="flex flex-wrap gap-2 pt-3 border-t border-slate-100">
                    <button type="submit" class="flex-1 py-2.5 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold">
                        Guardar Configuración
                    </button>
                    <button type="button" wire:click="previewSystemPrompt" class="py-2.5 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold">
                        Ver Prompt
                    </button>
                </div>
            </div>
        </div>
    </form>

    @if($showPromptPreview)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full p-5 max-h-[85vh] flex flex-col text-xs">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-slate-900">System Prompt Dinámico (Vista Previa en Vivo)</h3>
                    <button wire:click="$set('showPromptPreview', false)" class="text-lg text-slate-400 hover:text-slate-700">&times;</button>
                </div>
                <pre class="flex-1 overflow-y-auto bg-slate-900 text-slate-100 p-4 rounded-lg text-[11px] whitespace-pre-wrap">{{ $promptPreviewText }}</pre>
            </div>
        </div>
    @endif
</div>
