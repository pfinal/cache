<?php

namespace PFinal\Cache;

/**
 * Array缓存
 * 将数据存在一个数组中，仅在当次请求中有效
 */
class ArrayCache implements CacheInterface
{
    private $_cache;
    public $keyPrefix = '';
    public $hashKey = true;

    public function __construct($config = array())
    {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }
    }

    public function get($id)
    {
        $key = $this->generateUniqueKey($id);
        if (isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            return $this->_cache[$key][0];
        } else {
            return false;
        }
    }

    public function set($id, $value, $expire = 0)
    {
        $key = $this->generateUniqueKey($id);
        $this->_cache[$key] = array($value, $expire === 0 ? 0 : microtime(true) + $expire);
        return true;
    }

    public function add($id, $value, $expire = 0)
    {
        $key = $this->generateUniqueKey($id);
        if (isset($this->_cache[$key]) && ($this->_cache[$key][1] === 0 || $this->_cache[$key][1] > microtime(true))) {
            return false;
        } else {
            $this->_cache[$key] = array($value, $expire === 0 ? 0 : microtime(true) + $expire);
            return true;
        }
    }

    public function delete($id)
    {
        $key = $this->generateUniqueKey($id);
        unset($this->_cache[$key]);
        return true;
    }

    public function flush()
    {
        $this->_cache = [];
        return true;
    }

    protected function generateUniqueKey($key)
    {
        return $this->hashKey ? md5($this->keyPrefix . $key) : $this->keyPrefix . $key;
    }

    public function mget($id)
    {
        throw new \Exception(get_class($this) . ' does not support ' . __METHOD__ . '().');
    }

}
