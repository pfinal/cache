<?php

namespace PFinal\Cache;

interface CacheInterface
{
    public function get($id);

    public function mget($ids);

    public function set($id, $value, $expire = 0);

    public function add($id, $value, $expire = 0);

    public function delete($id);

    public function flush();
}
