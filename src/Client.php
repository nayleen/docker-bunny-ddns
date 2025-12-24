<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\HttpStatus;
use Amp\NullCancellation;
use OutOfBoundsException;
use RuntimeException;
use Safe;

/**
 * @psalm-type BunnyDnsZoneItem = array{
 *     Id: int,
 *     Type: int,
 *     Name: string,
 * }
 *
 * @psalm-type BunnyDnsZoneRecord = array{
 *     Id: int,
 *     Records: array<int, BunnyDnsZoneItem>,
 * }
 *
 * @psalm-type BunnyDnsZoneResponse = array{
 *     Items: array<int, BunnyDnsZoneRecord>,
 * }
 */
final readonly class Client
{
    private DelegateHttpClient $httpClient;

    private const string API_BASE_URI = 'https://api.bunny.net';

    private const int RECORD_TYPE_A = 0;

    private const string USER_AGENT = 'nayleen/bunny-ddns';

    public function __construct(
        private Config $config,
        ?DelegateHttpClient $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClientBuilder::buildDefault();
    }

    /**
     * @param 'GET'|'POST' $method
     * @param non-empty-string $uri
     */
    private function request(string $method, string $uri, string $body = ''): Request
    {
        $request = new Request(
            uri: sprintf('%s/%s', self::API_BASE_URI, ltrim($uri, '/')),
            method: $method,
            body: $body,
        );

        $request->setHeaders([
            'Accept' => 'application/json',
            'AccessKey' => $this->config->apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => self::USER_AGENT,
        ]);

        return $request;
    }

    /**
     * @param non-empty-string $name
     */
    public function resolveZone(string $name, Cancellation $cancellation = new NullCancellation()): Zone
    {
        assert($name !== '' && filter_var($name, FILTER_VALIDATE_DOMAIN) !== false);

        $response = $this->httpClient->request(
            request: $this->request('GET', '/dnszone?search=' . urlencode($name)),
            cancellation: $cancellation,
        );

        if ($response->getStatus() !== HttpStatus::OK) {
            throw new RuntimeException('Failed to resolve zone ID for zone ' . $name);
        }

        $body = $response->getBody()->buffer($cancellation);
        $data = Safe\json_decode($body, true);
        unset($body);

        /**
         * @phpstan-var BunnyDnsZoneResponse $data
         */
        if (!isset($data['Items']) || !is_array($data['Items']) || count($data['Items']) === 0) {
            throw new OutOfBoundsException('Zone not found: ' . $name);
        }

        $zoneId = (string) $data['Items'][0]['Id'];
        $records = $data['Items'][0]['Records'];

        foreach ($records as $record) {
            if (isset($record['Id'], $record['Type']) && $record['Type'] === self::RECORD_TYPE_A) {
                $recordId = $record['Id'];
                assert(is_int($recordId));

                $recordId = (string) $recordId;

                return new Zone($name, $zoneId, $recordId);
            }
        }

        throw new OutOfBoundsException('Zone not found: ' . $name);
    }

    /**
     * @param non-empty-string $ip
     */
    public function updateZoneRecord(Zone $zone, string $ip): void
    {
        assert($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false);

        $request = $this->request(
            method: 'POST',
            uri: sprintf('/dnszone/%s/records/%s', urlencode($zone->zoneId), urlencode($zone->recordId)),
            body: Safe\json_encode([
                'Id' => $zone->recordId,
                'Type' => self::RECORD_TYPE_A,
                'Value' => $ip,
            ]),
        );

        $response = $this->httpClient->request($request, new NullCancellation());

        if ($response->getStatus() !== HttpStatus::NO_CONTENT) {
            throw new RuntimeException('Failed to update DNS record for zone ' . $zone->name);
        }
    }
}
