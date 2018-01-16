<?php

namespace PFinal\Cache;

use DateInterval;

/**
 * Redis缓存
 *
 * composer require predis/predis
 */
class RedisCache implements CacheInterface
{
    /*
     $server = array(
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
    )*/
    public $server;

    public $keyPrefix = 'pfinal:cache:';

    /** @var $redis \Predis\Client */
    protected $redis;

    public function __construct($config = array())
    {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }
    }

    protected function getRedis()
    {
        if (!$this->redis instanceof \Predis\Client) {
            if (empty($this->server)) {
                $params = array(
                    'scheme' => 'tcp',
                    'host' => '127.0.0.1',
                    'port' => 6379,
                );
            } else {
                $params = $this->server;
            }
            $this->redis = new \Predis\Client($params);
        }

        return $this->redis;
    }

    public function add($key, $value, $ttl = 0)
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
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

        $key = $this->generateUniqueKey($key);

        /** @var  $status \Predis\Response\Status */
        if ($ttl == 0) {
            $status = $this->getRedis()->set($key, $this->serialize($value));
        } else {
            $status = $this->getRedis()->setex($key, $ttl, $this->serialize($value));
        }
        return $status->getPayload() === 'OK';
    }

    public function increment($key, $value = 1)
    {
        return $this->getRedis()->incrby($this->generateUniqueKey($key), (int)$value);
    }

    public function get($key, $default = null)
    {
        $key = $this->generateUniqueKey($key);
        $value = $this->getRedis()->get($key);
        if ($value !== null) {
            return $this->unserialize($value);
        }
        return $default;
    }

    public function mget($keys)
    {
        $this->getMultiple($keys, false);
    }

    public function delete($key)
    {
        $key = $this->generateUniqueKey($key);
        return (bool)$this->getRedis()->del($key);
    }

    public function flush()
    {
        return $this->clear();
    }

    protected function generateUniqueKey($key)
    {
        return $this->keyPrefix . $key;
    }

    protected function serialize($value)
    {
        return is_numeric($value) ? $value : serialize($value);
    }

    protected function unserialize($value)
    {
        return is_numeric($value) ? $value : @unserialize($value);
    }

    public function clear()
    {
        if (empty($this->keyPrefix)) {
            return $this->getRedis()->flushdb();
        }

        $keys = $this->getRedis()->keys($this->keyPrefix . '*');
        if (count($keys) <= 0) {
            return true;
        }

        $res = $this->getRedis()->del($keys);

        return count($keys) == $res;
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
        return (bool)$this->getRedis()->exists($this->generateUniqueKey($key));
    }
}