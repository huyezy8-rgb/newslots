<?php

namespace app\common\service;

use think\facade\Db;
use think\facade\Log;
use think\Exception;
use app\common\service\MessageService;
use app\common\service\AccountService;

class MemberLevelService
{
    protected ?MessageService $messageService = null;
    protected ?AccountService $accountService = null;

    /**
     * 获取消息服务实例
     */
    protected function getMessageService(): MessageService
    {
        return $this->messageService ??= new MessageService();
    }

    /**
     * 获取账户服务实例
     */
    protected function getAccountService(): AccountService
    {
        return $this->accountService ??= new AccountService();
    }

    /**
     * 升级指定用户等级
     * @param int $userId 用户ID
     * @return array 返回升级结果
     */
    public function upgradeByUserId(int $userId): array
    {
        // 获取用户信息
        $user = Db::name('account')
            ->where('id', $userId)
            ->field('id, vip, sum_recharge, channel_id')
            ->find();

        if (!$user) {
            throw new Exception(__('service.user_not_found'));
        }

        // 获取所有等级配置，按 level 正序（从低到高）
        $levels = Db::name('member_level_config')
            ->order('level', 'asc')
            ->select()
            ->toArray();

        if (empty($levels)) {
            throw new Exception(__('service.no_member_levels_configured'));
        }

        $upgradedLevels = [];

        // 开始事务
        Db::startTrans();
        try {
            // 逐级检查并升级
            foreach ($levels as $level) {
                // 检查是否满足该等级条件且当前等级低于该等级
                if (
                    bccomp((string)$user['sum_recharge'], (string)$level['recharge_requirement'], 2) >= 0 &&
                    $user['vip'] < $level['level']
                ) {
                    // 更新用户等级
                    Db::name('account')
                        ->where('id', $userId)
                        ->update(['vip' => $level['level']]);

                    // 更新用户当前等级，用于下次循环判断
                    $user['vip'] = $level['level'];

                    // 记录升级的等级
                    $upgradedLevels[] = $level;

                    // 创建升级奖励记录（不直接发放金额）
                    $this->createUpgradeReward($userId, $level);

                    // 发送升级通知站内信（不包含金额）
                    $this->sendUpgradeNotification($user, $level);
                }
            }

            // 提交事务
            Db::commit();

            if (empty($upgradedLevels)) {
                throw new Exception(__('service.no_level_change_needed'));
            }

            $levelNames = array_column($upgradedLevels, 'name');
            $message = __('service.user_upgraded_to_levels', ['levels' => implode(', ', $levelNames)]);
            $message .= "，升级奖励已生成，请前往VIP页面领取";

            return [
                'status' => true, 
                'msg' => $message,
                'upgraded_levels' => $upgradedLevels
            ];

        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::error("会员升级失败：" . json_encode([
                'user_id' => $userId,
                'upgraded_levels' => $upgradedLevels ?? [],
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            throw $e;
        }
    }

    /**
     * 发送升级通知站内信（不包含金额赠送）
     * @param array $user 用户信息
     * @param array $level 等级信息
     */
    protected function sendUpgradeNotification(array $user, array $level): void
    {
        try {
            $title = "VIP {$level['level']}";
            
            $content = "Congratulations on becoming a VIP! You've unlocked the withdrawal feature with a minimum withdrawal amount of \${$level['withdraw_limit']}. Enjoy exclusive perks, including daily login bonuses, betting rewards, and more. Please visit the VIP page to claim your upgrade rewards. Good luck!";

            // 发送站内信（系统通知类型，不包含金额）
            $this->getMessageService()->send([
                'user_id' => $user['id'],
                'channel_id' => $user['channel_id'] ?? 0,
                'type' => 'system',
                'title' => $title,
                'content' => $content,
                'event_name' => 'member_upgrade',
                'start_time' => time(),
            ]);

            Log::info("会员升级站内信发送成功: " . json_encode([
                'user_id' => $user['id'],
                'level' => $level['name']
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::error("会员升级站内信发送失败：" . json_encode([
                'user_id' => $user['id'],
                'level' => $level['name'],
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * 创建升级奖励记录
     * @param int $userId 用户ID
     * @param array $level 等级信息
     * @throws Exception 如果用户已存在奖励记录
     */
    protected function createUpgradeReward(int $userId, array $level): void
    {
        $currentTime = time();
        $previousLevel = null;
        $rewardId = null;
        
        // 检查是否已存在该用户的奖励记录
        $existingReward = Db::name('member_level_rewards')
            ->where('user_id', $userId)
            ->find();

        if ($existingReward) {
            // 如果已存在记录，检查当前等级是否已有升级奖励
            if ($existingReward['level'] >= $level['level']) {
                throw new Exception("用户已存在等级 {$existingReward['level']} 的奖励记录，无法创建等级 {$level['level']} 的升级奖励");
            }
            
            // 记录升级前等级
            $previousLevel = $existingReward['level'];
            $rewardId = $existingReward['id'];
            
            // 计算累加的升级奖励（如果当前升级奖励已领取，则累加新奖励；否则累加到未领取的奖励中）
            $currentUpgradeAmount = $existingReward['upgrade_reward_amount'] ?? 0;
            $newUpgradeAmount = $level['upgrade_reward'] ?? 0;
            
            // 如果之前的升级奖励已经领取，则将新奖励作为新的可领取奖励
            // 如果之前的升级奖励未领取，则累加到现有奖励中
            if (!empty($existingReward['upgrade_reward_claimed_time'])) {
                // 之前奖励已领取，新奖励单独计算
                $finalUpgradeAmount = $newUpgradeAmount;
                $upgradeIssuedTime = ($newUpgradeAmount > 0) ? $currentTime : $existingReward['upgrade_reward_issued_time'];
                $upgradeClaimedTime = null; // 重置领取时间
                $upgradeStatus = ($newUpgradeAmount > 0) ? 0 : 1; // 有奖励时为待领取，无奖励时为已领取
            } else {
                // 之前奖励未领取，累加奖励
                $finalUpgradeAmount = $currentUpgradeAmount + $newUpgradeAmount;
                $upgradeIssuedTime = ($finalUpgradeAmount > 0) ? 
                    ($existingReward['upgrade_reward_issued_time'] ?: $currentTime) : null;
                $upgradeClaimedTime = $existingReward['upgrade_reward_claimed_time']; // 保持原状态
                $upgradeStatus = ($finalUpgradeAmount > 0) ? 0 : 1; // 有奖励时为待领取，无奖励时为已领取
            }
            
            // 更新到新等级的奖励信息（不预设周奖励和月奖励金额）
            Db::name('member_level_rewards')
                ->where('user_id', $userId)
                ->update([
                    'level' => $level['level'],
                    'upgrade_reward_amount' => $finalUpgradeAmount,
                    'upgrade_reward_status' => $upgradeStatus,
                    'upgrade_reward_issued_time' => $upgradeIssuedTime,
                    'upgrade_reward_claimed_time' => $upgradeClaimedTime,
                    'update_time' => $currentTime
                ]);
        } else {
            // 创建新的奖励记录（首次升级，前等级为null）
            $upgradeAmount = $level['upgrade_reward'] ?? 0;
            $rewardId = Db::name('member_level_rewards')->insertGetId([
                'user_id' => $userId,
                'level' => $level['level'],
                'upgrade_reward_amount' => $upgradeAmount,
                'upgrade_reward_status' => ($upgradeAmount > 0) ? 0 : 1, // 有奖励金额时状态为0(待领取)，无奖励时为1(已领取)
                'upgrade_reward_issued_time' => ($upgradeAmount > 0) ? $currentTime : null,
                'weekly_reward_amount' => 0, // 初始为0，只有在发放时才设置金额
                'weekly_reward_status' => 1, // 初始为已领取状态
                'monthly_reward_amount' => 0, // 初始为0，只有在发放时才设置金额
                'monthly_reward_status' => 1, // 初始为已领取状态
                'create_time' => $currentTime,
                'update_time' => $currentTime
            ]);
        }

        // 如果有升级奖励金额，添加发放记录到日志表
        if (!empty($level['upgrade_reward']) && $level['upgrade_reward'] > 0) {
            Db::name('member_reward_logs')->insert([
                'user_id' => $userId,
                'reward_id' => $rewardId,
                'level' => $level['level'],
                'previous_level' => $previousLevel,
                'reward_type' => 'upgrade',
                'reward_amount' => $level['upgrade_reward'],
                'create_time' => $currentTime
            ]);
        }

        Log::info("升级奖励记录创建成功: " . json_encode([
            'user_id' => $userId,
            'previous_level' => $previousLevel,
            'current_level' => $level['level'],
            'reward_amount' => $level['upgrade_reward'] ?? 0
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 获取所有会员等级信息
     * @return array 返回所有等级信息
     */
    public static function getAllLevels(): array
    {
        try {
            $levels = Db::name('member_level_config')
                ->order('level', 'asc')
                ->select()
                ->toArray();

            if (empty($levels)) {
                return [
                    'status' => false,
                    'msg' => __('service.no_member_levels_configured'),
                    'data' => []
                ];
            }

            return [
                'status' => true,
                'msg' => __('service.get_levels_success'),
                'data' => $levels,
                'total' => count($levels)
            ];

        } catch (\Exception $e) {
            Log::error("获取会员等级信息失败：" . $e->getMessage());
            return [
                'status' => false,
                'msg' => __('service.get_levels_failed'),
                'data' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取用户可领取的奖励列表
     * @param int $userId 用户ID
     * @return array 返回奖励列表
     */
    public function getUserRewards(int $userId): array
    {
        try {
            // 获取用户的奖励记录（每个用户只有一条记录）
            $rewardRecord = Db::name('member_level_rewards')
                ->alias('r')
                ->leftJoin('member_level_config c', 'r.level = c.level')
                ->where('r.user_id', $userId)
                ->field('r.*, c.name as level_name')
                ->find();

            $claimableRewards = [];
            
            if ($rewardRecord) {
                // 检查升级奖励
                if ($rewardRecord['upgrade_reward_amount'] > 0 
                    && $rewardRecord['upgrade_reward_issued_time'] 
                    && !$rewardRecord['upgrade_reward_claimed_time']) {
                    $claimableRewards[] = [
                        'id' => $rewardRecord['id'],
                        'type' => 'upgrade',
                        'level' => $rewardRecord['level'],
                        'level_name' => $rewardRecord['level_name'],
                        'amount' => $rewardRecord['upgrade_reward_amount'],
                        'name' => '升级奖励',
                        'issued_time' => $rewardRecord['upgrade_reward_issued_time'],
                        'expire_time' => null // 升级奖励永不过期
                    ];
                }

                // 检查周奖励
                if ($rewardRecord['weekly_reward_amount'] > 0 
                    && $rewardRecord['weekly_reward_issued_time'] 
                    && !$rewardRecord['weekly_reward_claimed_time']) {
                    $expireTime = $rewardRecord['weekly_reward_issued_time'] + (7 * 24 * 3600);
                    if (time() < $expireTime) { // 未过期
                        $claimableRewards[] = [
                            'id' => $rewardRecord['id'],
                            'type' => 'weekly',
                            'level' => $rewardRecord['level'],
                            'level_name' => $rewardRecord['level_name'],
                            'amount' => $rewardRecord['weekly_reward_amount'],
                            'name' => '周奖励',
                            'issued_time' => $rewardRecord['weekly_reward_issued_time'],
                            'expire_time' => $expireTime
                        ];
                    }
                }

                // 检查月奖励
                if ($rewardRecord['monthly_reward_amount'] > 0 
                    && $rewardRecord['monthly_reward_issued_time'] 
                    && !$rewardRecord['monthly_reward_claimed_time']) {
                    $expireTime = $rewardRecord['monthly_reward_issued_time'] + (30 * 24 * 3600);
                    if (time() < $expireTime) { // 未过期
                        $claimableRewards[] = [
                            'id' => $rewardRecord['id'],
                            'type' => 'monthly',
                            'level' => $rewardRecord['level'],
                            'level_name' => $rewardRecord['level_name'],
                            'amount' => $rewardRecord['monthly_reward_amount'],
                            'name' => '月奖励',
                            'issued_time' => $rewardRecord['monthly_reward_issued_time'],
                            'expire_time' => $expireTime
                        ];
                    }
                }
            }

            return [
                'status' => true,
                'msg' => 'success',
                'data' => $claimableRewards,
                'total' => count($claimableRewards)
            ];

        } catch (\Exception $e) {
            Log::error("获取用户奖励列表失败：" . json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'status' => false,
                'msg' => 'failed',
                'data' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 领取奖励
     * @param int $userId 用户ID
     * @param int $rewardId 奖励记录ID
     * @param string $rewardType 奖励类型 (upgrade/weekly/monthly)
     * @return array 返回领取结果
     */
    public function claimReward(int $userId, string $rewardType): array
    {
        // 开始事务
        Db::startTrans();
        try {
            // 获取奖励记录
            $rewardRecord = Db::name('member_level_rewards')
                ->where('user_id', $userId)
                ->find();

            if (!$rewardRecord) {
                throw new Exception(__('memberlevel.reward_record_not_exists'));
            }

            $currentTime = time();
            $rewardAmount = 0;
            $updateData = ['update_time' => $currentTime];

            // 根据奖励类型处理
            switch ($rewardType) {
                case 'upgrade':
                    if ($rewardRecord['upgrade_reward_status'] != 0 || $rewardRecord['upgrade_reward_amount'] <= 0) {
                        throw new Exception(__('memberlevel.upgrade_reward_not_claimable'));
                    }
                    $rewardAmount = $rewardRecord['upgrade_reward_amount'];
                    $updateData['upgrade_reward_amount'] = 0; // 领取后清0
                    $updateData['upgrade_reward_status'] = 1; // 设置为已领取状态
                    $updateData['upgrade_reward_claimed_time'] = $currentTime;
                    break;

                case 'weekly':
                    if ($rewardRecord['weekly_reward_status'] != 0 || $rewardRecord['weekly_reward_amount'] <= 0) {
                        throw new Exception(__('memberlevel.weekly_reward_not_claimable'));
                    }
                    $rewardAmount = $rewardRecord['weekly_reward_amount'];
                    $updateData['weekly_reward_amount'] = 0; // 领取后清0
                    $updateData['weekly_reward_status'] = 1; // 设置为已领取状态
                    $updateData['weekly_reward_claimed_time'] = $currentTime;
                    break;

                case 'monthly':
                    if ($rewardRecord['monthly_reward_status'] != 0 || $rewardRecord['monthly_reward_amount'] <= 0) {
                        throw new Exception(__('memberlevel.monthly_reward_not_claimable'));
                    }
                    $rewardAmount = $rewardRecord['monthly_reward_amount'];
                    $updateData['monthly_reward_amount'] = 0; // 领取后清0
                    $updateData['monthly_reward_status'] = 1; // 设置为已领取状态
                    $updateData['monthly_reward_claimed_time'] = $currentTime;
                    break;

                default:
                    throw new Exception(__('memberlevel.invalid_reward_type'));
            }

            // 发放奖励到用户钱包
            $accountService = $this->getAccountService();
            $walletType = \app\api\enum\CoinLog::getWalletType('recharge_wallet');
            
            // 根据奖励类型设置不同的logTypeId和说明文字
            $rewardConfig = [
                'upgrade' => [
                    'logTypeId' => \app\api\enum\CoinLog::MemberUpgrade,
                    'note' => __('memberlevel.upgrade_reward_note')
                ],
                'weekly' => [
                    'logTypeId' => \app\api\enum\CoinLog::MemberWeeklyReward,
                    'note' => __('memberlevel.weekly_reward_note')
                ],
                'monthly' => [
                    'logTypeId' => \app\api\enum\CoinLog::MemberMonthlyReward,
                    'note' => __('memberlevel.monthly_reward_note')
                ]
            ];
            
            $config = $rewardConfig[$rewardType] ?? [
                'logTypeId' => \app\api\enum\CoinLog::MemberUpgrade,
                'note' => str_replace('{type}', $rewardType, __('memberlevel.member_level_reward_note'))
            ];
            
            $result = $accountService->increaseBalance(
                userId: $userId,
                amount: $rewardAmount,
                walletType: $walletType,
                logTypeId: $config['logTypeId'],
                note: $config['note']
            );


            // 更新奖励记录
            Db::name('member_level_rewards')
                ->where('user_id', $userId)
                ->update($updateData);

            Db::commit();

            return [
                'status' => true,
                'msg' => '奖励领取成功',
                'reward_amount' => $rewardAmount
            ];

        } catch (\Exception $e) {
            Db::rollback();
            Log::error("奖励领取失败：" . json_encode([
                'user_id' => $userId,
                'reward_type' => $rewardType,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'status' => false,
                'msg' => $e->getMessage()
            ];
        }
    }


    /**
     * 创建周奖励（定时任务调用）
     * @return array 返回创建结果
     */
    public function createWeeklyRewards(): array
    {
        try {
            $currentTime = time();
            $createdCount = 0;

            // 获取所有有周奖励的等级配置
            $levels = Db::name('member_level_config')
                ->where('weekly_reward', '>', 0)
                ->select()
                ->toArray();

            if (empty($levels)) {
                return ['status' => true, 'msg' => '没有配置周奖励的等级', 'created_count' => 0];
            }

            // 获取所有符合条件的用户（当前等级有周奖励）
            foreach ($levels as $level) {
                $users = Db::name('account')
                    ->where('vip', $level['level'])
                    ->field('id, vip')
                    ->select()
                    ->toArray();

                foreach ($users as $user) {
                    // 检查是否已存在该用户的奖励记录
                    $rewardRecord = Db::name('member_level_rewards')
                        ->where('user_id', $user['id'])
                        ->find();

                    if ($rewardRecord) {
                        // 检查用户当前等级是否匹配
                        if ($rewardRecord['level'] == $level['level']) {
                            // 检查本周是否已经发放过周奖励
                            $weekStart = strtotime('last Monday', $currentTime);
                            if (!$rewardRecord['weekly_reward_issued_time']
                                || $rewardRecord['weekly_reward_issued_time'] < $weekStart) {
                                // 更新周奖励
                                Db::name('member_level_rewards')
                                    ->where('id', $rewardRecord['id'])
                                    ->update([
                                        'weekly_reward_amount' => $level['weekly_reward'],
                                        'weekly_reward_status' => 0, // 发放时设置为待领取状态
                                        'weekly_reward_issued_time' => $currentTime,
                                        'weekly_reward_claimed_time' => null, // 重置领取时间
                                        'update_time' => $currentTime
                                    ]);
                                
                                // 添加发放记录到日志表
                                Db::name('member_reward_logs')->insert([
                                    'user_id' => $user['id'],
                                    'reward_id' => $rewardRecord['id'],
                                    'level' => $level['level'],
                                    'previous_level' => null,
                                    'reward_type' => 'weekly',
                                    'reward_amount' => $level['weekly_reward'],
                                    'create_time' => $currentTime
                                ]);
                                
                                $createdCount++;
                            }
                        }
                    } else {
                        // 创建新的奖励记录（用户首次达到该等级）
                        $rewardId = Db::name('member_level_rewards')->insertGetId([
                            'user_id' => $user['id'],
                            'level' => $level['level'],
                            'upgrade_reward_amount' => 0,
                            'upgrade_reward_status' => 1, // 无升级奖励时为已领取状态
                            'weekly_reward_amount' => $level['weekly_reward'],
                            'weekly_reward_status' => 0, // 发放时设置为待领取状态
                            'weekly_reward_issued_time' => $currentTime,
                            'monthly_reward_amount' => 0,
                            'monthly_reward_status' => 1, // 无月奖励时为已领取状态
                            'create_time' => $currentTime,
                            'update_time' => $currentTime
                        ]);
                        
                        // 添加发放记录到日志表
                        Db::name('member_reward_logs')->insert([
                            'user_id' => $user['id'],
                            'reward_id' => $rewardId,
                            'level' => $level['level'],
                            'previous_level' => null,
                            'reward_type' => 'weekly',
                            'reward_amount' => $level['weekly_reward'],
                            'create_time' => $currentTime
                        ]);
                        
                        $createdCount++;
                    }
                }
            }

            return [
                'status' => true,
                'msg' => '周奖励创建成功',
                'created_count' => $createdCount
            ];

        } catch (\Exception $e) {
            Log::error("创建周奖励失败：" . $e->getMessage());
            return [
                'status' => false,
                'msg' => $e->getMessage(),
                'created_count' => 0
            ];
        }
    }

    /**
     * 创建月奖励（定时任务调用）
     * @return array 返回创建结果
     */
    public function createMonthlyRewards(): array
    {
        try {
            $currentTime = time();
            $createdCount = 0;

            // 获取所有有月奖励的等级配置
            $levels = Db::name('member_level_config')
                ->where('monthly_reward', '>', 0)
                ->select()
                ->toArray();

            if (empty($levels)) {
                return ['status' => true, 'msg' => '没有配置月奖励的等级', 'created_count' => 0];
            }

            // 获取所有符合条件的用户（当前等级有月奖励）
            foreach ($levels as $level) {
                $users = Db::name('account')
                    ->where('vip', $level['level'])
                    ->field('id, vip')
                    ->select()
                    ->toArray();

                foreach ($users as $user) {
                    // 检查是否已存在该用户的奖励记录
                    $rewardRecord = Db::name('member_level_rewards')
                        ->where('user_id', $user['id'])
                        ->find();

                    if ($rewardRecord) {
                        // 检查用户当前等级是否匹配
                        if ($rewardRecord['level'] == $level['level']) {
                            // 检查本月是否已经发放过月奖励
                            $monthStart = strtotime('first day of this month', $currentTime);
                            if (!$rewardRecord['monthly_reward_issued_time'] 
                                || $rewardRecord['monthly_reward_issued_time'] < $monthStart) {
                                // 更新月奖励
                                Db::name('member_level_rewards')
                                    ->where('id', $rewardRecord['id'])
                                    ->update([
                                        'monthly_reward_amount' => $level['monthly_reward'],
                                        'monthly_reward_status' => 0, // 发放时设置为待领取状态
                                        'monthly_reward_issued_time' => $currentTime,
                                        'monthly_reward_claimed_time' => null, // 重置领取时间
                                        'update_time' => $currentTime
                                    ]);
                                
                                // 添加发放记录到日志表
                                Db::name('member_reward_logs')->insert([
                                    'user_id' => $user['id'],
                                    'reward_id' => $rewardRecord['id'],
                                    'level' => $level['level'],
                                    'previous_level' => null,
                                    'reward_type' => 'monthly',
                                    'reward_amount' => $level['monthly_reward'],
                                    'create_time' => $currentTime
                                ]);
                                
                                $createdCount++;
                            }
                        }
                    } else {
                        // 创建新的奖励记录（用户首次达到该等级）
                        $rewardId = Db::name('member_level_rewards')->insertGetId([
                            'user_id' => $user['id'],
                            'level' => $level['level'],
                            'upgrade_reward_amount' => 0,
                            'upgrade_reward_status' => 1, // 无升级奖励时为已领取状态
                            'weekly_reward_amount' => 0, // 初始为0，只有在发放周奖励时才设置
                            'weekly_reward_status' => 1, // 无周奖励时为已领取状态
                            'monthly_reward_amount' => $level['monthly_reward'],
                            'monthly_reward_status' => 0, // 发放时设置为待领取状态
                            'monthly_reward_issued_time' => $currentTime,
                            'create_time' => $currentTime,
                            'update_time' => $currentTime
                        ]);
                        
                        // 添加发放记录到日志表
                        Db::name('member_reward_logs')->insert([
                            'user_id' => $user['id'],
                            'reward_id' => $rewardId,
                            'level' => $level['level'],
                            'previous_level' => null,
                            'reward_type' => 'monthly',
                            'reward_amount' => $level['monthly_reward'],
                            'create_time' => $currentTime
                        ]);
                        
                        $createdCount++;
                    }
                }
            }

            return [
                'status' => true,
                'msg' => '月奖励创建成功',
                'created_count' => $createdCount
            ];

        } catch (\Exception $e) {
            Log::error("创建月奖励失败：" . $e->getMessage());
            return [
                'status' => false,
                'msg' => $e->getMessage(),
                'created_count' => 0
            ];
        }
    }

    /**
     * 获取用户当前等级详细信息
     * @param int $userId 用户ID
     * @return array 返回用户等级信息
     */
    public function getUserLevelInfo(int $userId): array
    {
        try {
            // 获取用户信息
            $user = Db::name('account')
                ->where('id', $userId)
                ->field('id, vip, sum_recharge, channel_id')
                ->find();

            if (!$user) {
                return [
                    'status' => false,
                    'msg' => '用户不存在',
                    'data' => []
                ];
            }

            // 获取当前等级配置
            $currentLevel = Db::name('member_level_config')
                ->where('level', $user['vip'])
                ->find();

            if (!$currentLevel) {
                return [
                    'status' => false,
                    'msg' => '当前等级配置不存在',
                    'data' => []
                ];
            }

            // 获取用户奖励记录
            $rewardRecord = Db::name('member_level_rewards')
                ->where('user_id', $userId)
                ->find();

            $data = [
                'user_id' => $user['id'],
                'current_level' => $user['vip'],
                'current_level_name' => $currentLevel['name'] ?: 'VIP' . $currentLevel['level'],
                'current_recharge' => $user['sum_recharge'],
                'level_config' => [
                    'id' => $currentLevel['id'],
                    'name' => $currentLevel['name'],
                    'level' => $currentLevel['level'],
                    'recharge_requirement' => $currentLevel['recharge_requirement'],
                    'withdraw_limit' => $currentLevel['withdraw_limit'],
                    'daily_withdraw_times' => $currentLevel['daily_withdraw_times'],
                    'withdraw_fee_percent' => $currentLevel['withdraw_fee_percent'],
                    'bonus_percent' => $currentLevel['bonus_percent'],
                    'upgrade_reward' => $currentLevel['upgrade_reward'] ?? 0,
                    'weekly_reward' => $currentLevel['weekly_reward'] ?? 0,
                    'monthly_reward' => $currentLevel['monthly_reward'] ?? 0,
                ],
                'rewards_status' => null
            ];

            // 如果有奖励记录，添加奖励状态信息
            if ($rewardRecord) {
                $data['rewards_status'] = [
                    'upgrade_reward_amount' => $rewardRecord['upgrade_reward_amount'],
                    'upgrade_reward_issued' => !empty($rewardRecord['upgrade_reward_issued_time']),
                    'upgrade_reward_claimed' => !empty($rewardRecord['upgrade_reward_claimed_time']),
                    'weekly_reward_amount' => $rewardRecord['weekly_reward_amount'],
                    'weekly_reward_issued' => !empty($rewardRecord['weekly_reward_issued_time']),
                    'weekly_reward_claimed' => !empty($rewardRecord['weekly_reward_claimed_time']),
                    'monthly_reward_amount' => $rewardRecord['monthly_reward_amount'],
                    'monthly_reward_issued' => !empty($rewardRecord['monthly_reward_issued_time']),
                    'monthly_reward_claimed' => !empty($rewardRecord['monthly_reward_claimed_time']),
                ];
            }

            return [
                'status' => true,
                'msg' => '获取用户等级信息成功',
                'data' => $data
            ];

        } catch (\Exception $e) {
            Log::error("获取用户等级信息失败：" . json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'status' => false,
                'msg' => '获取用户等级信息失败',
                'data' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取用户升级进度
     * @param int $userId 用户ID
     * @return array 返回升级进度信息
     */
    public function getUserUpgradeProgress(int $userId): array
    {
        try {
            // 获取用户信息
            $user = Db::name('account')
                ->where('id', $userId)
                ->field('id, vip, sum_recharge')
                ->find();

            if (!$user) {
                return [
                    'status' => false,
                    'msg' => '用户不存在',
                    'data' => []
                ];
            }

            // 获取当前等级配置
            $currentLevel = Db::name('member_level_config')
                ->where('level', $user['vip'])
                ->find();

            // 获取下一等级配置
            $nextLevel = Db::name('member_level_config')
                ->where('level', '>', $user['vip'])
                ->order('level', 'asc')
                ->find();

            $data = [
                'user_id' => $user['id'],
                'current_level' => $user['vip'],
                'current_level_name' => $currentLevel ? ($currentLevel['name'] ?: 'VIP' . $currentLevel['level']) : 'VIP0',
                'current_recharge' => $user['sum_recharge'],
                'next_level' => null,
                'next_level_name' => null,
                'next_level_requirement' => null,
                'progress_amount' => 0,
                'progress_percentage' => 0,
                'is_max_level' => false,
                'next_level_config' => null
            ];

            if ($nextLevel) {
                $requiredAmount = $nextLevel['recharge_requirement'];
                $currentAmount = $user['sum_recharge'];
                $progressAmount = max(0, $requiredAmount - $currentAmount);
                $progressPercentage = $requiredAmount > 0 ? min(100, ($currentAmount / $requiredAmount) * 100) : 100;

                $data['next_level'] = $nextLevel['level'];
                $data['next_level_name'] = $nextLevel['name'] ?: 'VIP' . $nextLevel['level'];
                $data['next_level_requirement'] = $requiredAmount;
                $data['progress_amount'] = $progressAmount;
                $data['progress_percentage'] = round($progressPercentage, 2);
                $data['next_level_config'] = [
                    'id' => $nextLevel['id'],
                    'name' => $nextLevel['name'],
                    'level' => $nextLevel['level'],
                    'recharge_requirement' => $nextLevel['recharge_requirement'],
                    'withdraw_limit' => $nextLevel['withdraw_limit'],
                    'daily_withdraw_times' => $nextLevel['daily_withdraw_times'],
                    'withdraw_fee_percent' => $nextLevel['withdraw_fee_percent'],
                    'bonus_percent' => $nextLevel['bonus_percent'],
                    'upgrade_reward' => $nextLevel['upgrade_reward'] ?? 0,
                    'weekly_reward' => $nextLevel['weekly_reward'] ?? 0,
                    'monthly_reward' => $nextLevel['monthly_reward'] ?? 0,
                ];
            } else {
                $data['is_max_level'] = true;
                $data['progress_percentage'] = 100;
            }

            return [
                'status' => true,
                'msg' => '获取升级进度成功',
                'data' => $data
            ];

        } catch (\Exception $e) {
            Log::error("获取升级进度失败：" . json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'status' => false,
                'msg' => '获取升级进度失败',
                'data' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取用户奖励发放记录
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string $rewardType 奖励类型（可选筛选）
     * @return array 返回奖励发放记录
     */
    public function getUserRewardLogs(int $userId, int $page = 1, int $limit = 20, string $rewardType = ''): array
    {
        try {
            $where = ['user_id' => $userId];
            
            // 如果指定了奖励类型，添加筛选条件
            if (!empty($rewardType) && in_array($rewardType, ['upgrade', 'weekly', 'monthly'])) {
                $where['reward_type'] = $rewardType;
            }

            // 获取总数
            $total = Db::name('member_reward_logs')
                ->where($where)
                ->count();

            // 获取分页数据
            $logs = Db::name('member_reward_logs')
                ->alias('l')
                ->leftJoin('member_level_config c', 'l.level = c.level')
                ->where($where)
                ->field('l.*, c.name as level_name')
                ->order('l.create_time', 'desc')
                ->limit(($page - 1) * $limit, $limit)
                ->select()
                ->toArray();

            // 格式化数据
            $formattedLogs = [];
            foreach ($logs as $log) {
                $formattedLogs[] = [
                    'id' => $log['id'],
                    'user_id' => $log['user_id'],
                    'reward_id' => $log['reward_id'],
                    'level' => $log['level'],
                    'level_name' => $log['level_name'] ?: 'VIP' . $log['level'],
                    'previous_level' => $log['previous_level'],
                    'reward_type' => $log['reward_type'],
                    'reward_type_text' => $this->getRewardTypeText($log['reward_type']),
                    'reward_amount' => $log['reward_amount'],
                    'create_time' => $log['create_time'],
                    'create_time_text' => date('Y-m-d H:i:s', $log['create_time']),
                    'level_change_text' => $this->getLevelChangeText($log),
                    'description' => $this->getRewardDescription($log)
                ];
            }

            return [
                'status' => true,
                'msg' => '获取奖励记录成功',
                'data' => $formattedLogs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];

        } catch (\Exception $e) {
            Log::error("获取用户奖励记录失败：" . json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'status' => false,
                'msg' => '获取奖励记录失败',
                'data' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取奖励类型文本
     * @param string $rewardType
     * @return string
     */
    private function getRewardTypeText(string $rewardType): string
    {
        $types = [
            'upgrade' => '升级奖励',
            'weekly' => '周奖励',
            'monthly' => '月奖励',
        ];
        return $types[$rewardType] ?? '未知';
    }

    /**
     * 获取等级变化描述
     * @param array $log
     * @return string
     */
    private function getLevelChangeText(array $log): string
    {
        if ($log['reward_type'] === 'upgrade' && $log['previous_level'] !== null) {
            return "V{$log['previous_level']} → V{$log['level']}";
        }
        return "V{$log['level']}";
    }

    /**
     * 获取奖励描述
     * @param array $log
     * @return string
     */
    private function getRewardDescription(array $log): string
    {
        $typeText = $this->getRewardTypeText($log['reward_type']);
        $levelText = $this->getLevelChangeText($log);
        
        if ($log['reward_type'] === 'upgrade' && $log['previous_level'] !== null) {
            return "{$typeText} ({$levelText})";
        }
        
        return "{$typeText} (V{$log['level']})";
    }

    /**
     * 为用户创建基础奖励记录
     * @param int $userId 用户ID
     * @return array 返回创建结果
     */
    public function createUserRewardRecord(int $userId): array
    {
        try {
            // 获取用户信息
            $user = Db::name('account')
                ->where('id', $userId)
                ->field('id, vip')
                ->find();

            if (!$user) {
                return [
                    'status' => false,
                    'msg' => '用户不存在'
                ];
            }

            // 检查是否已存在奖励记录
            $existingReward = Db::name('member_level_rewards')
                ->where('user_id', $userId)
                ->find();

            if ($existingReward) {
                return [
                    'status' => true,
                    'msg' => '用户奖励记录已存在'
                ];
            }

            $currentTime = time();
            Db::name('member_level_rewards')->insert([
                'user_id' => $userId,
                'level' => $user['vip'],
                'upgrade_reward_amount' => 0,
                'upgrade_reward_status' => 1, // 奖励为0时设置为已领取状态
                'weekly_reward_amount' => 0,
                'weekly_reward_status' => 1, // 奖励为0时设置为已领取状态
                'monthly_reward_amount' => 0,
                'monthly_reward_status' => 1, // 奖励为0时设置为已领取状态
                'create_time' => $currentTime,
                'update_time' => $currentTime
            ]);

                        Log::info("为用户创建奖励记录成功: " . json_encode([
                'user_id' => $userId,
                'level' => $user['vip']
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

             return [
                 'status' => true,
                 'msg' => '奖励记录创建成功'
             ];

        } catch (\Exception $e) {
            Log::error("创建用户奖励记录失败：" . json_encode([
                'user_id' => $userId,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'status' => false,
                'msg' => '创建奖励记录失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 一次性获取所有会员等级相关信息（优化版，减少数据库查询）
     * @param int $userId 用户ID
     * @return array 返回所有相关信息
     */
    public function getAllMemberLevelData(int $userId): array
    {
        try {
            $result = [


                'user_level' => null,
                'upgrade_progress' => null,
                'user_rewards' => [],
//                'rewards_total' => 0
                'levels' => [],
                'levels_total' => 0,
            ];

            // 1. 获取所有等级配置 - 简化字段
            $levels = Db::name('member_level_config')
                ->field('id, name, level, recharge_requirement, withdraw_limit, daily_withdraw_times, withdraw_fee_percent, bonus_percent, upgrade_reward, weekly_reward, monthly_reward')
                ->order('level', 'asc')
                ->select()
                ->toArray();

            $result['levels'] = $levels;
            $result['levels_total'] = count($levels);

            // 2. 获取用户基础信息（一次查询）
            $user = Db::name('account')
                ->where('id', $userId)
                ->field('id, vip, sum_recharge, channel_id')
                ->find();

            if (!$user) {
                return [
                    'status' => false,
                    'msg' => '用户不存在',
                    'data' => $result
                ];
            }

            // 3. 获取或创建用户奖励记录
            $rewardRecord = Db::name('member_level_rewards')
                ->where('user_id', $userId)
                ->find();

            if (!$rewardRecord) {
                // 自动创建奖励记录
                $createResult = $this->createUserRewardRecord($userId);
                if ($createResult['status']) {
                    $rewardRecord = Db::name('member_level_rewards')
                        ->where('user_id', $userId)
                        ->find();
                }
            }

            // 4. 构建用户等级信息
            $currentLevel = null;
            foreach ($levels as $level) {
                if ($level['level'] == $user['vip']) {
                    $currentLevel = $level;
                    break;
                }
            }

            if ($currentLevel) {
                $result['user_level'] = [
                    'level' => $user['vip'],
                    'level_name' => $currentLevel['name'] ?: 'VIP' . $currentLevel['level'],
                    'recharge_amount' => $user['sum_recharge'],
                    'withdraw_limit' => $currentLevel['withdraw_limit'],
                    'daily_withdraw_times' => $currentLevel['daily_withdraw_times'],
                    'bonus_percent' => $currentLevel['bonus_percent']
                ];
            }

            // 5. 构建升级进度信息
            $nextLevel = null;
            foreach ($levels as $level) {
                if ($level['level'] > $user['vip']) {
                    $nextLevel = $level;
                    break;
                }
            }

            if ($nextLevel) {
                $requiredAmount = $nextLevel['recharge_requirement'];
                $currentAmount = $user['sum_recharge'];
                $progressAmount = max(0, $requiredAmount - $currentAmount);
                $progressPercentage = $requiredAmount > 0 ? min(100, ($currentAmount / $requiredAmount) * 100) : 100;

                $result['upgrade_progress'] = [
                    'next_level' => $nextLevel['level'],
                    'next_level_name' => $nextLevel['name'] ?: 'VIP' . $nextLevel['level'],
                    'required_amount' => $requiredAmount,
                    'current_amount' => $currentAmount,
                    'need_amount' => $progressAmount,
                    'progress_percentage' => round($progressPercentage, 2),
                    'upgrade_reward' => $nextLevel['upgrade_reward'] ?? 0
                ];
            } else {
                $result['upgrade_progress'] = [
                    'next_level' => null,
                    'next_level_name' => '已达最高等级',
                    'required_amount' => 0,
                    'current_amount' => $user['sum_recharge'],
                    'need_amount' => 0,
                    'progress_percentage' => 100,
                    'upgrade_reward' => 0
                ];
            }

            // 6. 构建用户奖励信息 - 前端友好的对象结构
            $claimableCount = 0;
            
            if ($rewardRecord) {
                // 升级奖励
                $upgradeStatus = $rewardRecord['upgrade_reward_status'] ?? 0;
                $upgradeClaimable = ($upgradeStatus === 0 && $rewardRecord['upgrade_reward_amount'] > 0);
                if ($upgradeClaimable) $claimableCount++;

                // 周奖励
                $weeklyStatus = $rewardRecord['weekly_reward_status'] ?? 0;
                $weeklyClaimable = ($weeklyStatus === 0 && $rewardRecord['weekly_reward_amount'] > 0);
                if ($weeklyClaimable) $claimableCount++;

                // 月奖励
                $monthlyStatus = $rewardRecord['monthly_reward_status'] ?? 0;
                $monthlyClaimable = ($monthlyStatus === 0 && $rewardRecord['monthly_reward_amount'] > 0);
                if ($monthlyClaimable) $claimableCount++;

                // 获取即将领取的奖励信息
                $nextUpgradeReward = 0;
                $nextWeeklyReward = 0;
                $nextMonthlyReward = 0;
                
                // 找到下一等级的升级奖励
                foreach ($levels as $level) {
                    if ($level['level'] > $user['vip']) {
                        $nextUpgradeReward = $level['upgrade_reward'] ?? 0;
                        break;
                    }
                }
                
                // 当前等级的周月奖励（如果已领取，显示下次可获得的金额）
                if ($currentLevel) {
                    $nextWeeklyReward = $currentLevel['weekly_reward'] ?? 0;
                    $nextMonthlyReward = $currentLevel['monthly_reward'] ?? 0;
                }

                $userRewards = [
                    'upgrade' => [
                        'amount' => $rewardRecord['upgrade_reward_amount'] ?? 0,
                        'status' => $upgradeStatus, // 0-待领取，1-已领取，2-已过期
                        'next_reward' => $nextUpgradeReward // 即将领取的升级奖励
                    ],
                    'weekly' => [
                        'amount' => $rewardRecord['weekly_reward_amount'] ?? 0,
                        'status' => $weeklyStatus, // 0-待领取，1-已领取，2-已过期
                        'next_reward' => $nextWeeklyReward // 即将领取的周奖励
                    ],
                    'monthly' => [
                        'amount' => $rewardRecord['monthly_reward_amount'] ?? 0,
                        'status' => $monthlyStatus, // 0-待领取，1-已领取，2-已过期
                        'next_reward' => $nextMonthlyReward // 即将领取的月奖励
                    ]
                ];
            } else {
                // 如果没有奖励记录，创建默认结构
                // 获取即将领取的奖励信息
                $nextUpgradeReward = 0;
                $nextWeeklyReward = 0;
                $nextMonthlyReward = 0;
                
                // 找到下一等级的升级奖励
                foreach ($levels as $level) {
                    if ($level['level'] > $user['vip']) {
                        $nextUpgradeReward = $level['upgrade_reward'] ?? 0;
                        break;
                    }
                }
                
                // 当前等级的周月奖励
                if ($currentLevel) {
                    $nextWeeklyReward = $currentLevel['weekly_reward'] ?? 0;
                    $nextMonthlyReward = $currentLevel['monthly_reward'] ?? 0;
                }

                $userRewards = [
                    'upgrade' => [
                        'amount' => 0,
                        'status' => 1, // 无奖励时为已领取状态
                        'next_reward' => $nextUpgradeReward
                    ],
                    'weekly' => [
                        'amount' => 0,
                        'status' => 1, // 无奖励时为已领取状态
                        'next_reward' => $nextWeeklyReward
                    ],
                    'monthly' => [
                        'amount' => 0,
                        'status' => 1, // 无奖励时为已领取状态
                        'next_reward' => $nextMonthlyReward
                    ]
                ];
            }

            $result['user_rewards'] = $userRewards;
//            $result['rewards_total'] = $claimableCount;

            return [
                'status' => true,
                'msg' => '获取会员等级信息成功',
                'data' => $result
            ];

        } catch (\Exception $e) {
            Log::error("获取会员等级信息失败：" . json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [
                'status' => false,
                'msg' => '获取会员等级信息失败: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }


}
