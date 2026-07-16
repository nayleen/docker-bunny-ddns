<?php

declare(strict_types = 1);

namespace BunnyDdns\Tests\Unit;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use BunnyDdns\Client;
use BunnyDdns\Config;
use BunnyDdns\IpResolver;
use BunnyDdns\Updater;
use Closure;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use UnexpectedValueException;

/**
 * @internal
 */
final class ErrorHandlingTest extends TestCase
{
    private const string ZONE_RESPONSE = '{"Items":[{"Domain":"example.com","Id":1,"Records":[{"Id":2,"Type":0}]}]}';

    private static function config(bool $updateOnStart = false): Config
    {
        return Config::create([
            'API_KEY' => '00000000-0000-0000-0000-00000000000000000000-0000-0000-0000-000000000000',
            'UPDATE_INTERVAL' => 3600,
            'UPDATE_ON_START' => $updateOnStart,
            'ZONES' => 'example.com',
        ]);
    }

    private static function response(Request $request, int $status, string $body = ''): Response
    {
        return new Response('1.1', $status, null, [], $body, $request);
    }

    private static function updater(
        DelegateHttpClient $httpClient,
        bool $updateOnStart = false,
        LoggerInterface $logger = new NullLogger(),
    ): Updater {
        $config = self::config($updateOnStart);

        return new Updater(
            $config,
            new Client($config, $httpClient),
            new IpResolver($httpClient),
            $logger,
        );
    }

    #[Test]
    public function absent_zone_is_created(): void
    {
        $methods = [];
        $httpClient = new CallbackHttpClient(
            static function (Request $request) use (&$methods): Response {
                $method = $request->getMethod();
                $methods[] = $method;

                return match ($method) {
                    'GET' => self::response($request, 200, '{"Items":[]}'),
                    'POST' => self::response($request, 201, '{"Id":1}'),
                    'PUT' => self::response($request, 201, '{"Id":2}'),
                    default => throw new RuntimeException('Unexpected HTTP method ' . $method),
                };
            },
        );
        self::updater($httpClient)->run();

        self::assertSame(['GET', 'POST', 'PUT'], $methods);
    }

    #[Test]
    public function authentication_failure_does_not_create_a_zone(): void
    {
        $methods = [];
        $httpClient = new CallbackHttpClient(
            static function (Request $request) use (&$methods): Response {
                $methods[] = $request->getMethod();

                return self::response($request, 401);
            },
        );

        try {
            self::updater($httpClient)->run();
            self::fail('Expected authentication failure');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('got 401', $error->getMessage());
        }

        self::assertSame(['GET'], $methods);
    }

    #[Test]
    public function existing_zone_without_an_a_record_fails_clearly(): void
    {
        $httpClient = new CallbackHttpClient(
            static fn (Request $request): Response => self::response(
                $request,
                200,
                '{"Items":[{"Domain":"example.com","Id":1,"Records":[]}]}',
            ),
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('Zone has no A record: example.com');

        new Client(self::config(), $httpClient)->resolveZone('example.com');
    }

    #[Test]
    public function ip_resolution_falls_back_after_an_http_error(): void
    {
        $requests = 0;
        $httpClient = new CallbackHttpClient(
            static function (Request $request) use (&$requests): Response {
                ++$requests;

                return $requests === 1
                    ? self::response($request, 500)
                    : self::response($request, 200, "203.0.113.1\n");
            },
        );

        self::assertSame('203.0.113.1', new IpResolver($httpClient)->run());
        self::assertSame(2, $requests);
    }

    #[Test]
    public function ip_resolution_preserves_the_last_failure(): void
    {
        $requests = 0;
        $httpClient = new CallbackHttpClient(
            static function (Request $request) use (&$requests): Response {
                ++$requests;

                return self::response($request, 500);
            },
        );

        try {
            new IpResolver($httpClient)->run();
            self::fail('Expected IP resolution failure');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('ifconfig.io', $error->getPrevious()?->getMessage() ?? '');
            self::assertStringContainsString('got 500', $error->getPrevious()?->getMessage() ?? '');
        }

        self::assertSame(4, $requests);
    }

    #[Test]
    public function ip_resolution_propagates_cancellation(): void
    {
        $httpClient = new CallbackHttpClient(
            static function (): never {
                throw new CancelledException();
            },
        );

        $this->expectException(CancelledException::class);

        new IpResolver($httpClient)->run();
    }

    #[Test]
    public function ip_resolution_rejects_network_addresses(): void
    {
        $requests = 0;
        $httpClient = new CallbackHttpClient(
            static function (Request $request) use (&$requests): Response {
                ++$requests;

                return $requests === 1
                    ? self::response($request, 200, "ip=203.0.113.0\n")
                    : self::response($request, 200, "203.0.113.1\n");
            },
        );

        self::assertSame('203.0.113.1', new IpResolver($httpClient)->run());
        self::assertSame(2, $requests);
    }

    #[Test]
    public function updater_logs_failed_updates_with_context(): void
    {
        $responses = [
            [200, self::ZONE_RESPONSE],
            [200, "fl=123\nip=203.0.113.1\n"],
            [500, ''],
        ];
        $httpClient = new CallbackHttpClient(
            static function (Request $request) use (&$responses): Response {
                [$status, $body] = array_shift($responses)
                    ?? throw new RuntimeException('Unexpected HTTP request');

                return self::response($request, $status, $body);
            },
        );
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);

        self::updater($httpClient, updateOnStart: true, logger: $logger)->run();

        self::assertTrue($handler->hasRecordThatPasses(
            static fn (LogRecord $record): bool => $record->context['ip'] === '203.0.113.1'
                && $record->context['zones'] === ['example.com']
                && $record->context['exception'] instanceof RuntimeException
                && str_contains($record->context['exception']->getMessage(), 'got 500'),
            Level::Error,
        ));
    }

    #[Test]
    public function updater_updates_once_when_the_ip_is_unchanged(): void
    {
        $responses = [
            [200, self::ZONE_RESPONSE],
            [200, "fl=123\nip=203.0.113.1\n"],
            [204, ''],
            [200, self::ZONE_RESPONSE],
            [200, "fl=123\nip=203.0.113.1\n"],
        ];
        $httpClient = new CallbackHttpClient(
            static function (Request $request) use (&$responses): Response {
                [$status, $body] = array_shift($responses)
                    ?? throw new RuntimeException('Unexpected HTTP request');

                return self::response($request, $status, $body);
            },
        );
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $updater = self::updater($httpClient, updateOnStart: true, logger: $logger);

        $updater->run();
        $updater->run();

        self::assertTrue($handler->hasDebugThatContains('Updated zone record'));
        self::assertTrue($handler->hasInfoThatContains('IP address unchanged'));
    }

    #[Test]
    public function zone_search_does_not_use_a_partial_match(): void
    {
        $httpClient = new CallbackHttpClient(
            static fn (Request $request): Response => self::response(
                $request,
                200,
                '{"Items":[{"Domain":"other-example.com","Id":1,"Records":[{"Id":2,"Type":0}]}]}',
            ),
        );

        self::assertNull(new Client(self::config(), $httpClient)->resolveZone('example.com'));
    }
}

/**
 * @internal
 */
final readonly class CallbackHttpClient implements DelegateHttpClient
{
    /**
     * @param Closure(Request, Cancellation): Response $callback
     */
    public function __construct(private Closure $callback) {}

    public function request(Request $request, Cancellation $cancellation): Response
    {
        return ($this->callback)($request, $cancellation);
    }
}
