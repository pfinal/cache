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
     * 如果为false则不进行序列化和反序列化
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

    public function get($id)
    {
        $value = $this->getValue($this->generateUniqueKey($id));
        if ($value === false || $this->serializer === false)
            return $value;
        if ($this->serializer === null)
            $value = unserialize($value);
        else
            $value = call_user_func($this->serializer[1], $value);

        if (is_array($value)) {
            return $value[0];
        }
        return false;

    }

    public function mget($ids)
    {
        $uids = array();
        foreach ($ids as $id)
            $uids[$id] = $this->generateUniqueKey($id);

        $values = $this->getValues($uids);
        $results = array();
        if ($this->serializer === false) {
            foreach ($uids as $id => $uid)
                $results[$id] = isset($values[$uid]) ? $values[$uid] : false;
        } else {
            foreach ($uids as $id => $uid) {
                $results[$id] = false;
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

    /**
     * @param $id
     * @param mixed $value
     * @param int $expire 缓存过期时间(多少秒后过期)。0表示永不过期.
     * @return bool
     */
    public function set($id, $value, $expire = 0)
    {

        if ($this->serializer === null)
            $value = serialize(array($value));
        elseif ($this->serializer !== false)
            $value = call_user_func($this->serializer[0], array($value));

        return $this->setValue($this->generateUniqueKey($id), $value, $expire);
    }

    /**
     * @param $id
     * @param $value
     * @param int $expire 缓存过期时间(多少秒后过期)，如果大于30天，请使用UNIX时间戳。0表示永不过期.
     * @return bool
     */
    public function add($id, $value, $expire = 0)
    {
        if ($this->serializer === null)
            $value = serialize(array($value));
        elseif ($this->serializer !== false)
            $value = call_user_func($this->serializer[0], array($value));

        return $this->addValue($this->generateUniqueKey($id), $value, $expire);
    }

    public function flush()
    {
        return $this->flushValues();
    }

    public function delete($id)
    {
        return $this->deleteValue($this->generateUniqueKey($id));
    }

    protected function generateUniqueKey($key)
    {
        return $this->hashKey ? md5($this->keyPrefix . $key) : $this->keyPrefix . $key;
    }

    public function getGCProbability()
    {
        return $this->_gcProbability;
    }

    /**
     * @param integer $value 垃圾回收机制的概率 （最大百万分之一）
     * 在高速缓存中存储的数据块时，默认为100，意味着0.01％的概率
     * 这个数字应该是0到1000000之间。值为0表示每次都执行垃圾回收
     */
    public function setGCProbability($value)
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
    protected function flushValues()
    {
        $this->gc(false);
        return true;
    }

    protected function getValue($key)
    {
        $cacheFile = $this->getCacheFile($key);
        if (($time = @filemtime($cacheFile)) > time()) {
            return @file_get_contents($cacheFile);
        } elseif ($time > 0) {
            @unlink($cacheFile);
        }
        return false;
    }

    protected function getValues($keys)
    {
        $results = array();
        foreach ($keys as $key) {
            $results[$key] = $this->getValue($key);
        }
        return $results;
    }

    /**
     * @param string $key
     * @param string $value
     * @param integer $expire 缓存过期时间(多少秒后过期)。0表示永不过期.
     * @return boolean
     */
    protected function setValue($key, $value, $expire)
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

    protected function addValue($key, $value, $expire)
    {
        $cacheFile = $this->getCacheFile($key);

        if (file_exists($cacheFile) && filemtime($cacheFile) > time()) {
            return false;
        }
        return $this->setValue($key, $value, $expire);
    }

    protected function deleteValue($key)
    {
        $cacheFile = $this->getCacheFile($key);
        return @unlink($cacheFile);
    }

    protected function getCacheFile($key)
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
    public function gc($expiredOnly = true, $path = null)
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
    public static function createDirectory($path, $mode = 0775, $recursive = true)
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
}
