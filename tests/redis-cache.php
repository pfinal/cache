<?php

require './../vendor/autoload.php';

$cache = new \PFinal\Cache\RedisCache();

$cache->set('name', 'Ethan');
$cache->set('name2', 'Mary');

//$cache->set('name', 'Ethan',10);
//$cache->delete('name2');

var_dump($cache->get('name'));

//var_dump($cache->flush());
