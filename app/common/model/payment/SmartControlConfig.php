<?php

namespace app\common\model\payment;

use think\Model;

class SmartControlConfig extends Model
{
    protected $name = 'payment_smart_control_config';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $json = ['withdraw_pay_types', 'recharge_pay_types'];
}
