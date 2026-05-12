<?php

namespace app\common\service;

use app\common\model\LuckyWheelConfig;
use app\common\model\LuckyWheelTurntable;
use app\common\model\LuckyWheelLogs;
use app\common\model\Account;
use think\facade\Db;

class LuckyWheelService
{
    /**
     * 获取用户可用的转盘信息
     */
    public static function getUserWheels($userId)
    {
        // 获取用户信息
        $user = Account::find($userId);
        if (!$user) {
            return ['code' => 0, 'msg' => '用户不存在'];
        }
        
        // 获取活动配置（使用缓存）
        $config = LuckyWheelCacheService::getConfig();
        if (!$config) {
            return ['code' => 0, 'msg' => '活动未开启'];
        }
        
        // 计算用户充值总额（使用缓存）
        $totalRecharge = LuckyWheelCacheService::getUserRecharge($userId);
        
        // 获取所有转盘（使用缓存）
        $wheels = LuckyWheelCacheService::getWheels();
        
        $wheelList = [];
        foreach ($wheels as $wheel) {
            // 计算用户可用次数
            $availableTimes = self::calculateUserAvailableTimes($userId, $wheel, $totalRecharge);
            
            // 计算转盘总奖励金额
            $totalRewardAmount = self::calculateWheelTotalReward($wheel);
            
            // 判断是否解锁
            $isUnlocked = $totalRecharge >= $wheel->unlock_condition;
            
            $wheelList[] = [
                'id' => $wheel->id,
                'name' => $wheel->wheel_name,
                'unlock_condition' => $wheel->unlock_condition,
                'free_times' => $wheel->free_times,
                'max_user_times' => $wheel->max_user_times,
                'available_times' => $availableTimes,
                'prizes' => $wheel->prizes,
                'rules' => $wheel->rules,
                'is_unlocked' => $isUnlocked,
                'total_reward_amount' => $totalRewardAmount
            ];
        }
        
        return [
            'code' => 1,
            'data' => [
                'config' => [
                    'title' => $config->title,
                    'banner_image' => $config->banner_image,
                    'bet_multiple' => $config->bet_multiple
                ],
                'wheels' => $wheelList,
                'user_recharge' => $totalRecharge
            ]
        ];
    }
    
    /**
     * 执行转盘抽奖（使用Redis锁防止并发）
     */
    public static function draw($userId, $wheelId)
    {
        // 使用Redis锁防止并发抽奖
        $lockKey = "lucky_wheel_draw:{$userId}:{$wheelId}";
        
        return RedisLockService::executeWithLock($lockKey, function () use ($userId, $wheelId) {
            return self::executeDraw($userId, $wheelId);
        }, 10, 3, 100);
    }
    
    /**
     * 执行抽奖逻辑（内部方法）
     */
    private static function executeDraw($userId, $wheelId)
    {
        Db::startTrans();
        try {
            // 获取用户信息
            $user = Account::find($userId);
            if (!$user) {
                throw new \Exception('用户不存在');
            }
            
            // 获取转盘信息（使用缓存）
            $wheels = LuckyWheelCacheService::getWheels();
            $wheel = null;
            foreach ($wheels as $w) {
                if ($w->id == $wheelId) {
                    $wheel = $w;
                    break;
                }
            }
            
            if (!$wheel || $wheel->status != 1) {
                throw new \Exception('转盘不存在或已禁用');
            }
            
            // 计算用户充值总额（使用缓存）
            $totalRecharge = LuckyWheelCacheService::getUserRecharge($userId);
            
            // 检查解锁条件
            if ($totalRecharge < $wheel->unlock_condition) {
                throw new \Exception('转盘未解锁');
            }
            
            // 检查用户可用次数
            $availableTimes = self::calculateUserAvailableTimes($userId, $wheel, $totalRecharge);
            if ($availableTimes <= 0) {
                throw new \Exception('转盘次数已用完');
            }
            
            // 执行抽奖
            $prize = self::drawPrize($wheel->prizes);
            if (!$prize) {
                throw new \Exception('抽奖失败');
            }
            
            // 创建转盘记录
            $log = LuckyWheelLogs::createLog($userId, $wheelId, $prize['title'], $prize['amount']);
            
            // 如果中奖金额大于0，发放奖励
            if ($prize['amount'] > 0) {
                self::grantPrize($userId, $prize['amount'], $log->id);
            }
            
            // 清除相关缓存
            LuckyWheelCacheService::clearUserUsageCache($userId, $wheelId);
            LuckyWheelCacheService::clearUserCache($userId);
            
            Db::commit();
            
            return [
                'code' => 1,
                'data' => [
                    'prize' => $prize,
                    'log_id' => $log->id,
                    'remaining_times' => $availableTimes - 1
                ]
            ];
            
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 0, 'msg' => $e->getMessage()];
        }
    }
    
    /**
     * 计算用户可用次数（使用缓存）
     */
    private static function calculateUserAvailableTimes($userId, $wheel, $totalRecharge)
    {
        // 基础赠送次数
        $availableTimes = $wheel->free_times;
        
        // 根据规则计算额外次数
        foreach ($wheel->rules as $rule) {
            if ($rule['status'] != 1) continue;
            
            $conditionMet = false;
            if ($rule['rule_type'] == 2) { // 充值达到
                $conditionMet = $totalRecharge >= $rule['condition_value'];
            }
            
            if ($conditionMet) {
                $availableTimes += $rule['reward_times'];
            }
        }
        
        // 减去已使用次数（使用缓存）
        $usedTimes = LuckyWheelCacheService::getUserUsageCount($userId, $wheel->id);
        $availableTimes -= $usedTimes;
        
        // 检查最大次数限制
        if ($wheel->max_user_times > 0) {
            $availableTimes = min($availableTimes, $wheel->max_user_times - $usedTimes);
        }
        
        return max(0, $availableTimes);
    }
    
    /**
     * 计算转盘总奖励金额
     */
    private static function calculateWheelTotalReward($wheel)
    {
        $totalRewardAmount = 0;
        if (!empty($wheel->prizes)) {
            foreach ($wheel->prizes as $prize) {
                $totalRewardAmount += floatval($prize['amount']);
            }
        }
        return $totalRewardAmount;
    }
    
    /**
     * 执行抽奖逻辑
     */
    private static function drawPrize($prizes)
    {
        if (empty($prizes)) {
            return false;
        }
        
        // 计算总概率
        $totalProbability = 0;
        foreach ($prizes as $prize) {
            $totalProbability += floatval($prize['probability']);
        }
        
        // 如果总概率为0，随机返回一个奖项
        if ($totalProbability <= 0) {
            return $prizes[array_rand($prizes)];
        }
        
        // 生成随机数
        $random = mt_rand() / mt_getrandmax() * $totalProbability;
        $currentProb = 0;
        
        foreach ($prizes as $prize) {
            $currentProb += floatval($prize['probability']);
            if ($random <= $currentProb) {
                return $prize;
            }
        }
        
        // 如果没有命中任何奖项，返回最后一个
        return end($prizes);
    }
    
    /**
     * 发放奖励
     */
    private static function grantPrize($userId, $amount, $logId)
    {
        // 使用AccountService的increaseBalance方法发放奖励
        $accountService = new \app\common\service\AccountService();
        $result = $accountService->increaseBalance(
            $userId,
            $amount,
            1, // 充值钱包
            \app\api\enum\CoinLog::LuckyWheel,
            "幸运转盘中奖，记录ID: {$logId}"
        );
        
        if (!$result) {
            throw new \Exception('奖励发放失败');
        }
        
        // 更新转盘记录状态
        LuckyWheelLogs::where('id', $logId)->update(['status' => 1]);
    }
    
    /**
     * 获取用户转盘记录
     */
    public static function getUserLogs($userId, $wheelId = null, $page = 1, $limit = 10)
    {
        $query = LuckyWheelLogs::where('user_id', $userId);
        if ($wheelId) {
            $query->where('wheel_id', $wheelId);
        }
        
        $total = $query->count();
        $logs = $query->order('createtime', 'desc')
            ->page($page, $limit)
            ->select();
        
        // 计算用户总领取金额
        $totalAmountQuery = LuckyWheelLogs::where('user_id', $userId)
            ->where('prize_amount', '>', 0);
        if ($wheelId) {
            $totalAmountQuery->where('wheel_id', $wheelId);
        }
        $totalAmount = $totalAmountQuery->sum('prize_amount');
            
        return [
            'code' => 1,
            'data' => [
                'list' => $logs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_amount' => number_format($totalAmount, 2, '.', '')
            ]
        ];
    }
    
    /**
     * 清除用户缓存（供外部调用）
     */
    public static function clearUserCache($userId)
    {
        return LuckyWheelCacheService::clearUserCache($userId);
    }
    
    /**
     * 预热缓存
     */
    public static function warmUpCache()
    {
        return LuckyWheelCacheService::warmUpCache();
    }
    
    /**
     * 获取缓存统计
     */
    public static function getCacheStats()
    {
        return LuckyWheelCacheService::getCacheStats();
    }
} 