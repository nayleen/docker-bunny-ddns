<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\HttpStatus;
use Amp\NullCancellation;
use RuntimeException;
use Safe;
use UnexpectedValueException;

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
     * @param 'GET'|'POST'|'PUT' $method
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
    public function createZone(string $name, Cancellation $cancellation = new NullCancellation()): Zone
    {
        assert($name !== '' && filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false);

        // create the zone
        $request = $this->request(
            method: 'POST',
            uri: '/dnszone',
            body: Safe\json_encode([
                'Domain' => $name,
            ]),
        );

        $response = $this->httpClient->request($request, $cancellation);
        unset($request);

        if ($response->getStatus() !== HttpStatus::CREATED) {
            throw new RuntimeException(sprintf(
                'Failed to create zone %s: expected HTTP %d, got %d',
                $name,
                HttpStatus::CREATED,
                $response->getStatus(),
            ));
        }

        $body = $response->getBody()->buffer($cancellation);
        $data = Safe\json_decode($body, true);
        unset($body);

        if (!is_array($data) || !isset($data['Id']) || !is_int($data['Id'])) {
            throw new UnexpectedValueException('Invalid response while creating zone ' . $name);
        }

        $zoneId = (string) $data['Id'];

        // create the A record
        $request = $this->request(
            method: 'PUT',
            uri: sprintf('/dnszone/%s/records', urlencode($zoneId)),
            body: Safe\json_encode([
                'Type' => self::RECORD_TYPE_A,
                'Value' => '127.0.0.1',
            ]),
        );

        $response = $this->httpClient->request($request, $cancellation);
        unset($request);

        if ($response->getStatus() !== HttpStatus::CREATED) {
            throw new RuntimeException(sprintf(
                'Failed to create A record for zone %s: expected HTTP %d, got %d',
                $name,
                HttpStatus::CREATED,
                $response->getStatus(),
            ));
        }

        $body = $response->getBody()->buffer($cancellation);
        $data = Safe\json_decode($body, true);
        unset($body);

        if (!is_array($data) || !isset($data['Id']) || !is_int($data['Id'])) {
            throw new UnexpectedValueException('Invalid response while creating A record for zone ' . $name);
        }

        $recordId = (string) $data['Id'];

        return new Zone($name, $zoneId, $recordId);
    }

    /**
     * @param non-empty-string $name
     */
    public function resolveZone(string $name, Cancellation $cancellation = new NullCancellation()): ?Zone
    {
        assert($name !== '' && filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false);

        $response = $this->httpClient->request(
            request: $this->request('GET', '/dnszone?search=' . urlencode($name)),
            cancellation: $cancellation,
        );

        if ($response->getStatus() !== HttpStatus::OK) {
            throw new RuntimeException(sprintf(
                'Failed to resolve zone %s: expected HTTP %d, got %d',
                $name,
                HttpStatus::OK,
                $response->getStatus(),
            ));
        }

        $body = $response->getBody()->buffer($cancellation);
        $data = Safe\json_decode($body, true);
        unset($body);

        if (!is_array($data) || !isset($data['Items']) || !is_array($data['Items'])) {
            throw new UnexpectedValueException('Invalid response while resolving zone ' . $name);
        }

        $zoneData = null;

        foreach ($data['Items'] as $item) {
            if (!is_array($item) || !isset($item['Domain']) || !is_string($item['Domain'])) {
                throw new UnexpectedValueException('Invalid response while resolving zone ' . $name);
            }

            if (strcasecmp($item['Domain'], $name) === 0) {
                $zoneData = $item;

                break;
            }
        }

        if ($zoneData === null) {
            return null;
        }

        if (!isset($zoneData['Id']) || !is_int($zoneData['Id'])) {
            throw new UnexpectedValueException('Invalid response while resolving zone ' . $name);
        }

        $zoneId = (string) $zoneData['Id'];
        $records = $zoneData['Records'] ?? [];

        if (!is_array($records)) {
            throw new UnexpectedValueException('Invalid response while resolving zone ' . $name);
        }

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new UnexpectedValueException('Invalid response while resolving zone ' . $name);
            }

            if (($record['Type'] ?? null) === self::RECORD_TYPE_A) {
                if (!isset($record['Id']) || !is_int($record['Id'])) {
                    throw new UnexpectedValueException('Invalid A record while resolving zone ' . $name);
                }

                return new Zone($name, $zoneId, (string) $record['Id']);
            }
        }

        throw new UnexpectedValueException('Zone has no A record: ' . $name);
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
            throw new RuntimeException(sprintf(
                'Failed to update A record for zone %s: expected HTTP %d, got %d',
                $zone->name,
                HttpStatus::NO_CONTENT,
                $response->getStatus(),
            ));
        }
    }
}
