<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Cache;

/**
 * Cache interface for AWS AppConfig Provider
 */
interface CacheInterface
{
    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Set a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value, int $ttl): bool;

    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;

    /**
     * Clear all cached values
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool;
}
