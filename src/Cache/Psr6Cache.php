<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * PSR-6 cache adapter for AWS AppConfig Provider
 */
class Psr6Cache implements CacheInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cachePool
    ) {
    }

    public function get(string $key): mixed
    {
        try {
            $item = $this->cachePool->getItem($key);
            return $item->isHit() ? $item->get() : null;
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        try {
            $item = $this->cachePool->getItem($key);
            $item->set($value);
            $item->expiresAfter($ttl);
            return $this->cachePool->save($item);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return $this->cachePool->deleteItem($key);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    public function clear(): bool
    {
        return $this->cachePool->clear();
    }
}
