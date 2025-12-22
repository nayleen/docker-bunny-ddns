<?php

declare(strict_types = 1);

namespace BunnyDdns\Task;

use Amp\Cancellation;
use Amp\Http\Client\Request;
use Amp\Http\HttpStatus;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use BunnyDdns\ClientTrait;
use BunnyDdns\Zone;
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
final class ResolveZoneIds implements Task
{
    use ClientTrait;

    public function run(Channel $channel, Cancellation $cancellation): Zone
    {
        $zone = $channel->receive();
        assert(is_string($zone) && $zone !== '' && filter_var($zone, FILTER_VALIDATE_DOMAIN) !== false);

        $request = new Request('/dnszone?search=' . urlencode($zone));
        $response = $this->httpClient()->request($request, $cancellation);

        if ($response->getStatus() !== HttpStatus::OK) {
            throw new RuntimeException('Failed to resolve zone ID for zone ' . $zone);
        }

        $body = $response->getBody()->buffer($cancellation);
        $data = Safe\json_decode($body, true);
        unset($body);

        /**
         * @phpstan-var BunnyDnsZoneResponse $data
         */
        if (!isset($data['Items']) || !is_array($data['Items']) || count($data['Items']) === 0) {
            throw new OutOfBoundsException('Zone not found: ' . $zone);
        }

        $zoneId = (string) $data['Items'][0]['Id'];
        $records = $data['Items'][0]['Records'];

        foreach ($records as $record) {
            if (isset($record['Id'], $record['Type']) && $record['Type'] === self::RECORD_TYPE_A) {
                $recordId = $record['Id'];
                assert(is_int($recordId));

                $recordId = (string) $recordId;

                return new Zone($zone, $zoneId, $recordId);
            }
        }

        throw new OutOfBoundsException('Zone not found: ' . $zone);
    }
}
