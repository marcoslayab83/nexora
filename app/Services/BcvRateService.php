<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BcvRateService
{
    public function setRate(float $rateBs, string $source = 'manual', ?int $userId = null, ?string $notes = null): ExchangeRate
    {
        if ($rateBs <= 0) {
            throw new \InvalidArgumentException('La tasa BCV debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($rateBs, $source, $userId, $notes) {
            ExchangeRate::query()
                ->where('currency_code', 'USD')
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return ExchangeRate::query()->create([
                'currency_code' => 'USD',
                'rate_bs' => round($rateBs, 6),
                'source' => $source,
                'is_active' => true,
                'effective_at' => now(),
                'created_by' => $userId,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Attempts to fetch the official BCV USD rate from public BCV/DolarAPI endpoints.
     */
    public function syncFromBcv(?int $userId = null): ?ExchangeRate
    {
        $rate = $this->fetchFromDolarApi() ?? $this->fetchDirectlyFromBcv();

        if ($rate === null || $rate <= 0) {
            Log::warning('[NEXORA][BCV] No se pudo obtener tasa BCV automática en este intento.');

            return null;
        }

        return $this->setRate($rate, 'bcv_auto', $userId, 'Actualización automática BCV');
    }

    protected function fetchFromDolarApi(): ?float
    {
        try {
            $response = Http::timeout(8)->acceptJson()->get('https://ve.dolarapi.com/v1/dolares/oficial');
            if ($response->successful()) {
                $promedio = $response->json('promedio');
                if (is_numeric($promedio) && (float) $promedio > 0) {
                    return (float) $promedio;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[NEXORA][BCV] Fallo DolarAPI: '.$e->getMessage());
        }

        return null;
    }

    protected function fetchDirectlyFromBcv(): ?float
    {
        try {
            $response = Http::timeout(10)->withoutVerifying()->get('https://www.bcv.org.ve/');
            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();
            if (preg_match('/id="dolar".*?<strong>\s*([0-9.,]+)\s*<\/strong>/s', $html, $matches)) {
                $normalized = str_replace(['.', ','], ['', '.'], trim($matches[1]));
                if (is_numeric($normalized) && (float) $normalized > 0) {
                    return (float) $normalized;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('[NEXORA][BCV] Fallo scraping directo BCV: '.$e->getMessage());
        }

        return null;
    }
}
