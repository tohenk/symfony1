<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Predis\Client;

/**
 * Cache class that stores cached content in Redis.
 *
 * @author     Toha <tohenk@yahoo.com>
 */
class sfRedisCache extends sfCache
{
    public const OK = 'OK';
    public const EXPIRED_AT = 'EXAT';

    /** @var Client */
    protected $redis;

    /**
     * Initializes this sfCache instance.
     *
     * Available options:
     *
     * * redis: A redis object (optional)
     *
     * * host:       The default host (default to localhost)
     * * port:       The port for the default server (default to 6379)
     * * database:   The default database
     *
     * * servers:    An array of additional servers (keys: host, port, database, alias)
     *
     * * see sfCache for options available for all drivers
     *
     * @see sfCache
     */
    public function initialize($options = [])
    {
        parent::initialize($options);

        if ($this->getOption('redis')) {
            $this->redis = $this->getOption('redis');
        } else {
            $servers = [];
            if ($this->getOption('servers')) {
                foreach ($this->getOption('servers') as $svr => $server) {
                    if (!isset($server['host'])) {
                        throw new sfInitializationException(sprintf('Redis server host is not defined for %s.', $svr));
                    }
                    if (!isset($server['port'])) {
                        $server['port'] = 6379;
                    }
                    $servers[] = $server;
                }
            } else {
                $servers[] = [
                    'host' => $this->getOption('host', 'localhost'),
                    'port' => $this->getOption('port', 6379),
                    'database' => $this->getOption('database', 15),
                ];
            }
            if (1 === count($servers)) {
                $servers = $servers[0];
            }
            $this->redis = new Client($servers, ['prefix' => $this->getOption('prefix')]);
        }
    }

    /**
     * @see sfCache
     *
     * @return Client
     */
    public function getBackend()
    {
        return $this->redis;
    }

    /**
     * Check if response is success?
     *
     * @param string $response
     *
     * @return bool
     */
    protected function isSuccess($response)
    {
        return static::OK === (string) $response ? true : false;
    }

    /**
     * @see sfCache
     *
     * @param string|null $default
     */
    public function get($key, $default = null)
    {
        return null !== ($value = $this->redis->get($key)) ? $value : $default;
    }

    /**
     * @see sfCache
     */
    public function has($key)
    {
        return $this->redis->exists($key);
    }

    /**
     * @see sfCache
     *
     * @param mixed|null $lifetime
     */
    public function set($key, $data, $lifetime = null)
    {
        $lifetime = null === $lifetime ? $this->getOption('lifetime') : $lifetime;
        $timeout = time() + $lifetime;

        if ($this->isSuccess($this->redis->set($key, $data, static::EXPIRED_AT, $timeout))) {
            $this->setMetadata($key, $timeout);

            return true;
        }

        return false;
    }

    /**
     * @see sfCache
     */
    public function remove($key)
    {
        $this->redis->del($this->getMetadataKey($key));
        $count = (int) $this->redis->del($key);

        return $count > 0 ? true : false;
    }

    /**
     * @see sfCache
     */
    public function clean($mode = sfCache::ALL)
    {
        if (sfCache::ALL === $mode) {
            return $this->isSuccess($this->redis->flushall());
        }

        return true;
    }

    /**
     * @see sfCache
     */
    public function getLastModified($key)
    {
        if (null === ($retval = $this->getMetadata($key))) {
            return 0;
        }

        return $retval['lastModified'];
    }

    /**
     * @see sfCache
     */
    public function getTimeout($key)
    {
        if (null === ($retval = $this->getMetadata($key))) {
            return 0;
        }

        return $retval['timeout'];
    }

    /**
     * @see sfCache
     *
     * @throws sfCacheException
     */
    public function removePattern($pattern)
    {
        $regexp = self::patternToRegexp($this->getOption('prefix').$pattern);
        foreach ($this->redis->keys('*') as $key) {
            if (preg_match($regexp, $key)) {
                $this->remove(substr($key, strlen($this->getOption('prefix'))));
            }
        }

        return true;
    }

    /**
     * @see sfCache
     */
    public function getMany($keys)
    {
        $values = $this->redis->mget($keys);

        return array_combine($keys, $values);
    }

    /**
     * Get metadata key.
     *
     * @param string $key The cache key
     *
     * @return string
     */
    protected function getMetadataKey($key)
    {
        return implode(static::SEPARATOR, ['_metadata', $key]);
    }

    /**
     * Gets metadata about a key in the cache.
     *
     * @param string $key A cache key
     *
     * @return array An array of metadata information
     */
    protected function getMetadata($key)
    {
        if (null !== ($value = $this->redis->get($this->getMetadataKey($key)))) {
            $value = unserialize($value);
        }

        return $value;
    }

    /**
     * Stores metadata about a key in the cache.
     *
     * @param string $key     A cache key
     * @param int    $timeout The timeout
     */
    protected function setMetadata($key, $timeout)
    {
        $this->redis->set($this->getMetadataKey($key), serialize(['lastModified' => time(), 'timeout' => $timeout]), static::EXPIRED_AT, $timeout);
    }

    /**
     * Gets cache information.
     *
     * @return array
     */
    public function getCacheInfo()
    {
        $result = [];
        foreach ($this->redis->keys('*') as $key) {
            $normalizedKey = substr($key, strlen($this->getOption('prefix')));
            if ('_metadata:' !== substr($normalizedKey, 0, 10)) {
                $result[] = $normalizedKey;
            }
        }

        return $result;
    }
}
