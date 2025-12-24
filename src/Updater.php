<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;

/**
 * @api
 */
final class Updater
{
    private Client $client;

    private string $currentIp = self::IP_STARTUP_VALUE;

    private readonly Zones $zones;

    private const string IP_STARTUP_VALUE = '__MISSING';

    public function __construct(
        private readonly Config $config,
        ?Client $client = null,
        private readonly IpResolver $ipResolver = new IpResolver(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->client = $client ?? new Client($this->config);
        $this->zones = new Zones();
    }

    private function resolveZoneIds(): void
    {
        $futures = [];

        foreach ($this->config->zoneNames as $name) {
            $futures[] = Amp\async($this->client->resolveZone(...), $name)
                ->map(fn (Zone $zone) => $this->zones->set($zone)); // @phpstan-ignore-line
        }

        Amp\Future\awaitAll($futures);

        assert($this->zones->count() === count($this->config->zoneNames));
    }

    private function updateCheck(): void
    {
        $ip = Amp\async($this->ipResolver->run(...))->await();
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
        $futures = [];

        foreach ($this->zones as $zone) {
            $futures[] = Amp\async($this->client->updateZoneRecord(...), $zone, $ip)
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
            $this->updateCheck();
        }

        // schedule periodic IP + update checks
        EventLoop::unreference(EventLoop::repeat(
            $this->config->updateInterval,
            $this->updateCheck(...),
        ));
    }

    public function __debugInfo(): array
    {
        return [
            'config' => $this->config,
            'zones' => $this->zones->names(),
        ];
    }
}
