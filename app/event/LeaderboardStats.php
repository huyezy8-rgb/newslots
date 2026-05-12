<?php
declare(strict_types=1);

namespace app\event;

use app\common\service\LeaderboardService;
use think\facade\Log;

class LeaderboardStats
{
    /**
     * 处理排行榜统计事件
     * @param array $data 事件数据 ['amount' => float, 'user_id' => int]
     */
    public function handle(array $data): void
    {
        try {
            $amount = $data['amount'] ?? 0;
            $userId = $data['user_id'] ?? 0;
            
            if ($amount <= 0 || $userId <= 0) {
                Log::warning('LeaderboardStats: 无效的参数: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return;
            }

            // 获取用户渠道ID
            $userInfo = \think\facade\Db::name('account')->where('id', $userId)->field('channel_id')->find();
            $channelId = $userInfo['channel_id'] ?? null;

            Log::info('LeaderboardStats: 开始处理排行榜统计: ' . json_encode([
                'user_id' => $userId,
                'amount' => $amount,
                'channel_id' => $channelId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $leaderboardService = new LeaderboardService();
            $leaderboardService->updateUserStats(intval($userId), $amount, $channelId);

            Log::info('LeaderboardStats: 排行榜统计处理完成: ' . json_encode([
                'user_id' => $userId,
                'amount' => $amount
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        } catch (\Exception $e) {
            Log::error('LeaderboardStats: 处理排行榜统计失败: ' . json_encode([
                'error' => $e->getMessage(),
                'data' => $data
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
} 