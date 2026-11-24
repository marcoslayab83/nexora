<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\BcvRateService;
use Illuminate\Console\Command;

class SyncBcvRateCommand extends Command
{
    protected $signature = 'bcv:sync-rate {--force : Sync even if bcv_auto_sync setting is disabled}';

    protected $description = 'Sincroniza la tasa oficial USD/VES del BCV';

    public function handle(BcvRateService $service): int
    {
        if (! $this->option('force') && ! Setting::getBool('bcv_auto_sync', true)) {
            $this->info('Sincronización automática BCV deshabilitada en ajustes.');

            return self::SUCCESS;
        }

        $rate = $service->syncFromBcv();

        if (! $rate) {
            $this->warn('No se pudo obtener la tasa BCV en este momento. Se mantiene la tasa vigente.');

            return self::SUCCESS;
        }

        $this->info("Tasa BCV actualizada: {$rate->rate_bs} Bs/USD");

        return self::SUCCESS;
    }
}
