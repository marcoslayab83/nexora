<?php

namespace App\Livewire\Cash;

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Services\CashService;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Control de Caja')]
class CashManager extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    // Open session form
    public int $cash_register_id = 0;

    public string $opening_bs = '0';

    public string $opening_usd = '0';

    // Movement form
    public string $movement_type = 'out';

    public string $movement_currency = 'VES';

    public string $movement_amount = '0';

    public string $movement_reason = '';

    // Close session form
    public string $counted_cash_bs = '0';

    public string $counted_cash_usd = '0';

    public string $closing_notes = '';

    public function mount(): void
    {
        $this->refreshCurrency();
        $this->cash_register_id = CashRegister::query()->where('is_active', true)->value('id') ?? 0;
    }

    #[On('currency-changed')]
    #[On('rate-updated')]
    public function refreshCurrency(): void
    {
        $this->currency = CurrencyHelper::displayCurrency();
        $this->rate = CurrencyHelper::activeRate();
    }

    public function openSession(CashService $cashService): void
    {
        $this->validate([
            'cash_register_id' => ['required', 'exists:cash_registers,id'],
            'opening_bs' => ['required', 'numeric', 'min:0'],
            'opening_usd' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $cashService->openSession(
                $this->cash_register_id,
                Auth::id(),
                (float) $this->opening_bs,
                (float) $this->opening_usd
            );

            $this->dispatch('notify', type: 'success', message: 'Jornada de caja abierta correctamente.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function registerMovement(CashService $cashService): void
    {
        $this->validate([
            'movement_type' => ['required', 'in:in,out'],
            'movement_currency' => ['required', 'in:VES,USD'],
            'movement_amount' => ['required', 'numeric', 'gt:0'],
            'movement_reason' => ['required', 'string', 'max:200'],
        ]);

        $session = $cashService->anyOpenSession();
        if (! $session) {
            $this->dispatch('notify', type: 'error', message: 'No hay caja abierta.');

            return;
        }

        try {
            $cashService->addMovement(
                $session,
                $this->movement_type,
                $this->movement_currency,
                (float) $this->movement_amount,
                $this->movement_reason,
                Auth::id()
            );

            $this->reset(['movement_amount', 'movement_reason']);
            $this->movement_amount = '0';
            $this->dispatch('notify', type: 'success', message: 'Movimiento de caja registrado.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function closeSession(CashService $cashService): void
    {
        $this->validate([
            'counted_cash_bs' => ['required', 'numeric', 'min:0'],
            'counted_cash_usd' => ['required', 'numeric', 'min:0'],
        ]);

        $session = $cashService->anyOpenSession();
        if (! $session) {
            $this->dispatch('notify', type: 'error', message: 'No hay caja abierta para cerrar.');

            return;
        }

        try {
            $closed = $cashService->closeSession(
                $session,
                (float) $this->counted_cash_bs,
                (float) $this->counted_cash_usd,
                $this->closing_notes ?: null
            );

            $this->reset(['counted_cash_bs', 'counted_cash_usd', 'closing_notes']);
            $this->dispatch('notify', type: 'success', message: "Turno cerrado. Utilidad estimada: \${$closed->estimated_profit_usd}");
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function render(CashService $cashService)
    {
        $openSession = CashSession::query()
            ->where('status', 'open')
            ->with(['cashRegister', 'user', 'movements.user'])
            ->latest('opened_at')
            ->first();

        $summary = $openSession ? $cashService->sessionSummary($openSession) : null;

        return view('livewire.cash.cash-manager', [
            'registers' => CashRegister::query()->where('is_active', true)->get(),
            'openSession' => $openSession,
            'summary' => $summary,
            'closedSessions' => CashSession::query()->where('status', 'closed')->with(['cashRegister', 'user'])->latest('closed_at')->limit(10)->get(),
        ]);
    }
}
