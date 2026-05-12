<?php

namespace app\common\service;

use think\facade\Cache;

class RedisLockService
{
    /**
     * 获取分布式锁
     * @param string $key 锁的键名
     * @param int $ttl 锁的过期时间（秒）
     * @param int $retryTimes 重试次数
     * @param int $retryDelay 重试延迟（毫秒）
     * @return bool|string 成功返回锁的值，失败返回false
     */
    public static function acquireLock($key, $ttl = 10, $retryTimes = 3, $retryDelay = 100)
    {
        $lockValue = uniqid() . '_' . microtime(true);
        $lockKey = "lock:{$key}";
        
        for ($i = 0; $i <= $retryTimes; $i++) {
            // 尝试获取锁
            $result = Cache::store('redis')->set($lockKey, $lockValue, $ttl);
            
            if ($result) {
                return $lockValue;
            }
            
            // 如果还有重试次数，等待后重试
            if ($i < $retryTimes) {
                usleep($retryDelay * 1000); // 转换为微秒
            }
        }
        
        return false;
    }
    
    /**
     * 释放分布式锁
     * @param string $key 锁的键名
     * @param string $lockValue 锁的值
     * @return bool 是否成功释放
     */
    public static function releaseLock($key, $lockValue)
    {
        $lockKey = "lock:{$key}";
        
        // 使用Lua脚本确保原子性操作
        $luaScript = <<<LUA
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
LUA;
        
        // 通过Redis的EVAL命令执行Lua脚本
        $redis = Cache::store('redis')->handler();
        $result = $redis->eval($luaScript, [$lockKey, $lockValue], 1);
        
        return $result == 1;
    }
    
    /**
     * 使用锁执行回调函数
     * @param string $key 锁的键名
     * @param callable $callback 要执行的回调函数
     * @param int $ttl 锁的过期时间（秒）
     * @param int $retryTimes 重试次数
     * @param int $retryDelay 重试延迟（毫秒）
     * @return mixed 回调函数的返回值
     * @throws \Exception 获取锁失败时抛出异常
     */
    public static function executeWithLock($key, $callback, $ttl = 10, $retryTimes = 3, $retryDelay = 100)
    {
        $lockValue = self::acquireLock($key, $ttl, $retryTimes, $retryDelay);
        
        if ($lockValue === false) {
            throw new \Exception('获取锁失败，请稍后重试');
        }
        
        try {
            $result = call_user_func($callback);
            return $result;
        } finally {
            // 确保锁被释放
            self::releaseLock($key, $lockValue);
        }
    }
    
    /**
     * 检查锁是否存在
     * @param string $key 锁的键名
     * @return bool 是否存在
     */
    public static function isLocked($key)
    {
        $lockKey = "lock:{$key}";
        return Cache::store('redis')->has($lockKey);
    }
    
    /**
     * 强制释放锁（谨慎使用）
     * @param string $key 锁的键名
     * @return bool 是否成功释放
     */
    public static function forceReleaseLock($key)
    {
        $lockKey = "lock:{$key}";
        return Cache::store('redis')->delete($lockKey);
    }
} 