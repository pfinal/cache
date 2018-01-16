<?php

namespace PFinal\Cache;

interface CacheInterface extends \Psr\SimpleCache\CacheInterface
{
    public function get($id, $default = null);
    public function set($id, $value, $expire = 0);
    public function delete($id);

    public function mget($ids);
    public function add($id, $value, $expire = 0);
    public function increment($key, $value = 1);
    public function flush();
}
