<?php

namespace app\common\model;

use think\Model;

/**
 * 用户等级奖励模型
 */
class MemberLevelRewards extends Model
{
    protected $name = 'member_level_rewards';
    
    // 设置字段信息
    protected $schema = [
        'id'                           => 'int',
        'user_id'                      => 'int',
        'level'                        => 'int',
        'upgrade_reward_amount'        => 'float',
        'upgrade_reward_issued_time'   => 'int',
        'upgrade_reward_claimed_time'  => 'int',
        'upgrade_reward_status'        => 'int',
        'weekly_reward_amount'         => 'float',
        'weekly_reward_issued_time'    => 'int',
        'weekly_reward_claimed_time'   => 'int',
        'weekly_reward_status'         => 'int',
        'monthly_reward_amount'        => 'float',
        'monthly_reward_issued_time'   => 'int',
        'monthly_reward_claimed_time'  => 'int',
        'monthly_reward_status'        => 'int',
        'create_time'                  => 'int',
        'update_time'                  => 'int',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 奖励状态常量
    const STATUS_PENDING = 0;  // 待领取
    const STATUS_CLAIMED = 1;  // 已领取
    const STATUS_EXPIRED = 2;  // 已过期

    /**
     * 关联用户表
     */
    public function user()
    {
        return $this->belongsTo(Account::class, 'user_id', 'id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusText($status)
    {
        $statusTexts = [
            self::STATUS_PENDING => '待领取',
            self::STATUS_CLAIMED => '已领取',
            self::STATUS_EXPIRED => '已过期',
        ];
        return $statusTexts[$status] ?? '未知';
    }

    /**
     * 获取升级奖励状态文本
     */
    public function getUpgradeRewardStatusTextAttr($value, $data)
    {
        return $this->getStatusText($data['upgrade_reward_status']);
    }

    /**
     * 获取周奖励状态文本
     */
    public function getWeeklyRewardStatusTextAttr($value, $data)
    {
        return $this->getStatusText($data['weekly_reward_status']);
    }

    /**
     * 获取月奖励状态文本
     */
    public function getMonthlyRewardStatusTextAttr($value, $data)
    {
        return $this->getStatusText($data['monthly_reward_status']);
    }

    /**
     * 检查升级奖励是否可领取
     */
    public function isUpgradeRewardClaimable()
    {
        return $this->upgrade_reward_amount > 0 
            && $this->upgrade_reward_issued_time 
            && $this->upgrade_reward_status === self::STATUS_PENDING;
    }

    /**
     * 检查周奖励是否可领取
     */
    public function isWeeklyRewardClaimable()
    {
        return $this->weekly_reward_amount > 0 
            && $this->weekly_reward_issued_time 
            && $this->weekly_reward_status === self::STATUS_PENDING
            && !$this->isWeeklyRewardExpired();
    }

    /**
     * 检查月奖励是否可领取
     */
    public function isMonthlyRewardClaimable()
    {
        return $this->monthly_reward_amount > 0 
            && $this->monthly_reward_issued_time 
            && $this->monthly_reward_status === self::STATUS_PENDING
            && !$this->isMonthlyRewardExpired();
    }

    /**
     * 检查周奖励是否已过期（7天）
     */
    public function isWeeklyRewardExpired()
    {
        if (!$this->weekly_reward_issued_time) {
            return false;
        }
        return (time() - $this->weekly_reward_issued_time) > (7 * 24 * 3600);
    }

    /**
     * 检查月奖励是否已过期（30天）
     */
    public function isMonthlyRewardExpired()
    {
        if (!$this->monthly_reward_issued_time) {
            return false;
        }
        return (time() - $this->monthly_reward_issued_time) > (30 * 24 * 3600);
    }

    /**
     * 获取所有可领取的奖励
     */
    public function getClaimableRewards()
    {
        $rewards = [];

        if ($this->isUpgradeRewardClaimable()) {
            $rewards[] = [
                'type' => 'upgrade',
                'amount' => $this->upgrade_reward_amount,
                'name' => '升级奖励',
                'issued_time' => $this->upgrade_reward_issued_time
            ];
        }

        if ($this->isWeeklyRewardClaimable()) {
            $rewards[] = [
                'type' => 'weekly',
                'amount' => $this->weekly_reward_amount,
                'name' => '周奖励',
                'issued_time' => $this->weekly_reward_issued_time,
                'expire_time' => $this->weekly_reward_issued_time + (7 * 24 * 3600)
            ];
        }

        if ($this->isMonthlyRewardClaimable()) {
            $rewards[] = [
                'type' => 'monthly',
                'amount' => $this->monthly_reward_amount,
                'name' => '月奖励',
                'issued_time' => $this->monthly_reward_issued_time,
                'expire_time' => $this->monthly_reward_issued_time + (30 * 24 * 3600)
            ];
        }

        return $rewards;
    }

    /**
     * 领取升级奖励
     */
    public function claimUpgradeReward()
    {
        if (!$this->isUpgradeRewardClaimable()) {
            return false;
        }
        
        $this->upgrade_reward_claimed_time = time();
        $this->upgrade_reward_status = self::STATUS_CLAIMED;
        return $this->save();
    }

    /**
     * 领取周奖励
     */
    public function claimWeeklyReward()
    {
        if (!$this->isWeeklyRewardClaimable()) {
            return false;
        }
        
        $this->weekly_reward_claimed_time = time();
        $this->weekly_reward_status = self::STATUS_CLAIMED;
        return $this->save();
    }

    /**
     * 领取月奖励
     */
    public function claimMonthlyReward()
    {
        if (!$this->isMonthlyRewardClaimable()) {
            return false;
        }
        
        $this->monthly_reward_claimed_time = time();
        $this->monthly_reward_status = self::STATUS_CLAIMED;
        return $this->save();
    }

    /**
     * 设置奖励为过期状态
     */
    public function expireWeeklyReward()
    {
        if ($this->weekly_reward_status === self::STATUS_PENDING) {
            $this->weekly_reward_status = self::STATUS_EXPIRED;
            return $this->save();
        }
        return false;
    }

    /**
     * 设置月奖励为过期状态
     */
    public function expireMonthlyReward()
    {
        if ($this->monthly_reward_status === self::STATUS_PENDING) {
            $this->monthly_reward_status = self::STATUS_EXPIRED;
            return $this->save();
        }
        return false;
    }
}
