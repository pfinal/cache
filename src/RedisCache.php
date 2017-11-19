<?php

namespace PFinal\Cache;

/**
 * Redis缓存
 *
 * composer require predis/predis
 *
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
    public function add($key, $value, $expire = 0)
    {
        if ($this->getRedis()->exists($this->generateUniqueKey($key))) {
            return false;
        }

        return $this->set($key, $value, $expire);
    }

    /**
     * 存放数据到缓存中
     * @param string $key 要设置值的key
     * @param mixed $value 要存储的值，字符串和数值直接存储，其他类型序列化后存储
     * @param int $expire 当前写入缓存的数据的失效时间。如果此值设置为0表明此数据永不过期。以秒为单位的整数（从当前算起的时间差）来说明此数据的过期时间
     * @return boolean 成功时返回 true， 或者在失败时返回 false
     */
    public function set($key, $value, $expire = 0)
    {
        $key = $this->generateUniqueKey($key);

        /** @var  $status \Predis\Response\Status */
        if ($expire == 0) {
            $status = $this->getRedis()->set($key, serialize($value));
        } else {
            $status = $this->getRedis()->setex($key, $expire, serialize($value));
        }
        return $status->getPayload() === 'OK';
    }

    public function increment($key, $value = 1)
    {
        $key = $this->generateUniqueKey($key);

        return $this->getRedis()->incrby($key, $value);
    }

    /**
     * 从服务端检回一个元素
     * @param $key string 要获取值的key
     * @return mixed 返回key对应的存储元素的字符串值或者在失败或key未找到的时候返回false
     */
    public function get($key)
    {
        $key = $this->generateUniqueKey($key);
        $value = $this->getRedis()->get($key);
        if ($value !== false) {
            return @unserialize($value);
        }
        return false;
    }

    /**
     * 从服务端检回多个匹配的元素
     * @param $keys string|array 要获取值的key或key数组
     * @return array
     */
    public function mget($keys)
    {
        $keys = (array)$keys;
        $values = array();
        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }
        return $values;
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

    protected function generateUniqueKey($key)
    {
        return $this->keyPrefix . $key;
    }
}