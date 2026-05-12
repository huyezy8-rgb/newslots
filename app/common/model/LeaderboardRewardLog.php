<?php

namespace app\common\model;

use think\Model;

/**
 * 排行榜奖励发放日志模型
 */
class LeaderboardRewardLog extends Model
{
    protected $name = 'leaderboard_reward_log';
    
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    
    // 排行榜类型
    const TYPE_DAILY = 'daily';
    const TYPE_WEEKLY = 'weekly';
    const TYPE_MONTHLY = 'monthly';
    
    protected $type = [
        'channel_id' => 'integer',
        'pool_amount' => 'decimal:4',
        'distributed_amount' => 'decimal:4',
        'success_count' => 'integer',
        'fail_count' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp'
    ];

    /**
     * 获取排行榜类型文本
     */
    public function getTypeTextAttr($value, $data)
    {
        $types = [
            self::TYPE_DAILY => '日榜',
            self::TYPE_WEEKLY => '周榜',
            self::TYPE_MONTHLY => '月榜'
        ];
        
        return $types[$data['type']] ?? '未知';
    }

    /**
     * 获取发放成功率
     */
    public function getSuccessRateAttr($value, $data)
    {
        $total = $data['success_count'] + $data['fail_count'];
        if ($total == 0) {
            return '0%';
        }
        
        return round($data['success_count'] / $total * 100, 2) . '%';
    }
}
