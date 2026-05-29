<?php

namespace app\common\validate\payment;

use think\Validate;

class Methods extends Validate
{
    protected $failException = true;

    /**
     * 验证规则
     */
    protected $rule = [
        'min_recharge_amount' => 'float|egt:0',
        'max_recharge_amount' => 'float|egt:0',
        'min_withdraw_amount' => 'float|egt:0',
        'max_withdraw_amount' => 'float|egt:0',
    ];

    /**
     * 提示消息
     */
    protected $message = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['min_recharge_amount', 'max_recharge_amount', 'min_withdraw_amount', 'max_withdraw_amount'],
        'edit' => ['min_recharge_amount', 'max_recharge_amount', 'min_withdraw_amount', 'max_withdraw_amount'],
    ];

}
