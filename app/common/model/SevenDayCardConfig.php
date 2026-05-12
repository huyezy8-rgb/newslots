<?php

namespace app\common\model;

use think\Model;

/**
 * 七天卡活动配置模型
 */
class SevenDayCardConfig extends Model
{
    protected $table = 'slot_seven_day_card_config';
    
    protected $autoWriteTimestamp = true;
    
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    
    // 字段类型转换
    protected $type = [
        'bet_multiple' => 'float',
        'original_price' => 'float',
        'current_price' => 'float',
        'seven_day_rewards' => 'json',
        'rescue_rewards' => 'json',
        'daily_rewards' => 'json',
        'status' => 'integer',
        'is_pwa' => 'integer',
    ];
    
    /**
     * 获取活动配置
     */
    public static function getActiveConfig()
    {
        return self::where('status', 1)->find();
    }
    
    /**
     * 获取或创建默认配置
     */
    public static function getOrCreateConfig()
    {
        $config = self::find(1);
        if (!$config) {
            $config = self::create([
                'title' => '七天卡',
                'bet_multiple' => 1.0,
                'original_price' => 0.00,
                'current_price' => 19.99,
                'seven_day_rewards' => [22, 5, 7, 4, 4, 4, 8],
                'rescue_rewards' => [3, 3, 3, 3, 3, 3, 3],
                'daily_rewards' => [1, 1, 3, 1, 1, 1, 5],
                'status' => 1,
                'is_pwa' => 0
            ]);
        }
        return $config;
    }
}
