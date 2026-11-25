<?php

namespace App\Livewire\Settings;

use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use App\Services\BcvRateService;
use App\Services\CostCascadeService;
use App\Services\EvolutionApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Configuración & Métodos de Pago')]
class SettingsManager extends Component
{
    public string $costing_method = 'weighted_average';

    public bool $allow_negative_stock = false;

    public bool $bcv_auto_sync = true;

    public string $manual_bcv_rate = '';

    // New payment method
    public string $pm_name = '';

    public string $pm_currency = 'VES';

    public bool $pm_is_cash = false;

    public bool $pm_requires_reference = true;

    // New user
    public string $user_name = '';

    public string $user_email = '';

    public string $user_password = '';

    public string $user_role = 'cashier';

    // Edit user modal
    public bool $showEditUserModal = false;

    public ?int $editingUserId = null;

    public string $edit_user_name = '';

    public string $edit_user_email = '';

    public string $edit_user_password = '';

    public string $edit_user_role = 'cashier';

    public bool $edit_user_active = true;

    // WhatsApp Evolution API settings
    public string $evolution_api_url = '';

    public string $evolution_api_key = '';

    public string $evolution_instance = 'nexora';

    public string $evolution_webhook_secret = '';

    public bool $whatsapp_auto_reply_menu = true;

    public ?string $evolutionTestResult = null;

    public function mount(): void
    {
        $this->costing_method = Setting::getValue('costing_method', 'weighted_average');
        $this->allow_negative_stock = Setting::getBool('allow_negative_stock', false);
        $this->bcv_auto_sync = Setting::getBool('bcv_auto_sync', true);
        $this->manual_bcv_rate = (string) round(ExchangeRate::currentRate('USD'), 4);
        $this->evolution_api_url = (string) Setting::getValue('evolution_api_url', '');
        $this->evolution_api_key = (string) Setting::getValue('evolution_api_key', '');
        $this->evolution_instance = (string) Setting::getValue('evolution_instance', 'nexora');
        $this->evolution_webhook_secret = (string) Setting::getValue('evolution_webhook_secret', 'nexora-wh-2026');
        $this->whatsapp_auto_reply_menu = Setting::getBool('whatsapp_auto_reply_menu', true);
    }

    public function saveGeneralSettings(CostCascadeService $costCascadeService): void
    {
        $previousMethod = Setting::getValue('costing_method', 'weighted_average');

        Setting::setValue('costing_method', $this->costing_method, 'costing');
        Setting::setValue('allow_negative_stock', $this->allow_negative_stock, 'inventory');
        Setting::setValue('bcv_auto_sync', $this->bcv_auto_sync, 'currency');

        if ($previousMethod !== $this->costing_method) {
            $costCascadeService->recalculateAll('costing_method_change');
        }

        $this->dispatch('notify', type: 'success', message: 'Configuración del sistema actualizada.');
    }

    public function recalculateAllCosts(CostCascadeService $costCascadeService): void
    {
        $costCascadeService->recalculateAll('manual_recalculation');
        $this->dispatch('notify', type: 'success', message: 'Todos los escandallos y productos han sido recalculados.');
    }

    public function updateBcvManual(BcvRateService $bcvService): void
    {
        $this->validate(['manual_bcv_rate' => ['required', 'numeric', 'gt:0']]);
        $rate = $bcvService->setRate((float) $this->manual_bcv_rate, 'manual', Auth::id(), 'Actualización desde Configuración');
        $this->dispatch('rate-updated', rate: (float) $rate->rate_bs);
        $this->dispatch('notify', type: 'success', message: 'Tasa BCV actualizada.');
    }

    public function createPaymentMethod(): void
    {
        $this->validate([
            'pm_name' => ['required', 'string', 'max:80'],
            'pm_currency' => ['required', 'in:VES,USD'],
        ]);

        PaymentMethod::query()->create([
            'name' => $this->pm_name,
            'code' => Str::slug($this->pm_name, '_').'_'.Str::lower(Str::random(4)),
            'currency' => $this->pm_currency,
            'is_cash' => $this->pm_is_cash,
            'requires_reference' => $this->pm_requires_reference,
            'is_active' => true,
            'sort_order' => (PaymentMethod::query()->max('sort_order') ?? 0) + 1,
        ]);

        $this->reset(['pm_name']);
        $this->dispatch('notify', type: 'success', message: 'Método de pago creado.');
    }

    public function togglePaymentMethod(int $id): void
    {
        $pm = PaymentMethod::query()->findOrFail($id);
        $pm->update(['is_active' => ! $pm->is_active]);
    }

    public function createUser(): void
    {
        $this->validate([
            'user_name' => ['required', 'string', 'max:120'],
            'user_email' => ['required', 'email', 'unique:users,email'],
            'user_password' => ['required', 'string', 'min:6'],
            'user_role' => ['required', 'in:admin,supervisor,cashier,kitchen'],
        ]);

        User::query()->create([
            'name' => $this->user_name,
            'email' => $this->user_email,
            'password' => Hash::make($this->user_password),
            'role' => $this->user_role,
            'is_active' => true,
        ]);

        $this->reset(['user_name', 'user_email', 'user_password']);
        $this->dispatch('notify', type: 'success', message: 'Usuario creado correctamente.');
    }

    public function toggleUser(int $id): void
    {
        if ($id === Auth::id()) {
            $this->dispatch('notify', type: 'warning', message: 'No puedes desactivar tu propio usuario activo.');

            return;
        }
        $user = User::query()->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);
    }

    public function editUser(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingUserId = $user->id;
        $this->edit_user_name = $user->name;
        $this->edit_user_email = $user->email;
        $this->edit_user_password = '';
        $this->edit_user_role = $user->role;
        $this->edit_user_active = (bool) $user->is_active;
        $this->showEditUserModal = true;
    }

    public function updateUser(): void
    {
        $user = User::query()->findOrFail($this->editingUserId);

        $this->validate([
            'edit_user_name' => ['required', 'string', 'max:120'],
            'edit_user_email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'edit_user_password' => ['nullable', 'string', 'min:6'],
            'edit_user_role' => ['required', 'in:admin,supervisor,cashier,kitchen'],
        ]);

        if ($user->id === Auth::id() && (! $this->edit_user_active || $this->edit_user_role !== 'admin')) {
            $this->dispatch('notify', type: 'warning', message: 'No puedes quitar el rol administrador ni desactivar tu propia sesión.');

            return;
        }

        $payload = [
            'name' => $this->edit_user_name,
            'email' => $this->edit_user_email,
            'role' => $this->edit_user_role,
            'is_active' => $this->edit_user_active,
        ];

        if (trim($this->edit_user_password) !== '') {
            $payload['password'] = Hash::make($this->edit_user_password);
        }

        $user->update($payload);

        $this->reset(['showEditUserModal', 'editingUserId', 'edit_user_password']);
        $this->dispatch('notify', type: 'success', message: 'Usuario actualizado correctamente.');
    }

    public function saveWhatsAppSettings(): void
    {
        Setting::setValue('evolution_api_url', rtrim(trim($this->evolution_api_url), '/'), 'whatsapp');
        Setting::setValue('evolution_api_key', trim($this->evolution_api_key), 'whatsapp');
        Setting::setValue('evolution_instance', trim($this->evolution_instance) ?: 'nexora', 'whatsapp');
        Setting::setValue('evolution_webhook_secret', trim($this->evolution_webhook_secret), 'whatsapp');
        Setting::setValue('whatsapp_auto_reply_menu', $this->whatsapp_auto_reply_menu, 'whatsapp');

        $this->dispatch('notify', type: 'success', message: 'Configuración de WhatsApp (Evolution API) guardada.');
    }

    public function testEvolutionConnection(EvolutionApiService $evolution): void
    {
        $this->saveWhatsAppSettings();
        $res = $evolution->getConnectionState();
        $this->evolutionTestResult = $res['ok']
            ? 'Conectado (estado instancia: '.$res['state'].')'
            : 'Sin conexión: '.($res['error'] ?? $res['state']);
    }

    public function render()
    {
        return view('livewire.settings.settings-manager', [
            'ratesHistory' => ExchangeRate::query()->with('createdBy')->latest('effective_at')->limit(10)->get(),
            'paymentMethods' => PaymentMethod::query()->orderBy('sort_order')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }
}
