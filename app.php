<?php

declare(strict_types = 1);

namespace BunnyDdns;

use function Amp\trapSignal;

require __DIR__ . '/vendor/autoload.php';

// bootstrap
$config = Config::create($_ENV, $_SERVER);
$logger = Logger::create($config->logLevel);

$updater = new Updater($config, $logger);
$updater->run();

// await shutdown via signals
trapSignal([SIGINT, SIGQUIT, SIGTERM]);
