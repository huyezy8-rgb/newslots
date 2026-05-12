<?php

namespace app\common\model\payment;

use think\Model;

/**
 * Channels
 */
class Channels extends Model
{
    // 表名
    protected $name = 'payment_channels';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    protected $json = ['config'];

}