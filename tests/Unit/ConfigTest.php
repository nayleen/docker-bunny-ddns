<?php

declare(strict_types = 1);

namespace BunnyDdns\Tests\Unit;

use BunnyDdns\Config;
use InvalidArgumentException;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Safe;

/**
 * @internal
 */
final class ConfigTest extends TestCase
{
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private const array VALID_PARAMETERS = [
        'API_KEY' => '00000000-0000-0000-0000-00000000000000000000-0000-0000-0000-000000000000',
        'ZONES' => 'example.com',
    ];

    /**
     * @return iterable<string, array<string, array<non-empty-string, mixed>>>
     */
    public static function configAssertions(): iterable
    {
        yield 'missing API key' => [
            'parameters' => array_intersect_assoc(self::VALID_PARAMETERS, array_flip(['ZONES'])),
        ];

        yield 'missing zones' => [
            'parameters' => array_intersect_assoc(self::VALID_PARAMETERS, array_flip(['API_KEY'])),
        ];
    }

    /**
     * @return iterable<string, array{array<non-empty-string, mixed>}>
     */
    public static function invalidConfig(): iterable
    {
        foreach ([
            'invalid API key' => ['API_KEY', 'invalid'],
            'non-string API key' => ['API_KEY', []],
            'invalid API key file' => ['API_KEY_FILE', []],
            'invalid auto-create flag' => ['AUTO_CREATE_ZONES', 'maybe'],
            'invalid log level' => ['LOG_LEVEL', 'verbose'],
            'non-string log level' => ['LOG_LEVEL', []],
            'non-numeric interval' => ['UPDATE_INTERVAL', 'soon'],
            'non-positive interval' => ['UPDATE_INTERVAL', 0],
            'invalid update-on-start flag' => ['UPDATE_ON_START', 'maybe'],
            'invalid zone' => ['ZONES', 'not a domain'],
            'non-string zones' => ['ZONES', []],
        ] as $name => [$key, $value]) {
            $parameters = self::VALID_PARAMETERS;
            $parameters[$key] = $value;

            yield $name => [$parameters];
        }
    }

    /**
     * @return iterable<string, array{string, int, array<non-empty-string>}>
     */
    public static function invalidConstructorArguments(): iterable
    {
        yield 'empty API key' => ['', 30, ['example.com']];
        yield 'non-positive interval' => [self::VALID_PARAMETERS['API_KEY'], 0, ['example.com']];
        yield 'empty zones' => [self::VALID_PARAMETERS['API_KEY'], 30, []];
    }

    /**
     * @param array<non-empty-string> $zones
     */
    #[DataProvider('invalidConstructorArguments')]
    #[Test]
    public function constructor_rejects_invalid_values(string $apiKey, int $interval, array $zones): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Config($apiKey, true, Level::Info, $interval, true, $zones);
    }

    #[Test]
    public function create_deduplicates_zones(): void
    {
        $parameters = self::VALID_PARAMETERS;
        $parameters['ZONES'] = 'example.com,example.com';

        self::assertSame(['example.com'], Config::create($parameters)->zoneNames);
    }

    #[Test]
    public function create_loads_api_key_from_secret_file(): void
    {
        $apiKeyFile = dirname(__DIR__) . '/Fixtures/api_key_file.txt';

        $parameters = [
            'API_KEY_FILE' => $apiKeyFile,
            'ZONES' => 'example.com',
        ];

        $config = Config::create($parameters);

        self::assertSame($config->apiKey, trim(Safe\file_get_contents($apiKeyFile)));
    }

    #[Test]
    public function create_reads_log_level(): void
    {
        $parameters = self::VALID_PARAMETERS;
        $parameters['LOG_LEVEL'] = 'ALERT';

        $config = Config::create($parameters);

        self::assertSame(Level::Alert, $config->logLevel);
    }

    #[Test]
    public function create_reads_update_interval(): void
    {
        $parameters = self::VALID_PARAMETERS;
        $parameters['UPDATE_INTERVAL'] = '3600';

        $config = Config::create($parameters);

        self::assertSame(3600, $config->updateInterval);
    }

    #[Test]
    public function create_reads_update_on_start(): void
    {
        $parameters = self::VALID_PARAMETERS;
        $parameters['UPDATE_ON_START'] = '0';

        $config = Config::create($parameters);

        self::assertFalse($config->updateOnStart);
    }

    /**
     * @param array<non-empty-string, mixed> $parameters
     */
    #[DataProvider('invalidConfig')]
    #[Test]
    public function create_rejects_invalid_config(array $parameters): void
    {
        $this->expectException(InvalidArgumentException::class);

        Config::create($parameters);
    }

    /**
     * @param array<non-empty-string, mixed> $parameters
     */
    #[DataProvider('configAssertions')]
    #[Test]
    public function create_requires_mandatory_config(array $parameters): void
    {
        $this->expectException(RuntimeException::class);

        Config::create($parameters);
    }
}
