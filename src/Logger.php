<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\ByteStream;
use Amp\ByteStream\WritableStream;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\Processor\PsrLogMessageProcessor;
use Revolt\EventLoop;
use Throwable;

/**
 * @api
 */
final readonly class Logger
{
    private function __construct() {}

    public static function create(
        Level $logLevel,
        ?WritableStream $sink = null,
    ): Monolog {
        $dateFormat = 'Y-m-d H:i:s';
        $logFormat = "[%datetime%] %level_name%: %message% %context% %extra%\n";

        $logHandler = new StreamHandler(sink: $sink ?? ByteStream\getStderr(), level: $logLevel);
        $logHandler->pushProcessor(new PsrLogMessageProcessor(dateFormat: $dateFormat, removeUsedContextFields: true));
        $logHandler->setFormatter(new ConsoleFormatter(format: $logFormat, dateFormat: $dateFormat, ignoreEmptyContextAndExtra: true));

        $logger = new Monolog('bunny-ddns');
        $logger->pushHandler($logHandler);

        EventLoop::setErrorHandler(function (Throwable $throwable) use ($logger): void {
            $logger->error($throwable->getMessage(), ['exception' => $throwable]);
        });

        return $logger;
    }
}
