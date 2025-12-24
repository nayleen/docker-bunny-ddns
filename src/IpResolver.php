<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\NullCancellation;
use RuntimeException;
use Throwable;

final class IpResolver
{
    private DelegateHttpClient $httpClient;

    /**
     * @var array<int, non-empty-string>
     */
    private const array IP_LOOKUP_SERVICES = [
        'https://1.1.1.1/cdn-cgi/trace',
        'https://cloudflare.com/cdn-cgi/trace',
        'https://icanhazip.com/',
        'https://api.ipify.org',
        'https://api.my-ip.io/v2/ip.txt',
    ];

    public function __construct(?DelegateHttpClient $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClientBuilder::buildDefault();
    }

    /**
     * @param non-empty-string $response
     * @param non-empty-string $serviceUrl
     * @return non-empty-string
     */
    private function extractIp(string $response, string $serviceUrl): string
    {
        $hostname = parse_url($serviceUrl, PHP_URL_HOST);
        assert(is_string($hostname) && $hostname !== '');

        if (in_array($hostname, ['1.1.1.1', 'cloudflare.com'], true)) {
            $lines = explode("\n", $response);

            foreach ($lines as $line) {
                if (str_starts_with($line, 'ip=')) {
                    $ip = trim(substr($line, 3));
                    assert($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false);

                    return $ip;
                }
            }
        }

        $ip = trim(explode("\n", $response)[0]);
        assert($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false);

        return $ip;
    }

    public function run(Cancellation $cancellation = new NullCancellation()): string
    {
        $index = 0;
        $maxIndex = count(self::IP_LOOKUP_SERVICES);

        while ($index < $maxIndex) {
            $serviceUrl = self::IP_LOOKUP_SERVICES[$index++];

            try {
                $response = $this->httpClient->request(new Request($serviceUrl), $cancellation);
                $body = $response->getBody()->buffer($cancellation);
                assert($body !== '');

                $ip = $this->extractIp($body, $serviceUrl);
                assert(filter_var($ip, FILTER_VALIDATE_IP));

                return $ip;
            } catch (Throwable) {
            }
        }

        throw new RuntimeException('Failed to determine current IP address');
    }
}
