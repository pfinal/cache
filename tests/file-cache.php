<?php

require './../vendor/autoload.php';

$config = array(
    'cachePath' => './cache',
    'keyPrefix' => 'test',
);

$fileCache = new \PFinal\Cache\FileCache($config);

$fileCache->set('name', 'Ethan');

echo $fileCache->get('name');

