<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\File;
use InvalidArgumentException;
use Monolog\Level;
use RuntimeException;
use SensitiveParameter;
use UnexpectedValueException;
use UnhandledMatchError;

/**
 * @api
 */
final readonly class Config
{
    private const string API_KEY_FORMAT = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{20}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';

    private const string DEFAULT_LOG_LEVEL = 'info';

    private const int DEFAULT_UPDATE_INTERVAL = 30; // seconds

    /**
     * @param non-empty-string $apiKey
     * @param positive-int $updateInterval
     * @param non-empty-string[] $zoneNames
     */
    public function __construct(
        #[SensitiveParameter] public string $apiKey,
        public Level $logLevel,
        public int $updateInterval,
        public bool $updateOnStart,
        public array $zoneNames,
    ) {
        assert($this->apiKey !== '');
        assert($this->updateInterval > 0);
        assert(count($this->zoneNames) > 0);
    }

    /**
     * @param array<non-empty-string, mixed> $env
     * @param array<non-empty-string, mixed> $server
     */
    public static function create(array $env, array $server): self
    {
        $apiKey = self::loadApiKeyFromSecret($env, $server)
            ?? $env['API_KEY']
            ?? $server['API_KEY']
            ?? throw new RuntimeException('Bunny API key not provided in API_KEY or API_KEY_FILE environment variable');

        assert(is_string($apiKey) && $apiKey !== '');

        if (!preg_match(self::API_KEY_FORMAT, $apiKey)) {
            throw new InvalidArgumentException('Invalid Bunny API key format provided in API_KEY environment variable');
        }

        $logLevel = $env['LOG_LEVEL']
            ?? $server['LOG_LEVEL']
            ?? self::DEFAULT_LOG_LEVEL;

        try {
            $logLevel = Level::fromName($logLevel); // @phpstan-ignore-line
        } catch (UnhandledMatchError) {
            throw new InvalidArgumentException('Invalid log level provided in LOG_LEVEL environment variable');
        }

        $updateInterval = $env['UPDATE_INTERVAL']
            ?? $server['UPDATE_INTERVAL']
            ?? self::DEFAULT_UPDATE_INTERVAL;

        if (!is_numeric($updateInterval)) {
            throw new InvalidArgumentException('Invalid update interval provided in UPDATE_INTERVAL environment variable');
        }

        $updateInterval = (int) $updateInterval;

        if ($updateInterval <= 0) {
            throw new UnexpectedValueException('Invalid update interval provided in UPDATE_INTERVAL environment variable');
        }

        $updateOnStart = $env['UPDATE_ON_START']
            ?? $server['UPDATE_ON_START']
            ?? true;

        $updateOnStart = filter_var($updateOnStart, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($updateOnStart === null) {
            throw new InvalidArgumentException('Invalid value provided in UPDATE_ON_START environment variable');
        }

        $zones = $env['ZONES'] ?? $server['ZONES'] ?? '';
        assert(is_string($zones));

        $zoneNames = array_filter(
            explode(',', $zones),
            static fn (string $name): bool => $name !== '' && filter_var($name, FILTER_VALIDATE_DOMAIN) !== false,
        );

        if ($zoneNames === []) {
            throw new UnexpectedValueException('No valid DNS zone names provided in ZONES environment variable');
        }

        return new self(
            $apiKey,
            $logLevel,
            $updateInterval,
            $updateOnStart,
            $zoneNames,
        );
    }

    /**
     * @param array<non-empty-string, mixed> $env
     * @param array<non-empty-string, mixed> $server
     * @return non-empty-string|null
     */
    private static function loadApiKeyFromSecret(
        array $env,
        array $server,
    ): ?string {
        $path = $env['API_KEY_FILE']
            ?? $server['API_KEY_FILE']
            ?? null;

        if ($path === null) {
            return null;
        }

        assert(is_string($path) && $path !== '');

        if (!File\exists($path)) {
            return null;
        }

        $apiKey = trim(File\read($path));

        if ($apiKey === '') {
            throw new UnexpectedValueException('API key file is empty');
        }

        return $apiKey;
    }

    public function __debugInfo(): array
    {
        return [
            'api_key' => '***sensitive***',
            'log_level' => $this->logLevel->getName(),
            'update_interval' => $this->updateInterval,
            'update_on_start' => $this->updateOnStart,
            'zone_names' => $this->zoneNames,
        ];
    }
}
