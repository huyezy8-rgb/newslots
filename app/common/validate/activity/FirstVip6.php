<?php

namespace app\common\validate\activity;

use think\Validate;

/**
 * 6%VIP充值验证器
 */
class FirstVip6 extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'id' => 'require|integer',
        'title' => 'require|max:255',
        'context' => 'require',
        'amount_list' => 'require',
        'pay_channels' => 'require|array',
        'enable_reward' => 'require|in:0,1',
        'reward_strategy' => 'in:fixed,range,percent',
    ];

    /**
     * 错误信息
     * @var array
     */
    protected $message = [
        'id.require' => 'ID不能为空',
        'id.integer' => 'ID必须为整数',
        'title.require' => '标题不能为空',
        'title.max' => '标题长度不能超过255个字符',
        'context.require' => '说明内容不能为空',
        'amount_list.require' => '金额配置不能为空',
        'pay_channels.require' => '支付通道配置不能为空',
        'pay_channels.array' => '支付通道配置必须为数组',
        'enable_reward.require' => '启用状态不能为空',
        'enable_reward.in' => '启用状态值错误',
        'reward_strategy.in' => '奖励策略值错误',
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'edit' => ['id', 'title', 'context', 'amount_list', 'pay_channels', 'enable_reward', 'reward_strategy'],
    ];

    /**
     * 自定义验证规则
     */
    protected function validateAmountList($value, $rule, $data = [])
    {
        try {
            $amountList = json_decode($value, true);
            if (!is_array($amountList) || empty($amountList)) {
                return '金额配置必须包含一个金额项';
            }
            
            $item = $amountList[0];
            if (!isset($item['amount']) || !is_numeric($item['amount']) || $item['amount'] <= 0) {
                return '金额配置中的金额必须为正数';
            }
            if (!isset($item['reward_percent']) || !is_numeric($item['reward_percent']) || $item['reward_percent'] < 0) {
                return '金额配置中的奖励百分比不能为负数';
            }
            
            return true;
        } catch (Exception $e) {
            return '金额配置格式错误';
        }
    }

    /**
     * 自定义验证规则
     */
    protected function validatePayChannels($value, $rule, $data = [])
    {
        if (!is_array($value)) {
            return '支付通道配置必须为数组';
        }

        foreach ($value as $item) {
            if (!isset($item['channel']) || empty($item['channel'])) {
                return '支付通道配置中的通道标识不能为空';
            }
            if (!isset($item['reward_percent']) || !is_numeric($item['reward_percent']) || $item['reward_percent'] < 0) {
                return '支付通道配置中的奖励百分比不能为负数';
            }
        }

        return true;
    }
} 