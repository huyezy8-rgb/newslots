<?php

namespace app\common\validate\payment;

use think\Validate;

class Channels extends Validate
{
    protected $failException = true;

    /**
     * 验证规则
     */
    protected $rule = [
        'weight' => 'integer|egt:0',
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
        'add'  => ['weight'],
        'edit' => ['weight'],
    ];

}
