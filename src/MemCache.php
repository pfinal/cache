<?php

namespace PFinal\Cache;

use DateInterval;

/**
 * Memcache缓存
 */
class MemCache implements CacheInterface
{
    private $_cache;
    public $servers = array();
    public $keyPrefix = 'pfinal:cache:';

    public function __construct($config = array())
    {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }

        $this->_cache = new \Memcache();
        if (count($this->servers) > 0) {
            foreach ($this->servers as $server) {
                $this->_cache->addServer($server['host'], $server['port'], false, $server['weight']);
            }
        } else {
            $this->_cache->addServer('127.0.0.1', 11211);
        }
    }

    public function add($key, $value, $expire = 0)
    {
        $key = $this->generateUniqueKey($key);
        return $this->_cache->add($key, $this->serialize($value), MEMCACHE_COMPRESSED, $expire);
    }

    public function set($key, $value, $ttl = 0)
    {
        if ($ttl instanceof \DateInterval) {
            $ttl = $ttl->y * 365 * 24 * 60 * 60
                + $ttl->m * 30 * 24 * 60 * 60
                + $ttl->d * 24 * 60 * 60
                + $ttl->h * 60 * 60
                + $ttl->i * 60
                + $ttl->s;
        } else {
            $ttl = (int)$ttl;
        }

        $ttl = $ttl > 0 ? $ttl + time() : 0;

        $key = $this->generateUniqueKey($key);
        return $this->_cache->set($key, $this->serialize($value), MEMCACHE_COMPRESSED, $ttl);
    }

    public function increment($key, $value = 1)
    {
        $key = $this->generateUniqueKey($key);

        return $this->_cache->increment($key, $value);
    }

    public function get($key, $default = null)
    {
        $key = $this->generateUniqueKey($key);
        $val = $this->_cache->get($key);

        if ($val !== false) {
            return $this->unserialize($val);
        }

        return $default;
    }

    public function mget($keys)
    {
        return $this->getMultiple($keys, false);
    }

    public function delete($key)
    {
        $key = $this->generateUniqueKey($key);
        return $this->_cache->delete($key);
    }

    public function flush()
    {
        return $this->clear();
    }

    protected function generateUniqueKey($key)
    {
        return $this->keyPrefix . $key;
    }

    public function clear()
    {
        return $this->_cache->flush();
    }

    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys)) {
            if (!$keys instanceof \Traversable) {
                throw new InvalidArgumentException('$keys is neither an array nor Traversable');
            }
            $keys = iterator_to_array($keys, false);
        }

        $values = array();
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    public function setMultiple($values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has($key)
    {
        $key = $this->generateUniqueKey($key);
        $val = $this->_cache->get($key);

        return $val !== false;
    }

    protected function serialize($value)
    {
        return is_numeric($value) ? $value : serialize($value);
    }

    protected function unserialize($value)
    {
        return is_numeric($value) ? $value : @unserialize($value);
    }

}