<?php

namespace PFinal\Cache;

/**
 * Array缓存
 * 将数据存在一个数组中，仅在当次请求中有效
 */
class ArrayCache implements CacheInterface
{
    private $_cache;

    public function __construct($config = array())
    {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }
    }

    public function get($id, $default = null)
    {
        $key = $this->generateUniqueKey($id);
        if (array_key_exists($key, $this->_cache) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            return $this->_cache[$key][0];
        } else {
            return $default;
        }
    }

    public function set($id, $value, $ttl = 0)
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

        $key = $this->generateUniqueKey($id);
        $this->_cache[$key] = array($value, $ttl === 0 ? 0 : microtime(true) + $ttl);
        return true;
    }

    public function add($key, $value, $ttl = 0)
    {
        if ($this->has($key)) {
            return false;
        } else {
            return $this->set($key, $value, $ttl);
        }
    }

    public function delete($id)
    {
        $key = $this->generateUniqueKey($id);
        unset($this->_cache[$key]);
        return true;
    }

    public function increment($key, $value = 1)
    {
        if (!$this->has($key)) {
            $this->set($key, $value);
            return $value;
        }

        $this->_cache[$key][0] += (int)$value;

        return $this->_cache[$key][0];
    }

    public function flush()
    {
        return $this->clear();
    }

    protected function generateUniqueKey($key)
    {
        return (string)$key;
    }

    public function mget($keys)
    {
        return $this->getMultiple($keys, false);
    }

    public function clear()
    {
        $this->_cache = array();
        return true;
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
        if (!is_array($keys)) {
            if (!$keys instanceof \Traversable) {
                throw new InvalidArgumentException('$keys is neither an array nor Traversable');
            }
            $keys = iterator_to_array($keys, false);
        }

        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has($key)
    {
        return isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true));
    }
}
