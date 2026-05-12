<?php

namespace app\common\validate\activity;


use think\Validate;

/**
 * 每日首充配置验证器
 */
class FirstDepositDaily extends Validate
{
    protected $rule = [
        'id' => 'require|integer',
        'title' => 'require|max:255',
        'context' => 'require',
        'enable_reward' => 'require|in:0,1',
        'reward_strategy' => 'require|in:fixed,range,percent',
        'reward_value' => 'require|json',
        'amount_list' => 'require|json',
        'pay_channels' => 'require|json',
        'task_reward' => 'require|float|egt:0',
        'update_time' => 'integer',
    ];

    protected $message = [
        'id.require' => 'ID不能为空',
        'id.integer' => 'ID必须为整数',
        'title.require' => '配置标题不能为空',
        'title.max' => '配置标题长度不能超过255个字符',
        'context.require' => '说明内容不能为空',
        'enable_reward.require' => '启用充值奖励不能为空',
        'enable_reward.in' => '启用充值奖励值无效',
        'reward_strategy.require' => '奖励策略不能为空',
        'reward_strategy.in' => '奖励策略值无效',
        'reward_value.require' => '奖励值配置不能为空',
        'reward_value.json' => '奖励值配置格式错误',
        'amount_list.require' => '金额配置不能为空',
        'amount_list.json' => '金额配置格式错误',
        'pay_channels.require' => '支付通道配置不能为空',
        'pay_channels.json' => '支付通道配置格式错误',
        'task_reward.require' => '任务奖励不能为空',
        'task_reward.float' => '任务奖励必须为数字',
        'task_reward.egt' => '任务奖励不能小于0',
        'update_time.integer' => '更新时间必须为整数',
    ];

    protected $scene = [
        'edit' => ['title', 'context', 'enable_reward', 'reward_strategy', 'reward_value', 'amount_list', 'pay_channels', 'task_reward'],
    ];

    /**
     * 验证奖励值配置
     * @param string $value
     * @param string $rule
     * @param array $data
     * @return bool|string
     */
    protected function validateRewardValue($value, $rule, $data)
    {
        $rewardValue = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '奖励值配置JSON格式错误';
        }

        $strategy = $data['reward_strategy'] ?? '';
        switch ($strategy) {
            case 'fixed':
                if (!isset($rewardValue['fixed']) || $rewardValue['fixed'] <= 0) {
                    return '固定奖励值必须大于0';
                }
                break;
            case 'range':
                if (!isset($rewardValue['min']) || !isset($rewardValue['max']) || 
                    $rewardValue['min'] <= 0 || $rewardValue['max'] <= 0 || 
                    $rewardValue['min'] >= $rewardValue['max']) {
                    return '区间奖励值配置错误，最小值必须小于最大值且都大于0';
                }
                break;
            case 'percent':
                if (!isset($rewardValue['percent']) || $rewardValue['percent'] <= 0 || $rewardValue['percent'] > 100) {
                    return '百分比奖励值必须在1-100之间';
                }
                break;
        }

        return true;
    }

    /**
     * 验证金额配置
     * @param string $value
     * @param string $rule
     * @param array $data
     * @return bool|string
     */
    protected function validateAmountList($value, $rule, $data)
    {
        $amountList = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '金额配置JSON格式错误';
        }

        if (!is_array($amountList) || empty($amountList)) {
            return '金额配置不能为空';
        }

        foreach ($amountList as $index => $item) {
            if (!isset($item['amount']) || $item['amount'] <= 0) {
                return "第" . ($index + 1) . "项金额必须大于0";
            }
            if (!isset($item['reward_percent']) || $item['reward_percent'] < 0 || $item['reward_percent'] > 100) {
                return "第" . ($index + 1) . "项奖励百分比必须在0-100之间";
            }
        }

        return true;
    }

    /**
     * 验证支付通道配置
     * @param string $value
     * @param string $rule
     * @param array $data
     * @return bool|string
     */
    protected function validatePayChannels($value, $rule, $data)
    {
        $payChannels = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '支付通道配置JSON格式错误';
        }

        if (!is_array($payChannels) || empty($payChannels)) {
            return '支付通道配置不能为空';
        }

        foreach ($payChannels as $index => $item) {
            if (!isset($item['channel']) || empty($item['channel'])) {
                return "第" . ($index + 1) . "项通道标识不能为空";
            }
            if (!isset($item['reward_percent']) || $item['reward_percent'] < 0 || $item['reward_percent'] > 100) {
                return "第" . ($index + 1) . "项奖励百分比必须在0-100之间";
            }
        }

        return true;
    }
} 