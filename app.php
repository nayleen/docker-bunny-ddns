<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\Http\Client\HttpClientBuilder;
use Safe;

use function Amp\trapSignal;

require __DIR__ . '/vendor/autoload.php';

$config = Config::fromGlobals();
$logger = Logger::create($config->logLevel);

$command = $argv[1] ?? null;

if ($command !== null) {
    if ($command !== 'healthcheck') {
        $logger->error('Unknown command "{command}", expected "healthcheck"', [
            'command' => $command,
        ]);

        exit(2);
    }

    $document = Health::query() ?? ['status' => 'unknown'];

    echo Safe\json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

    exit($document['status'] === Health::STATUS_HEALTHY ? 0 : 1);
}

$health = new Health(Health::timeoutForInterval($config->updateInterval));
$health->update(Health::STATUS_STARTING);

$httpClient = HttpClientBuilder::buildDefault();
$ipResolver = new IpResolver($httpClient);

$updater = new Updater(
    $config,
    new Client($config, $httpClient),
    $ipResolver,
    $logger,
    $health,
);
$updater->run();

trapSignal([SIGINT, SIGQUIT, SIGTERM]);
