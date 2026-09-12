<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Observability\BusinessHealthService;
use App\Services\Observability\OperationalAlertService;
use App\Services\Observability\QueueHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class MonitorOperationalHealth extends Command
{
    protected $signature = 'operations:monitor-health
        {--json : Print machine-readable output}
        {--no-alert : Do not deliver the external alert}';

    protected $description = 'Check queue, payment expiration, and ticket email health';

    public function handle(
        QueueHealthService $queueHealth,
        BusinessHealthService $businessHealth,
        OperationalAlertService $alerts
    ): int {
        $context = [
            'healthy' => true,
            'queue' => $queueHealth->snapshot(),
            'business' => $businessHealth->snapshot(),
        ];
        $context['healthy'] = $context['queue']['healthy'] && $context['business']['healthy'];

        if (! $context['healthy']) {
            Log::critical('Operational health check failed', $context);
            if (! $this->option('no-alert')) {
                $alerts->send('cinema.operational_health_failed', $context);
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($context, JSON_THROW_ON_ERROR));
        } else {
            $this->components->twoColumnDetail('Queue health', $context['queue']['healthy'] ? 'OK' : 'ALERT');
            foreach ($context['business']['checks'] as $name => $check) {
                $this->components->twoColumnDetail(
                    str_replace('_', ' ', ucfirst($name)),
                    "{$check['count']} / {$check['threshold']}"
                );
            }
        }

        return $context['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
