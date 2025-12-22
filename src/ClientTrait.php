<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\ModifyRequest;
use Amp\Http\Client\Interceptor\SetRequestHeader;
use Amp\Http\Client\Request;
use Amp\Parallel\Worker\Task;
use League\Uri\Http;
use SensitiveParameter;

/**
 * @internal
 * @mixin Task
 */
trait ClientTrait
{
    private DelegateHttpClient $httpClient;

    private const int RECORD_TYPE_A = 0;

    final public function __construct(
        #[SensitiveParameter] private readonly string $apiKey,
        ?DelegateHttpClient $httpClient = null,
    ) {
        assert($this->apiKey !== '');

        if (isset($httpClient)) {
            $this->httpClient = $httpClient;
        }
    }

    private function httpClient(): DelegateHttpClient
    {
        if (isset($this->httpClient)) {
            return $this->httpClient;
        }

        return $this->httpClient = (new HttpClientBuilder())
            ->intercept(new class('https://api.bunny.net/') extends ModifyRequest {
                public function __construct(string $baseUri)
                {
                    parent::__construct(function (Request $request) use ($baseUri): Request {
                        $request->setUri(
                            (string) Http::parse($request->getUri(), $baseUri),
                        );

                        return $request;
                    });
                }
            })
            ->intercept(new SetRequestHeader('Accept', 'application/json'))
            ->intercept(new SetRequestHeader('AccessKey', $this->apiKey))
            ->intercept(new SetRequestHeader('Content-Type', 'application/json'))
            ->intercept(new SetRequestHeader('User-Agent', 'nayleen/bunny-ddns'))
            ->build();
    }

    final public function __debugInfo(): null
    {
        return null;
    }
}
