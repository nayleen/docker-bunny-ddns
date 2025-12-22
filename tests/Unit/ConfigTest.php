<?php

declare(strict_types = 1);

namespace BunnyDdns\Tests\Unit;

use BunnyDdns\Config;
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
    private const array VALID_ENV = [
        'API_KEY' => '00000000-0000-0000-0000-00000000000000000000-0000-0000-0000-000000000000',
        'ZONES' => 'example.com',
    ];

    /**
     * @return iterable<string, array<string, array<non-empty-string, mixed>>>
     */
    public static function configAssertions(): iterable
    {
        yield 'missing API key' => [
            'env' => array_intersect_assoc(self::VALID_ENV, array_flip(['ZONES'])),
        ];

        yield 'missing zones' => [
            'env' => array_intersect_assoc(self::VALID_ENV, array_flip(['API_KEY'])),
        ];
    }

    /**
     * @param array<non-empty-string, mixed> $env
     * @return void
     */
    #[Test]
    #[DataProvider('configAssertions')]
    public function create_asserts_mandatory_config(array $env): void
    {
        self::expectException(RuntimeException::class);

        Config::create($env, []);
    }

    #[Test]
    public function create_loads_api_key_from_secret_file(): void
    {
        $apiKeyFile = dirname(__DIR__) . '/Fixtures/api_key_file.txt';

        $env = [
            'API_KEY_FILE' => $apiKeyFile,
            'ZONES' => 'example.com',
        ];

        $config = Config::create($env, []);

        self::assertSame($config->apiKey, trim(Safe\file_get_contents($apiKeyFile)));
    }

    #[Test]
    public function create_reads_log_level(): void
    {
        $env = self::VALID_ENV;
        $env['LOG_LEVEL'] = 'ALERT';

        $config = Config::create($env, []);

        self::assertSame(Level::Alert, $config->logLevel);
    }

    #[Test]
    public function create_reads_update_interval(): void
    {
        $env = self::VALID_ENV;
        $env['UPDATE_INTERVAL'] = '3600';

        $config = Config::create($env, []);

        self::assertSame(3600, $config->updateInterval);
    }

    #[Test]
    public function create_reads_update_on_start(): void
    {
        $env = self::VALID_ENV;
        $env['UPDATE_ON_START'] = '0';

        $config = Config::create($env, []);

        self::assertFalse($config->updateOnStart);
    }
}
