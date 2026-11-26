<?php

namespace App\Livewire;

use App\Models\CashSession;
use App\Models\ExchangeRate;
use App\Models\WhatsAppOrder;
use App\Services\BcvRateService;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class TopBar extends Component
{
    public string $displayCurrency = 'USD';

    public float $activeRate = 1.0;

    public bool $showRateModal = false;

    public string $manualRateInput = '';

    public function mount(): void
    {
        $this->displayCurrency = CurrencyHelper::displayCurrency();
        $this->activeRate = CurrencyHelper::activeRate();
        $this->manualRateInput = (string) round($this->activeRate, 2);
    }

    public function setCurrency(string $currency): void
    {
        if (! in_array($currency, ['USD', 'VES'], true)) {
            return;
        }

        session(['display_currency' => $currency]);
        $this->displayCurrency = $currency;
        $this->dispatch('currency-changed', currency: $currency);
    }

    public function syncBcv(BcvRateService $service): void
    {
        $rate = $service->syncFromBcv(Auth::id());

        if ($rate) {
            $this->activeRate = (float) $rate->rate_bs;
            $this->manualRateInput = (string) round($this->activeRate, 2);
            $this->dispatch('rate-updated', rate: $this->activeRate);
            $this->dispatch('notify', type: 'success', message: 'Tasa BCV actualizada: '.number_format($this->activeRate, 2, ',', '.').' Bs/$');
        } else {
            $this->dispatch('notify', type: 'warning', message: 'No se pudo conectar al BCV ahora. Puedes editar la tasa manualmente.');
        }
    }

    public function openRateModal(): void
    {
        $this->manualRateInput = (string) round(ExchangeRate::currentRate('USD'), 4);
        $this->showRateModal = true;
    }

    public function saveManualRate(BcvRateService $service): void
    {
        $this->validate([
            'manualRateInput' => ['required', 'numeric', 'min:0.01'],
        ]);

        $rate = $service->setRate((float) $this->manualRateInput, 'manual', Auth::id(), 'Actualización rápida desde barra superior');
        $this->activeRate = (float) $rate->rate_bs;
        $this->showRateModal = false;
        $this->dispatch('rate-updated', rate: $this->activeRate);
        $this->dispatch('notify', type: 'success', message: 'Tasa BCV fijada en '.number_format($this->activeRate, 2, ',', '.').' Bs/$');
    }

    #[On('rate-updated')]
    public function refreshRate(): void
    {
        $this->activeRate = CurrencyHelper::activeRate();
    }

    public function render()
    {
        $openSession = CashSession::query()->where('status', 'open')->with('cashRegister')->first();
        $pendingApprovals = WhatsAppOrder::query()
            ->where('status', WhatsAppOrder::STATE_PENDING_STORE_APPROVAL)
            ->count();

        return view('livewire.top-bar', [
            'openSession' => $openSession,
            'pendingApprovals' => $pendingApprovals,
        ]);
    }
}
