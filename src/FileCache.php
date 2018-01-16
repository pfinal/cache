<?php

namespace PFinal\Cache;

/**
 * 文件缓存
 */
class FileCache implements CacheInterface
{
    /**
     * @var string 缓存文件存放目录
     */
    public $cachePath = null;

    /**
     * @var string 缓存文件后缀，默认为 ".bin"
     */
    public $cacheFileSuffix = '.bin';

    /**
     * @var int 子目录级别
     * 如果没有子目录，当文件数量巨大(如1万以上)，你可能需要将此值设置为1或2，以降低文件系统的压力
     * 此属性值不应超过16，推荐使用一个小于3的值
     */
    public $directoryLevel = 1;

    /**
     * @var array|boolean 使用什么函数来进行序列化和反序列化
     * 默认为null ,表示使用 PHP 的 'serialize()' 和 'unserialize()' 函数
     * 如果需要指定其它函数，则需要一个索引数组，将用第一个来进行序列化，第二个进行反序列化
     */
    public $serializer;

    /**
     * @var string 键(key)的前缀
     */
    public $keyPrefix = '';

    /**
     * @var bool 是否对保存的键(key)
     */
    public $hashKey = true;

    /**
     * @var int 垃圾回收概率 默认0.01％的概率
     */
    private $_gcProbability = 100;

    /**
     * @var bool 是否已触发了垃圾回收
     */
    private $_gced = false;

    public function __construct($config = array())
    {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }

        if (empty($this->cachePath)) {
            $this->cachePath = sys_get_temp_dir();
        }
        static::createDirectory($this->cachePath);
    }

    public function set($key, $value, $ttl = null)
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

        if ($this->serializer === null) {
            $value = serialize(array($value));
        } else {
            $value = call_user_func($this->serializer[0], array($value));
        }

        return $this->setValue($this->generateUniqueKey($key), $value, $ttl);
    }

    public function get($key, $default = null)
    {
        $value = $this->getValue($this->generateUniqueKey($key));

        if ($value === false) {
            return $default;
        }

        if ($this->serializer === null) {
            $value = unserialize($value);
        } else {
            $value = call_user_func($this->serializer[1], $value);
        }

        if (is_array($value)) {
            return $value[0];
        }

        return $default;
    }

    /**
     * @deprecated
     */
    public function mget($ids)
    {
        return $this->getMultiple($ids, false);
    }


    /**
     * @deprecated
     */
    public function add($key, $value, $expire = 0)
    {
        if ($this->serializer === null)
            $value = serialize(array($value));
        else {
            $value = call_user_func($this->serializer[0], array($value));
        }

        return $this->addValue($this->generateUniqueKey($key), $value, $expire);
    }

    public function increment($key, $value = 1)
    {
        $payload = $this->getPayload($key);

        $payload['data'] = $payload['data'] + $value;

        $this->set($key, $payload['data'], (int)$payload['time']);

        return $payload['data'];
    }

    /**
     * 还剩多少秒过期 以及 缓存内容
     *
     * @param string $key
     * @return array
     */
    private function getPayload($key)
    {
        $emptyPayload = array('data' => null, 'time' => null);

        $cacheFile = $this->getCacheFile($this->generateUniqueKey($key));

        $mtime = @filemtime($cacheFile);
        if (!$mtime) {
            return $emptyPayload;
        }

        $expire = $mtime - time();

        if ($expire <= 0) {
            $this->delete($key);
            return $emptyPayload;
        }

        $value = @file_get_contents($cacheFile);

        if ($this->serializer === null) {
            $value = unserialize($value);
        } else {
            $value = call_user_func($this->serializer[1], $value);
        }

        if (!is_array($value)) {
            return $emptyPayload;
        }

        return array('data' => $value[0], 'time' => $expire);
    }

    /**
     * @deprecated
     */
    public function flush()
    {
        return $this->clear();
    }

    public function delete($key)
    {
        return $this->deleteValue($this->generateUniqueKey($key));
    }

    private function generateUniqueKey($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('$key is not a string');
        }
        return $this->hashKey ? md5($this->keyPrefix . $key) : $this->keyPrefix . $key;
    }

    private function getGCProbability()
    {
        return $this->_gcProbability;
    }

    /**
     * @param integer $value 垃圾回收机制的概率 （最大百万分之一）
     * 在高速缓存中存储的数据块时，默认为100，意味着0.01％的概率
     * 这个数字应该是0到1000000之间。值为0表示每次都执行垃圾回收
     */
    private function setGCProbability($value)
    {
        $value = (int)$value;
        if ($value < 0) {
            $value = 0;
        }
        if ($value > 1000000) {
            $value = 1000000;
        }
        $this->_gcProbability = $value;
    }

    /**
     * 删除所有缓存
     */
    private function flushValues()
    {
        $this->gc(false);
        return true;
    }

    /**
     * @param string $key 添加前缀的key
     * @return bool|string
     */
    private function getValue($key)
    {
        $cacheFile = $this->getCacheFile($key);
        if (($time = @filemtime($cacheFile)) > time()) {
            return @file_get_contents($cacheFile);
        } elseif ($time > 0) {
            @unlink($cacheFile);
        }
        return false;
    }

    /**
     * @param array $keys 添加前缀的key
     * @return array
     */
    private function getValues($keys)
    {
        $results = array();
        foreach ($keys as $key) {
            $results[$key] = $this->getValue($key);
        }
        return $results;
    }

    /**
     * @param string $key 添加前缀的key
     * @param string $value
     * @param integer $expire 缓存过期时间(多少秒后过期)。0表示永不过期.
     * @return boolean
     */
    private function setValue($key, $value, $expire)
    {
        if (!$this->_gced && mt_rand(0, 1000000) < $this->_gcProbability) {
            $this->gc();
            $this->_gced = true;
        }

        if ($expire <= 0) {
            $expire = 31536000; // 1 year
        }

        $cacheFile = $this->getCacheFile($key);
        if ($this->directoryLevel > 0)
            @static::createDirectory(dirname($cacheFile));
        if (@file_put_contents($cacheFile, $value, LOCK_EX) !== false) {
            return @touch($cacheFile, $expire + time());
        } else {
            return false;
        }
    }

    /**
     * @param string $key 添加前缀的key
     * @param $value
     * @param $expire
     * @return bool
     */
    private function addValue($key, $value, $expire)
    {
        $cacheFile = $this->getCacheFile($key);

        if (file_exists($cacheFile) && filemtime($cacheFile) > time()) {
            return false;
        }
        return $this->setValue($key, $value, $expire);
    }

    private function deleteValue($key)
    {
        $cacheFile = $this->getCacheFile($key);
        return @unlink($cacheFile);
    }

    private function getCacheFile($key)
    {
        if ($this->directoryLevel > 0) {
            $base = $this->cachePath;
            for ($i = 0; $i < $this->directoryLevel; ++$i) {
                if (($prefix = substr($key, $i + $i, 2)) !== false)
                    $base .= DIRECTORY_SEPARATOR . $prefix;
            }
            return $base . DIRECTORY_SEPARATOR . $key . $this->cacheFileSuffix;
        } else {
            return $this->cachePath . DIRECTORY_SEPARATOR . $key . $this->cacheFileSuffix;
        }
    }

    /**
     * 删除过期的缓存文件.
     * @param boolean $expiredOnly 如果为true 只删除过期的缓存文件  false 删除所有缓存文件
     * @param string $path 指定缓存目录，如果为null，则为$cachePath属性指定的目录
     */
    private function gc($expiredOnly = true, $path = null)
    {
        if ($path === null) {
            $path = $this->cachePath;
        }
        if (($handle = opendir($path)) === false) {
            return;
        }
        while (($file = readdir($handle)) !== false) {
            if ($file[0] === '.') {
                continue;
            }
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) {
                $this->gc($expiredOnly, $fullPath);
            } elseif ($expiredOnly && @filemtime($fullPath) < time() || !$expiredOnly) {
                @unlink($fullPath);
            }
        }
        closedir($handle);
    }

    /**
     * 递归创建目录
     * @param $path
     * @param int $mode
     * @param bool $recursive
     * @return bool
     */
    private static function createDirectory($path, $mode = 0775, $recursive = true)
    {
        if (is_dir($path)) {
            return true;
        }
        $parentDir = dirname($path);
        if ($recursive && !is_dir($parentDir)) {
            static::createDirectory($parentDir, $mode, true);
        }
        $result = mkdir($path, $mode);
        chmod($path, $mode);

        return $result;
    }

    public function clear()
    {
        return $this->flushValues();
    }

    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys)) {
            if (!$keys instanceof \Traversable) {
                throw new InvalidArgumentException('$keys is neither an array nor Traversable');
            }
            $keys = iterator_to_array($keys, false);
        }

        $uids = array();
        foreach ($keys as $id) {
            $uids[$id] = $this->generateUniqueKey($id);
        }

        $values = $this->getValues($uids);
        $results = array();
        if ($this->serializer === false) {
            foreach ($uids as $id => $uid) {
                $results[$id] = isset($values[$uid]) ? $values[$uid] : $default;
            }
        } else {
            foreach ($uids as $id => $uid) {
                $results[$id] = $default;
                if (isset($values[$uid])) {
                    $value = $this->serializer === null ? unserialize($values[$uid]) : call_user_func($this->serializer[1], $values[$uid]);
                    if (is_array($value)) {
                        $results[$id] = $value[0];
                    }
                }
            }
        }
        return $results;
    }

    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values)) {
            if (!$values instanceof \Traversable) {
                throw new InvalidArgumentException('$values is neither an array nor Traversable');
            }
            $values = iterator_to_array($values, false);
        }

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
        $payload = $this->getPayload($key);

        return $payload['time'] !== null;
    }
}
