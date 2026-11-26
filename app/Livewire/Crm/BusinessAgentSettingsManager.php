<?php

namespace App\Livewire\Crm;

use App\Models\BusinessAgentSetting;
use App\Models\Conversation;
use App\Services\SalesAgentService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Agente IA & Delivery')]
class BusinessAgentSettingsManager extends Component
{
    public ?int $selectedId = null;

    public string $branch_code = 'main';

    public string $business_name = '';

    public string $gratitude_greeting = '';

    public string $extensive_business_context = '';

    public string $delivery_provider_phone = '';

    /** @var array<int, string> */
    public array $delivery_required_fields = [];

    public string $newFieldLabel = '';

    public string $llm_provider = 'openai';

    public string $llm_api_key = '';

    public string $llm_model = 'gpt-4o-mini';

    public string $llm_base_url = '';

    public bool $is_active = true;

    public bool $showPromptPreview = false;

    public string $promptPreviewText = '';

    public function mount(): void
    {
        $first = BusinessAgentSetting::activeDefault();
        if ($first) {
            $this->loadProfile($first->id);
        } else {
            $this->startNewProfile();
        }
    }

    public function loadProfile(int $id): void
    {
        $profile = BusinessAgentSetting::query()->findOrFail($id);
        $this->selectedId = $profile->id;
        $this->branch_code = $profile->branch_code;
        $this->business_name = $profile->business_name;
        $this->gratitude_greeting = $profile->gratitude_greeting;
        $this->extensive_business_context = (string) ($profile->extensive_business_context ?? '');
        $this->delivery_provider_phone = (string) ($profile->delivery_provider_phone ?? '');
        $this->delivery_required_fields = $profile->delivery_required_fields ?: BusinessAgentSetting::defaultDeliveryFields();
        $this->llm_provider = $profile->llm_provider;
        $this->llm_api_key = (string) ($profile->llm_api_key ?? '');
        $this->llm_model = $profile->llm_model;
        $this->llm_base_url = (string) ($profile->llm_base_url ?? '');
        $this->is_active = (bool) $profile->is_active;
    }

    public function startNewProfile(): void
    {
        $this->selectedId = null;
        $this->branch_code = 'sucursal-'.(BusinessAgentSetting::query()->count() + 1);
        $this->business_name = 'Nexora Pastelería';
        $this->gratitude_greeting = '¡Hola! Muchas gracias por comunicarte con nosotros, es un gusto atenderte hoy.';
        $this->extensive_business_context = '';
        $this->delivery_provider_phone = '';
        $this->delivery_required_fields = BusinessAgentSetting::defaultDeliveryFields();
        $this->llm_provider = 'openai';
        $this->llm_api_key = '';
        $this->llm_model = 'gpt-4o-mini';
        $this->llm_base_url = '';
        $this->is_active = true;
    }

    public function addDeliveryField(): void
    {
        $label = trim($this->newFieldLabel);
        if ($label === '') {
            return;
        }
        $this->delivery_required_fields[] = $label;
        $this->newFieldLabel = '';
    }

    public function removeDeliveryField(int $index): void
    {
        unset($this->delivery_required_fields[$index]);
        $this->delivery_required_fields = array_values($this->delivery_required_fields);
    }

    public function loadContextTemplate(): void
    {
        $this->extensive_business_context = <<<'TXT'
HORARIOS DE ATENCIÓN:
Lunes a Domingo de 7:30 AM a 8:00 PM.

ZONAS DE COBERTURA Y DELIVERY:
- Casco Central / Zona Norte: $1.50 (entrega 20-35 min)
- Zona Sur / Este: $2.50 (entrega 30-45 min)
- Retiro en tienda: Gratis.

PROMOCIONES VIGENTES:
- Combo Trío Pastelero (3 pasteles a elegir + 1 bebida fría).

MÉTODOS DE PAGO Y DATOS BANCARIOS:
1. Pago Móvil (Bs. a tasa BCV del día):
   - Banco: Banesco (0134)
   - Teléfono: 0414-0000000
   - RIF / CI: J-40123456-7
2. Zelle ($):
   - Correo: pagos@nexora.centralces.pro
   - Titular: Nexora Pastelería C.A.
3. Divisas ($) en efectivo o Punto de Venta al retirar en tienda.

POLÍTICAS DE ENTREGA:
- Todo pedido con delivery se despacha inmediatamente después de validar el comprobante de pago en tienda.
- El cliente debe enviar foto del comprobante o número de referencia, monto, banco y fecha.
TXT;
    }

    public function save(): void
    {
        $this->validate([
            'branch_code' => ['required', 'string', 'max:50', 'unique:business_agent_settings,branch_code'.($this->selectedId ? ','.$this->selectedId : '')],
            'business_name' => ['required', 'string', 'max:150'],
            'gratitude_greeting' => ['required', 'string', 'max:1000'],
            'extensive_business_context' => ['nullable', 'string'],
            'delivery_provider_phone' => ['nullable', 'string', 'max:40'],
            'llm_provider' => ['required', 'in:openai,gemini,openai_compatible'],
            'llm_model' => ['required', 'string', 'max:80'],
        ]);

        $fields = array_values(array_filter(array_map('trim', $this->delivery_required_fields), fn ($v) => $v !== ''));
        if (empty($fields)) {
            $fields = BusinessAgentSetting::defaultDeliveryFields();
        }

        $payload = [
            'branch_code' => trim($this->branch_code),
            'business_name' => trim($this->business_name),
            'gratitude_greeting' => trim($this->gratitude_greeting),
            'extensive_business_context' => trim($this->extensive_business_context),
            'delivery_provider_phone' => preg_replace('/\D+/', '', $this->delivery_provider_phone) ?: null,
            'delivery_required_fields' => $fields,
            'llm_provider' => $this->llm_provider,
            'llm_api_key' => trim($this->llm_api_key) ?: null,
            'llm_model' => trim($this->llm_model),
            'llm_base_url' => trim($this->llm_base_url) ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->selectedId) {
            $profile = BusinessAgentSetting::query()->findOrFail($this->selectedId);
            $profile->update($payload);
        } else {
            $profile = BusinessAgentSetting::query()->create($payload);
            $this->selectedId = $profile->id;
        }

        $this->delivery_required_fields = $fields;
        $this->dispatch('notify', type: 'success', message: 'Configuración del Agente IA y Delivery guardada.');
    }

    public function previewSystemPrompt(SalesAgentService $agentService): void
    {
        $this->save();
        $profile = BusinessAgentSetting::query()->findOrFail($this->selectedId);
        $dummyConv = new Conversation([
            'agent_state' => 'GREETING',
        ]);
        $this->promptPreviewText = $agentService->buildDynamicSystemPrompt($profile, $dummyConv, null);
        $this->showPromptPreview = true;
    }

    public function render()
    {
        return view('livewire.crm.business-agent-settings-manager', [
            'profiles' => BusinessAgentSetting::query()->orderBy('id')->get(),
        ]);
    }
}
