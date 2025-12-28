<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\Http\Client\HttpClientBuilder;

use function Amp\trapSignal;

require __DIR__ . '/vendor/autoload.php';

// parse config
$parameters = [];

foreach ($_ENV as $key => $value) {
    if (is_string($key) && $key !== '') {
        $parameters[$key] = $value;
    }
}
var_dump($parameters);

$config = Config::create($parameters);

// bootstrap
$httpClient = HttpClientBuilder::buildDefault();
$ipResolver = new IpResolver($httpClient);
$logger = Logger::create($config->logLevel);

// wire and run the updater
$updater = new Updater(
    $config,
    new Client($config, $httpClient),
    $ipResolver,
    $logger,
);
$updater->run();

// await shutdown via signals
trapSignal([SIGINT, SIGQUIT, SIGTERM]);
