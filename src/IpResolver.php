<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\HttpStatus;
use Amp\NullCancellation;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

use function Safe\parse_url;

final class IpResolver
{
    private DelegateHttpClient $httpClient;

    /**
     * @var array<int, non-empty-string>
     */
    private const array IP_LOOKUP_SERVICES = [
        'https://cloudflare.com/cdn-cgi/trace',
        'https://icanhazip.com/',
        'https://api.ipify.org/',
        'https://ifconfig.io/ip',
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

        if (!is_string($hostname) || $hostname === '') {
            throw new UnexpectedValueException('Invalid IP lookup service URL: ' . $serviceUrl);
        }

        if ($hostname === 'cloudflare.com') {
            $lines = explode("\n", $response);

            foreach ($lines as $line) {
                if (str_starts_with($line, 'ip=')) {
                    $ip = trim(substr($line, 3));

                    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }

                    break;
                }
            }
        } else {
            $ip = trim(explode("\n", $response)[0]);

            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        throw new UnexpectedValueException('Invalid response from IP lookup service ' . $serviceUrl);
    }

    public function run(Cancellation $cancellation = new NullCancellation()): string
    {
        $index = 0;
        $maxIndex = count(self::IP_LOOKUP_SERVICES);
        $lastError = null;

        while ($index < $maxIndex) {
            $serviceUrl = self::IP_LOOKUP_SERVICES[$index++];

            try {
                $response = $this->httpClient->request(new Request($serviceUrl), $cancellation);

                if ($response->getStatus() !== HttpStatus::OK) {
                    throw new RuntimeException(sprintf(
                        'Expected HTTP %d, got %d',
                        HttpStatus::OK,
                        $response->getStatus(),
                    ));
                }

                $body = $response->getBody()->buffer($cancellation);

                if ($body === '') {
                    throw new UnexpectedValueException('Empty response');
                }

                $ip = $this->extractIp($body, $serviceUrl);

                if (str_ends_with($ip, '.0')) {
                    $lastError = new UnexpectedValueException('Rejected network address from ' . $serviceUrl);

                    continue;
                }

                return $ip;
            } catch (CancelledException $error) {
                throw $error;
            } catch (Throwable $error) {
                $lastError = new RuntimeException(
                    sprintf('IP lookup via %s failed: %s', $serviceUrl, $error->getMessage()),
                    previous: $error,
                );
            }
        }

        throw new RuntimeException('Failed to determine current IP address', previous: $lastError);
    }
}
