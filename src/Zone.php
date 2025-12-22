<?php

declare(strict_types = 1);

namespace BunnyDdns;

/**
 * @internal
 */
final readonly class Zone
{
    /**
     * @param non-empty-string $name
     * @param numeric-string $zoneId
     * @param numeric-string $recordId
     */
    public function __construct(
        public string $name,
        public string $zoneId,
        public string $recordId,
    ) {
        assert($this->name !== '');
        assert($this->zoneId !== '' && is_numeric($this->zoneId));
        assert($this->recordId !== '' && is_numeric($this->recordId));
    }

    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'zone_id' => $this->zoneId,
            'record_id' => $this->recordId,
        ];
    }
}
