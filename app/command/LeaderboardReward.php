<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use app\common\service\LeaderboardService;
use app\common\service\AccountService;
use app\api\enum\CoinLog;

class LeaderboardReward extends Command
{
    protected function configure()
    {
        $this->setName('leaderboard:reward')
            ->setDescription('发放排行榜奖金池奖励')
            ->addArgument('type', null, '排行榜类型: daily/weekly/monthly', 'daily');
    }

    
    protected function execute(Input $input, Output $output)
    {
        // 确保时区设置正确（命令行脚本需要手动设置时区）
        // 优先使用系统配置的时区，如果没有则使用默认时区
        $systemTimezone = get_sys_config('time_zone');
        $defaultTimezone = \think\facade\Config::get('app.default_timezone', 'America/New_York');
        $timezone = $systemTimezone ?: $defaultTimezone;
        date_default_timezone_set($timezone);
        
        $type = $input->getArgument('type');

        if (!in_array($type, ['daily', 'weekly', 'monthly'])) {
            $output->error('排行榜类型必须是: daily, weekly, monthly');
            return;
        }

        $output->writeln("开始发放{$type}排行榜奖励...");

        try {
            $this->distributeRewards($type, $output);
            $output->writeln("{$type}排行榜奖励发放完成");
        } catch (\Exception $e) {
            $output->error("发放{$type}排行榜奖励失败: " . $e->getMessage());
            Log::error("LeaderboardReward: 发放{$type}排行榜奖励失败: " . json_encode([
                'error' => $e->getMessage(),
                'type' => $type
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 发放排行榜奖励
     * @param string $type 排行榜类型
     * @param Output $output
     */
    private function distributeRewards(string $type, Output $output): void
    {
        $leaderboardService = new LeaderboardService();

        // 检查是否已经发放过奖励
        $period = $this->getRewardPeriod($type);
        $output->writeln("检查奖励发放周期: {$period}");
        
        // 获取所有渠道
        $channels = Db::name('channel_list')->select();
        $output->writeln("找到 " . count($channels) . " 个活跃渠道");
        
        // 为每个渠道分别发放奖励
        foreach ($channels as $channel) {
            $channelId = $channel['id'];
            $channelName = $channel['name'];
            
            $output->writeln("\n=== 处理渠道: {$channelName} (ID: {$channelId}) ===");
            
            // 检查该渠道今天是否已经发放过该类型的奖励
            $existingLog = Db::name('leaderboard_reward_log')
                ->where('type', $type)
                ->where('channel_id', $channelId)
                ->where('create_time', '>=', strtotime('today'))
                ->where('create_time', '<', strtotime('today') + 86400)
                ->find();

            if ($existingLog) {
                $output->writeln("❌ 渠道 {$channelName} 的 {$type}排行榜奖励今天已经发放过，跳过重复发放");
                $output->writeln("上次发放时间: " . date('Y-m-d H:i:s', $existingLog['create_time']));
                $output->writeln("上次发放金额: {$existingLog['distributed_amount']}");
                $output->writeln("成功发放用户数: {$existingLog['success_count']}");
                continue;
            }
            
            // 为该渠道发放奖励
            $this->distributeRewardsForChannel($type, $channelId, $channelName, $output);
        }
    }

    /**
     * 为指定渠道发放排行榜奖励
     * @param string $type 排行榜类型
     * @param int $channelId 渠道ID
     * @param string $channelName 渠道名称
     * @param Output $output
     */
    private function distributeRewardsForChannel(string $type, int $channelId, string $channelName, Output $output): void
    {
        $leaderboardService = new LeaderboardService();
        
        // 检查指定周期是否已经发放过奖励
        // $periodLog = Db::name('leaderboard_reward_log')
        //     ->where('type', $type)
        //     ->where('create_time', '>=', strtotime($period))
        //     ->where('create_time', '<', strtotime($period) + 86400)
        //     ->find();
            
        // if ($periodLog) {
        //     $output->writeln("❌ {$type}排行榜奖励在周期 {$period} 已经发放过，跳过重复发放");
        //     $output->writeln("发放时间: " . date('Y-m-d H:i:s', $periodLog['create_time']));
        //     $output->writeln("发放金额: {$periodLog['distributed_amount']}");
        //     return;
        // }
        
        $output->writeln("✅ 检查通过，开始为渠道 {$channelName} 发放 {$type} 排行榜奖励");

        // 获取指定周期的奖金池总金额（用于奖励发放）
        $poolAmount = $leaderboardService->getPrizePoolForReward($type, $channelId);

        // 显示调试信息
        $period = $this->getRewardPeriod($type);
        $output->writeln("发放{$type}排行榜奖励，周期: {$period}，渠道: {$channelName}");

        if ($poolAmount <= 0) {
            $output->writeln("渠道 {$channelName} 的 {$type}排行榜奖金池为空，跳过发放");
            return;
        }

        $output->writeln("渠道 {$channelName} 的 {$type}排行榜奖金池总金额: {$poolAmount}");

        // 获取指定周期的排行榜数据（用于奖励发放）
        $leaderboard = $leaderboardService->getLeaderboardForReward($type, 0, $channelId);

        if (empty($leaderboard)) {
            $output->writeln("{$type}排行榜数据为空，跳过发放");
            return;
        }

        $output->writeln("开始发放奖励给 " . count($leaderboard) . " 名用户...");

        // 先创建奖励发放日志记录（获取ID）
        $rewardLogId = $this->recordDistributionLog($type, $channelId, $poolAmount, 0, 0, 0);

        $totalDistributed = 0;
        $successCount = 0;
        $failCount = 0;

        foreach ($leaderboard as $rankData) {
            try {
                $userId = $rankData['user_id'];
                $rank = $rankData['rank'];
                $rewardPercent = $rankData['reward_percent'];

                if ($rewardPercent <= 0) {
                    $output->writeln("用户 {$userId} 排名 {$rank} 无奖励比例，跳过");
                    continue;
                }

                // 计算奖励金额
                $rewardAmount = round($poolAmount * $rewardPercent / 100, 2);

                if ($rewardAmount <= 0) {
                    $output->writeln("用户 {$userId} 排名 {$rank} 奖励金额为0，跳过");
                    continue;
                }

                // 发放奖励（传递 reward_log_id）
                $this->giveReward($userId, $rewardAmount, $type, $rank, $rewardPercent, $channelId, $rewardLogId);

                $totalDistributed += $rewardAmount;
                $successCount++;

                $output->writeln("用户 {$userId} 排名 {$rank} 获得奖励: {$rewardAmount} (比例: {$rewardPercent}%)");
            } catch (\Exception $e) {
                $failCount++;
                $output->writeln("用户 {$userId} 排名 {$rank} 发放失败: " . $e->getMessage());
                Log::error("LeaderboardReward: 发放奖励失败: " . json_encode([
                    'user_id' => $userId,
                    'rank' => $rank,
                    'type' => $type,
                    'error' => $e->getMessage()
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        // 更新发放统计日志的实际数据
        Db::name('leaderboard_reward_log')
            ->where('id', $rewardLogId)
            ->update([
                'distributed_amount' => $totalDistributed,
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'update_time' => time()
            ]);

        $output->writeln("发放完成统计:");
        $output->writeln("- 奖金池总金额: {$poolAmount}");
        $output->writeln("- 实际发放金额: {$totalDistributed}");
        $output->writeln("- 成功发放用户数: {$successCount}");
        $output->writeln("- 失败用户数: {$failCount}");

        // 清除Redis中的排行榜信息和奖金池缓存
        try {
            $leaderboardService->clearLeaderboardCache($type, $channelId);
            $output->writeln("✅ 已清除渠道 {$channelName} 的 {$type}排行榜和奖金池Redis缓存");
        } catch (\Exception $e) {
            $output->writeln("⚠️ 清除Redis缓存失败: " . $e->getMessage());
            Log::error("LeaderboardReward: 清除Redis缓存失败: " . json_encode([
                'error' => $e->getMessage(),
                'type' => $type,
                'channel_id' => $channelId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
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
                // 周榜：获取上周的数据
                $lastWeekStart = strtotime('last monday', $now);
                return date('Y-W', $lastWeekStart);
            case 'monthly':
                // 月榜：获取上月的数据
                return date('Y-m', strtotime('-1 month', $now));
            default:
                return date('Y-m-d', $now);
        }
    }

    /**
     * 发放奖励给用户
     * @param int $userId 用户ID
     * @param float $amount 奖励金额
     * @param string $type 排行榜类型
     * @param int $rank 排名
     * @param float $rewardPercent 奖励比例
     * @param int $channelId 渠道ID
     * @param int $rewardLogId 奖励发放日志ID
     */
    private function giveReward(int $userId, float $amount, string $type, int $rank, float $rewardPercent, int $channelId = 0, int $rewardLogId = 0): void
    {
        // 确定日志类型
        $logTypeId = match ($type) {
            'daily' => CoinLog::LeaderboardDaily,
            'weekly' => CoinLog::LeaderboardWeekly,
            'monthly' => CoinLog::LeaderboardMonthly,
            default => CoinLog::LeaderboardDaily
        };

        // 生成备注
        $typeText = match ($type) {
            'daily' => '日榜',
            'weekly' => '周榜',
            'monthly' => '月榜',
            default => '排行榜'
        };

        $note = "{$typeText}第{$rank}名奖励，奖励比例{$rewardPercent}%";

        // 使用AccountService的increaseBalance方法发放奖励
        $accountService = new AccountService();
        $walletType = 1; // 充值钱包

        $result = $accountService->increaseBalance(
            userId: $userId,
            amount: $amount,
            walletType: $walletType,
            logTypeId: $logTypeId,
            note: $note
        );

        if (!$result) {
            throw new \Exception("奖励发放失败");
        }

        // 更新排行榜统计表的奖励信息
        $this->updateLeaderboardStatsReward($userId, $type, $amount, $rewardPercent, $rank, $note, $channelId, $rewardLogId);

        Log::info("LeaderboardReward: 发放奖励成功: " . json_encode([
            'user_id' => $userId,
            'amount' => $amount,
            'type' => $type,
            'rank' => $rank,
            'reward_percent' => $rewardPercent
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 更新排行榜统计表的奖励信息
     * @param int $userId 用户ID
     * @param string $type 排行榜类型
     * @param float $amount 奖励金额
     * @param float $rewardPercent 奖励比例
     * @param int $rank 排名
     * @param string $note 备注
     * @param int $channelId 渠道ID
     * @param int $rewardLogId 奖励发放日志ID
     */
    private function updateLeaderboardStatsReward(int $userId, string $type, float $amount, float $rewardPercent, int $rank, string $note, int $channelId = 0, int $rewardLogId = 0): void
    {
        try {
            // 获取当前周期
            $period = $this->getRewardPeriod($type);
            
            // 查找对应的排行榜统计记录
            $statsRecord = Db::name('leaderboard_stats')
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('period', $period)
                ->where('channel_id', $channelId)
                ->find();

            if ($statsRecord) {
                // 更新现有记录
                Db::name('leaderboard_stats')
                    ->where('id', $statsRecord['id'])
                    ->update([
                        'reward_log_id' => $rewardLogId,
                        'reward_amount' => $amount,
                        'reward_ratio' => $rewardPercent,
                        'rank' => $rank,
                        'reward_remark' => $note,
                        'update_time' => time()
                    ]);
            } else {
                // 如果找不到记录，创建新记录
                // 获取用户基本信息
                $userInfo = Db::name('user')
                    ->field('username,nickname,avatar')
                    ->where('id', $userId)
                    ->find();

                Db::name('leaderboard_stats')->insert([
                    'user_id' => $userId,
                    'type' => $type,
                    'period' => $period,
                    'total_bet' => 0, // 如果没有统计记录，设为0
                    'pool_amount' => 0,
                    'username' => $userInfo['username'] ?? '',
                    'nickname' => $userInfo['nickname'] ?? '',
                    'avatar' => $userInfo['avatar'] ?? '',
                    'channel_id' => $channelId,
                    'reward_log_id' => $rewardLogId,
                    'reward_amount' => $amount,
                    'reward_ratio' => $rewardPercent,
                    'rank' => $rank,
                    'reward_remark' => $note,
                    'create_time' => time(),
                    'update_time' => time()
                ]);
            }

            Log::info("LeaderboardReward: 更新排行榜统计表成功: " . json_encode([
                'user_id' => $userId,
                'type' => $type,
                'period' => $period,
                'channel_id' => $channelId,
                'reward_amount' => $amount,
                'rank' => $rank
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        } catch (\Exception $e) {
            Log::error("LeaderboardReward: 更新排行榜统计表失败: " . json_encode([
                'user_id' => $userId,
                'type' => $type,
                'channel_id' => $channelId,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 记录发放统计日志
     * @param string $type 排行榜类型
     * @param int $channelId 渠道ID
     * @param float $poolAmount 奖金池总金额
     * @param float $totalDistributed 实际发放金额
     * @param int $successCount 成功用户数
     * @param int $failCount 失败用户数
     * @return int 返回奖励日志ID
     */
    private function recordDistributionLog(string $type, int $channelId, float $poolAmount, float $totalDistributed, int $successCount, int $failCount): int
    {
        try {
            $rewardLogId = Db::name('leaderboard_reward_log')->insertGetId([
                'type' => $type,
                'channel_id' => $channelId,
                'pool_amount' => $poolAmount,
                'distributed_amount' => $totalDistributed,
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'create_time' => time(),
                'update_time' => time()
            ]);
            return $rewardLogId;
        } catch (\Exception $e) {
            Log::error("LeaderboardReward: 记录发放统计失败: " . json_encode([
                'error' => $e->getMessage(),
                'type' => $type,
                'channel_id' => $channelId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            throw $e;
        }
    }
}
