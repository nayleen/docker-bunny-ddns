<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use Throwable;

/**
 * @api
 */
final class Updater
{
    private Client $client;

    /**
     * @var non-empty-string
     */
    private string $currentIp = self::IP_STARTUP_VALUE;

    private readonly Zones $zones;

    /**
     * @var non-empty-string
     */
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
                ->catch(function (Throwable $error) use ($name) {
                    if (!$this->config->autoCreateZones) {
                        throw $error;
                    }

                    $this->logger->notice('Zone "{name}" not found, creating it', [
                        'name' => $name,
                    ]);

                    return $this->client->createZone($name);
                })
                ->map(fn (Zone $zone) => $this->zones->set($zone));
        }

        Amp\Future\awaitAll($futures);

        assert($this->zones->count() === count($this->config->zoneNames));
    }

    private function updateCheck(): void
    {
        $ip = Amp\async($this->ipResolver->run(...))->await();
        assert(is_string($ip) && $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false);

        $changed = $this->currentIp !== $ip;

        if ($changed) {
            $message = $this->currentIp === self::IP_STARTUP_VALUE
                ? 'Initial IP address detected ({ip}), updating DNS zones'
                : "IP address changed ({$this->currentIp} => {ip}), updating DNS zones";

            $this->logger->notice($message, [
                'ip' => $ip,
                'zones' => $this->zones->names(),
            ]);

            try {
                $this->updateZones($ip)
                    ->map(fn () => $this->currentIp = $ip)
                    ->await();

                assert($this->currentIp === $ip);
            } catch (Throwable) {
                $this->logger->error('Failed to update DNS zone records');
            }
        } else {
            $this->logger->info('IP address unchanged, no update needed');
        }

        $this->logger->info('Running next check in {interval} seconds', [
            'interval' => $this->config->updateInterval,
        ]);
    }

    /**
     * @param non-empty-string $ip
     */
    private function updateZones(string $ip): Amp\Future // @phpstan-ignore-line
    {
        $futures = [];

        foreach ($this->zones as $zone) {
            $futures[] = Amp\async($this->client->updateZoneRecord(...), $zone, $ip)
                ->map(fn () => $this->logger->debug('Updated zone record for "{zone}"', [
                    'zone' => $zone->name,
                ]));
        }

        Amp\Future\await($futures);

        return Amp\Future::complete();
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
