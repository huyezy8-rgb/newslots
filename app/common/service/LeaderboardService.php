<?php


namespace app\common\service;

use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;

class LeaderboardService
{
    // Redis键前缀
    private const REDIS_PREFIX = 'leaderboard:';
    private const POOL_PREFIX = 'prize_pool:';

    // 排行榜类型
    private const TYPE_DAILY = 'daily';
    private const TYPE_WEEKLY = 'weekly';
    private const TYPE_MONTHLY = 'monthly';


    /**
     * 获取排行榜配置
     * @return array
     */
    private function getConfig(): array
    {
        // 优先从缓存读取基础配置
        $cacheKey = 'leaderboard:base_config';
        $config = \think\facade\Cache::get($cacheKey);
        
        if (!$config) {
            // 从数据库获取启用的排行榜配置
            $config = Db::name('ranking_activity')
                ->where('status', 1)
                ->order('id', 'desc')
                ->find();
            
            // 缓存基础配置信息，缓存1小时
            \think\facade\Cache::set($cacheKey, $config, 3600);
        } else {
            // 如果从缓存读取的是字符串，需要反序列化
            if (is_string($config)) {
                $decodedConfig = json_decode($config, true);
                $config = $decodedConfig ?: [];
            }
            
            // 确保 $config 是数组
            if (!is_array($config)) {
                $config = [];
            }
        }

        return [
            'bet_ratio' => [
                'daily' => floatval($config['daily_pool_ratio'] ?? 1.00),
                'weekly' => floatval($config['weekly_pool_ratio'] ?? 1.00),
                'monthly' => floatval($config['monthly_pool_ratio'] ?? 1.00),
            ],
            'daily_pool_ratio' => floatval($config['daily_pool_ratio'] ?? 1.00),
            'weekly_pool_ratio' => floatval($config['weekly_pool_ratio'] ?? 1.00),
            'monthly_pool_ratio' => floatval($config['monthly_pool_ratio'] ?? 1.00),
            'daily_limit' => intval($config['day_limit']),
            'weekly_limit' => intval($config['week_limit']),
            'monthly_limit' => intval($config['month_limit']),
            'day_rewards' => $this->decodeJsonField($config['day_rewards'] ?? '[]'),
            'week_rewards' => $this->decodeJsonField($config['week_rewards'] ?? '[]'),
            'month_rewards' => $this->decodeJsonField($config['month_rewards'] ?? '[]'),
        ];
    }

    /**
     * 安全解码JSON字段
     * @param mixed $field
     * @return array
     */
    private function decodeJsonField($field): array
    {
        if (is_array($field)) {
            return $field;
        }
        
        if (is_string($field)) {
            $decoded = json_decode($field, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return [];
    }

    /**
     * 获取指定排行榜类型的入池比例
     * @param string $type 排行榜类型
     * @return float
     */
    public function getBetRatio(string $type): float
    {
        $config = $this->getConfig();
        return $config['bet_ratio'][$type] ?? 1.00;
    }

    /**
     * 清除排行榜配置缓存
     * @return bool
     */
    public function clearConfigCache(): bool
    {
        $baseCacheKey = 'leaderboard:base_config';
        $processedCacheKey = 'leaderboard:processed_config';
        
        $result1 = \think\facade\Cache::delete($baseCacheKey);
        $result2 = \think\facade\Cache::delete($processedCacheKey);
        
        return $result1 && $result2;
    }

    /**
     * 更新用户排行榜统计
     * @param int $userId 用户ID
     * @param float $amount 下注金额
     */
    public function updateUserStats(int $userId, float $amount, int $channelId = null): void
    {
        try {
            // 获取用户信息
            $userInfo = Db::name('account')->where('id', $userId)->find();
            if (!$userInfo) {
                Log::warning('LeaderboardService: 用户不存在: ' . json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return;
            }

            // 获取当前时间
            $now = time();
            $today = date('Y-m-d', $now);
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $monthStart = date('Y-m', $now);

            // 更新日榜（存储当天数据，用于明天发放）
            $this->updateLeaderboard(self::TYPE_DAILY, $userId, $amount, $today, $userInfo, $channelId);

            // 更新周榜（存储本周数据，用于周日发放）
            $this->updateLeaderboard(self::TYPE_WEEKLY, $userId, $amount, $weekStart, $userInfo, $channelId);

            // 更新月榜（存储本月数据，用于下月1号发放）
            $this->updateLeaderboard(self::TYPE_MONTHLY, $userId, $amount, $monthStart, $userInfo, $channelId);

            Log::info('LeaderboardService: 用户排行榜统计更新完成: ' . json_encode([
                'user_id' => $userId,
                'amount' => $amount
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 更新用户排行榜统计失败: ' . json_encode([
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'amount' => $amount
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 更新指定类型的排行榜
     * @param string $type 排行榜类型
     * @param int $userId 用户ID
     * @param float $amount 下注金额
     * @param string $period 统计周期
     * @param array $userInfo 用户信息
     * @param int|null $channelId 渠道ID
     */
    private function updateLeaderboard(string $type, int $userId, float $amount, string $period, array $userInfo, int $channelId = null): void
    {
        // Redis键（只保留当前周期）
        $redisKey = self::REDIS_PREFIX . $type;
        $poolKey = self::POOL_PREFIX . $type;
        
        // 添加渠道标识
        if ($channelId !== null) {
            $redisKey .= ':channel:' . $channelId;
            $poolKey .= ':channel:' . $channelId;
        }

        // 获取配置
        $config = $this->getConfig();

        // 根据排行榜类型获取对应的奖池比例
        $poolRate = match ($type) {
            self::TYPE_DAILY => $config['daily_pool_ratio'] ?? 1.00,
            self::TYPE_WEEKLY => $config['weekly_pool_ratio'] ?? 1.00,
            self::TYPE_MONTHLY => $config['monthly_pool_ratio'] ?? 1.00,
            default => 1.00
        };
        
        // 计算入池金额（不保留小数位，避免小比例时被四舍五入为0）
        $poolAmount = $amount * $poolRate / 100;

        // 更新Redis有序集合（只保留当前周期）
        $this->updateRedisLeaderboard($redisKey, $userId, $amount, $userInfo);

        // 更新奖金池（只保留当前周期）
        $this->updatePrizePool($poolKey, $poolAmount);

        // 更新MySQL记录（保留历史数据）
        $this->updateMysqlRecord($type, $userId, $amount, $period, $userInfo, $poolAmount);
    }

    /**
     * 更新Redis有序集合
     * @param string $redisKey Redis键
     * @param int $userId 用户ID
     * @param float $amount 下注金额
     * @param array $userInfo 用户信息
     */
    private function updateRedisLeaderboard(string $redisKey, int $userId, float $amount, array $userInfo): void
    {
        try {
            // 获取当前分数
            $currentScore = Cache::store('redis')->zScore($redisKey, $userId) ?? 0;
            $newScore = $currentScore + $amount;

            // 更新有序集合
            Cache::store('redis')->zAdd($redisKey, $newScore, $userId);

            Log::info('LeaderboardService: Redis排行榜更新: ' . json_encode([
                'key' => $redisKey,
                'user_id' => $userId,
                'old_score' => $currentScore,
                'new_score' => $newScore
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::error('LeaderboardService: Redis排行榜更新失败: ' . json_encode([
                'error' => $e->getMessage(),
                'key' => $redisKey,
                'user_id' => $userId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 更新奖金池
     * @param string $poolKey 奖金池Redis键
     * @param float $poolAmount 入池金额
     */
    private function updatePrizePool(string $poolKey, float $poolAmount): void
    {
        try {
            // 增加奖金池金额
            Cache::store('redis')->incrByFloat($poolKey, $poolAmount);

            Log::info('LeaderboardService: 奖金池更新: ' . json_encode([
                'key' => $poolKey,
                'pool_amount' => $poolAmount
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 奖金池更新失败: ' . json_encode([
                'error' => $e->getMessage(),
                'key' => $poolKey
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 更新MySQL记录
     * @param string $type 排行榜类型
     * @param int $userId 用户ID
     * @param float $amount 下注金额
     * @param string $period 统计周期
     * @param array $userInfo 用户信息
     * @param float $poolAmount 入池金额
     */
    private function updateMysqlRecord(string $type, int $userId, float $amount, string $period, array $userInfo, float $poolAmount): void
    {
        try {
            Db::transaction(function () use ($type, $userId, $amount, $period, $userInfo, $poolAmount) {
                // 查找或创建用户排行榜记录
                $record = Db::name('leaderboard_stats')
                    ->where('user_id', $userId)
                    ->where('type', $type)
                    ->where('period', $period)
                    ->find();

                if ($record) {
                    // 更新现有记录
                    Db::name('leaderboard_stats')
                        ->where('id', $record['id'])
                        ->update([
                            'total_bet' => $record['total_bet'] + $amount,
                            'pool_amount' => $record['pool_amount'] + $poolAmount,
                            'update_time' => time()
                        ]);
                } else {
                    // 创建新记录
                    Db::name('leaderboard_stats')->insert([
                        'user_id' => $userId,
                        'type' => $type,
                        'period' => $period,
                        'total_bet' => $amount,
                        'pool_amount' => $poolAmount,
                        'username' => $userInfo['name'] ?? '',
                        'nickname' => $userInfo['nickname'] ?? '',
                        // 'avatar' => $userInfo['avatar'] ?? '',
                        'channel_id' => $userInfo['channel_id'] ?? 0,
                        'create_time' => time(),
                        'update_time' => time()
                    ]);
                }

                // 更新奖金池记录
                $poolQuery = Db::name('leaderboard_pool')
                    ->where('type', $type)
                    ->where('period', $period)
                    ->where('channel_id', $userInfo['channel_id'] ?? 0);
                
                $poolRecord = $poolQuery->find();

                if ($poolRecord) {
                    Db::name('leaderboard_pool')
                        ->where('id', $poolRecord['id'])
                        ->update([
                            'total_amount' => $poolRecord['total_amount'] + $poolAmount,
                            'update_time' => time()
                        ]);
                } else {
                    Db::name('leaderboard_pool')->insert([
                        'type' => $type,
                        'period' => $period,
                        'channel_id' => $userInfo['channel_id'] ?? 0,
                        'total_amount' => $poolAmount,
                        'create_time' => time(),
                        'update_time' => time()
                    ]);
                }
            });

            Log::info('LeaderboardService: MySQL记录更新完成: ' . json_encode([
                'type' => $type,
                'user_id' => $userId,
                'period' => $period
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::error('LeaderboardService: MySQL记录更新失败: ' . json_encode([
                'error' => $e->getMessage(),
                'type' => $type,
                'user_id' => $userId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 获取排行榜数据
     * @param string $type 排行榜类型
     * @param int $limit 限制数量（可选，默认使用配置限制）
     * @return array
     */
    public function getLeaderboard(string $type, int $limit = 0, int $channelId = null): array
    {
        try {
            $redisKey = self::REDIS_PREFIX . $type;
            
            // 添加渠道标识
            if ($channelId !== null) {
                $redisKey .= ':channel:' . $channelId;
            }

            // 如果没有指定限制数量，使用配置中的限制
            if ($limit <= 0) {
                $config = $this->getConfig();
                $limit = $config[$type . '_limit'] ?? 300;
            }

            // 从Redis获取排行榜数据（按分数降序）
            $leaderboard = Cache::store('redis')->zRevRange($redisKey, 0, $limit - 1, true);

            // 如果Redis中没有数据，尝试从MySQL获取当前周期的数据
            if (empty($leaderboard)) {
                Log::info('LeaderboardService: Redis中无数据，尝试从MySQL获取: ' . json_encode(['type' => $type, 'channel_id' => $channelId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return $this->getLeaderboardFromMysql($type, $limit, $channelId);
            }

            // 获取用户详细信息
            $userIds = array_keys($leaderboard);
            $userQuery = Db::name('account')
                ->whereIn('id', $userIds)
                ->field('id,name,nickname');
                
            // 添加渠道过滤
            if ($channelId !== null) {
                $userQuery->where('channel_id', $channelId);
            }
            
            $users = $userQuery->select()->toArray();

            $userMap = array_column($users, null, 'id');

            $result = [];
            $rank = 1;

            // 获取排行榜配置
            $config = $this->getLeaderboardConfig();
            
            // 根据类型获取对应的奖励配置
            $rawRewards = [];
            switch ($type) {
                case 'daily':
                    $rawRewards = $config['day_rewards'] ?? [];
                    break;
                case 'weekly':
                    $rawRewards = $config['week_rewards'] ?? [];
                    break;
                case 'monthly':
                    $rawRewards = $config['month_rewards'] ?? [];
                    break;
            }
            
            // 解析奖励配置为排名映射（使用平分逻辑）
            $rewardsMap = $this->parseRewardsConfig($rawRewards);
            // 解析排名显示格式
            $displayMap = $this->parseRewardsDisplayConfig($rawRewards);

            foreach ($leaderboard as $userId => $score) {
                $userInfo = $userMap[$userId] ?? [];
                
                // 从解析后的奖励映射中获取该排名的奖励比例
                $rewardPercent = $rewardsMap[$rank] ?? 0;
                // 从显示格式映射中获取该排名的显示格式
                $rankDisplay = $displayMap[$rank] ?? (string)$rank;

                $result[] = [
                    'rank' => $rankDisplay,
                    'user_id' => $userId,
                    'score' => number_format($score, 2, '.', ''),
                    'reward_percent' => $rewardPercent,
                    'username' => $userInfo['nickname'] ?? '',
                    'nickname' => $userInfo['nickname'] ?? '',
                ];
                
                $rank++;
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 获取排行榜数据失败: ' . json_encode([
                'error' => $e->getMessage(),
                'type' => $type
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [];
        }
    }

    /**
     * 从MySQL获取当前周期的排行榜数据
     * @param string $type 排行榜类型
     * @param int $limit 限制数量
     * @return array
     */
    private function getLeaderboardFromMysql(string $type, int $limit, int $channelId = null): array
    {
        try {
            // 获取当前周期
            $period = $this->getCurrentPeriod($type);
            
            // 从MySQL获取指定周期的排行榜数据
            $query = Db::name('leaderboard_stats')
                ->where('type', $type)
                ->where('period', $period);
                
            // 添加渠道过滤
            if ($channelId !== null) {
                $query->where('channel_id', $channelId);
            }
            
            $leaderboardData = $query
                ->order('total_bet', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();

            if (empty($leaderboardData)) {
                Log::info('LeaderboardService: MySQL中无当前周期数据: ' . json_encode([
                    'type' => $type,
                    'period' => $period,
                    'channel_id' => $channelId
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return [];
            }

            // 重新构建Redis有序集合
            $this->rebuildRedisLeaderboard($type, $leaderboardData, $channelId);

            $result = [];
            $rank = 1;

            // 获取排行榜配置
            $config = $this->getLeaderboardConfig();
            
            // 根据类型获取对应的奖励配置
            $rawRewards = [];
            switch ($type) {
                case 'daily':
                    $rawRewards = $config['day_rewards'] ?? [];
                    break;
                case 'weekly':
                    $rawRewards = $config['week_rewards'] ?? [];
                    break;
                case 'monthly':
                    $rawRewards = $config['month_rewards'] ?? [];
                    break;
            }
            
            // 解析奖励配置为排名映射（使用平分逻辑）
            $rewardsMap = $this->parseRewardsConfig($rawRewards);
            // 解析排名显示格式
            $displayMap = $this->parseRewardsDisplayConfig($rawRewards);

            foreach ($leaderboardData as $data) {
                $userId = $data['user_id'];
                
                // 从解析后的奖励映射中获取该排名的奖励比例
                $rewardPercent = $rewardsMap[$rank] ?? 0;
                // 从显示格式映射中获取该排名的显示格式
                $rankDisplay = $displayMap[$rank] ?? (string)$rank;

                $result[] = [
                    'rank' => $rankDisplay,
                    'user_id' => $userId,
                    'score' => number_format($data['total_bet'], 2, '.', ''),
                    'reward_percent' => $rewardPercent,
                    'username' => $data['username'] ?? '',
                    'nickname' => $data['nickname'] ?? '',
                ];
                
                $rank++;
            }

            Log::info('LeaderboardService: 从MySQL获取排行榜数据并重建Redis缓存成功: ' . json_encode([
                'type' => $type,
                'period' => $period,
                'count' => count($result)
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $result;
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 从MySQL获取排行榜数据失败: ' . json_encode([
                'error' => $e->getMessage(),
                'type' => $type
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [];
        }
    }

    /**
     * 重新构建Redis有序集合
     * @param string $type 排行榜类型
     * @param array $leaderboardData 排行榜数据
     * @param int|null $channelId 渠道ID
     */
    private function rebuildRedisLeaderboard(string $type, array $leaderboardData, int $channelId = null): void
    {
        try {
            $redisKey = self::REDIS_PREFIX . $type;
            
            // 添加渠道标识
            if ($channelId !== null) {
                $redisKey .= ':channel:' . $channelId;
            }

            // 清空现有的Redis有序集合
            Cache::store('redis')->del($redisKey);

            // 重新构建有序集合
            $redisData = [];
            foreach ($leaderboardData as $data) {
                $redisData[$data['user_id']] = (float)$data['total_bet'];
            }

            if (!empty($redisData)) {
                // 批量添加到Redis有序集合
                $redis = Cache::store('redis');
                $successCount = 0;
                
                try {
                    // 尝试使用管道操作提高性能
                    $redis->multi();
                    
                    // 批量添加数据
                    foreach ($redisData as $userId => $score) {
                        $redis->zAdd($redisKey, $score, $userId);
                    }
                    
                    // 执行管道操作
                    $result = $redis->exec();
                    $successCount = count($redisData);
                    
                } catch (\Exception $e) {
                    // 如果管道操作失败，回退到逐个添加
                    Log::warning('LeaderboardService: 管道操作失败，回退到逐个添加: ' . $e->getMessage());
                    
                    foreach ($redisData as $userId => $score) {
                        try {
                            $redis->zAdd($redisKey, $score, $userId);
                            $successCount++;
                        } catch (\Exception $e2) {
                            Log::error('LeaderboardService: 添加单个用户失败: ' . json_encode([
                                'user_id' => $userId,
                                'score' => $score,
                                'error' => $e2->getMessage()
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        }
                    }
                }
                
                // 验证操作结果
                $actualCount = $redis->zCard($redisKey);
                
                Log::info('LeaderboardService: Redis有序集合重建完成: ' . json_encode([
                    'type' => $type,
                    'channel_id' => $channelId,
                    'key' => $redisKey,
                    'expected_count' => count($redisData),
                    'success_count' => $successCount,
                    'actual_count' => $actualCount
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        } catch (\Exception $e) {
            Log::error('LeaderboardService: Redis有序集合重建失败: ' . json_encode([
                'error' => $e->getMessage(),
                'type' => $type,
                'channel_id' => $channelId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 获取当前周期
     * @param string $type 排行榜类型
     * @return string
     */
    private function getCurrentPeriod(string $type): string
    {
        $now = time();
        
        switch ($type) {
            case 'daily':
                return date('Y-m-d', $now);
            case 'weekly':
                return date('Y-m-d', strtotime('monday this week'));
            case 'monthly':
                return date('Y-m', $now);
            default:
                return date('Y-m-d', $now);
        }
    }

    /**
     * 获取奖金池金额
     * @param string $type 排行榜类型
     * @return float
     */
    public function getPrizePool(string $type, int $channelId = null): float
    {
        try {
            $poolKey = self::POOL_PREFIX . $type;
            if ($channelId !== null) {
                $poolKey .= ':channel:' . $channelId;
            }
            
            // 优先从Redis获取
            $poolAmount = Cache::store('redis')->get($poolKey);
            
            if ($poolAmount !== null && $poolAmount > 0) {
                return (float)$poolAmount;
            }
            
            // Redis中没有数据，从MySQL获取当前周期的奖金池
            Log::info('LeaderboardService: Redis中无奖金池数据，尝试从MySQL获取: ' . json_encode(['type' => $type], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            
            $period = $this->getCurrentPeriod($type);
            $poolQuery = Db::name('leaderboard_pool')
                ->where('type', $type)
                ->where('period', $period);
                
            // 添加渠道过滤
            if ($channelId !== null) {
                $poolQuery->where('channel_id', $channelId);
            }
            
            $poolData = $poolQuery->find();
            
            if ($poolData && $poolData['total_amount'] > 0) {
                $amount = (float)$poolData['total_amount'];
                
                // 同步到Redis
                Cache::store('redis')->set($poolKey, $amount);
                
                Log::info('LeaderboardService: 从MySQL获取奖金池并同步到Redis: ' . json_encode([
                    'type' => $type,
                    'period' => $period,
                    'amount' => $amount
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                
                return $amount;
            }
            
            // 如果MySQL中也没有数据，尝试从排行榜统计数据计算
            $stats = Db::name('leaderboard_stats')
                ->where('type', $type)
                ->where('period', $period)
                ->select()
                ->toArray();
            
            if (!empty($stats)) {
                $totalPoolAmount = array_sum(array_column($stats, 'pool_amount'));
                
                if ($totalPoolAmount > 0) {
                    // 同步到Redis
                    Cache::store('redis')->set($poolKey, $totalPoolAmount);
                    
                    // 创建奖金池记录
                    Db::name('leaderboard_pool')->insert([
                        'type' => $type,
                        'period' => $period,
                        'total_amount' => $totalPoolAmount,
                        'status' => 1, // 进行中
                        'create_time' => time(),
                        'update_time' => time()
                    ]);
                    
                    Log::info('LeaderboardService: 从统计数据计算奖金池并同步: ' . json_encode([
                        'type' => $type,
                        'period' => $period,
                        'amount' => $totalPoolAmount
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    
                    return $totalPoolAmount;
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 获取奖金池金额失败: ' . json_encode([
                'error' => $e->getMessage(),
                'type' => $type
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return 0;
        }
    }

    /**
     * 重置排行榜（用于周期切换时）
     * @param string $type 排行榜类型
     */
    public function resetLeaderboard(string $type): void
    {
        try {
            $redisKey = self::REDIS_PREFIX . $type;
            $poolKey = self::POOL_PREFIX . $type;

            // 清空Redis中的排行榜和奖金池
            Cache::store('redis')->del($redisKey);
            Cache::store('redis')->del($poolKey);

            Log::info('LeaderboardService: 重置排行榜: ' . json_encode(['type' => $type], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 重置排行榜失败: ' . json_encode([
                'error' => $e->getMessage(),
                'type' => $type
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 清除排行榜和奖金池缓存（用于奖励发放后）
     * @param string $type 排行榜类型
     * @param int|null $channelId 渠道ID，如果为null则清除所有渠道的缓存
     */
    public function clearLeaderboardCache(string $type, int $channelId = null): void
    {
        try {
            if ($channelId !== null) {
                // 清除指定渠道的缓存
                $redisKey = self::REDIS_PREFIX . $type . ':channel:' . $channelId;
                $poolKey = self::POOL_PREFIX . $type . ':channel:' . $channelId;
                
                Cache::store('redis')->del($redisKey);
                Cache::store('redis')->del($poolKey);
                
                Log::info('LeaderboardService: 清除排行榜缓存: ' . json_encode([
                    'type' => $type,
                    'channel_id' => $channelId
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                // 清除所有渠道的缓存（包括无渠道标识的）
                $redisKey = self::REDIS_PREFIX . $type;
                $poolKey = self::POOL_PREFIX . $type;
                
                Cache::store('redis')->del($redisKey);
                Cache::store('redis')->del($poolKey);
                
                // 获取所有可能的渠道缓存键并清除（使用模糊匹配）
                // 注意：这里需要根据实际的Redis客户端实现来调整
                $pattern = $redisKey . ':channel:*';
                $this->clearCacheByPattern($pattern);
                
                $poolPattern = $poolKey . ':channel:*';
                $this->clearCacheByPattern($poolPattern);
                
                Log::info('LeaderboardService: 清除所有渠道排行榜缓存: ' . json_encode([
                    'type' => $type
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 清除排行榜缓存失败: ' . json_encode([
                'error' => $e->getMessage(),
                'type' => $type,
                'channel_id' => $channelId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 根据模式清除缓存
     * @param string $pattern 缓存键模式
     */
    private function clearCacheByPattern(string $pattern): void
    {
        try {
            // 使用KEYS命令查找匹配的键（注意：在生产环境中可能影响性能，但通常渠道数量不会太多）
            $keys = Cache::store('redis')->keys($pattern);
            
            if (!empty($keys)) {
                Cache::store('redis')->del($keys);
                
                Log::info('LeaderboardService: 根据模式清除缓存: ' . json_encode([
                    'pattern' => $pattern,
                    'keys_count' => count($keys)
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        } catch (\Exception $e) {
            Log::warning('LeaderboardService: 根据模式清除缓存失败: ' . json_encode([
                'error' => $e->getMessage(),
                'pattern' => $pattern
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 获取排行榜配置信息
     * @return array
     */
    public function getLeaderboardConfig(): array
    {
        // 使用不同的缓存键，避免与getConfig冲突
        $cacheKey = 'leaderboard:processed_config';

        try {
            // 优先从缓存获取处理后的配置
            $cachedConfig = Cache::store('redis')->get($cacheKey);
            if ($cachedConfig !== null) {
                Log::info('LeaderboardService: 从缓存获取处理后的排行榜配置');
                // 缓存中存储的是处理后的数组，直接返回
                return is_array($cachedConfig) ? $cachedConfig : [];
            }

            Log::info('LeaderboardService: 缓存未命中，重新计算排行榜配置');

            // 缓存未命中，重新计算
            $config = $this->getConfig();

            // 获取最大人数限制
            $maxLimit = max(
                $config['daily_limit'] ?? 100,
                $config['weekly_limit'] ?? 100,
                $config['monthly_limit'] ?? 100
            );

            // 处理奖励配置，返回二维数组格式
            $rewardsList = $this->formatRewardsToArray(
                $config['day_rewards'] ?? [],
                $config['week_rewards'] ?? [],
                $config['month_rewards'] ?? [],
                $maxLimit
            );

            $result = [
                'daily_limit' => $config['daily_limit'] ?? 100,
                'weekly_limit' => $config['weekly_limit'] ?? 100,
                'monthly_limit' => $config['monthly_limit'] ?? 100,
                'day_rewards' => $config['day_rewards'] ?? [],
                'week_rewards' => $config['week_rewards'] ?? [],
                'month_rewards' => $config['month_rewards'] ?? [],
                'rewards_list' => $rewardsList
            ];

            // 缓存结果，直接存储数组，设置1小时过期
            Cache::store('redis')->set($cacheKey, $result, 3600);

            Log::info('LeaderboardService: 排行榜配置已缓存', [
                'cache_key' => $cacheKey,
                'expire_time' => 3600
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 获取排行榜配置失败', [
                'error' => $e->getMessage(),
                'cache_key' => $cacheKey
            ]);

            // 返回默认配置，确保始终返回数组
            return [
                'bet_ratio' => 0,
                'daily_limit' => 100,
                'weekly_limit' => 100,
                'monthly_limit' => 100,
                'rewards_list' => []
            ];
        }
    }


    /**
     * 格式化奖励配置为二维数组
     * @param array $dayRewards 日榜奖励配置
     * @param array $weekRewards 周榜奖励配置
     * @param array $monthRewards 月榜奖励配置
     * @param int $maxLimit 最大人数限制
     * @return array 格式化后的奖励配置
     */
    private function formatRewardsToArray(array $dayRewards, array $weekRewards, array $monthRewards, int $maxLimit): array
    {
        // 解析各榜单的奖励配置（平分后的比例）
        $dayRewardsMap = $this->parseRewardsConfig($dayRewards);
        $weekRewardsMap = $this->parseRewardsConfig($weekRewards);
        $monthRewardsMap = $this->parseRewardsConfig($monthRewards);

        // 解析各榜单的显示格式（单个显示单个，多个显示范围）
        $dayDisplayMap = $this->parseRewardsConfigDisplay($dayRewards);
        $weekDisplayMap = $this->parseRewardsConfigDisplay($weekRewards);
        $monthDisplayMap = $this->parseRewardsConfigDisplay($monthRewards);

        $result = [];
        $processedRanks = []; // 记录已处理的排名，避免重复

        // 遍历所有奖励配置，按原始配置生成结果
        foreach ($dayRewards as $reward) {
            if (isset($reward['rank_start']) && isset($reward['rank_end']) && isset($reward['reward_percent'])) {
                $start = intval($reward['rank_start']);
                $end = intval($reward['rank_end']);
                
                // 检查是否超出最大限制
                if ($start > $maxLimit) {
                    continue;
                }
                
                // 限制结束排名不超过最大限制
                $end = min($end, $maxLimit);
                
                // 查找该排名对应的显示格式
                $dayDisplay = $this->findDisplayFormat($dayDisplayMap, $start);
                $weekDisplay = $this->findDisplayFormat($weekDisplayMap, $start);
                $monthDisplay = $this->findDisplayFormat($monthDisplayMap, $start);

                // 如果是单个排名，使用平分后的比例
                if ($start == $end) {
                    $result[] = [
                        'rank' => $start,
                        'rank_display' => $dayDisplay,
                        'daily_reward' => number_format($dayRewardsMap[$start] ?? 0, 2, '.', ''),
                        'weekly_reward' => number_format($weekRewardsMap[$start] ?? 0, 2, '.', ''),
                        'monthly_reward' => number_format($monthRewardsMap[$start] ?? 0, 2, '.', '')
                    ];
                    $processedRanks[] = $start;
                } else {
                    // 如果是排名范围，使用原始的总奖励比例（不平分）
                    $originalDailyReward = floatval($reward['reward_percent']);
                    $originalWeeklyReward = $this->getOriginalRewardForRange($weekRewards, $start, $end);
                    $originalMonthlyReward = $this->getOriginalRewardForRange($monthRewards, $start, $end);
                    
                    $result[] = [
                        'rank' => $start . '-' . $end,
                        'rank_display' => $dayDisplay,
                        'daily_reward' => number_format($originalDailyReward, 2, '.', ''),
                        'weekly_reward' => number_format($originalWeeklyReward, 2, '.', ''),
                        'monthly_reward' => number_format($originalMonthlyReward, 2, '.', '')
                    ];
                    
                    // 记录这个范围内的所有排名为已处理
                    for ($i = $start; $i <= $end; $i++) {
                        $processedRanks[] = $i;
                    }
                }
            }
        }

        // 按rank字段排序
        usort($result, function($a, $b) {
            $rankA = $a['rank'];
            $rankB = $b['rank'];
            
            // 如果是范围格式（如"51-55"），提取起始排名
            if (strpos($rankA, '-') !== false) {
                $rankA = intval(explode('-', $rankA)[0]);
            } else {
                $rankA = intval($rankA);
            }
            
            if (strpos($rankB, '-') !== false) {
                $rankB = intval(explode('-', $rankB)[0]);
            } else {
                $rankB = intval($rankB);
            }
            
            return $rankA - $rankB;
        });

        return $result;
    }

    /**
     * 查找指定排名对应的显示格式
     * @param array $displayMap 显示格式映射
     * @param int $rank 排名
     * @return string 显示格式
     */
    private function findDisplayFormat(array $displayMap, int $rank): string
    {
        foreach ($displayMap as $config) {
            $start = intval($config['rank_start']);
            $end = intval($config['rank_end']);
            
            if ($rank >= $start && $rank <= $end) {
                return $config['display_format'];
            }
        }
        
        return (string)$rank; // 默认返回排名数字
    }

    /**
     * 获取排名范围的原始奖励比例
     * @param array $rewards 奖励配置
     * @param int $start 起始排名
     * @param int $end 结束排名
     * @return float 原始奖励比例
     */
    private function getOriginalRewardForRange(array $rewards, int $start, int $end): float
    {
        foreach ($rewards as $reward) {
            if (isset($reward['rank_start']) && isset($reward['rank_end']) && isset($reward['reward_percent'])) {
                $rewardStart = intval($reward['rank_start']);
                $rewardEnd = intval($reward['rank_end']);
                
                if ($rewardStart == $start && $rewardEnd == $end) {
                    return floatval($reward['reward_percent']);
                }
            }
        }
        
        return 0.0; // 默认返回0
    }

    /**
     * 解析奖励配置为排名映射
     * @param array $rewards 原始奖励配置
     * @return array 排名到奖励百分比的映射
     */
    private function parseRewardsConfig(array $rewards): array
    {
        $rewardMap = [];

        foreach ($rewards as $reward) {
            // 数据格式：{ rank_start: 1, rank_end: 1, reward_percent: 10.00 }
            if (isset($reward['rank_start']) && isset($reward['rank_end']) && isset($reward['reward_percent'])) {
                $start = intval($reward['rank_start']);
                $end = intval($reward['rank_end']);
                $totalPercentage = floatval($reward['reward_percent']);
                
                // 计算该排名范围内的人数
                $userCount = $end - $start + 1;
                
                // 计算每个用户应得的奖励比例（平分）
                $perUserPercentage = $userCount > 0 ? round($totalPercentage / $userCount, 2) : 0;

                for ($i = $start; $i <= $end; $i++) {
                    $rewardMap[$i] = $perUserPercentage;
                }
            }
        }

        return $rewardMap;
    }

    /**
     * 解析奖励配置为排名显示格式映射（排行榜接口用 - 显示单个排名）
     * @param array $rewards 原始奖励配置
     * @return array 排名到显示格式的映射
     */
    private function parseRewardsDisplayConfig(array $rewards): array
    {
        $displayMap = [];

        foreach ($rewards as $reward) {
            // 数据格式：{ rank_start: 1, rank_end: 1, reward_percent: 10.00 }
            if (isset($reward['rank_start']) && isset($reward['rank_end']) && isset($reward['reward_percent'])) {
                $start = intval($reward['rank_start']);
                $end = intval($reward['rank_end']);
                
                // 排行榜接口显示单个排名
                for ($i = $start; $i <= $end; $i++) {
                    $displayMap[$i] = (string)$i;
                }
            }
        }

        return $displayMap;
    }

    /**
     * 解析奖励配置为配置接口显示格式（单个显示单个，多个显示范围）
     * @param array $rewards 原始奖励配置
     * @return array 配置接口的显示格式
     */
    private function parseRewardsConfigDisplay(array $rewards): array
    {
        $configDisplay = [];

        foreach ($rewards as $reward) {
            // 数据格式：{ rank_start: 1, rank_end: 1, reward_percent: 10.00 }
            if (isset($reward['rank_start']) && isset($reward['rank_end']) && isset($reward['reward_percent'])) {
                $start = intval($reward['rank_start']);
                $end = intval($reward['rank_end']);
                
                // 配置接口显示格式：单个显示单个，多个显示范围
                if ($start == $end) {
                    // 单个排名，显示为 "1"
                    $displayFormat = (string)$start;
                } else {
                    // 排名范围，显示为 "51-55"
                    $displayFormat = $start . '-' . $end;
                }
                
                $configDisplay[] = [
                    'rank_start' => $start,
                    'rank_end' => $end,
                    'reward_percent' => $reward['reward_percent'],
                    'display_format' => $displayFormat
                ];
            }
        }

        return $configDisplay;
    }


    /**
     * 获取用户排名
     * @param int $userId
     * @param string $type
     * @return array
     */
    public function getUserRanking(int $userId, string $type, int $channelId = null): array
    {
        try {
            $redisKey = 'leaderboard:' . $type;
            if ($channelId !== null) {
                $redisKey .= ':channel:' . $channelId;
            }

            $user = Db::name('account')->where('id', $userId)->field('id,name,nickname')->find();

            // 获取用户分数
            $score = Cache::store('redis')->zScore($redisKey, $userId) ?? 0;

            if ($score <= 0) {
                return [
                    'rank' => 'No rank',
                    'user_id' => $userId,
                    'score' => '0.00',
                    'username' => $user['name'] ?? '',
                    'nickname' => $user['nickname'] ?? '',
                    'reward_percent' => 0
                ];
            }

            // 获取用户排名（从1开始）
            $rank = Cache::store('redis')->zRevRank($redisKey, $userId) + 1;

            // 获取排行榜配置，检查用户是否在榜单内
            $config = $this->getLeaderboardConfig();
            $limit = 0;
            switch ($type) {
                case 'daily':
                    $limit = $config['daily_limit'] ?? 100;
                    break;
                case 'weekly':
                    $limit = $config['weekly_limit'] ?? 100;
                    break;
                case 'monthly':
                    $limit = $config['monthly_limit'] ?? 100;
                    break;
            }


            // 检查用户是否在榜单内
            if ($rank > $limit) {
                return [
                    'rank' => 'No rank',
                    'user_id' => $userId,
                    'score' => number_format($score, 2, '.', ''),
                    'username' => $user['name'] ?? '',
                    'nickname' => $user['nickname'] ?? '',
                    'reward_percent' => 0
                ];
            }

            // 获取用户奖励比例和显示格式
            $rawRewards = [];
            switch ($type) {
                case 'daily':
                    $rawRewards = $config['day_rewards'] ?? [];
                    break;
                case 'weekly':
                    $rawRewards = $config['week_rewards'] ?? [];
                    break;
                case 'monthly':
                    $rawRewards = $config['month_rewards'] ?? [];
                    break;
            }
            
            // 解析奖励配置为排名映射（使用平分逻辑）
            $rewardsMap = $this->parseRewardsConfig($rawRewards);
            // 解析排名显示格式
            $displayMap = $this->parseRewardsDisplayConfig($rawRewards);
            
            // 从解析后的奖励映射中获取该排名的奖励比例
            $rewardPercent = $rewardsMap[$rank] ?? 0;
            // 从显示格式映射中获取该排名的显示格式
            $rankDisplay = $displayMap[$rank] ?? (string)$rank;

            return [
                'rank' => $rankDisplay,
                'user_id' => $userId,
                'score' => number_format($score, 2, '.', ''),
                'username' => $user['name'] ?? '',
                'nickname' => $user['nickname'] ?? '',
                'reward_percent' => $rewardPercent
            ];
        } catch (\Exception $e) {
            Log::error('Leaderboard: 获取用户排名失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'type' => $type
            ]);

            return [
                'rank' => 'No rank',
                'user_id' => $userId,
                'score' => '0.00',
                'username' => $user['name'] ?? '',
                'nickname' => $user['nickname'] ?? '',
                'reward_percent' => 0
            ];
        }
    }

    /**
     * 获取指定周期的排行榜数据
     * @param string $type 排行榜类型
     * @param string $period 周期标识
     * @param int $limit 限制数量
     * @return array
     */
    public function getLeaderboardByPeriod(string $type, string $period, int $limit = 0, int $channelId = null): array
    {
        try {
            // 如果没有指定限制数量，使用配置中的限制
            if ($limit <= 0) {
                $config = $this->getConfig();
                $limit = $config[$type . '_limit'] ?? 300;
            }

            // 从MySQL获取指定周期的排行榜数据
            $query = Db::name('leaderboard_stats')
                ->where('type', $type)
                ->where('period', $period);
                
            // 添加渠道过滤
            if ($channelId !== null) {
                $query->where('channel_id', $channelId);
            }
            
            $leaderboardData = $query
                ->order('total_bet', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();

            if (empty($leaderboardData)) {
                return [];
            }

            $result = [];
            $rank = 1;

            // 获取排行榜配置
            $config = $this->getLeaderboardConfig();
            
            // 使用修改后的奖励计算逻辑（平分奖励）
            $rewardsList = $config['rewards_list'] ?? [];
            $rewardsMap = [];
            
            // 根据类型获取对应的奖励配置
            $rawRewards = [];
            switch ($type) {
                case 'daily':
                    $rawRewards = $config['day_rewards'] ?? [];
                    break;
                case 'weekly':
                    $rawRewards = $config['week_rewards'] ?? [];
                    break;
                case 'monthly':
                    $rawRewards = $config['month_rewards'] ?? [];
                    break;
            }
            
            // 解析奖励配置为排名映射（使用平分逻辑）
            $rewardsMap = $this->parseRewardsConfig($rawRewards);
            // 解析排名显示格式
            $displayMap = $this->parseRewardsDisplayConfig($rawRewards);

            foreach ($leaderboardData as $data) {
                $userId = $data['user_id'];
                
                // 从解析后的奖励映射中获取该排名的奖励比例
                $rewardPercent = $rewardsMap[$rank] ?? 0;
                // 从显示格式映射中获取该排名的显示格式
                $rankDisplay = $displayMap[$rank] ?? (string)$rank;

                $result[] = [
                    'rank' => $rankDisplay,
                    'user_id' => $userId,
                    'score' => number_format($data['total_bet'], 2, '.', ''),
                    'pool_amount' => number_format($data['pool_amount'], 2, '.', ''),
                    'reward_percent' => $rewardPercent,
                    'username' => $data['username'] ?? '',
                    'nickname' => $data['nickname'] ?? '',
                ];
                
                $rank++;
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 获取指定周期排行榜数据失败', [
                'error' => $e->getMessage(),
                'type' => $type,
                'period' => $period
            ]);
            return [];
        }
    }

    /**
     * 获取指定周期的奖金池金额
     * @param string $type 排行榜类型
     * @param string $period 周期标识
     * @return float
     */
    public function getPrizePoolByPeriod(string $type, string $period, int $channelId = null): float
    {
        try {
            // 从MySQL获取指定周期的奖金池数据
            $poolQuery = Db::name('leaderboard_pool')
                ->where('type', $type)
                ->where('period', $period);
                
            // 添加渠道过滤
            if ($channelId !== null) {
                $poolQuery->where('channel_id', $channelId);
            }
            
            $poolData = $poolQuery->find();

            return $poolData ? (float)$poolData['total_amount'] : 0;
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 获取指定周期奖金池金额失败', [
                'error' => $e->getMessage(),
                'type' => $type,
                'period' => $period
            ]);
            return 0;
        }
    }

    /**
     * 获取指定周期的排行榜数据（用于奖励发放）
     * @param string $type 排行榜类型
     * @param int $limit 限制数量
     * @return array
     */
    public function getLeaderboardForReward(string $type, int $limit = 0, int $channelId = null): array
    {
        $period = $this->getRewardPeriod($type);
        return $this->getLeaderboardByPeriod($type, $period, $limit, $channelId);
    }

    /**
     * 获取指定周期的奖金池金额（用于奖励发放）
     * @param string $type 排行榜类型
     * @return float
     */
    public function getPrizePoolForReward(string $type, int $channelId = null): float
    {
        $period = $this->getRewardPeriod($type);
        return $this->getPrizePoolByPeriod($type, $period, $channelId);
    }

    /**
     * 获取奖励发放对应的周期
     * @param string $type 排行榜类型
     * @return string
     */
    private function getRewardPeriod(string $type): string
    {
        $now = time();
        
        switch ($type) {
            case 'daily':
                // 日榜：获取昨天的数据
                return date('Y-m-d', strtotime('-1 day', $now));
            case 'weekly':
                // 周榜：获取上周的数据（上周一的日期）
                $lastWeekStart = strtotime('last monday', $now);
                return date('Y-m-d', $lastWeekStart);
            case 'monthly':
                // 月榜：获取上月的数据
                return date('Y-m', strtotime('-1 month', $now));
            default:
                return date('Y-m-d', $now);
        }
    }

    /**
     * 强制重建排行榜缓存（用于测试或手动恢复）
     * @param string $type 排行榜类型
     * @param int|null $channelId 渠道ID
     * @return bool
     */
    public function forceRebuildLeaderboardCache(string $type, int $channelId = null): bool
    {
        try {
            $redisKey = self::REDIS_PREFIX . $type;
            if ($channelId !== null) {
                $redisKey .= ':channel:' . $channelId;
            }

            // 清空Redis缓存
            Cache::store('redis')->del($redisKey);

            // 获取当前周期的数据
            $period = $this->getCurrentPeriod($type);
            
            // 从MySQL获取数据
            $query = Db::name('leaderboard_stats')
                ->where('type', $type)
                ->where('period', $period);
                
            if ($channelId !== null) {
                $query->where('channel_id', $channelId);
            }
            
            $leaderboardData = $query
                ->order('total_bet', 'desc')
                ->select()
                ->toArray();

            if (!empty($leaderboardData)) {
                // 重建Redis有序集合
                $this->rebuildRedisLeaderboard($type, $leaderboardData, $channelId);
                
                Log::info('LeaderboardService: 强制重建排行榜缓存成功: ' . json_encode([
                    'type' => $type,
                    'channel_id' => $channelId,
                    'count' => count($leaderboardData)
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('LeaderboardService: 强制重建排行榜缓存失败: ' . json_encode([
                'error' => $e->getMessage(),
                'type' => $type,
                'channel_id' => $channelId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        }
    }
}
