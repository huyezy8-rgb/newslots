<?php

namespace app\common\service;

use app\api\enum\CoinLog;
use app\common\service\AccountService;
use think\facade\Db;

class PddService
{
    /**
     * 获取用户当前展示的进度（优先 status=1 可领取，其次 status=0 进行中）
     * 用于前端 /pdd/index 接口展示
     */
    public static function getDisplayProgress(int $userId): array
    {
        //进度是否首次  1 是  0否
        $progress_frist = 1;
        // 优先查找可领取的进度（status=1），其次查找进行中的进度（status=0）
        $progress = Db::name('pdd_progress')
            ->where('user_id', $userId)
            ->whereIn('status', [0, 1])
            ->order('status', 'desc') // 优先返回可领取状态=1
            ->order('id', 'asc') // 按ID正序
            ->find();
            
        if (!$progress) {
            // 如果没有找到任何进度，调用初始化方法创建
            return self::getOrInitInviteProgress($userId);
        }else{
            $progress_frist = 0;
        }
        
        // 计算目标金额（从系统配置 pdd_withdrawal 读取，持久化在进度表）
        $configuredTarget = (float)(get_sys_config('pdd_withdrawal') ?? 30.0);
        $targetAmount = (float)($progress['target_amount'] ?? 0) > 0 ? (float)$progress['target_amount'] : $configuredTarget;
        if (empty($progress['target_amount']) || (float)$progress['target_amount'] <= 0 || (float)$progress['target_amount'] !== $targetAmount) {
            // 将目标金额写入当前进度，保持持久化
            Db::name('pdd_progress')->where('id', $progress['id'])->update([
                'target_amount' => $targetAmount,
                'update_time' => time(),
            ]);
            $progress['target_amount'] = $targetAmount;
        }
        
        // 计算进度百分比和剩余金额
        $currentReward = (float)$progress['invite_reward'];
        $progress['progress_percent'] = $targetAmount > 0 ? min(round(($currentReward / $targetAmount) * 100, 2), 100) : 0;
        $progress['remaining_amount'] = max($targetAmount - $currentReward, 0);
        $progress['progress_frist']= $progress_frist;
        return $progress;
    }

    /**
     * 基于邀请进度：获取或初始化一条进行中的记录（status 0/1），首次进入直接送 23 元
     * 返回包含可提现状态的完整进度信息
     * 用于业务逻辑处理，优先返回 status=0 的记录
     */
    public static function getOrInitInviteProgress(int $userId): array
    {
        //进度是否首次  1 是  0否
        $progress_frist = 1;
        // 查找当前进行中的进度（优先 status=0，其次 status=1）
        $progress = Db::name('pdd_progress')
            ->where('user_id', $userId)
            ->whereIn('status', [0, 1])
            ->order('status', 'asc') // 优先返回进行中状态=0
            ->order('id', 'asc') // 按ID正序
            ->find();
            
        // 如果当前进度已达成目标（invite_reward >= target_amount）
        if ($progress && (float)$progress['invite_reward'] >= (float)($progress['target_amount'] ?? (get_sys_config('pdd_withdrawal') ?? 30.0))) {
            // 标记为可领取（仅提现后才置为已领取）
            if ((int)$progress['status'] !== 1 && (int)$progress['status'] !== 2) {
                Db::name('pdd_progress')->where('id', $progress['id'])->update([
                    'status' => 1, // 可领取
                    'update_time' => time()
                ]);
                // 同步内存状态
                $progress['status'] = 1;
            }

            // 确保不存在进行中的未完成进度（仅当没有 status=0 的记录时才预创建下一轮）
            $next = Db::name('pdd_progress')
                ->where('user_id', $userId)
                ->where('status', 0)
                ->where('id', '<>', $progress['id'])
                ->order('id', 'desc')
                ->find();
            if (!$next) {
                $newId = Db::name('pdd_progress')->insertGetId([
                    'user_id'       => $userId,
                    'group_id'      => 0,
                    'status'        => 0,
                    'invite_reward' => 0.00, // 新一轮从0开始
                    'target_amount' => (float)(get_sys_config('pdd_withdrawal') ?? 30.0),
                    'create_time'   => time(),
                    'update_time'   => time(),
                ]);
                // 为下一轮创建group并更新group_id
                $newGroup = \app\common\model\PddProgressGroup::getOrCreateGroup($userId, $newId);
                Db::name('pdd_progress')->where('id', $newId)->update([
                    'group_id' => $newGroup['id'] ?? 0,
                    'update_time' => time(),
                ]);
            }
        }
            
        if (!$progress) {
            // 仅首次创建赠送 23 元，后续新进度从 0 开始
            $hasAny = Db::name('pdd_progress')->where('user_id', $userId)->find();
            
            if ($hasAny) {
                $initReward = 0.00;
            } else {
                $min = (float)get_sys_config('pdd_init_min');
                $max = (float)get_sys_config('pdd_init_max');
                $initReward = round($min + mt_rand(0, 10000) / 10000 * ($max - $min), 2);
            }
       

            
            $id = Db::name('pdd_progress')->insertGetId([
                'user_id'       => $userId,
                'group_id'      => 0, // 暂时设为0，后续会创建对应的group
                'status'        => 0,
                'invite_reward' => $initReward,
                'target_amount' => (float)(get_sys_config('pdd_withdrawal') ?? 30.0),
                'create_time'   => time(),
                'update_time'   => time(),
            ]);
            // 首次初始化赠送金额通过 AccountService 计入 pdd_reward
            if ($initReward > 0) {
                (new AccountService())->increaseBalance($userId, (float)$initReward, 3, CoinLog::PDDInitReward, 'PDD 首次初始化赠送');
            }
            $progress = Db::name('pdd_progress')->where('id', $id)->find();
            
            // 为这个进度创建对应的group
            $group = \app\common\model\PddProgressGroup::getOrCreateGroup($userId, $id);
            
            // 更新进度记录的group_id
            Db::name('pdd_progress')->where('id', $id)->update([
                'group_id' => $group['id'],
                'update_time' => time()
            ]);
            
            // 重新获取更新后的进度记录
            $progress = Db::name('pdd_progress')->where('id', $id)->find();
        }else{
            $progress_frist = 0;
        }
        
        // 获取对应的进度组信息，如果不存在则创建
        $group = \app\common\model\PddProgressGroup::getGroupByProgress($userId, $progress['id']);
        if (!$group) {
            $group = \app\common\model\PddProgressGroup::getOrCreateGroup($userId, $progress['id']);
        }
        
        // 如果进度记录的group_id为0，更新为正确的group_id
        if ($progress['group_id'] == 0 && $group) {
            Db::name('pdd_progress')->where('id', $progress['id'])->update([
                'group_id' => $group['id'],
                'update_time' => time()
            ]);
            $progress['group_id'] = $group['id'];
        }
        
        // 计算目标金额（从系统配置 pdd_withdrawal 读取，持久化在进度表）
        $configuredTarget = (float)(get_sys_config('pdd_withdrawal') ?? 30.0);
        $targetAmount = (float)($progress['target_amount'] ?? 0) > 0 ? (float)$progress['target_amount'] : $configuredTarget;
        if (empty($progress['target_amount']) || (float)$progress['target_amount'] <= 0 || (float)$progress['target_amount'] !== $targetAmount) {
            // 将目标金额写入当前进度，保持持久化
            Db::name('pdd_progress')->where('id', $progress['id'])->update([
                'target_amount' => $targetAmount,
                'update_time' => time(),
            ]);
        }
        
        // 可提现状态改由外层基于 status 判断，不再在 progress 内返回
        $progress['progress_percent'] = round(min(($progress['invite_reward'] / $targetAmount) * 100, 100), 4);
        $progress['remaining_amount'] = round(max($targetAmount - $progress['invite_reward'], 0), 4);
        $progress['target_amount'] = $targetAmount;
        $progress['progress_frist']= $progress_frist;

        return $progress;
    }

    /**
     * 处理邀请注册奖励
     * 从系统配置获取奖励金额
     */
    public static function handleInviteRegistration(int $inviterUserId, int $inviteUserId): void
    {
        // 默认邀请注册奖励金额
        //$inviteRewardAmount = (float)(get_sys_config('pdd_invite_register_reward') ?? 0.1);
        $arr = [0.01,0.02,0.03,0.04,0.05,0.06];
        $inviteRewardAmount = $arr[array_rand($arr)];
        $isQualifiedFill = false; // 标记是否为补齐奖励

        // 读取解锁配置：需要多少个达标人数才能触发补齐（默认 2）
        $requiredQualifiedCount = (int)(get_sys_config('pdd_unlock_required_invites') ?? 2);

        // 获取邀请人的当前进度与分组
        $progress = self::getOrInitInviteProgress($inviterUserId);
        $group = \app\common\model\PddProgressGroup::getGroupByProgress($inviterUserId, $progress['id']);
        
        if ($group) {
            // 直接使用 has_qualified_user 字段判断达标的被邀请人数量
            $qualifiedCount = (int)($group['has_qualified_user'] ?? 0);

            // 检查当前达标人数是否已达到解锁条件
            if ($qualifiedCount >= $requiredQualifiedCount) {
                // 已达标人数满足条件，直接给予剩余金额奖励（不需要新邀请人达标）
                $targetAmount = (float)($progress['target_amount'] ?? (get_sys_config('pdd_withdrawal') ?? 30.0));
                $currentReward = (float)$progress['invite_reward'];
                $remaining = max($targetAmount - $currentReward, 0);
                
                if ($remaining > 0) {
                    $inviteRewardAmount = $remaining;
                    $isQualifiedFill = true; // 标记为补齐奖励
                }
            }
        }

        if ($inviteRewardAmount > 0) {
            self::recordInviteReward($inviterUserId, $inviteUserId, $inviteRewardAmount, $isQualifiedFill);
        }
    }

    /**
     * 处理用户充值达标（累计充值50元）
     * 检查该用户是否是被邀请的，如果是则标记为达标
     */
    public static function handleUserRechargeQualified(int $userId): void
    {
        // 获取用户累计充值金额
        $user = Db::name('account')->where('id', $userId)->field('sum_recharge')->find();
        if (!$user) {
            return; // 用户不存在
        }
        
        $totalRecharge = (float)$user['sum_recharge'];
        $minValidRecharge = (float)(get_sys_config('pdd_valid_invite_recharge_min') ?? 50.0);
        if ($totalRecharge < $minValidRecharge) {
            return; // 累计充值未达到有效邀请金额
        }

        // 查找邀请该用户的邀请人
        $inviteLogs = Db::name('pdd_invite_log')
            ->where('invite_user_id', $userId)
            ->select()
            ->toArray();

        foreach ($inviteLogs as $log) {
            $inviterUserId = $log['inviter_user_id'];
            $progressId = $log['pdd_progress_id'];
            
            // 标记该用户在对应的进度组中为达标
            \app\common\model\PddProgressGroup::markUserQualified($inviterUserId, $progressId, $userId);
        }
    }


    /**
     * 记录邀请奖励（内部使用，不单独对外暴露接口）
     * - 记录一条邀请奖励日志（pdd_invite_log）
     * - 同步累加进度表 invite_reward，满 30 置为可提现
     */
    public static function recordInviteReward(int $inviterUserId, int $inviteUserId, float $amount = 0.1, bool $isQualifiedFill = false): void
    {
        // 使用数据库锁防止并发问题
        try {
            Db::transaction(function () use ($inviterUserId, $inviteUserId, $amount, $isQualifiedFill) {
                // 1) 双重检查是否已经存在邀请关系（防止并发）
                $existingLog = Db::name('pdd_invite_log')
                    ->where('inviter_user_id', $inviterUserId)
                    ->where('invite_user_id', $inviteUserId)
                    ->lock(true)
                    ->find();
                
                if ($existingLog) {
                    // 邀请关系已存在，直接返回
                    return;
                }

                // 2) 检查被邀请人是否已被其他人邀请
                $otherInviter = Db::name('pdd_invite_log')
                    ->where('invite_user_id', $inviteUserId)
                    ->where('inviter_user_id', '<>', $inviterUserId)
                    ->lock(true)
                    ->find();
                
                if ($otherInviter) {
                    // 被邀请人已被其他人邀请，直接返回
                    return;
                }

                // 3) 锁定或初始化进度
                $progress = Db::name('pdd_progress')
                    ->where('user_id', $inviterUserId)
                    ->whereIn('status', [0, 1])
                    ->order('id', 'desc')
                    ->lock(true)
                    ->find();
                if (!$progress) {
                    $progress = self::getOrInitInviteProgress($inviterUserId);
                    $progress = Db::name('pdd_progress')->where('id', $progress['id'])->lock(true)->find();
                }

                // 4) 写邀请奖励日志（使用 INSERT IGNORE 防止唯一约束冲突）
                try {
                    Db::name('pdd_invite_log')->insert([
                        'inviter_user_id' => $inviterUserId,
                        'invite_user_id'  => $inviteUserId,
                        'amount'          => round($amount, 2),
                        'pdd_progress_id' => (int)$progress['id'],
                        'create_time'     => time(),
                    ]);
                } catch (\Exception $e) {
                    // 如果插入失败（可能是唯一约束冲突），直接返回
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        return;
                    }
                    throw $e;
                }

                // 5) 累加奖励并更新状态
                $targetAmount = (float)($progress['target_amount'] ?? (get_sys_config('pdd_target_amount') ?? 30.0));
                $targetAmount = (float)($progress['target_amount'] ?? (get_sys_config('pdd_target_amount') ?? 30.0));
                
                $currentReward = (float)$progress['invite_reward'];
                $newReward = round($currentReward + (float)$amount, 2);
                
                // 限制 invite_reward 不能超过目标金额
                if ($newReward > $targetAmount) {
                    $newReward = $targetAmount;
                }
                
                $actualAmount = $newReward - $currentReward; // 实际发放的金额
                $newStatus = $newReward >= $targetAmount ? 1 : (int)$progress['status'];
                
                Db::name('pdd_progress')->where('id', (int)$progress['id'])->update([
                    'invite_reward' => $newReward,
                    'status'        => $newStatus,
                    'update_time'   => time(),
                ]);
                
                // 同步更新用户账户的 pdd_reward 字段（只更新实际发放的金额，通过 AccountService）
                if ($actualAmount > 0) {
                    $coinLogType = $isQualifiedFill ? CoinLog::PDDQualifiedFill : CoinLog::PDDInviteReward;
                    $description = $isQualifiedFill ? 'PDD 达标补齐奖励' : 'PDD 邀请奖励';
                    (new AccountService())->increaseBalance($inviterUserId, (float)$actualAmount, 3, $coinLogType, $description);
                }
                
                // 6) 将邀请用户添加到进度组
                \app\common\model\PddProgressGroup::addInvitedUser($inviterUserId, $progress['id'], $inviteUserId);
            });
        } catch (\Exception $e) {
            // 如果是唯一约束冲突，直接返回
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return;
            }
            throw $e;
        }
    }


    /**
     * 基于邀请进度发起提现：根据progress_id提现，转入充值账户
     */
    public static function withdrawByInviteProgress(int $userId, int $progressId): array
    {
        return Db::transaction(function () use ($userId, $progressId) {
            // 查找指定的进度记录
            $progress = Db::name('pdd_progress')
                ->where('id', $progressId)
                ->where('user_id', $userId)
                ->lock(true)
                ->find();
            
            if (!$progress) {
                throw new \Exception('Progress not found');
            }
            
            $targetAmount = (float)($progress['target_amount'] ?? (get_sys_config('pdd_withdrawal') ?? 30.0));
            if ((float)$progress['invite_reward'] < $targetAmount || (int)$progress['status'] != 1) {
                throw new \Exception('Not eligible to transfer');
            }

            // 从 pdd_reward 划转到 recharge_wallet（充值账户），不收手续费
            $accountService = new AccountService();
            $accountService->decreaseBalance($userId, $targetAmount, 3, CoinLog::PDDWithdraw, 'PDD提现划转出');
            $accountService->increaseBalance($userId, $targetAmount, 1, CoinLog::PDDWithdraw, 'PDD提现转入充值账户');

            // 置当前轮为已领取
            Db::name('pdd_progress')->where('id', (int)$progress['id'])->update([
                'status' => 2,
                'update_time' => time(),
            ]);
            
            // 检查是否已存在 status=0 的进度记录，如果存在则不创建新的
            $existingProgress = Db::name('pdd_progress')
                ->where('user_id', $userId)
                ->where('status', 0)
                ->find();
            
            $nextId = null;
            $pdd_init_min = Db::name('config')->where('id',66)->value('value');
            $pdd_init_max = Db::name('config')->where('id',67)->value('value');
            $newInvite_reward = rand($pdd_init_min,$pdd_init_max);
            Db::name('account')->where('id', $userId)->update(['pdd_reward' => $newInvite_reward]);

            if (!$existingProgress) {
                // 只有在没有 status=0 的进度记录时才创建新的
                $nextId = Db::name('pdd_progress')->insertGetId([
                    'user_id'       => $userId,
                    'group_id'      => 0,
                    'status'        => 0,
                    'invite_reward' => $newInvite_reward,
                    'target_amount' => (float)(get_sys_config('pdd_withdrawal') ?? 30.0),
                    'create_time'   => time(),
                    'update_time'   => time(),
                ]);
                $newGroup = \app\common\model\PddProgressGroup::getOrCreateGroup($userId, $nextId);
                Db::name('pdd_progress')->where('id', $nextId)->update([
                    'group_id' => $newGroup['id'] ?? 0,
                    'update_time' => time(),
                ]);
            }

            return [
                'withdrawn_amount' => $targetAmount,
                'progress_id' => (int)$progress['id'],
                'next_progress_id' => $nextId
            ];
        });
    }
}

