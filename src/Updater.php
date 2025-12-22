<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp;
use Amp\Parallel\Worker;
use BunnyDdns\Task\GetCurrentIp;
use BunnyDdns\Task\ResolveZoneIds;
use BunnyDdns\Task\UpdateZoneRecord;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;

/**
 * @api
 */
final class Updater
{
    private string $currentIp = self::IP_STARTUP_VALUE;

    private readonly Worker\WorkerPool $workerPool;

    private readonly Zones $zones;

    private const string IP_STARTUP_VALUE = '__MISSING';

    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?Worker\WorkerPool $workerPool = null,
    ) {
        $this->workerPool = $workerPool ?? Worker\workerPool();
        $this->zones = new Zones();
    }

    private function resolveZoneIds(): void
    {
        $task = new ResolveZoneIds($this->config->apiKey);

        $futures = [];
        foreach ($this->config->zoneNames as $name) {
            $resolveTask = $this->workerPool->submit($task);
            $resolveTask->getChannel()->send($name);

            $futures[] = $resolveTask
                ->getFuture()
                ->map(fn (Zone $zone) => $this->zones->set($zone)); // @phpstan-ignore-line
        }

        Amp\Future\awaitAll($futures);

        assert($this->zones->count() === count($this->config->zoneNames));
    }

    private function updateOnIpChange(): void
    {
        static $task = new GetCurrentIp();

        $ip = $this->workerPool->submit($task)->await(); // @phpstan-ignore-line
        assert(is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false);

        $changed = $this->currentIp !== $ip;

        if ($changed) {
            $message = $this->currentIp === self::IP_STARTUP_VALUE
                ? 'Initial IP address detected ({ip}), updating DNS zones'
                : "IP address changed ({$this->currentIp} => {ip}), updating DNS zones";

            $this->logger->notice($message, [
                'ip' => $ip,
                'zones' => $this->zones->names(),
            ]);

            $this->updateZones($ip);
            $this->currentIp = $ip;
        } else {
            $this->logger->info('IP address unchanged, no update needed');
        }

        assert($this->currentIp === $ip);

        $this->logger->info('Running next check in {interval} seconds', [
            'interval' => $this->config->updateInterval,
        ]);
    }

    private function updateZones(string $ip): void
    {
        static $task = new UpdateZoneRecord($this->config->apiKey);

        $futures = [];
        foreach ($this->zones as $zone) {
            $updateTask = $this->workerPool->submit($task); // @phpstan-ignore-line
            $updateTask->getChannel()->send([$zone, $ip]);

            $futures[] = $updateTask
                ->getFuture()
                ->map(fn () => $this->logger->debug('Updated zone record for "{zone}"', [
                    'zone' => $zone->name,
                ]));
        }

        Amp\Future\awaitAll($futures);
    }

    public function run(): void
    {
        // resolve zone + A record IDs
        $this->resolveZoneIds();

        if ($this->config->updateOnStart) {
            $this->updateOnIpChange();
        }

        // schedule periodic IP + update checks
        EventLoop::unreference(EventLoop::repeat(
            $this->config->updateInterval,
            $this->updateOnIpChange(...),
        ));
    }

    public function __debugInfo(): array
    {
        return [
            'config' => $this->config,
        ];
    }
}
