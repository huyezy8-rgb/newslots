<?php

namespace app\common\validate\activity;

use think\Validate;

/**
 * 站内信活动配置验证器
 */
class InternalMessage extends Validate
{
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'title' => 'require|max:255',
        'content' => 'require',
        'amount' => 'require|float|egt:0',
        'wallet_type' => 'require|in:experience_wallet,recharge_wallet,game_wallet',
        'valid_hours' => 'require|integer|egt:0',
    ];

    protected $message = [
        'id.require' => '配置ID不能为空',
        'id.integer' => '配置ID必须为整数',
        'id.gt' => '配置ID必须大于0',
        'title.require' => '消息标题不能为空',
        'title.max' => '消息标题长度不能超过255个字符',
        'content.require' => '消息内容不能为空',
        'amount.require' => '赠送金额不能为空',
        'amount.float' => '赠送金额必须为数字',
        'amount.egt' => '赠送金额不能小于0',
        'wallet_type.require' => '钱包类型不能为空',
        'wallet_type.in' => '钱包类型选择错误',
        'valid_hours.require' => '有效期不能为空',
        'valid_hours.integer' => '有效期必须为整数',
        'valid_hours.egt' => '有效期不能小于0',
    ];

    protected $scene = [
        'edit' => ['title', 'content', 'amount', 'wallet_type', 'valid_hours'],
        'create' => ['title', 'content', 'amount', 'wallet_type', 'valid_hours'],
    ];

    /**
     * 自定义验证规则：验证金额格式
     * @param mixed $value 验证值
     * @param string $rule 验证规则
     * @param array $data 验证数据
     * @return bool
     */
    protected function checkAmount($value, $rule, $data)
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $amount = floatval($value);
        if ($amount < 0) {
            return false;
        }
        
        // 检查小数位数不超过2位
        $decimalPlaces = strlen(substr(strrchr($amount, "."), 1));
        if ($decimalPlaces > 2) {
            return false;
        }
        
        return true;
    }

    /**
     * 自定义验证规则：验证有效期合理性
     * @param mixed $value 验证值
     * @param string $rule 验证规则
     * @param array $data 验证数据
     * @return bool
     */
    protected function checkValidHours($value, $rule, $data)
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $hours = intval($value);
        if ($hours < 0) {
            return false;
        }
        
        // 如果设置为永久有效（0），则通过验证
        if ($hours == 0) {
            return true;
        }
        
        // 检查有效期是否合理（不超过1年）
        if ($hours > 8760) { // 365天 * 24小时
            return false;
        }
        
        return true;
    }

    /**
     * 自定义验证规则：验证钱包类型
     * @param mixed $value 验证值
     * @param string $rule 验证规则
     * @param array $data 验证数据
     * @return bool
     */
    protected function checkWalletType($value, $rule, $data)
    {
        $allowedTypes = ['experience_wallet', 'recharge_wallet', 'game_wallet'];
        return in_array($value, $allowedTypes);
    }
} 