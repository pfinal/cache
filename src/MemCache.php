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

    /**
     * 增加一个条目到缓存服务器
     * 仅键名不存在的情况下，往缓存中存储值
     * @param string $key 要设置值的key
     * @param mixed $value 要存储的值，字符串和数值直接存储，其他类型序列化后存储
     * @param int $expire 当前写入缓存的数据的失效时间。如果此值设置为0表明此数据永不过期。以秒为单位的整数（从当前算起的时间差）来说明此数据的过期时间。
     * @return boolean 成功时返回 true， 或者在失败时返回 false. 如果这个key已经存在返回false
     */
    public function add($key, $value, $expire = 0)
    {
        $key = $this->generateUniqueKey($key);
        return $this->_cache->add($key, $value, MEMCACHE_COMPRESSED, $expire);
    }

    /**
     * 存放数据到缓存中
     * @param string $key 要设置值的key
     * @param mixed $value 要存储的值，字符串和数值直接存储，其他类型序列化后存储
     * @param int $expire 当前写入缓存的数据的失效时间。如果此值设置为0表明此数据永不过期。以秒为单位的整数（从当前算起的时间差）来说明此数据的过期时间
     * @return boolean 成功时返回 true， 或者在失败时返回 false.
     */
    public function set($key, $value, $expire = 0)
    {
        $expire = $expire > 0 ? $expire + time() : 0;

        $key = $this->generateUniqueKey($key);
        return $this->_cache->set($key, $value, MEMCACHE_COMPRESSED, $expire);
    }

    public function increment($key, $value = 1)
    {
        $key = $this->generateUniqueKey($key);

        return $this->_cache->increment($key, $value);
    }

    /**
     * 从服务端检回一个元素
     * @param $key string | array 要获取值的key或key数组
     * @return mixed 返回key对应的存储元素的字符串值或者在失败或key未找到的时候返回false
     */
    public function get($key, $default = null)
    {
        $key = $this->generateUniqueKey($key);
        $val = $this->_cache->get($key);

        if ($val === false) {
            return $default;
        }

        return $val;
    }

    /**
     * 从服务端检回多个匹配的元素
     * @param $keys array 要获取值的key或key数组
     * @return mixed 返回key对应的存储元素的字符串值或者在失败或key未找到的时候返回false
     */
    public function mget($keys)
    {
        return $this->getMultiple($keys, false);
    }

    /**
     * 从服务端删除一个元素
     * @param string $key 要删除的元素的key
     * @return mixed 成功时返回 true， 或者在失败时返回 false.
     */
    public function delete($key)
    {
        return $this->_cache->delete($key);
    }

    /**
     * 清洗（删除）已经存储的所有的元素
     * 立即使所有已经存在的元素失效。并不会真正的释放任何资源，而是仅仅标记所有元素都失效了，因此已经被使用的内存会被新的元素复写
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

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        return $this->_cache->flush();
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
        return false !== $this->get($key, false);
    }
}