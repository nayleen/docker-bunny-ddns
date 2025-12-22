<?php

declare(strict_types = 1);

namespace BunnyDdns;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @internal
 * @implements IteratorAggregate<non-empty-string, Zone>
 */
final class Zones implements Countable, IteratorAggregate
{
    /**
     * @var array<non-empty-string, Zone>
     */
    private array $zones;

    public function __construct()
    {
        $this->zones = [];
    }

    public function count(): int
    {
        return count($this->zones);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->zones as $name => $zone) {
            yield $name => $zone;
        }
    }

    /**
     * @return non-empty-string[]
     */
    public function names(): array
    {
        $names = array_keys($this->zones);
        sort($names, SORT_NATURAL);

        return $names;
    }

    public function set(Zone $zone): void
    {
        $this->zones[$zone->name] = $zone;
    }

    public function __debugInfo(): array
    {
        return array_map(fn (Zone $zone) => $zone->__debugInfo(), $this->zones);
    }
}
