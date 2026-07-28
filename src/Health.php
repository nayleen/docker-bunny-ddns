<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\File;
use Safe;
use Throwable;

/**
 * @api
 * @phpstan-type HealthState array{
 *     status: non-empty-string,
 *     ip: non-empty-string|null,
 *     zones: list<non-empty-string>,
 * }
 */
final readonly class Health
{
    public const string FILE_PATH = '/dev/shm/bunny-ddns.json';

    // Allows short update intervals enough time to complete.
    public const float MIN_TIMEOUT = 60.0; // seconds

    public const string STATUS_HEALTHY = 'healthy';

    public const string STATUS_STARTING = 'starting';

    public const string STATUS_UNHEALTHY = 'unhealthy';

    /**
     * @param float $timeout seconds a healthy status may go without a refresh
     * @param non-empty-string $filePath
     */
    public function __construct(
        private float $timeout = self::MIN_TIMEOUT,
        private string $filePath = self::FILE_PATH,
    ) {}

    /**
     * @param non-empty-string $filePath
     *
     * @return HealthState|null
     */
    public static function query(string $filePath = self::FILE_PATH): ?array
    {
        try {
            $state = Safe\json_decode(File\read($filePath), true);
        } catch (Throwable) {
            return null;
        }

        if (!is_array($state)) {
            return null;
        }

        $status = $state['status'] ?? null;
        $expiresAt = $state['expires_at'] ?? null;
        $ip = $state['ip'] ?? null;
        $zones = $state['zones'] ?? [];

        if (
            !is_string($status)
            || $status === ''
            || ($status === self::STATUS_HEALTHY && !is_int($expiresAt) && !is_float($expiresAt))
            || ($ip !== null && (!is_string($ip) || $ip === ''))
            || !is_array($zones)
        ) {
            return null;
        }

        if ($status === self::STATUS_HEALTHY && $expiresAt <= microtime(true)) {
            $status = self::STATUS_UNHEALTHY;
        }

        return [
            'status' => $status,
            'ip' => $ip,
            'zones' => array_values(array_filter(
                $zones,
                static fn (mixed $zone): bool => is_string($zone) && $zone !== '',
            )),
        ];
    }

    public static function timeoutForInterval(int $updateInterval): float
    {
        return max(self::MIN_TIMEOUT, $updateInterval * 2);
    }

    /**
     * @param self::STATUS_* $status
     * @param non-empty-string|null $ip
     * @param list<non-empty-string> $zones
     */
    public function update(string $status, ?string $ip = null, array $zones = []): void
    {
        $temporaryPath = $this->filePath . '.tmp';

        File\write($temporaryPath, Safe\json_encode([
            'status' => $status,
            'expires_at' => $status === self::STATUS_HEALTHY ? microtime(true) + $this->timeout : null,
            'ip' => $ip,
            'zones' => $zones,
        ]));
        File\move($temporaryPath, $this->filePath);
    }
}
