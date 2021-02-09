<?php
/**
 * 基于Redis实现的分布式互斥锁，如果redis是集群，有主从自动切换
 * 则redis锁也无法保证完全原子性质，最好使用redis单点.
 * 互斥锁核心问题：
 * +-----------------------------+
 * | 1、获取锁的整个过程必须原子性  |
 * +-----------------------------+
 * | 2、上锁和解锁必须同一个客户端  |
 * +----------------------------+
 * | 3、必须有死锁处理方案         |
 * +-----------------------------+
 *
 * Created by PhpStorm.
 * User: randy
 * Date: 2020-05-30
 * Time: 10:04
 */


class RedisLock
{
    /**
     * 锁的生存期，即：锁超时时间，用于防止死锁
     */
    const LOCK_TTL = 10;

    /**
     * @var string 锁拥有者Id
     * 防止解锁其他客户端的锁
     */
    private $_lockOwnerId;

    private $_keyPrefix = "mutex:";

    private $_redis;

    public function __construct()
    {
        //todo 此处自己实现redis连接
        $this->_redis = new Redis();
    }

    /**
     * 堵塞式互斥锁，该方法慎用！！！！
     * @param $key
     * @param int $blockSeconds 锁等待超时时间 0-立即返回结果
     * @param int $ttl 锁生存期，超过该时间，认为发生死锁。
     * @return boolean
     * @throws \Exception
     */
    public function lock($key, $blockSeconds = 0, $ttl = self::LOCK_TTL)
    {
        $expireTime = microtime(true) + $blockSeconds;
        if ($blockSeconds <= 0) {
            return $this->_tryLock($key, $ttl);
        }
        while (!$this->_tryLock($key, $ttl)) {
            //此处用的协程版本, 非协程使用usleep(20000);
            \co::sleep(0.2);
            if (microtime(true) > $expireTime) {
                throw new \Exception("lock block timeout");
            }
        };
    }


    public function unlock($key)
    {
        $key = $this->_keyPrefix . $key;
        $flag = $this->_lockOwnerId;

        //此处保证只解锁自己的锁
        $script = <<<SCRIPT
                if redis.call("get",KEYS[1]) == ARGV[1]
                then
                    return redis.call("del",KEYS[1])
                else
                    return 0
                end
SCRIPT;
        return $this->_redis->rawCommand('EVAL', $script, 1, $key, $flag);

    }


    /**
     * 非堵塞式，直接返回
     * @param $key
     * @param int $ttl
     * @return boolean
     */
    private function _tryLock($key, $ttl = self::LOCK_TTL)
    {
        $key = $this->_keyPrefix . $key;
        $flag = md5(microtime(true));
        if ($this->_redis->set($key, $flag, ['NX', 'EX' => $ttl])) {
            $this->_lockOwnerId = $flag;
            return true;
        }
        return false;

    }
}