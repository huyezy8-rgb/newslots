<?php

namespace app\event;

use app\common\model\Account;
use app\common\service\MessageService;
use think\facade\Db;
use think\facade\Log;

class GameVip
{
    protected ?MessageService $messageService = null;
    protected string $rewardTable = 'activity_gamevip_user'; // 奖励记录表

    /**
     * 获取消息服务实例
     */
    protected function getMessageService(): MessageService
    {
        return $this->messageService ??= new MessageService();
    }

    /**
     * 处理VIP游戏投注事件
     */
    public function handle(array $params): void
    {
        try {
            $userId = (int)$params['user_id'];
            $gameId = $params['game_id'];
            $amount = abs(floatval($params['amount']));

            Log::info("GameVip event triggered", [
                'user_id' => $userId,
                'game_id' => $gameId,
                'amount' => $amount
            ]);

            // 1. 验证用户有效性
            $user = $this->validateUser($userId);
            if (!$user) {
                return;
            }

            // 2. 获取并验证活动配置
            $config = $this->getActivityConfig();
            if (!$config) {
                return;
            }

            // 3. 检查游戏参与资格
                if (!$this->isGameEligible($gameId, $config['game_id_list'])) {
                Log::info("Game not eligible for VIP activity", ['game_id' => $gameId]);
                return;
            }

            // 4. 更新用户累计投注
            $this->updateUserBetSum($userId, $amount);

            // 5. 检查并发放奖励
            $this->checkAndSendReward($userId, $user['channel_id'] ?? 0, $config['reward_list']);
        } catch (\Throwable $e) {
            Log::error("GameVip event error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 验证用户有效性
     */
    private function validateUser(int $userId): ?array
    {
        $user = Account::find($userId);
        if (!$user) {
            Log::error("User not found", ['user_id' => $userId]);
            return null;
        }
        return $user->toArray();
    }

    /**
     * 获取活动配置
     */
    private function getActivityConfig(): ?array
    {
        $config = get_sys_config(group: 'game_vip_375');

        if (empty($config['activity_375_reward_list'])) {
            Log::error("VIP activity config missing");
            return null;
        }

        // 按投注要求从低到高排序，确保先检查低档位奖励
        usort($config['activity_375_reward_list'], function ($a, $b) {
            return $a['total_bet'] <=> $b['total_bet'];
        });

        // 直接查数据库，获取所有 fs=1 的 game_id
        $game_id_list = \app\common\model\GameLists::where('status', 1)->where('fs', 1)->column('game_id');

        return [
            'reward_list' => $config['activity_375_reward_list'],
            'game_id_list' => $game_id_list
        ];
    }

    /**
     * 检查游戏是否参与活动
     */
    private function isGameEligible(string $gameId, array $eligibleGames): bool
    {
        return in_array($gameId, $eligibleGames);
    }

    /**
     * 更新用户累计投注金额
     */
    private function updateUserBetSum(int $userId, float $amount): void
    {
        Db::name('account')
            ->where('id', $userId)
            ->inc('game_vip_bet_sum', $amount)
            ->update(['update_time' => time()]);

        Log::info("User VIP bet sum updated", [
            'user_id' => $userId,
            'amount' => $amount
        ]);
    }

    /**
     * 检查并发放所有符合条件的奖励
     */
    private function checkAndSendReward(int $userId, int $channelId, array $rewardList): void
    {
        $totalBet = Db::name('account')
            ->where('id', $userId)
            ->value('game_vip_bet_sum');

        if ($totalBet === null) {
            return;
        }

        // 获取已发放的奖励档位
        $rewardedLevels = Db::name($this->rewardTable)
            ->where('user_id', $userId)
            ->column('reward_level');

        // 检查所有奖励档位
        foreach ($rewardList as $reward) {
            // 检查是否已达到投注要求且未发放过该档位奖励
            if ($totalBet >= $reward['total_bet'] && !in_array($reward['total_bet'], $rewardedLevels)) {
                $this->sendReward($userId, $channelId, $reward);
            }
        }
    }

    /**
     * 发送奖励并记录
     */
    private function sendReward(int $userId, int $channelId, array $reward): void
    {
        $startTime = strtotime('tomorrow'); // 第二天0点

        // 发送奖励消息
        $this->getMessageService()->send([
            'user_id'     => $userId,
            'channel_id'  => $channelId,
            'type'        => 'gift',
            'title'       => "VIP Game Rebate", // VIP游戏返利
            'content'     => sprintf("Congratulations! Your betting has reached $%s, you've earned a $%s bonus!",
                number_format($reward['total_bet']),
                number_format($reward['bonus'], 2)), // 您下注已达到X，奖励$X
            'amount'      => $reward['bonus'],
            'wallet_type' => 1,
            'start_time'  => $startTime,
            'event_name'  => 'game_vip_375',
        ]);

        // 记录已发放奖励
        Db::name($this->rewardTable)->insert([
            'user_id'      => $userId,
            'channel_id'   => $channelId,
            'reward_level'  => $reward['total_bet'],
            'reward_amount' => $reward['bonus'],
            'total_bet'     => Db::name('account')
                ->where('id', $userId)
                ->value('game_vip_bet_sum'),
            'create_time'  => time(),
            'update_time'  => time()
        ]);

        Log::info("VIP reward sent and recorded", [
            'user_id' => $userId,
            'reward_level' => $reward['total_bet'],
            'reward_amount' => $reward['bonus']
        ]);
    }
}