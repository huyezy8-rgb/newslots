<?php

namespace app\common\model\recharge;

use think\Model;

/**
 * Orders
 */
class Orders extends Model
{
    // 表名
    protected $name = 'recharge_orders';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;


    public function getAmountAttr($value): ?float
    {
        return is_null($value) ? null : (float)$value;
    }
}