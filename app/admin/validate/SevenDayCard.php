<?php

namespace app\admin\validate;

use think\Validate;

/**
 * 七天卡活动验证器
 */
class SevenDayCard extends Validate
{
    protected $rule = [
        'title' => 'require|max:100',
        'bet_multiple' => 'require|float|>=:0',
        'original_price' => 'require|float|>=:0',
        'current_price' => 'require|float|>=:0',
        'seven_day_rewards' => 'require|array|length:7',
        'rescue_rewards' => 'require|array|length:7',
        'daily_rewards' => 'require|array|length:7',
        'status' => 'in:0,1',
        'is_pwa' => 'in:0,1',
    ];

    protected $message = [
        'title.require' => '活动标题不能为空',
        'title.max' => '活动标题不能超过100个字符',
        'bet_multiple.require' => '打码倍数不能为空',
        'bet_multiple.float' => '打码倍数必须是数字',
        'bet_multiple.>=' => '打码倍数不能小于0',
        'original_price.require' => '划线价格不能为空',
        'original_price.float' => '划线价格必须是数字',
        'original_price.>=' => '划线价格不能小于0',
        'current_price.require' => '现价不能为空',
        'current_price.float' => '现价必须是数字',
        'current_price.>=' => '现价不能小于0',
        'seven_day_rewards.require' => '七天奖励配置不能为空',
        'seven_day_rewards.array' => '七天奖励配置必须是数组',
        'seven_day_rewards.length' => '七天奖励配置必须包含7个元素',
        'rescue_rewards.require' => '救援金配置不能为空',
        'rescue_rewards.array' => '救援金配置必须是数组',
        'rescue_rewards.length' => '救援金配置必须包含7个元素',
        'daily_rewards.require' => '每日奖励配置不能为空',
        'daily_rewards.array' => '每日奖励配置必须是数组',
        'daily_rewards.length' => '每日奖励配置必须包含7个元素',
        'status.in' => '状态值无效',
        'is_pwa.in' => 'PWA开关值无效',
    ];

    protected $scene = [
        'edit' => ['title', 'bet_multiple', 'original_price', 'current_price', 'seven_day_rewards', 'rescue_rewards', 'daily_rewards', 'status', 'is_pwa'],
    ];
}
