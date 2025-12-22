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
use RuntimeException;
use Safe;

final class UpdateZoneRecord implements Task
{
    use ClientTrait;

    public function run(Channel $channel, Cancellation $cancellation): null
    {
        $payload = $channel->receive();
        assert(is_array($payload));

        [$zone, $ip] = $payload;

        assert($zone instanceof Zone);
        assert(is_string($ip) && $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP));

        $request = new Request(
            uri: sprintf('/dnszone/%s/records/%s', urlencode($zone->zoneId), urlencode($zone->recordId)),
            method: 'POST',
            body: Safe\json_encode([
                'Id' => $zone->recordId,
                'Type' => self::RECORD_TYPE_A,
                'Value' => $ip,
            ]),
        );

        $response = $this->httpClient()->request($request, $cancellation);

        if ($response->getStatus() !== HttpStatus::NO_CONTENT) {
            throw new RuntimeException('Failed to update DNS record for zone ' . $zone->name);
        }

        return null;
    }
}
