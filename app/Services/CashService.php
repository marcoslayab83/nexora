<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\SalePayment;
use DomainException;
use Illuminate\Support\Facades\DB;

class CashService
{
    public function openSessionForUser(int $userId): ?CashSession
    {
        return CashSession::query()
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    public function anyOpenSession(): ?CashSession
    {
        return CashSession::query()
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    public function openSession(int $cashRegisterId, int $userId, float $openingBs, float $openingUsd): CashSession
    {
        return DB::transaction(function () use ($cashRegisterId, $userId, $openingBs, $openingUsd) {
            $existing = CashSession::query()
                ->where('cash_register_id', $cashRegisterId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new DomainException('Ya existe una jornada de caja abierta en esta caja registradora.');
            }

            CashRegister::query()->findOrFail($cashRegisterId);

            return CashSession::query()->create([
                'cash_register_id' => $cashRegisterId,
                'user_id' => $userId,
                'opened_at' => now(),
                'opening_bs' => round($openingBs, 2),
                'opening_usd' => round($openingUsd, 2),
                'expected_cash_bs' => round($openingBs, 2),
                'expected_cash_usd' => round($openingUsd, 2),
                'status' => 'open',
            ]);
        });
    }

    public function addMovement(
        CashSession $session,
        string $type,
        string $currency,
        float $amount,
        string $reason,
        int $userId
    ): CashMovement {
        if ($session->status !== 'open') {
            throw new DomainException('No se pueden registrar movimientos en una caja cerrada.');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El monto del movimiento debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($session, $type, $currency, $amount, $reason, $userId) {
            $locked = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            $delta = $type === 'in' ? $amount : -$amount;

            if ($currency === 'VES') {
                $locked->update([
                    'expected_cash_bs' => round((float) $locked->expected_cash_bs + $delta, 2),
                ]);
            } else {
                $locked->update([
                    'expected_cash_usd' => round((float) $locked->expected_cash_usd + $delta, 2),
                ]);
            }

            return CashMovement::query()->create([
                'cash_session_id' => $locked->id,
                'user_id' => $userId,
                'type' => $type,
                'currency' => $currency,
                'amount' => round($amount, 2),
                'reason' => $reason,
            ]);
        });
    }

    public function sessionSummary(CashSession $session): array
    {
        $paymentsByMethod = SalePayment::query()
            ->selectRaw('payment_method_id, currency, SUM(amount_original) as total_original, SUM(amount_usd_equivalent) as total_usd')
            ->whereHas('sale', fn ($q) => $q->where('cash_session_id', $session->id)->where('status', 'completed'))
            ->with('paymentMethod')
            ->groupBy('payment_method_id', 'currency')
            ->get();

        $salesStats = $session->sales()
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total_usd), 0) as total_usd, COALESCE(SUM(total_bs), 0) as total_bs, COALESCE(SUM(total_cost_usd), 0) as total_cost_usd, COALESCE(SUM(net_profit_usd), 0) as net_profit_usd, COALESCE(SUM(change_bs), 0) as total_change_bs, COALESCE(SUM(change_usd), 0) as total_change_usd')
            ->first();

        return [
            'payments_by_method' => $paymentsByMethod,
            'sales_count' => (int) ($salesStats->sales_count ?? 0),
            'total_usd' => (float) ($salesStats->total_usd ?? 0),
            'total_bs' => (float) ($salesStats->total_bs ?? 0),
            'total_cost_usd' => (float) ($salesStats->total_cost_usd ?? 0),
            'net_profit_usd' => (float) ($salesStats->net_profit_usd ?? 0),
            'total_change_bs' => (float) ($salesStats->total_change_bs ?? 0),
            'total_change_usd' => (float) ($salesStats->total_change_usd ?? 0),
        ];
    }

    public function closeSession(
        CashSession $session,
        float $countedBs,
        float $countedUsd,
        ?string $closingNotes = null
    ): CashSession {
        return DB::transaction(function () use ($session, $countedBs, $countedUsd, $closingNotes) {
            $locked = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->status !== 'open') {
                throw new DomainException('Esta jornada de caja ya fue cerrada.');
            }

            $summary = $this->sessionSummary($locked);
            $diffBs = round($countedBs - (float) $locked->expected_cash_bs, 2);
            $diffUsd = round($countedUsd - (float) $locked->expected_cash_usd, 2);

            $locked->update([
                'closed_at' => now(),
                'counted_cash_bs' => round($countedBs, 2),
                'counted_cash_usd' => round($countedUsd, 2),
                'difference_bs' => $diffBs,
                'difference_usd' => $diffUsd,
                'total_sales_usd' => $summary['total_usd'],
                'total_cost_usd' => $summary['total_cost_usd'],
                'estimated_profit_usd' => $summary['net_profit_usd'],
                'status' => 'closed',
                'closing_notes' => $closingNotes,
            ]);

            return $locked->fresh(['cashRegister', 'user']);
        });
    }
}
