<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\File;
use InvalidArgumentException;
use Monolog\Level;
use RuntimeException;
use SensitiveParameter;
use UnexpectedValueException;

use function Safe\preg_match;

/**
 * @api
 */
final readonly class Config
{
    private const string API_KEY_FORMAT = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{20}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';

    private const string DEFAULT_LOG_LEVEL = 'info';

    private const int DEFAULT_UPDATE_INTERVAL = 30; // seconds

    /**
     * @param non-empty-string[] $zoneNames
     */
    public function __construct(
        #[SensitiveParameter] public string $apiKey,
        public bool $autoCreateZones,
        public Level $logLevel,
        public int $updateInterval,
        public bool $updateOnStart,
        public array $zoneNames,
    ) {
        if ($this->apiKey === '') {
            throw new InvalidArgumentException('Bunny API key cannot be empty');
        }

        if ($this->updateInterval <= 0) {
            throw new InvalidArgumentException('Update interval must be positive');
        }

        if ($this->zoneNames === []) {
            throw new InvalidArgumentException('At least one DNS zone name is required');
        }
    }

    /**
     * @param array<non-empty-string, mixed> $parameters
     */
    public static function create(array $parameters = []): self
    {
        $apiKey = self::loadApiKeyFromSecret($parameters)
            ?? $parameters['API_KEY']
            ?? throw new RuntimeException('Bunny API key not provided in API_KEY or API_KEY_FILE environment variable');

        if (
            !is_string($apiKey)
            || $apiKey === ''
            || !preg_match(self::API_KEY_FORMAT, $apiKey)
        ) {
            throw new InvalidArgumentException('Invalid Bunny API key provided');
        }

        $autoCreateZones = $parameters['AUTO_CREATE_ZONES'] ?? true;
        $autoCreateZones = filter_var($autoCreateZones, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($autoCreateZones === null) {
            throw new InvalidArgumentException('Invalid value provided in AUTO_CREATE_ZONES environment variable');
        }

        $logLevel = $parameters['LOG_LEVEL'] ?? self::DEFAULT_LOG_LEVEL;

        if (!is_string($logLevel)) {
            throw new InvalidArgumentException('Invalid log level provided in LOG_LEVEL environment variable');
        }

        $logLevel = array_find(
            Level::cases(),
            static fn (Level $level): bool => strcasecmp($level->name, $logLevel) === 0,
        ) ?? throw new InvalidArgumentException('Invalid log level provided in LOG_LEVEL environment variable');

        $updateInterval = $parameters['UPDATE_INTERVAL'] ?? self::DEFAULT_UPDATE_INTERVAL;

        if (!is_numeric($updateInterval)) {
            throw new InvalidArgumentException('Invalid update interval provided in UPDATE_INTERVAL environment variable');
        }

        $updateInterval = (int) $updateInterval;

        if ($updateInterval <= 0) {
            throw new InvalidArgumentException('Invalid update interval provided in UPDATE_INTERVAL environment variable');
        }

        $updateOnStart = $parameters['UPDATE_ON_START'] ?? true;
        $updateOnStart = filter_var($updateOnStart, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($updateOnStart === null) {
            throw new InvalidArgumentException('Invalid value provided in UPDATE_ON_START environment variable');
        }

        $zones = $parameters['ZONES'] ?? '';

        if (!is_string($zones)) {
            throw new InvalidArgumentException('Invalid value provided in ZONES environment variable');
        }

        $zoneNames = array_filter(
            explode(',', $zones),
            static fn (string $name): bool => $name !== ''
                && filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false,
        );
        $zoneNames = array_values(array_unique($zoneNames));

        if ($zoneNames === []) {
            throw new InvalidArgumentException('No valid DNS zone names provided in ZONES environment variable');
        }

        return new self(
            $apiKey,
            $autoCreateZones,
            $logLevel,
            $updateInterval,
            $updateOnStart,
            $zoneNames,
        );
    }

    public static function fromGlobals(): self
    {
        $parameters = array_filter($_SERVER, fn ($key) => is_string($key) && $key !== '', ARRAY_FILTER_USE_KEY);

        return self::create($parameters);
    }

    /**
     * @param array<non-empty-string, mixed> $parameters
     * @return non-empty-string|null
     */
    private static function loadApiKeyFromSecret(
        array $parameters,
    ): ?string {
        $path = $parameters['API_KEY_FILE'] ?? null;

        if ($path === null) {
            return null;
        }

        if (!is_string($path) || $path === '') {
            throw new InvalidArgumentException('Invalid value provided in API_KEY_FILE environment variable');
        }

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
            'auto_create_zones' => $this->autoCreateZones,
            'log_level' => $this->logLevel->getName(),
            'update_interval' => $this->updateInterval,
            'update_on_start' => $this->updateOnStart,
            'zone_names' => $this->zoneNames,
        ];
    }
}
