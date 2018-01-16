<?php

class FileCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return \Psr\SimpleCache\CacheInterface
     */
    private function getCache()
    {
        //return new \PFinal\Cache\MemCache();
        //return new \PFinal\Cache\RedisCache();

        $config = array(
            'cachePath' => __DIR__ . '/cache',
            'keyPrefix' => 'test',
        );
        return new \PFinal\Cache\FileCache($config);
    }

    public function testGet()
    {
        $c = $this->getCache();

        $this->assertTrue($c->set('d', array()));

        $this->assertTrue($c->get('fake') === null);
        $this->assertTrue($c->get('fake', false) === false);

        $this->assertTrue($c->set('str', 'str'));
        $this->assertTrue($c->set('int', 1));
        $this->assertTrue($c->set('arr', array(1, 2, 3)));

        $this->assertTrue($c->get('arr') === array(1, 2, 3));
        $this->assertTrue($c->get('int') == 1);  // RedisCache 对数字，返回字符串格式
        $this->assertTrue($c->get('str') === 'str');

        $this->assertTrue($c->delete('arr'));
        $this->assertTrue($c->delete('int'));
        $this->assertTrue($c->delete('str'));

        $this->assertTrue($c->get('arr') === null);
        $this->assertTrue($c->get('int') === null);
        $this->assertTrue($c->get('str') === null);
    }


    public function testSet()
    {
        $c = $this->getCache();

        //缓存1秒
        $this->assertTrue($c->set('int', 'a', 1));
        $this->assertTrue($c->get('int') === 'a');
        sleep(2);
        $this->assertTrue($c->get('int') === null);

        //缓存1秒
        $this->assertTrue($c->set('int', 'a', new DateInterval('PT1S')));
        $this->assertTrue($c->get('int') === 'a');
        sleep(2);
        $this->assertTrue($c->get('int') === null);
    }

    public function testClear()
    {
        $c = $this->getCache();

        $this->assertTrue($c->set('a', 'v1'));
        $this->assertTrue($c->set('b', 'v1'));

        $this->assertTrue($c->clear());

        $this->assertTrue($c->get('a') === null);
        $this->assertTrue($c->get('b') === null);
    }

    public function testMultiple()
    {
        $c = $this->getCache();

        $this->assertTrue($c->setMultiple(array('a' => 'v1', 'b' => 'v2', 'c' => 'v3')));
        $this->assertTrue($c->get('a') == 'v1');
        $this->assertTrue($c->getMultiple(array('a', 'b', 'd'), false) === array('a' => 'v1', 'b' => 'v2', 'd' => false));

        $c->deleteMultiple(array('a', 'b'));

        $this->assertTrue($c->get('a') === null);
        $this->assertTrue($c->get('b') === null);
        $this->assertTrue($c->get('c') === 'v3');

        $this->assertTrue($c->setMultiple(array('aa' => 'v1', 'bb' => 'v2'), 1));
        $this->assertTrue($c->getMultiple(array('aa', 'bb', 'cc')) === array('aa' => 'v1', 'bb' => 'v2', 'cc' => null));
        sleep(2);
        $this->assertTrue($c->getMultiple(array('aa', 'bb', 'cc')) === array('aa' => null, 'bb' => null, 'cc' => null));
    }

    public function testHas()
    {
        $c = $this->getCache();

        $this->assertTrue($c->set('a', 0));
        $this->assertTrue($c->set('b', false));
        $this->assertTrue($c->set('c', null));
        $this->assertTrue($c->set('d', array()));

        $this->assertTrue($c->get('a') == 0);// RedisCache 对数字，返回字符串格式
        $this->assertTrue($c->get('b') === false);
        $this->assertTrue($c->get('c') === null);
        $this->assertTrue($c->get('d') === array());

        $this->assertTrue($c->has('a'));
        $this->assertTrue($c->has('b'));
        $this->assertTrue($c->has('c'));
        $this->assertTrue($c->has('d'));
    }

    public function testIncrement()
    {
        $c = $this->getCache();

        $c->delete('count');
        $c->set('count',0);

        $this->assertTrue($c->increment('count') == 1);
        $this->assertTrue($c->increment('count', 6) == 7);
    }
}
