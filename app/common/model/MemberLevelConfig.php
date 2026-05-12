<?php

namespace app\common\model;

use think\Model;

/**
 * 会员等级配置模型
 */
class MemberLevelConfig extends Model
{
    protected $name = 'member_level_config';
    
    // 设置字段信息
    protected $schema = [
        'id'                    => 'int',
        'name'                  => 'string',
        'level'                 => 'int',
        'recharge_requirement'  => 'float',
        'withdraw_limit'        => 'float',
        'daily_withdraw_times'  => 'int',
        'withdraw_fee_percent'  => 'float',
        'bonus_percent'         => 'float',
        'upgrade_reward'        => 'float',
        'weekly_reward'         => 'float',
        'monthly_reward'        => 'float',
        'create_time'           => 'int',
        'update_time'           => 'int',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    /**
     * 关联用户奖励表
     */
    public function rewards()
    {
        return $this->hasMany(MemberLevelRewards::class, 'level_id', 'id');
    }

    /**
     * 获取等级显示名称
     */
    public function getDisplayNameAttr($value, $data)
    {
        return $data['name'] ?: 'VIP' . $data['level'];
    }

    /**
     * 检查是否有升级奖励
     */
    public function hasUpgradeReward()
    {
        return !empty($this->float) && $this->float > 0;
    }

    /**
     * 检查是否有周奖励
     */
    public function hasWeeklyReward()
    {
        return !empty($this->weekly_reward) && $this->weekly_reward > 0;
    }

    /**
     * 检查是否有月奖励
     */
    public function hasMonthlyReward()
    {
        return !empty($this->monthly_reward) && $this->monthly_reward > 0;
    }

    /**
     * 获取所有奖励信息
     */
    public function getAllRewards()
    {
        $rewards = [];
        
        if ($this->hasUpgradeReward()) {
            $rewards['upgrade'] = [
                'type' => 'upgrade',
                'amount' => $this->float,
                'name' => '升级奖励'
            ];
        }
        
        if ($this->hasWeeklyReward()) {
            $rewards['weekly'] = [
                'type' => 'weekly',
                'amount' => $this->weekly_reward,
                'name' => '周奖励'
            ];
        }
        
        if ($this->hasMonthlyReward()) {
            $rewards['monthly'] = [
                'type' => 'monthly',
                'amount' => $this->monthly_reward,
                'name' => '月奖励'
            ];
        }
        
        return $rewards;
    }
}
