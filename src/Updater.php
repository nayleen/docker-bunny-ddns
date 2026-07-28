<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use RuntimeException;
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
        private readonly ?Health $health = null,
    ) {
        $this->client = $client ?? new Client($this->config);
        $this->zones = new Zones();
    }

    /**
     * @param Health::STATUS_* $status
     * @param non-empty-string|null $ip
     */
    private function reportHealth(string $status, ?string $ip = null): void
    {
        $ip ??= $this->currentIp;

        $this->health?->update(
            $status,
            $ip === self::IP_STARTUP_VALUE ? null : $ip,
            $this->zones->names(),
        );
    }

    private function resolveZoneIds(): void
    {
        $futures = [];

        foreach ($this->config->zoneNames as $name) {
            /** @var Amp\Future<Zone|null> $future */
            $future = Amp\async(fn (): ?Zone => $this->client->resolveZone($name));
            $futures[] = $future
                ->map(function (?Zone $zone) use ($name): void {
                    if ($zone !== null) {
                        $this->zones->set($zone);

                        return;
                    }

                    if (!$this->config->autoCreateZones) {
                        throw new RuntimeException('Zone not found: ' . $name);
                    }

                    $this->logger->notice('Zone "{name}" not found, creating it', [
                        'name' => $name,
                    ]);

                    $this->zones->set($this->client->createZone($name));
                });
        }

        // Observe every future so sibling errors are handled.
        [$errors] = Amp\Future\awaitAll($futures);

        if ($errors !== []) {
            throw array_shift($errors);
        }

        assert($this->zones->count() === count($this->config->zoneNames));
    }

    private function updateCheck(): void
    {
        $ip = null;

        // Keep transient upstream failures from stopping future checks.
        try {
            /** @var non-empty-string $ip */
            $ip = Amp\async($this->ipResolver->run(...))->await();
            assert($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false);

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

                assert($this->currentIp === $ip);
            } else {
                $this->logger->info('IP address unchanged, no update needed');
            }

            $this->reportHealth(Health::STATUS_HEALTHY, $ip);
        } catch (Throwable $error) {
            $this->logger->error('Update check failed', [
                'exception' => $error,
                'ip' => $ip,
                'zones' => $this->zones->names(),
            ]);

            $this->reportHealth(Health::STATUS_UNHEALTHY, $ip);
        }

        $this->logger->info('Running next check in {interval} seconds', [
            'interval' => $this->config->updateInterval,
        ]);
    }

    /**
     * @param non-empty-string $ip
     */
    private function updateZones(string $ip): void
    {
        $futures = [];

        foreach ($this->zones as $zone) {
            $futures[] = Amp\async($this->client->updateZoneRecord(...), $zone, $ip)
                ->map(fn () => $this->logger->debug('Updated zone record for "{zone}"', [
                    'zone' => $zone->name,
                ]));
        }

        Amp\Future\await($futures);
    }

    public function run(): void
    {
        $this->resolveZoneIds();

        $this->reportHealth(Health::STATUS_STARTING);

        if ($this->config->updateOnStart) {
            $this->updateCheck();
        }

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
