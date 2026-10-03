<?php

namespace App\Console\Commands;

use App\Http\Requests\ExtApi\JogoLautMonitoringRequest;
use App\Services\DatasourceRegistry;
use App\Services\JogoLaut\JogoLautMonitoringService;
use Illuminate\Console\Command;

/**
 * Builds the default JOGO LAUT monitoring payload as each snapshot bucket
 * opens, so that visitors read a cache somebody else already filled instead
 * of waiting on seven upstream tables themselves.
 *
 * Scheduled once per bucket (app/Console/Kernel.php). Warming the default
 * covers every request that varies only `include` or `locale`, and the raw
 * reads it caches serve any `window` for the default `days` as well.
 */
class JogoLautWarm extends Command
{
    protected $signature = 'cms:jogolaut-warm';

    protected $description = 'Mengisi cache payload monitoring JOGO LAUT untuk blok waktu yang sedang berjalan';

    public function handle(DatasourceRegistry $datasources, JogoLautMonitoringService $monitoring): int
    {
        if (! $datasources->isConfigured('jogolaut')) {
            // Normal on a laptop or a server that does not serve JOGO LAUT.
            $this->components->info('Datasource jogolaut belum dikonfigurasi; dilewati.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        $failed = [];

        foreach (JogoLautMonitoringRequest::LOCALES as $locale) {
            $payload = $monitoring->build(JogoLautMonitoringService::defaults($locale));

            foreach ($payload['sections'] as $key => $section) {
                if (! empty($section['error'])) {
                    $failed[$key] = true;
                }
            }
        }

        $ms = (int) round((microtime(true) - $started) * 1000);

        if ($failed !== []) {
            $this->components->error('Section gagal: '.implode(', ', array_keys($failed))." ({$ms} ms)");

            return self::FAILURE;
        }

        $this->components->info("Cache JOGO LAUT terisi ({$ms} ms).");

        return self::SUCCESS;
    }
}
