<?php
namespace app\common\model;

use think\Model;

class RankingActivity extends Model
{
    protected $table = 'slot_ranking_activity';
    protected $autoWriteTimestamp = true;

    // JSON字段
    protected $json = ['day_rewards', 'week_rewards', 'month_rewards'];
    protected $jsonAssoc = true;

    // 允许写入的字段
    protected $allowField = [
        'name', 'status', 'daily_pool_ratio', 'weekly_pool_ratio', 'monthly_pool_ratio',
        'day_limit', 'week_limit', 'month_limit', 'day_rewards', 'week_rewards', 'month_rewards'
    ];

    // 获取状态文本
    public function getStatusTextAttr($value, $data)
    {
        $status = [0 => '禁用', 1 => '启用'];
        return $status[$data['status']] ?? '未知';
    }

    // 获取当前活动
    public static function getCurrentActivity()
    {
        return self::where('status', 1)->find();
    }

    // 获取奖励配置
    public function getRewardConfig($rankType)
    {
        $field = $rankType . '_rewards';
        return $this->$field ?? [];
    }

    // 设置奖励配置
    public function setRewardConfig($rankType, $config)
    {
        $field = $rankType . '_rewards';
        $this->$field = $config;
        return $this;
    }

    // 获取所有奖励配置
    public function getAllRewardConfigs()
    {
        return [
            'day' => $this->day_rewards ?? [],
            'week' => $this->week_rewards ?? [],
            'month' => $this->month_rewards ?? []
        ];
    }
}