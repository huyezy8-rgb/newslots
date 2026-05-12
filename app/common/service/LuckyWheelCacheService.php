<?php

namespace app\common\service;

use think\facade\Cache;
use app\common\model\LuckyWheelConfig;
use app\common\model\LuckyWheelTurntable;

class LuckyWheelCacheService
{
    // 缓存键前缀
    const CACHE_PREFIX = 'lucky_wheel:';
    const CONFIG_CACHE_KEY = 'config';
    const WHEELS_CACHE_KEY = 'wheels';
    const USER_WHEELS_CACHE_KEY = 'user_wheels:';
    const USER_RECHARGE_CACHE_KEY = 'user_recharge:';
    const USER_BET_CACHE_KEY = 'user_bet:';
    const USER_USAGE_CACHE_KEY = 'user_usage:';
    
    // 缓存过期时间（秒）
    const CONFIG_CACHE_TTL = 3600;      // 1小时
    const WHEELS_CACHE_TTL = 3600;      // 1小时
    const USER_CACHE_TTL = 300;         // 5分钟
    const USAGE_CACHE_TTL = 60;         // 1分钟
    
    /**
     * 获取活动配置（带缓存）
     */
    public static function getConfig()
    {
        $cacheKey = self::CACHE_PREFIX . self::CONFIG_CACHE_KEY;
        
        return Cache::remember($cacheKey, function () {
            return LuckyWheelConfig::getActiveConfig();
        }, self::CONFIG_CACHE_TTL);
    }
    
    /**
     * 获取转盘列表（带缓存）
     */
    public static function getWheels()
    {
        $cacheKey = self::CACHE_PREFIX . self::WHEELS_CACHE_KEY;
        
        return Cache::remember($cacheKey, function () {
            return LuckyWheelTurntable::getAvailableWheels();
        }, self::WHEELS_CACHE_TTL);
    }
    
    /**
     * 获取用户充值总额（带缓存）
     */
    public static function getUserRecharge($userId)
    {
        $cacheKey = self::CACHE_PREFIX . self::USER_RECHARGE_CACHE_KEY . $userId;
        
        return Cache::remember($cacheKey, function () use ($userId) {
            return \think\facade\Db::name('recharge_orders')
                ->where('user_id', $userId)
                ->where('pay_status', 1)
                ->sum('amount');
        }, self::USER_CACHE_TTL);
    }
    
    /**
     * 获取用户下注总额（带缓存）
     */
    public static function getUserBet($userId)
    {
        $cacheKey = self::CACHE_PREFIX . self::USER_BET_CACHE_KEY . $userId;
        
        return Cache::remember($cacheKey, function () use ($userId) {
            return \think\facade\Db::name('game_transactions')
                ->where('user_id', $userId)
                ->where('reason', '下注')
                ->sum('amount');
        }, self::USER_CACHE_TTL);
    }
    
    /**
     * 获取用户转盘使用次数（带缓存）
     */
    public static function getUserUsageCount($userId, $wheelId)
    {
        $cacheKey = self::CACHE_PREFIX . self::USER_USAGE_CACHE_KEY . $userId . ':' . $wheelId;
        
        return Cache::remember($cacheKey, function () use ($userId, $wheelId) {
            return \app\common\model\LuckyWheelLogs::getUserUsageCount($userId, $wheelId);
        }, self::USAGE_CACHE_TTL);
    }
    
    /**
     * 获取用户转盘信息（带缓存）
     */
    public static function getUserWheels($userId)
    {
        $cacheKey = self::CACHE_PREFIX . self::USER_WHEELS_CACHE_KEY . $userId;
        
        return Cache::remember($cacheKey, function () use ($userId) {
            return LuckyWheelService::getUserWheels($userId);
        }, self::USER_CACHE_TTL);
    }
    
    /**
     * 清除活动配置缓存
     */
    public static function clearConfigCache()
    {
        $cacheKey = self::CACHE_PREFIX . self::CONFIG_CACHE_KEY;
        return Cache::delete($cacheKey);
    }
    
    /**
     * 清除转盘配置缓存
     */
    public static function clearWheelsCache()
    {
        $cacheKey = self::CACHE_PREFIX . self::WHEELS_CACHE_KEY;
        return Cache::delete($cacheKey);
    }
    
    /**
     * 清除用户相关缓存
     */
    public static function clearUserCache($userId)
    {
        $keys = [
            self::CACHE_PREFIX . self::USER_RECHARGE_CACHE_KEY . $userId,
            self::CACHE_PREFIX . self::USER_BET_CACHE_KEY . $userId,
            self::CACHE_PREFIX . self::USER_WHEELS_CACHE_KEY . $userId,
        ];
        
        foreach ($keys as $key) {
            Cache::delete($key);
        }
        
        // 清除用户所有转盘的使用次数缓存
        $wheels = self::getWheels();
        foreach ($wheels as $wheel) {
            $usageKey = self::CACHE_PREFIX . self::USER_USAGE_CACHE_KEY . $userId . ':' . $wheel->id;
            Cache::delete($usageKey);
        }
    }
    
    /**
     * 清除用户特定转盘的使用次数缓存
     */
    public static function clearUserUsageCache($userId, $wheelId)
    {
        $cacheKey = self::CACHE_PREFIX . self::USER_USAGE_CACHE_KEY . $userId . ':' . $wheelId;
        return Cache::delete($cacheKey);
    }
    
    /**
     * 清除所有用户相关缓存
     */
    public static function clearAllUserCache()
    {
        // 获取所有用户相关的缓存键
        $patterns = [
            self::CACHE_PREFIX . self::USER_WHEELS_CACHE_KEY . '*',
            self::CACHE_PREFIX . self::USER_RECHARGE_CACHE_KEY . '*',
            self::CACHE_PREFIX . self::USER_BET_CACHE_KEY . '*',
            self::CACHE_PREFIX . self::USER_USAGE_CACHE_KEY . '*'
        ];
        
        $allKeys = [];
        foreach ($patterns as $pattern) {
            $keys = Cache::store('redis')->keys($pattern);
            if (!empty($keys)) {
                $allKeys = array_merge($allKeys, $keys);
            }
        }
        
        if (!empty($allKeys)) {
            return Cache::store('redis')->del($allKeys);
        }
        
        return true;
    }
    
    /**
     * 清除所有幸运转盘相关缓存
     */
    public static function clearAllCache()
    {
        // 获取所有幸运转盘相关的缓存键
        $pattern = self::CACHE_PREFIX . '*';
        $keys = Cache::store('redis')->keys($pattern);
        
        if (!empty($keys)) {
            return Cache::store('redis')->del($keys);
        }
        
        return true;
    }
    
    /**
     * 预热缓存
     */
    public static function warmUpCache()
    {
        // 预热活动配置
        self::getConfig();
        
        // 预热转盘配置
        self::getWheels();
        
        return true;
    }
    
    /**
     * 获取缓存统计信息
     */
    public static function getCacheStats()
    {
        $pattern = self::CACHE_PREFIX . '*';
        $keys = Cache::store('redis')->keys($pattern);
        
        $stats = [
            'total_keys' => count($keys),
            'config_keys' => 0,
            'wheels_keys' => 0,
            'user_keys' => 0,
        ];
        
        foreach ($keys as $key) {
            if (strpos($key, self::CONFIG_CACHE_KEY) !== false) {
                $stats['config_keys']++;
            } elseif (strpos($key, self::WHEELS_CACHE_KEY) !== false) {
                $stats['wheels_keys']++;
            } elseif (strpos($key, 'user_') !== false) {
                $stats['user_keys']++;
            }
        }
        
        return $stats;
    }
} 