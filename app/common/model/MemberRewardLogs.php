<?php

namespace app\common\model;

use think\Model;

/**
 * 会员奖励发放记录模型
 */
class MemberRewardLogs extends Model
{
    protected $name = 'member_reward_logs';
    
    // 设置字段信息
    protected $schema = [
        'id'             => 'int',
        'user_id'        => 'int',
        'reward_id'      => 'int',
        'level'          => 'int',
        'previous_level' => 'int',
        'reward_type'    => 'string',
        'reward_amount'  => 'float',
        'create_time'    => 'int',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = false; // 日志表不需要更新时间

    // 奖励类型
    const REWARD_TYPE_UPGRADE = 'upgrade';
    const REWARD_TYPE_WEEKLY = 'weekly';
    const REWARD_TYPE_MONTHLY = 'monthly';

    /**
     * 关联用户表
     */
    public function user()
    {
        return $this->belongsTo(Account::class, 'user_id', 'id');
    }

    /**
     * 关联奖励表
     */
    public function reward()
    {
        return $this->belongsTo(MemberLevelRewards::class, 'reward_id', 'id');
    }

    /**
     * 获取奖励类型文本
     */
    public function getRewardTypeTextAttr($value, $data)
    {
        $types = [
            self::REWARD_TYPE_UPGRADE => '升级奖励',
            self::REWARD_TYPE_WEEKLY => '周奖励',
            self::REWARD_TYPE_MONTHLY => '月奖励',
        ];
        return $types[$data['reward_type']] ?? '未知';
    }





    /**
     * 获取等级变化描述
     */
    public function getLevelChangeTextAttr($value, $data)
    {
        if ($data['reward_type'] === 'upgrade' && $data['previous_level'] !== null) {
            return "V{$data['previous_level']} → V{$data['level']}";
        }
        return "V{$data['level']}";
    }

    /**
     * 获取完整的奖励描述
     */
    public function getRewardDescriptionAttr($value, $data)
    {
        $typeText = $this->getRewardTypeTextAttr(null, $data);
        $levelText = $this->getLevelChangeTextAttr(null, $data);
        
        if ($data['reward_type'] === 'upgrade' && $data['previous_level'] !== null) {
            return "{$typeText} ({$levelText})";
        }
        
        return "{$typeText} (V{$data['level']})";
    }
}
