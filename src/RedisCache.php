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

    /**
     * 增加一个条目到缓存服务器
     * 仅键名不存在的情况下，往缓存中存储值
     * @param string $key 要设置值的key
     * @param mixed $value 要存储的值
     * @param int $expire 当前写入缓存的数据的失效时间。如果此值设置为0表明此数据永不过期。以秒为单位的整数（从当前算起的时间差）来说明此数据的过期时间
     * @return boolean 成功时返回 true， 或者在失败时返回 false. 如果这个key已经存在返回false
     */
    public function add($key, $value, $ttl = 0)
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    /**
     * 存放数据到缓存中
     * @param string $key 要设置值的key
     * @param mixed $value 要存储的值，字符串和数值直接存储，其他类型序列化后存储
     * @param int $ttl 当前写入缓存的数据的失效时间。如果此值设置为0表明此数据永不过期。以秒为单位的整数（从当前算起的时间差）来说明此数据的过期时间
     * @return boolean 成功时返回 true， 或者在失败时返回 false
     */
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

    /**
     * 指定key的值，自增
     * @param $key
     * @param int $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        return $this->getRedis()->incrby($this->generateUniqueKey($key), (int)$value);
    }

    /**
     * 从服务端检回一个元素
     * @param $key string 要获取值的key
     * @return mixed 返回key对应的存储元素的字符串值或者在失败或key未找到的时候返回false
     */
    public function get($key, $defult = null)
    {
        $key = $this->generateUniqueKey($key);
        $value = $this->getRedis()->get($key);
        if ($value !== false) {
            return $this->unserialize($value);
        }
        return $defult;
    }

    /**
     * 从服务端检回多个匹配的元素
     * @param $keys string|array 要获取值的key或key数组
     * @return array
     */
    public function mget($keys)
    {
        $this->getMultiple($keys, false);
    }

    /**
     * 删除一个元素
     * @param string $key 要删除的元素的key
     * @return bool 成功时返回 true， 或者在失败时返回 false.
     */
    public function delete($key)
    {
        $key = $this->generateUniqueKey($key);
        return (bool)$this->getRedis()->del($key);
    }

    /**
     * 删除所有的元素
     * @return mixed 成功时返回 true， 或者在失败时返回 false
     */
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

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
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

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
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

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        return (bool)$this->getRedis()->exists($this->generateUniqueKey($key));
    }
}