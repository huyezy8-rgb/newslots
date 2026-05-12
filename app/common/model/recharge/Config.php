<?php

namespace app\common\model\recharge;

use think\Model;

/**
 * Config
 */
class Config extends Model
{
    protected $json = ['amount_list', 'pay_channels', 'reward_value'];
    // 表名
    protected $name = 'recharge_config';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

}